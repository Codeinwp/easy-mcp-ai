<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Redactor;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class User_Signup_Handler extends Command_Handler {

    const OBJ_FIELDS = array( 'signup_id', 'user_login', 'user_email', 'registered', 'active', 'activation_key' );

    const COLUMNS = array( 'signup_id', 'domain', 'path', 'title', 'user_login', 'user_email', 'registered', 'activated', 'active', 'activation_key', 'meta' );

    const LIST_MAX = 1000;

    const NOT_CAPTURED = 'The signups table is written with direct database queries, which change history records only while database capture is on.';

    const MSG_LIST_CAP   = "wp_cli_run returns at most %d signups in one 'wp user signup list' command, and the network has %d. Page it with --per_page=<n>, narrow it with a --<field>=<value> filter, or count it with --format=count.";
    const REFUSE_KEY     = 'it filters on the activation key, which wp_cli_run never prints';
    const REFUSE_UNKNOWN = 'it is not a column of the signups table, and WP-CLI would ignore it';

    public function list_( Invocation $inv, Context $ctx ): void {
        global $wpdb;
        $this->require_multisite( $ctx );
        $assoc_args = $inv->assoc;
        $this->refuse_filters( $ctx, $assoc_args );

        if ( isset( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = explode( ',', (string) $assoc_args['fields'] );
        } else {
            $assoc_args['fields'] = self::OBJ_FIELDS;
        }

        $per_page = (int) Read_Value::flag( $assoc_args, 'per_page' );
        $limit    = $per_page ? $wpdb->prepare( 'LIMIT %d', $per_page ) : '';
        $query    = "SELECT * FROM $wpdb->signups {$limit}";

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's own query; the LIMIT is prepared above.
        $results = $wpdb->get_results( $query, ARRAY_A );
        $signups = self::matching( (array) $results, $assoc_args );

        $format = Read_Value::flag( $assoc_args, 'format', 'table' );
        if ( 'count' !== $format && count( $signups ) > self::LIST_MAX ) {
            $ctx->out->error( sprintf( self::MSG_LIST_CAP, self::LIST_MAX, count( $signups ) ) );
        }

        if ( 'ids' === $format ) {
            $ids = array();
            foreach ( $signups as $signup ) {
                $ids[] = $signup['signup_id'];
            }
            $ctx->out->line( implode( ' ', $ids ) );
            return;
        }
        $signups = $this->mask( $ctx, $signups, $assoc_args );
        $formatter = $this->formatter_for( $ctx, $assoc_args );
        $formatter->display_items( $signups );
    }

    private static function matching( array $results, array $assoc_args ): array {
        $signups = array();
        foreach ( $results as $item ) {
            foreach ( array_keys( $item ) as $field ) {
                if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] !== $item[ $field ] ) {
                    continue 2;
                }
            }
            $signups[] = $item;
        }
        return $signups;
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $this->require_multisite( $ctx );
        $signup     = Fetchers::of( 'signup' )->get_check( isset( $inv->args[0] ) ? $inv->args[0] : '', $ctx->out );
        $assoc_args = $inv->assoc;
        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = array_keys( (array) $signup );
        }
        $rows      = $this->mask( $ctx, array( (array) $signup ), $assoc_args );
        $formatter = $this->formatter_for( $ctx, $assoc_args );
        $formatter->display_items( array( (object) $rows[0] ) );
    }

    public function activate( Invocation $inv, Context $ctx ): void {
        $this->require_multisite( $ctx );
        $args    = array_values( $inv->args );
        $signups = $this->get_many( $ctx, $args );
        $ctx->not_captured( self::NOT_CAPTURED );

        $successes = 0;
        $errors    = 0;

        foreach ( $signups as $signup ) {
            $result = \wpmu_activate_signup( $signup->activation_key );

            if ( \is_wp_error( $result ) ) {
                $ctx->out->warning( "Failed activating signup {$signup->signup_id}." );
                ++$errors;
            } else {
                $ctx->mark_changed();
                $ctx->out->log( "Signup {$signup->signup_id} activated. Password: {$result['password']}" );
                ++$successes;
            }
        }

        Values::report_batch( $ctx->out, 'signup', 'activate', count( $args ), $successes, $errors );
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        global $wpdb;
        $this->require_multisite( $ctx );
        $args  = array_values( $inv->args );
        $count = count( $args );
        $all   = Read_Value::flag( $inv->assoc, 'all', false );

        if ( ( 0 < $count && true === $all ) || ( 0 === $count && true !== $all ) ) {
            $ctx->out->error( 'You need to specify either one or more signups or provide the --all flag.' );
        }
        $ctx->not_captured( self::NOT_CAPTURED );

        if ( true === $all ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's own statement; the table name is $wpdb's.
            $results = $wpdb->query( 'DELETE FROM ' . $wpdb->signups );
            if ( ! $results ) {
                $ctx->out->error( 'Error deleting signups.' );
            }
            $ctx->mark_changed();
            $ctx->out->success( 'Deleted all signups.' );
            return;
        }

        $signups   = $this->get_many( $ctx, $args );
        $successes = 0;
        $errors    = 0;

        foreach ( $signups as $signup ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's own statement.
            $result = $wpdb->delete( $wpdb->signups, array( 'signup_id' => $signup->signup_id ), array( '%d' ) );
            if ( $result ) {
                $ctx->mark_changed();
                $ctx->out->log( "Signup {$signup->signup_id} deleted." );
                ++$successes;
            } else {
                $ctx->out->warning( "Failed deleting signup {$signup->signup_id}." );
                ++$errors;
            }
        }

        Values::report_batch( $ctx->out, 'signup', 'delete', $count, $successes, $errors );
    }

    public function describe_activate( Invocation $inv ): array {
        if ( ! \is_multisite() ) {
            return array( 'This is not a multisite installation → nothing to activate' );
        }
        $lines = array();
        foreach ( $inv->args as $arg ) {
            $signup = Fetchers::of( 'signup' )->get( $arg );
            if ( ! $signup ) {
                $lines[] = sprintf( "Signup '%s': not found → nothing to activate", $arg );
                continue;
            }
            $site    = '' !== (string) $signup->domain ? sprintf( ' and the site %s%s', $signup->domain, $signup->path ) : '';
            $lines[] = sprintf(
                'Signup %d (%s, %s): %s → %s',
                (int) $signup->signup_id,
                $signup->user_login,
                $signup->user_email,
                (int) $signup->active ? 'already active' : 'pending',
                (int) $signup->active ? 'nothing to activate' : 'activate (creates the user' . $site . ')'
            );
        }
        return $lines;
    }

    public function describe_delete( Invocation $inv ): array {
        global $wpdb;
        if ( ! \is_multisite() ) {
            return array( 'This is not a multisite installation → nothing to delete' );
        }
        if ( true === Read_Value::flag( $inv->assoc, 'all', false ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- a count of the table WP-CLI empties.
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $wpdb->signups" );
            return array( sprintf( 'Signups: %d → delete all', $total ) );
        }
        $lines = array();
        foreach ( $inv->args as $arg ) {
            $signup = Fetchers::of( 'signup' )->get( $arg );
            if ( ! $signup ) {
                $lines[] = sprintf( "Signup '%s': not found → nothing to delete", $arg );
                continue;
            }
            $lines[] = sprintf( 'Signup %d (%s, %s, %s) → delete', (int) $signup->signup_id, $signup->user_login, $signup->user_email, (int) $signup->active ? 'active' : 'pending' );
        }
        return $lines;
    }

    private function require_multisite( Context $ctx ): void {
        if ( ! \is_multisite() ) {
            $ctx->out->error( 'This is not a multisite installation.' );
        }
    }

    private function get_many( Context $ctx, array $args ): array {
        $fetcher = Fetchers::of( 'signup' );
        $signups = array();
        foreach ( $args as $arg ) {
            $signup = $fetcher->get( $arg );
            if ( $signup ) {
                $signups[] = $signup;
                continue;
            }
            $message = sprintf( $fetcher->message(), $arg );
            $ctx->out->warning( $message );
            $ctx->fail_missing_target( $message );
        }
        return $signups;
    }

    private function formatter_for( Context $ctx, array &$assoc_args ): Formatter {
        if ( ! empty( $assoc_args['fields'] ) ) {
            $fields = is_string( $assoc_args['fields'] ) ? explode( ',', $assoc_args['fields'] ) : (array) $assoc_args['fields'];
        } else {
            $fields = self::OBJ_FIELDS;
        }
        return new Formatter( $ctx->out, $assoc_args, $fields, 'signup' );
    }

    private function mask( Context $ctx, array $rows, array $assoc_args ): array {
        $shown = self::shown_fields( $assoc_args );
        if ( $rows && in_array( 'activation_key', $shown, true ) ) {
            $ctx->redacted( 'activation_key' );
        }
        $meta_shown = in_array( 'meta', $shown, true );
        foreach ( $rows as $i => $row ) {
            if ( array_key_exists( 'activation_key', $row ) ) {
                $rows[ $i ]['activation_key'] = Redactor::MASK;
            }
            if ( array_key_exists( 'meta', $row ) && is_string( $row['meta'] ) ) {
                $rows[ $i ]['meta'] = self::mask_row_meta( $ctx, $row, $meta_shown );
            }
        }
        return $rows;
    }

    private static function shown_fields( array $assoc_args ): array {
        return isset( $assoc_args['field'] ) && is_string( $assoc_args['field'] )
            ? array( trim( $assoc_args['field'] ) )
            : array_map( 'trim', array_map( 'strval', (array) ( isset( $assoc_args['fields'] ) ? $assoc_args['fields'] : self::OBJ_FIELDS ) ) );
    }

    private static function mask_row_meta( Context $ctx, array $row, bool $meta_shown ): string {
        $keys   = array();
        $masked = self::mask_meta( $row['meta'], $keys );
        if ( $meta_shown ) {
            foreach ( $keys as $key ) {
                $ctx->redacted( sprintf( "meta '%s' of signup %s", $key, isset( $row['signup_id'] ) ? (string) $row['signup_id'] : '?' ) );
            }
        }
        return $masked;
    }

    public static function mask_meta( string $raw, array &$masked ): string {
        $masked = array();
        if ( ! function_exists( 'is_serialized' ) || ! \is_serialized( $raw ) ) {
            return $raw;
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- classes are refused, so nothing is instantiated.
        $data = @unserialize( $raw, array( 'allowed_classes' => false ) );
        if ( ! is_array( $data ) ) {
            return $raw;
        }
        $data = self::mask_secret_values( $data, $masked );
        if ( ! $masked ) {
            return $raw;
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- the column's own format, as wpmu_signup_user() stores it.
        return serialize( $data );
    }

    private static function mask_secret_values( array $data, array &$masked ): array {
        foreach ( $data as $key => $value ) {
            if ( is_string( $key ) && Option_Handler::key_is_secret( $key ) ) {
                $data[ $key ] = Redactor::MASK;
                $masked[]     = $key;
            } elseif ( is_array( $value ) ) {
                $data[ $key ] = self::mask_secret_values( $value, $masked );
            }
        }
        return $data;
    }

    private function refuse_filters( Context $ctx, array $assoc ): void {
        $errors = array();
        foreach ( array_keys( $assoc ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'field', 'fields', 'format', 'per_page' ), true ) ) {
                continue;
            }
            if ( 'activation_key' === $key ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::REFUSE_KEY );
            } elseif ( ! in_array( $key, self::COLUMNS, true ) ) {
                $errors[] = sprintf( ' --%s parameter is not available through wp_cli_run: %s', $key, self::REFUSE_UNKNOWN );
            }
        }
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
    }
}
