<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_User_Meta extends Base_Tool {

    use User_Meta_Auth_Guard;

    public function get_name() {
        return 'wp_get_user_meta';
    }

    public function get_description() {
        return 'Reads user meta for a given user. Required: `user_id`. Optional: `key` (single key — omit to fetch all meta), `single` (boolean, default true — when true returns a single value; when false returns an array of values for the key). Returns { user_id, key, value } for one key, or { user_id, meta, withheld_keys } for all meta, where `withheld_keys` (sorted) names every key the policy left out of `meta`, so an omission is never silent. Users can read their own meta without edit_users. Auth-sensitive keys (session_tokens, _password_reset_key, _application_passwords, etc.) and the role keys ({prefix}capabilities, {prefix}user_level) are blocked for all callers including administrators; in the all-meta read, `_`-prefixed keys are also withheld from callers without manage_options unless the `easy_mcp_ai_user_meta_readable_protected_keys` filter lists them.';
    }

    public function get_category() {
        return 'users';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'user_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the user to read meta for.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'Optional single meta key. Omit to fetch all readable meta.',
                ),
                'single'  => array(
                    'type'        => 'boolean',
                    'description' => 'Only applies when `key` is provided. When true (default) returns a single value; when false returns an array of values for the key. Ignored when `key` is omitted (all meta is returned in that case).',
                    'default'     => true,
                ),
            ),
            'required'   => array( 'user_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id' ) );
        $user_id = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        if ( ! get_userdata( $user_id ) ) {
            throw new \InvalidArgumentException( 'User not found.' );
        }
        $is_self = ( $user_id === get_current_user_id() );
        if ( ! $is_self && ! current_user_can( 'edit_user', $user_id ) ) {
            throw new \RuntimeException( 'You do not have permission to read this user\'s meta.' );
        }
        $key    = isset( $arguments['key'] ) ? sanitize_text_field( (string) $arguments['key'] ) : '';
        $single = isset( $arguments['single'] ) ? (bool) $arguments['single'] : true;
        if ( '' !== $key ) {
            self::refuse_unreadable_key( $key );
            $value = get_user_meta( $user_id, $key, $single );
            return array( 'user_id' => $user_id, 'key' => $key, 'value' => $value );
        }
        return self::all_meta( $user_id );
    }

    private static function refuse_unreadable_key( string $key ): void {
        if ( self::is_auth_protected( $key ) ) {
            throw new \RuntimeException( 'Auth-sensitive meta keys are not readable via this tool.' );
        }
        if ( self::is_privilege_key( $key ) ) {
            throw new \RuntimeException( 'Privilege meta keys (wp_capabilities, wp_user_level) are not readable via this tool.' );
        }
        if ( self::is_blocked_by_filter( $key ) ) {
            throw new \RuntimeException( 'This meta key cannot be read via MCP.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function all_meta( int $user_id ): array {
        $all      = get_user_meta( $user_id );
        $withheld = array();
        foreach ( $all as $k => $_ ) {
            if ( ! self::is_key_readable( (string) $k ) ) {
                unset( $all[ $k ] );
                $withheld[] = (string) $k;
            }
        }
        sort( $withheld, SORT_STRING );
        return array(
            'user_id'       => $user_id,
            'meta'          => $all,
            'withheld_keys' => $withheld,
        );
    }

    public static function is_key_readable( string $key ): bool {
        if ( '' === $key || self::is_auth_protected( $key ) || self::is_privilege_key( $key ) || self::is_blocked_by_filter( $key ) ) {
            return false;
        }
        if ( '_' === $key[0] && ! current_user_can( 'manage_options' ) ) {
            $readable_protected = apply_filters( 'easy_mcp_ai_user_meta_readable_protected_keys', array( 'wp_user-settings', 'wp_user-settings-time' ) );
            return in_array( $key, (array) $readable_protected, true );
        }
        return true;
    }
}
