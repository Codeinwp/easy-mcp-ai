<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Meta_Command;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class User_Meta_Adapter extends Meta_Command {

    protected $meta_type = 'user';

    const TOOLS = array(
        'list'   => array( 'wp_get_user_meta' ),
        'get'    => array( 'wp_get_user_meta' ),
        'delete' => array( 'wp_delete_user_meta' ),
        'add'    => array( 'wp_update_user_meta' ),
        'update' => array( 'wp_get_user_meta', 'wp_update_user_meta' ),
    );

    const MSG_CANNOT_READ = 'You do not have permission to read this user\'s meta.';
    const MSG_CANNOT_EDIT = 'You do not have permission to edit this user.';

    const WITHHELD = 'wp_get_user_meta left out %d meta %s it never shows: %s. WP-CLI would list %s.';

    const NOT_JSON = 'wp_cli_run cannot hand this value to wp_update_user_meta: it is not valid UTF-8, so it cannot travel as JSON.';

    protected function resolve_args( array $args, string $subcommand ): array {
        if ( ! isset( self::TOOLS[ $subcommand ] ) ) {
            return $args;
        }
        $tools = self::TOOLS[ $subcommand ];
        $last  = array_pop( $tools );
        foreach ( $tools as $tool ) {
            Fetchers::require_tool( $this->ctx, $this->inv, $tool );
        }
        $ctx     = $this->ctx;
        $refusal = 'list' === $subcommand || 'get' === $subcommand ? self::MSG_CANNOT_READ : self::MSG_CANNOT_EDIT;
        $user    = Fetchers::of( 'user' )->get_check_for(
            $ctx,
            $this->inv,
            $last,
            isset( $args[0] ) ? $args[0] : '',
            static function ( object $user ) use ( $ctx, $refusal ): ?string {
                $id = isset( $user->ID ) ? (int) $user->ID : 0;
                return ( $id === $ctx->user_id() || \current_user_can( 'edit_user', $id ) ) ? null : $refusal;
            }
        );
        $args[0] = $user->ID;
        return $args;
    }

    protected function check_object_id( $object_id, string $subcommand ) {
        return $object_id;
    }

    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        $arguments = array( 'user_id' => $object_id );
        if ( '' !== $meta_key ) {
            $arguments['key']    = $meta_key;
            $arguments['single'] = $single;
        }
        $result = $this->delegate( 'wp_get_user_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        $payload = $result['payload'];
        if ( '' !== $meta_key ) {
            return array_key_exists( 'value', $payload ) ? $payload['value'] : '';
        }
        $this->warn_withheld( $payload );
        return isset( $payload['meta'] ) && is_array( $payload['meta'] ) ? $payload['meta'] : array();
    }

    private function warn_withheld( array $payload ): void {
        $withheld = isset( $payload['withheld_keys'] ) ? array_values( array_map( 'strval', (array) $payload['withheld_keys'] ) ) : array();
        $keys     = null !== $this->inv && ! empty( $this->inv->assoc['keys'] ) ? explode( ',', (string) $this->inv->assoc['keys'] ) : null;
        if ( null !== $keys ) {
            $withheld = array_values( array_intersect( $withheld, $keys ) );
        }
        if ( ! $withheld ) {
            return;
        }
        $withheld = array_values( array_unique( $withheld ) );
        $count    = count( $withheld );
        $this->ctx->out->warning( sprintf( self::WITHHELD, $count, 1 === $count ? 'key' : 'keys', Self_Protection::omitted_names_text( $withheld ), 1 === $count ? 'it' : 'them' ) );
    }

    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        $value     = \wp_unslash( $meta_value );
        $arguments = array(
            'user_id' => $object_id,
            'key'     => $meta_key,
            'mode'    => 'add',
        );
        $arguments = array_merge( $arguments, $this->value_arguments( $value, is_string( $value ) ) );
        $result    = $this->delegate( 'wp_update_user_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['updated'] );
    }

    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        $arguments = array_merge(
            array(
                'user_id' => $object_id,
                'key'     => $meta_key,
                'mode'    => 'update',
            ),
            $this->value_arguments( \wp_unslash( $meta_value ), false )
        );
        $result    = $this->delegate( 'wp_update_user_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['updated'] );
    }

    protected function delete_metadata( $object_id, string $meta_key, string $meta_value = '' ): bool {
        $arguments = array(
            'user_id' => $object_id,
            'key'     => $meta_key,
        );
        if ( '' !== $meta_value ) {
            $arguments['value'] = $meta_value;
        }
        $result = $this->delegate( 'wp_delete_user_meta', $arguments );
        if ( ! $result['ok'] ) {
            $this->ctx->out->error( (string) $result['error'] );
        }
        return ! empty( $result['payload']['deleted'] );
    }

    protected function protect_key( string $key, bool $write ): ?string {
        $refusal = parent::protect_key( $key, $write );
        if ( null !== $refusal ) {
            return $refusal;
        }
        return Self_Protection::user_meta_key( $key );
    }

    private function value_arguments( $value, bool $plain ): array {
        if ( $plain ) {
            return array(
                'value'        => (string) $value,
                'value_format' => 'plaintext',
            );
        }
        $json = json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- the tool decodes it with json_decode(); wp_json_encode() would rewrite invalid UTF-8.
        if ( false === $json ) {
            $this->ctx->out->error( self::NOT_JSON );
        }
        return array(
            'value'        => (string) $json,
            'value_format' => 'json',
        );
    }
}
