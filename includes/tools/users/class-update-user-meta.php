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
        return 'Updates a user meta key/value. Required: `user_id`, `key`, `value`. Optional: `mode` ("update", the default, replaces the value with update_user_meta(); "add" appends one more row for the key with add_user_meta(), even when the key already exists), `value_format` ("plaintext", the default, stores `value` as sent; "json" decodes `value` as JSON first, so an object or array is stored as an array). Returns { updated: bool, user_id, key, mode, changed: bool, unchanged: bool, meta_id (add mode only) }: `changed` is whether the stored rows differ afterwards; `unchanged` is true when the key already held exactly this value, so nothing was written — `updated: false` with `unchanged: false` means WordPress refused the write. Users can update their own meta. Auth-sensitive keys are blocked for everyone, and so is a key with a character outside printable ASCII (an accented letter, a full-width form) or a backslash: the database compares keys ignoring accents and width, and WordPress strips a backslash before storing the key, so either could reach a protected key. Writes to any `_`-prefixed key require manage_options (additional admin-only allowlist via `easy_mcp_ai_user_meta_writable_protected_keys` filter).';
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
                    'description' => 'The new value for the meta key. For booleans use "1" / "0" (WordPress meta storage semantics); numbers are also accepted as strings. With value_format "json", a JSON document (e.g. {"a":1} or [1,2]) that is decoded before it is stored.',
                ),
                'mode'    => array(
                    'type'        => 'string',
                    'enum'        => array( 'update', 'add' ),
                    'default'     => 'update',
                    'description' => '"update" (default) replaces the value (update_user_meta()). "add" appends a new row for the key (add_user_meta() with unique false), keeping any rows already there.',
                ),
                'value_format' => array(
                    'type'        => 'string',
                    'enum'        => array( 'plaintext', 'json' ),
                    'default'     => 'plaintext',
                    'description' => '"plaintext" (default) stores `value` as sent. "json" decodes `value` as JSON and stores the result (an object becomes an associative array); invalid JSON is refused.',
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
        self::refuse_unless_editable( $user_id );
        $key = self::writable_key( $arguments['key'] );

        $mode = isset( $arguments['mode'] ) ? $arguments['mode'] : 'update';
        $this->refuse_unless_one_of( 'mode', $mode, array( 'update', 'add' ), $mode, '"update" or "add"' );
        $format = isset( $arguments['value_format'] ) ? $arguments['value_format'] : 'plaintext';
        $this->refuse_unless_one_of( 'value_format', $format, array( 'plaintext', 'json' ), $format, '"plaintext" or "json"' );

        $value = $arguments['value'];
        if ( 'json' === $format ) {
            $value = self::decode_json_value( $value );
        }
        $slash = ( 'json' === $format || 'add' === $mode );

        $before = self::meta_rows( $user_id, $key );

        if ( 'add' === $mode ) {
            return self::add_row( $user_id, $key, $value, $before );
        }
        return self::update_rows( $user_id, $key, $value, $slash, $before );
    }

    private static function refuse_unless_editable( int $user_id ): void {
        if ( ! get_userdata( $user_id ) ) {
            throw new \InvalidArgumentException( 'User not found.' );
        }
        $is_self = ( $user_id === get_current_user_id() );
        if ( ! $is_self && ! current_user_can( 'edit_user', $user_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this user.' );
        }
    }

    private static function writable_key( $raw ): string {
        $key = sanitize_text_field( (string) $raw );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }
        if ( self::has_slash( $key ) ) {
            throw new \RuntimeException( self::slash_refusal( $key, 'written' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( self::is_outside_printable_ascii( $key ) ) {
            throw new \RuntimeException( self::outside_printable_ascii_refusal( $key, 'written' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
        if ( '_' === $key[0] ) {
            self::refuse_protected_key( $key );
        }
        return $key;
    }

    private static function refuse_protected_key( string $key ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new \RuntimeException( 'Protected meta keys require administrator privileges.' );
        }
        $allowed = apply_filters( 'easy_mcp_ai_user_meta_writable_protected_keys', array() );
        if ( ! in_array( $key, (array) $allowed, true ) ) {
            throw new \RuntimeException( sprintf( 'Protected meta key %s is not in the writable allowlist.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function meta_rows( int $user_id, string $key ): array {
        $rows = get_user_meta( $user_id, $key );
        return is_array( $rows ) ? $rows : array();
    }

    private static function add_row( int $user_id, string $key, $value, array $before ): array {
        $meta_id = add_user_meta( $user_id, $key, wp_slash( $value ), false );
        $after   = self::meta_rows( $user_id, $key );
        return array(
            'updated'   => ( false !== $meta_id ),
            'user_id'   => $user_id,
            'key'       => $key,
            'mode'      => 'add',
            'changed'   => ( false !== $meta_id ) || $after !== $before,
            'unchanged' => false,
            'meta_id'   => ( false !== $meta_id && is_numeric( $meta_id ) ) ? (int) $meta_id : null,
        );
    }

    private static function update_rows( int $user_id, string $key, $value, bool $slash, array $before ): array {
        $result    = update_user_meta( $user_id, $key, $slash ? wp_slash( $value ) : $value );
        $after     = self::meta_rows( $user_id, $key );
        $unchanged = self::is_unchanged( $result, $before, wp_unslash( $slash ? wp_slash( $value ) : $value ) );
        return array(
            'updated'   => ( false !== $result ),
            'user_id'   => $user_id,
            'key'       => $key,
            'mode'      => 'update',
            'changed'   => $after !== $before,
            'unchanged' => $unchanged,
        );
    }

    private static function decode_json_value( $value ) {
        if ( ! is_string( $value ) ) {
            throw new \InvalidArgumentException( 'value_format "json" needs `value` as a string holding a JSON document.' );
        }
        $decoded = json_decode( $value, true );
        if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
            throw new \InvalidArgumentException( sprintf( 'value is not valid JSON: %s', json_last_error_msg() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( null === $decoded ) {
            throw new \InvalidArgumentException( 'value_format "json" cannot store a JSON null: WordPress meta has no null value. Use wp_delete_user_meta to remove the key.' );
        }
        return $decoded;
    }

    private static function is_unchanged( $result, array $before, $stored ): bool {
        return false === $result && 1 === count( $before ) && self::same_meta_value( reset( $before ), $stored );
    }

    private static function same_meta_value( $stored, $sent ): bool {
        if ( is_bool( $sent ) || null === $sent ) {
            $sent = $sent ? '1' : '';
        }
        if ( is_scalar( $sent ) && ( is_scalar( $stored ) || null === $stored ) ) {
            return (string) $stored === (string) $sent;
        }
        return $stored === $sent;
    }
}
