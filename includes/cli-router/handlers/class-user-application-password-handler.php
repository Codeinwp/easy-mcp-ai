<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Db_Object_Command;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class User_Application_Password_Handler extends Command_Handler {

    const APPLICATION_PASSWORD_FIELDS = array( 'uuid', 'app_id', 'name', 'password', 'created', 'last_used', 'last_ip' );

    const NOT_CAPTURED = 'Application passwords live in user meta that change history stores redacted, so a deleted or renamed password cannot be restored from it.';

    const MSG_CANNOT_LIST       = 'Sorry, you are not allowed to list application passwords for this user.';
    const MSG_CANNOT_READ       = 'Sorry, you are not allowed to read this application password.';
    const MSG_CANNOT_EDIT       = 'Sorry, you are not allowed to edit this application password.';
    const MSG_CANNOT_DELETE     = 'Sorry, you are not allowed to delete this application password.';
    const MSG_CANNOT_DELETE_ALL = 'Sorry, you are not allowed to delete application passwords for this user.';

    const REFUSE_HASH_FILTER = 'it filters on the password hash, which wp_cli_run never prints';
    const REFUSE_UNKNOWN     = 'it is not a field of an application password (uuid, app_id, name, created, last_used, last_ip), and WP-CLI would ignore it';
    const REFUSE_UPDATE      = 'only --name can be changed; WordPress ignores every other field';

    public function list_( Invocation $inv, Context $ctx ): void {
        $user_id    = $this->user_id( $inv, $ctx, 'list_app_passwords', self::MSG_CANNOT_LIST );
        $assoc_args = $inv->assoc;
        $this->refuse_filters( $ctx, $assoc_args );

        $application_passwords = \WP_Application_Passwords::get_user_application_passwords( $user_id );
        if ( $application_passwords instanceof \WP_Error ) {
            $ctx->out->error( Db_Object_Command::error_to_string( $application_passwords ) );
        }
        if ( empty( $application_passwords ) ) {
            $application_passwords = array();
        }

        $order   = Read_Value::flag( $assoc_args, 'order' );
        $orderby = Read_Value::flag( $assoc_args, 'orderby' );
        self::sort( $application_passwords, (string) $orderby, (string) $order );

        $fields = self::APPLICATION_PASSWORD_FIELDS;

        foreach ( array( 'app-id', 'last-used', 'last-ip' ) as $flag ) {
            if ( array_key_exists( $flag, $assoc_args ) ) {
                $underscored_flag                = str_replace( '-', '_', $flag );
                $assoc_args[ $underscored_flag ] = $assoc_args[ $flag ];
                unset( $assoc_args[ $flag ] );
            }
        }

        foreach ( $fields as $field ) {
            if ( ! array_key_exists( $field, $assoc_args ) ) {
                continue;
            }
            $value                 = Read_Value::flag( $assoc_args, $field );
            $application_passwords = array_filter(
                $application_passwords,
                static function ( array $application_password ) use ( $field, $value ): bool {
                    return $application_password[ $field ] === $value;
                }
            );
        }

        if ( ! empty( $assoc_args['fields'] ) ) {
            $fields = explode( ',', (string) $assoc_args['fields'] );
        }

        $format = Read_Value::flag( $assoc_args, 'format', 'table' );
        $this->report_redacted( $ctx, $assoc_args, $fields, 'ids' === $format );
        $application_passwords = self::mask( $application_passwords );

        $formatter = new Formatter( $ctx->out, $assoc_args, $fields );
        if ( 'ids' === $format ) {
            $formatter->display_items( self::pluck( $application_passwords, 'uuid' ) );
        } else {
            $formatter->display_items( $application_passwords );
        }
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $user_id = $this->user_id( $inv, $ctx, 'read_app_password', self::MSG_CANNOT_READ );
        $uuid    = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';

        $application_password = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );
        if ( $application_password instanceof \WP_Error ) {
            $ctx->out->error( Db_Object_Command::error_to_string( $application_password ) );
        }
        if ( null === $application_password ) {
            $ctx->out->error( 'No application password found for this user ID and UUID.' );
        }

        $assoc_args = $inv->assoc;
        $fields     = self::APPLICATION_PASSWORD_FIELDS;
        if ( ! empty( $assoc_args['fields'] ) ) {
            $fields = explode( ',', (string) $assoc_args['fields'] );
        }
        $this->report_redacted( $ctx, $assoc_args, $fields, false );

        $formatter = new Formatter( $ctx->out, $assoc_args, $fields );
        $formatter->display_items( self::mask( array( $application_password ) ) );
    }

    public function exists( Invocation $inv, Context $ctx ): void {
        $user_id  = $this->user_id( $inv, $ctx, 'list_app_passwords', self::MSG_CANNOT_LIST );
        $app_name = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';

        if ( method_exists( '\\WP_Application_Passwords', 'application_name_exists_for_user' ) ) {
            $result = \WP_Application_Passwords::application_name_exists_for_user( $user_id, $app_name );
        } else {
            $result = false;
            foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( $user_id ) as $password ) {
                if ( strtolower( $password['name'] ) === strtolower( $app_name ) ) {
                    $result = true;
                    break;
                }
            }
        }
        if ( $result instanceof \WP_Error ) {
            $ctx->out->error( Db_Object_Command::error_to_string( $result ) );
        }
        $ctx->out->halt( $result ? 0 : 1 );
    }

    public function preflight_update( Invocation $inv, Context $ctx ): void {
        $errors = array();
        foreach ( array_keys( $inv->assoc ) as $key ) {
            if ( 'name' !== (string) $key ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::REFUSE_UPDATE );
            }
        }
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $this->preflight_update( $inv, $ctx );
        $user_id = $this->user_id( $inv, $ctx, 'edit_app_password', self::MSG_CANNOT_EDIT );
        $uuid    = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        $ctx->not_captured( self::NOT_CAPTURED );

        $before = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );
        $result = \WP_Application_Passwords::update_application_password( $user_id, $uuid, $inv->assoc );
        if ( $result instanceof \WP_Error ) {
            $ctx->out->error( Db_Object_Command::error_to_string( $result ) );
        }
        $after = \WP_Application_Passwords::get_user_application_password( $user_id, $uuid );
        if ( is_array( $before ) && is_array( $after ) && $before['name'] !== $after['name'] ) {
            $ctx->mark_changed();
        }
        $ctx->out->success( 'Updated application password.' );
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        $args    = array_values( $inv->args );
        $all     = true === Read_Value::flag( $inv->assoc, 'all', false );
        $user_id = $this->user_id( $inv, $ctx, $all ? 'delete_app_passwords' : 'list_app_passwords', $all ? self::MSG_CANNOT_DELETE_ALL : self::MSG_CANNOT_LIST );
        array_shift( $args );
        $count = count( $args );

        if ( ( 0 < $count && $all ) || ( 0 === $count && ! $all ) ) {
            $ctx->out->error( 'You need to specify either one or more UUIDS or provide the --all flag' );
        }
        $ctx->not_captured( self::NOT_CAPTURED );

        if ( $all ) {
            self::delete_all( $user_id, $ctx );
            return;
        }
        self::delete_uuids( $user_id, $args, $ctx );
    }

    private static function delete_all( int $user_id, Context $ctx ): void {
        $result = \WP_Application_Passwords::delete_all_application_passwords( $user_id );
        if ( $result instanceof \WP_Error ) {
            $ctx->out->error( Db_Object_Command::error_to_string( $result ) );
        }
        if ( (int) $result > 0 ) {
            $ctx->mark_changed();
        }
        $ctx->out->success( 'Deleted all application passwords.' );
    }

    private static function delete_uuids( int $user_id, array $args, Context $ctx ): void {
        $count = count( $args );
        foreach ( $args as $uuid ) {
            if ( ! \current_user_can( 'delete_app_password', $user_id, (string) $uuid ) ) {
                $ctx->out->error( self::MSG_CANNOT_DELETE );
            }
        }

        $errors = 0;
        foreach ( $args as $uuid ) {
            $result = \WP_Application_Passwords::delete_application_password( $user_id, (string) $uuid );
            if ( $result instanceof \WP_Error ) {
                $ctx->out->warning( "Failed to delete UUID {$uuid}: " . $result->get_error_message() );
                ++$errors;
                continue;
            }
            $ctx->mark_changed();
        }

        $ctx->out->success(
            sprintf(
                'Deleted %d of %d application %s.',
                $count - $errors,
                $count,
                1 === $count ? 'password' : 'passwords'
            )
        );
        $ctx->out->halt( 0 === $errors ? 0 : 1 );
    }

    public function describe_update( Invocation $inv ): array {
        $user = self::peek_user( isset( $inv->args[0] ) ? $inv->args[0] : '' );
        $uuid = isset( $inv->args[1] ) ? (string) $inv->args[1] : '';
        if ( ! $user ) {
            return array( sprintf( "User '%s': not found → nothing to update", isset( $inv->args[0] ) ? $inv->args[0] : '' ) );
        }
        $item = \WP_Application_Passwords::get_user_application_password( (int) $user->ID, $uuid );
        if ( ! is_array( $item ) ) {
            return array( sprintf( 'User %s (%d) application password %s: not found → nothing to update', $user->user_login, (int) $user->ID, $uuid ) );
        }
        $name = isset( $inv->assoc['name'] ) && is_string( $inv->assoc['name'] ) ? $inv->assoc['name'] : null;
        return array( sprintf( "User %s (%d) application password %s: name '%s' → %s", $user->user_login, (int) $user->ID, $uuid, $item['name'], null === $name ? 'no change' : "'" . $name . "'" ) );
    }

    public function describe_delete( Invocation $inv ): array {
        $user = self::peek_user( isset( $inv->args[0] ) ? $inv->args[0] : '' );
        if ( ! $user ) {
            return array( sprintf( "User '%s': not found → nothing to delete", isset( $inv->args[0] ) ? $inv->args[0] : '' ) );
        }
        $stored = array();
        foreach ( (array) \WP_Application_Passwords::get_user_application_passwords( (int) $user->ID ) as $item ) {
            $stored[ (string) $item['uuid'] ] = $item;
        }
        $uuids = array_values( array_slice( $inv->args, 1 ) );
        if ( true === Read_Value::flag( $inv->assoc, 'all', false ) ) {
            $uuids = array_keys( $stored );
            if ( ! $uuids ) {
                return array( sprintf( 'User %s (%d): no application passwords → nothing to delete', $user->user_login, (int) $user->ID ) );
            }
        }
        sort( $uuids, SORT_STRING );
        $lines = array();
        foreach ( $uuids as $uuid ) {
            if ( ! isset( $stored[ $uuid ] ) ) {
                $lines[] = sprintf( 'User %s (%d) application password %s: not found → nothing to delete', $user->user_login, (int) $user->ID, $uuid );
                continue;
            }
            $item    = $stored[ $uuid ];
            $used    = ! empty( $item['last_used'] ) ? gmdate( 'Y-m-d H:i:s', (int) $item['last_used'] ) . ' UTC' : 'never';
            $lines[] = sprintf( "User %s (%d) application password %s ('%s', last used %s) → delete", $user->user_login, (int) $user->ID, $uuid, $item['name'], $used );
        }
        return $lines;
    }

    private function user_id( Invocation $inv, Context $ctx, string $cap, string $refusal ): int {
        $user = Fetchers::of( 'user' )->get_check( isset( $inv->args[0] ) ? $inv->args[0] : '', $ctx->out );
        $id   = (int) $user->ID;
        if ( ! \current_user_can( $cap, $id ) ) {
            $ctx->out->error( $refusal );
        }
        return $id;
    }

    private function refuse_filters( Context $ctx, array $assoc ): void {
        $errors = array();
        foreach ( array_keys( $assoc ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'field', 'fields', 'format', 'orderby', 'order' ), true ) ) {
                continue;
            }
            if ( 'password' === $key ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::REFUSE_HASH_FILTER );
                continue;
            }
            if ( ! in_array( str_replace( '-', '_', $key ), self::APPLICATION_PASSWORD_FIELDS, true ) ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::REFUSE_UNKNOWN );
            }
        }
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
    }

    private function report_redacted( Context $ctx, array $assoc, array $fields, bool $ids ): void {
        if ( $ids ) {
            return;
        }
        $shown = isset( $assoc['field'] ) && is_string( $assoc['field'] ) ? array( trim( $assoc['field'] ) ) : array_map( 'trim', $fields );
        if ( in_array( 'password', $shown, true ) ) {
            $ctx->redacted( 'password' );
        }
    }

    private static function mask( array $items ): array {
        foreach ( $items as $key => $item ) {
            if ( is_array( $item ) && array_key_exists( 'password', $item ) ) {
                $items[ $key ]['password'] = Redactor::MASK;
            }
        }
        return $items;
    }

    private static function pluck( array $items, string $field ): array {
        $out = array();
        foreach ( $items as $key => $item ) {
            $out[ $key ] = $item[ $field ];
        }
        return $out;
    }

    private static function sort( array &$items, string $orderby, string $order ): void {
        if ( PHP_VERSION_ID >= 80000 ) {
            usort(
                $items,
                static function ( array $a, array $b ) use ( $orderby, $order ): int {
                    $x = isset( $a[ $orderby ] ) ? $a[ $orderby ] : null;
                    $y = isset( $b[ $orderby ] ) ? $b[ $orderby ] : null;
                    if ( 'asc' === $order ) {
                        return $x > $y ? 1 : ( $y > $x ? -1 : 0 );
                    }
                    return $x < $y ? 1 : ( $y < $x ? -1 : 0 );
                }
            );
            return;
        }
        usort(
            $items,
            static function ( array $a, array $b ) use ( $orderby, $order ): bool {
                return 'asc' === $order
                    ? $a[ $orderby ] > $b[ $orderby ]
                    : $a[ $orderby ] < $b[ $orderby ];
            }
        );
    }

    private static function peek_user( string $arg ): ?object {
        $user = Fetchers::user( $arg, new Output() );
        return $user ? $user : null;
    }
}
