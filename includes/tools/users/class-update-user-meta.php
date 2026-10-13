<?php
namespace Easy_MCP_AI\Tools\Users;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_User_Meta extends Base_Tool {

    use User_Meta_Auth_Guard;

    public function get_name() {
        return 'wp_update_user_meta';
    }

    public function get_description() {
        return 'Updates a user meta key/value. Required: `user_id`, `key`, `value`. Returns { updated: bool, user_id, key }. Users can update their own meta. Auth-sensitive keys are blocked for everyone. Writes to any `_`-prefixed key require manage_options (additional admin-only allowlist via `easy_mcp_ai_user_meta_writable_protected_keys` filter).';
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
            'readOnlyHint'    => false,
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
                    'description' => 'The ID of the user to update meta for.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'The meta key to update.',
                ),
                'value'   => array(
                    'type'        => 'string',
                    'description' => 'The new value for the meta key. For booleans use "1" / "0" (WordPress meta storage semantics); numbers are also accepted as strings.',
                ),
            ),
            'required'   => array( 'user_id', 'key', 'value' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'user_id', 'key' ) );
        if ( ! array_key_exists( 'value', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameter: value' );
        }
        $user_id = $this->parse_required_id( $arguments['user_id'], 'user_id' );
        if ( ! get_userdata( $user_id ) ) {
            throw new \InvalidArgumentException( 'User not found.' );
        }
        $is_self = ( $user_id === get_current_user_id() );
        if ( ! $is_self && ! current_user_can( 'edit_user', $user_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this user.' );
        }
        $key = sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }
        if ( self::is_auth_protected( $key ) ) {
            throw new \RuntimeException( 'Auth-sensitive meta keys cannot be written via this tool.' );
        }
        if ( self::is_privilege_key( $key ) ) {
            throw new \RuntimeException( 'Privilege meta keys (wp_capabilities, wp_user_level) cannot be written via this tool.' );
        }
        if ( self::is_blocked_by_filter( $key ) ) {
            throw new \RuntimeException( 'This meta key cannot be written via MCP.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( '_' === ( $key[0] ?? '' ) ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                throw new \RuntimeException( 'Protected meta keys require administrator privileges.' );
            }
            $allowed = apply_filters( 'easy_mcp_ai_user_meta_writable_protected_keys', array() );
            if ( ! in_array( $key, (array) $allowed, true ) ) {
                throw new \RuntimeException( sprintf( 'Protected meta key %s is not in the writable allowlist.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        $result = update_user_meta( $user_id, $key, $arguments['value'] );
        return array( 'updated' => ( false !== $result ), 'user_id' => $user_id, 'key' => $key );
    }
}
