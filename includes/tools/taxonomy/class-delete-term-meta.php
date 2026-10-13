<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Term_Meta extends Base_Tool {

    use Term_Meta_Auth_Guard;

    public function get_name() {
        return 'wp_delete_term_meta';
    }

    public function get_description() {
        return 'Deletes a term meta key. Required: `term_id`, `key`. Optional: `value` — if provided, deletes only rows matching that value; omit to remove ALL rows for the key. Returns { deleted: bool, term_id, taxonomy, key, value_provided: bool }. Authorization is WordPress\'s own: the per-term `edit_term` capability, and for a protected key (leading underscore) core\'s `delete_term_meta` check for that term and key — the key\'s registered auth callback plus any auth_term_meta filters; a protected key nothing has registered needs administrator privileges.';
    }

    public function get_category() {
        return 'taxonomy';
    }

    public function get_required_capability() {
        
        
        
        
        
        
        return 'read';
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
                'term_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term.',
                ),
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'Meta key to delete.',
                ),
                'value'   => array(
                    'type'        => 'string',
                    'description' => 'If provided, only rows matching this value are deleted. Omit to remove all rows for the key. For booleans use "1" / "0" (WordPress meta storage semantics); numbers are also accepted as strings. Caution: a value another plugin wrote as a native false is stored as an empty string, so "0" will not match it — and passing an empty string here deletes EVERY row for the key, not just the empty-valued one.',
                ),
            ),
            'required'   => array( 'term_id', 'key' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id', 'key' ) );
        $term_id = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $key     = sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }

        $term = get_term( $term_id );
        if ( ! $term || is_wp_error( $term ) ) {
            throw new \InvalidArgumentException( 'Term not found.' );
        }
        $tax_obj = get_taxonomy( $term->taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException( 'Invalid taxonomy.' );
        }
        
        
        if ( ! current_user_can( 'edit_term', $term_id ) ) {
            throw new \RuntimeException( sprintf( 'Insufficient capability for taxonomy %s: WordPress does not grant edit_term for term %d to the current user.', $term->taxonomy, $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->assert_term_meta_key_allowed( $key, $term, 'delete_term_meta' );

        $blocked_patterns = apply_filters( 'easy_mcp_ai_term_meta_blocked_key_patterns', array() );
        foreach ( $blocked_patterns as $pattern ) {
            if ( fnmatch( $pattern, $key ) ) {
                throw new \RuntimeException( 'This meta key cannot be deleted via MCP.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $value_provided = array_key_exists( 'value', $arguments );
        if ( $value_provided ) {
            $deleted = delete_term_meta( $term_id, $key, $arguments['value'] );
        } else {
            $deleted = delete_term_meta( $term_id, $key );
        }

        return array(
            'deleted'        => (bool) $deleted,
            'term_id'        => $term_id,
            'taxonomy'       => $term->taxonomy,
            'key'            => $key,
            'value_provided' => $value_provided,
        );
    }
}
