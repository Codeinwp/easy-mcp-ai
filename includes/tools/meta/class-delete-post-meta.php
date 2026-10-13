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
        return 'Deletes post meta for a given post. Required: `post_id`, `key`. Optional: `value` — if provided, deletes only matching rows; omit to remove ALL rows for the key. Returns { deleted: bool, post_id, key, value_provided: bool }. Protected meta keys (those WordPress registers as protected via `is_protected_meta()`) require manage_options capability; otherwise rejected.';
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
            ),
            'required'   => array( 'post_id', 'key' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id', 'key' ) );
        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $key     = sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }
        $this->refuse_missing_post( $post_id );
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'You do not have permission to edit this post.' );
        }
        if ( is_protected_meta( $key, 'post' ) && ! current_user_can( 'manage_options' ) ) {
            throw new \RuntimeException( 'Protected meta keys require administrator privileges.' );
        }
        $value_provided = array_key_exists( 'value', $arguments );
        if ( $value_provided ) {
            $deleted = delete_post_meta( $post_id, $key, $arguments['value'] );
        } else {
            $deleted = delete_post_meta( $post_id, $key );
        }
        return array(
            'deleted'        => (bool) $deleted,
            'post_id'        => $post_id,
            'key'            => $key,
            'value_provided' => $value_provided,
        );
    }
}
