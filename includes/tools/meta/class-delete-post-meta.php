<?php
namespace Easy_MCP_AI\Tools\Meta;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Post_Meta extends Base_Tool {

    public function get_name() {
        return 'wp_delete_post_meta';
    }

    public function get_description() {
        return 'Deletes post meta for a given post. Required: `post_id`, `key`. Optional: `value` — if provided, deletes only matching rows; omit to remove ALL rows for the key. Optional `exact_key` (boolean, default false): when true the key is matched exactly as stored, byte for byte; by default it is cleaned first (surrounding whitespace, tags and %xx sequences removed). Returns { deleted: bool, post_id, key, value_provided: bool } — deleted is false when no row matched. Authorization is WordPress\'s own: `edit_post` on the post, then core\'s meta capability for deleting that key on that post — for a protected key (leading underscore) the key\'s registered auth callback plus any auth_post_meta filters decide; a protected key nothing has registered needs administrator privileges (manage_options). A key that an enabled SEO plugin integration exposes (Rank Math, Yoast, SEOPress, The SEO Framework) is refused when that plugin restricts the field for your role, as writing it through wp_update_post_meta would be.';
    }

    public function get_category() {
        return 'meta';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'The meta key to delete.',
                ),
                'value'   => array(
                    'type'        => 'string',
                    'description' => 'Optional. If provided, deletes only meta rows matching this value. For booleans use "1" / "0" (WordPress meta storage semantics); numbers are also accepted as strings. Caution: a value another plugin wrote as a native false is stored as an empty string, so "0" will not match it — and passing an empty string here deletes EVERY row for the key, not just the empty-valued one.',
                ),
                'exact_key' => array(
                    'type'        => 'boolean',
                    'description' => 'When true, key is matched exactly as stored (spaces, backslashes, % sequences and all). Default false: key is cleaned with sanitize_text_field() first, which trims it and strips tags and %xx sequences, so such a key cannot be reached.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'post_id', 'key' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'key' ) );
        $post_id   = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $exact_key = isset( $arguments['exact_key'] ) && rest_sanitize_boolean( $arguments['exact_key'] );
        $key       = $exact_key ? (string) $arguments['key'] : sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }
        $this->refuse_missing_post( $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this post.' );
        }
        $this->authorize_key( $key, $post_id );
        $this->refuse_restricted_integration_field( $key, $post_id );
        
        
        $target         = $exact_key ? wp_slash( $key ) : $key;
        $value_provided = array_key_exists( 'value', $arguments );
        if ( $value_provided ) {
            $deleted = delete_post_meta( $post_id, $target, $arguments['value'] );
        } else {
            $deleted = delete_post_meta( $post_id, $target );
        }
        if ( $deleted ) {
            
            
            $this->clear_page_cache( array( $post_id ) );
        }
        return array(
            'deleted'        => (bool) $deleted,
            'post_id'        => $post_id,
            'key'            => $key,
            'value_provided' => $value_provided,
        );
    }

    




















    private function authorize_key( string $key, int $post_id ): void {
        $cap = 'delete_post_meta';
        if ( current_user_can( $cap, $post_id, $key ) ) {
            return;
        }
        if ( ! is_protected_meta( $key, 'post' ) ) {
            throw new \RuntimeException( sprintf( 'WordPress refused %1$s for the key "%2$s" on post %3$d: an auth_post_meta filter denied your user. Nothing was deleted.', $cap, $key, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $post_type = (string) get_post_type( $post_id );
        if ( self::key_is_claimed( $key, $post_type ) ) {
            throw new \RuntimeException( sprintf( 'WordPress refused %1$s for the protected key "%2$s" on post %3$d: the key is registered, and its auth callback or an auth_post_meta filter denied your user. Nothing was deleted.', $cap, $key, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new \RuntimeException( sprintf( 'Protected meta keys require administrator privileges when nothing has registered them, and nothing registers "%1$s" for post type %2$s.', $key, $post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    








    private static function key_is_claimed( string $key, string $post_type ): bool {
        if ( function_exists( 'get_registered_meta_keys' ) ) {
            foreach ( array( $post_type, '' ) as $subtype ) {
                $registered = get_registered_meta_keys( 'post', $subtype );
                if ( isset( $registered[ $key ] ) ) {
                    return true;
                }
            }
        }
        return (bool) has_filter( "auth_post_meta_{$key}_for_{$post_type}" ) || (bool) has_filter( "auth_post_meta_{$key}" );
    }

    














    private function refuse_restricted_integration_field( string $key, int $post_id ): void {
        if ( ! class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
            return;
        }
        foreach ( \Easy_MCP_AI\Meta\Meta_Exposure::enabled_providers() as $provider ) {
            if ( ! array_key_exists( $key, $provider->get_fields() ) ) {
                continue;
            }
            if ( ! $provider->can_edit_field( $key, get_current_user_id(), $post_id ) ) {
                throw new \RuntimeException( sprintf( 'You are not allowed to delete "%1$s" (%2$s restricts this field for your role). Nothing was deleted.', $key, $provider->get_label() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }
}
