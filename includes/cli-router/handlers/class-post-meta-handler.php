<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Adapters\Post_Adapter;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Meta_Command;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Post_Meta_Handler extends Meta_Command {

    protected $meta_type = 'post';

    const META_CAPS = array(
        'add'              => 'add_post_meta',
        'update'           => 'edit_post_meta',
        'patch'            => 'edit_post_meta',
        'clean-duplicates' => 'delete_post_meta',
    );

    const KEY_INDEX = array(
        'add'              => 1,
        'update'           => 1,
        'patch'            => 2,
        'clean-duplicates' => 1,
    );

    const MSG_REFUSED_REGISTERED = "WordPress refused %s for the protected key '%s' on post %d: the key is registered, and its auth callback or an auth_post_meta filter denied your user.";

    const MSG_REFUSED_PROTECTED = "Protected meta keys need the manage_options capability when nothing registers them, and nothing registers '%s' for post type %s.";

    const MSG_REFUSED_KEY = "WordPress refused %s for the key '%s' on post %d: an auth_post_meta filter denied your user.";

    const MSG_REFUSED_INTEGRATION = "You are not allowed to change '%s' (%s restricts this field for your role). Nothing was changed.";

    const MSG_ORPHAN_ROWS = "Could not find the post with ID %d. wp_cli_run does not clean meta rows of a post that does not exist: it cannot check your capabilities on a missing post.";

    const MSG_KEY_CASE = "wp_cli_run refuses the key '%1\$s' on post %2\$d: '%3\$s' is a registered key for post type %4\$s, and the meta table compares keys without regard to case, so '%1\$s' would reach the rows of '%3\$s' without the checks that apply to it. Use '%3\$s'.";

    public function preflight_id( Invocation $inv, Context $ctx ): void {
        Ids::require_digits( $ctx, self::id_arg( $inv ), 'a post ID' );
    }

    public function is_rest_key( Invocation $inv ): bool {
        $sub  = isset( $inv->leaf['name'] ) ? (string) $inv->leaf['name'] : '';
        $args = array_values( $inv->args );
        $id   = self::id_arg( $inv );
        $key  = isset( self::KEY_INDEX[ $sub ], $args[ self::KEY_INDEX[ $sub ] ] ) ? (string) $args[ self::KEY_INDEX[ $sub ] ] : '';
        if ( '' === $key || ! function_exists( 'get_post' ) ) {
            return false;
        }
        $post = \get_post( (int) $id );
        if ( ! $post ) {
            return false;
        }
        $type = (string) $post->post_type;
        return is_array( Post_Adapter::read_base( $type, $inv->command ) ) && Post_Adapter::is_rest_meta_key( $type, $key );
    }

    private static function id_arg( Invocation $inv ): string {
        $args = array_values( $inv->args );
        $sub  = isset( $inv->leaf['name'] ) ? (string) $inv->leaf['name'] : '';
        $at   = 'patch' === $sub ? 1 : 0;
        return isset( $args[ $at ] ) ? $args[ $at ] : '';
    }

    protected function check_object_id( $object_id, string $subcommand ) {
        $post = Fetchers::of( 'post' )->get_check( $object_id, $this->ctx->out );
        $this->gate( $post, $subcommand );
        return $post->ID;
    }

    protected function get_metadata( $object_id, string $meta_key = '', bool $single = false ) {
        return \get_post_meta( $object_id, $meta_key, $single );
    }

    protected function add_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \add_post_meta( $object_id, $meta_key, $meta_value );
    }

    protected function update_metadata( $object_id, string $meta_key, $meta_value ): bool {
        return (bool) \update_post_meta( $object_id, $meta_key, $meta_value );
    }

    protected function delete_metadata( $object_id, string $meta_key, $meta_value = '' ): bool {
        throw new \LogicException( 'post meta delete is delegated (Post_Meta_Adapter).' );
    }

    protected function meta_rows( $object_id, string $meta_key ): array {
        global $wpdb;
        $post = \get_post( (int) $object_id );
        if ( $post ) {
            $this->gate( $post, 'clean-duplicates' );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WP-CLI's own query (Post_Meta_Command::clean_duplicates()).
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key=%s AND post_id=%d", $meta_key, $object_id ) );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();
        if ( $rows && ! $post ) {
            $this->ctx->out->error( sprintf( self::MSG_ORPHAN_ROWS, (int) $object_id ) );
        }
        return $rows;
    }

    protected function delete_meta_by_mid( int $meta_id ): bool {
        return (bool) \delete_metadata_by_mid( 'post', $meta_id );
    }

    private function gate( object $post, string $subcommand ): void {
        $post_id = (int) $post->ID;
        $this->ctx->require_cap( 'edit_post', $post_id );

        $args = array_values( $this->inv->args );
        $key  = isset( self::KEY_INDEX[ $subcommand ], $args[ self::KEY_INDEX[ $subcommand ] ] ) ? (string) $args[ self::KEY_INDEX[ $subcommand ] ] : '';
        $cap  = isset( self::META_CAPS[ $subcommand ] ) ? self::META_CAPS[ $subcommand ] : 'edit_post_meta';

        $registered = self::registered_case_variant( $key, (string) $post->post_type );
        if ( null !== $registered ) {
            $this->ctx->out->error( sprintf( self::MSG_KEY_CASE, $key, $post_id, $registered, (string) $post->post_type ) );
        }

        $this->require_meta_cap( $cap, $key, $post_id, (string) $post->post_type );
        $this->require_integration_field( $key, $post_id );
    }

    private function require_meta_cap( string $cap, string $key, int $post_id, string $post_type ): void {
        if ( \is_protected_meta( $key, 'post' ) ) {
            if ( ! \current_user_can( $cap, $post_id, $key ) ) {
                if ( self::key_is_registered( $key, $post_type ) ) {
                    $this->ctx->out->error( sprintf( self::MSG_REFUSED_REGISTERED, $cap, $key, $post_id ) );
                }
                if ( ! \current_user_can( 'manage_options' ) ) {
                    $this->ctx->out->error( sprintf( self::MSG_REFUSED_PROTECTED, $key, $post_type ) );
                }
            }
        } elseif ( ! \current_user_can( $cap, $post_id, $key ) ) {
            $this->ctx->out->error( sprintf( self::MSG_REFUSED_KEY, $cap, $key, $post_id ) );
        }
    }

    private function require_integration_field( string $key, int $post_id ): void {
        if ( ! class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            return;
        }
        foreach ( \Easy_MCP_AI\Meta\Meta_Exposure::enabled_providers() as $provider ) {
            if ( array_key_exists( $key, $provider->get_fields() ) && ! $provider->can_edit_field( $key, \get_current_user_id(), $post_id ) ) {
                $this->ctx->out->error( sprintf( self::MSG_REFUSED_INTEGRATION, $key, $provider->get_label() ) );
            }
        }
    }

    private static function registered_case_variant( string $key, string $post_type ): ?string {
        $names = array();
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( array( $post_type, '' ) as $subtype ) {
                foreach ( array_keys( (array) \get_registered_meta_keys( 'post', $subtype ) ) as $name ) {
                    $names[] = (string) $name;
                }
            }
        }
        if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            foreach ( \Easy_MCP_AI\Meta\Meta_Exposure::enabled_providers() as $provider ) {
                foreach ( array_keys( $provider->get_fields() ) as $name ) {
                    $names[] = (string) $name;
                }
            }
        }
        if ( in_array( $key, $names, true ) ) {
            return null;
        }
        foreach ( $names as $name ) {
            if ( 0 === strcasecmp( $name, $key ) ) {
                return $name;
            }
        }
        return null;
    }

    private static function key_is_registered( string $key, string $post_type ): bool {
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( array( $post_type, '' ) as $subtype ) {
                $registered = \get_registered_meta_keys( 'post', $subtype );
                if ( isset( $registered[ $key ] ) ) {
                    return true;
                }
            }
        }
        return (bool) \has_filter( "auth_post_meta_{$key}_for_{$post_type}" ) || (bool) \has_filter( "auth_post_meta_{$key}" );
    }
}
