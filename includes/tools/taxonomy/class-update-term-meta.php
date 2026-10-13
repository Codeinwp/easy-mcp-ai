<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Term_Meta extends Base_Tool {

    use Term_Meta_Auth_Guard;

    public function get_name() {
        return 'wp_update_term_meta';
    }

    public function get_description() {
        return 'Updates a single term meta key/value. Required: `term_id`, `key`, `value`. Returns { updated: bool, term_id, key }. Scalars are stored as sent; an array or object (repeater rows, a settings blob) is sent as a JSON-encoded string and is stored serialised — wp_get_term_meta returns it structured again. Authorization is WordPress\'s own: the per-term `edit_term` capability, and for a protected key (leading underscore) core\'s `edit_term_meta` check for that term and key — the key\'s registered auth callback plus any auth_term_meta filters; a protected key nothing has registered needs administrator privileges. Not for Yoast SEO: Yoast keeps category and tag SEO in its own settings and never reads _yoast_wpseo_* term meta, so those keys are refused here, and the refusal names the tool that writes them.';
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
            'destructiveHint' => false,
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
                    'description' => 'Meta key to update.',
                ),
                'value'   => array(
                    
                    
                    
                    
                    'description' => 'Meta value to set: a string, number, boolean, array or object (null is refused). Scalars: for booleans use "1" / "0" (WordPress meta storage semantics). Arrays and objects — repeater rows, a settings blob — are stored serialised and wp_get_term_meta returns them structured; JSON-encoded text starting with "[" or "{" is decoded the same way. Consequence: a string that is itself valid JSON for an array or object cannot be stored as literal text.',
                ),
            ),
            'required'   => array( 'term_id', 'key', 'value' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id', 'key' ) );
        if ( ! array_key_exists( 'value', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameter: value' );
        }
        if ( null === $arguments['value'] ) {
            
            throw new \InvalidArgumentException( 'Parameter value cannot be null. Use wp_delete_term_meta to remove the key.' );
        }
        $term_id = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $key     = sanitize_text_field( (string) $arguments['key'] );
        if ( '' === $key ) {
            throw new \InvalidArgumentException( 'Key cannot be empty.' );
        }

        
        
        
        if ( 0 === strpos( $key, '_yoast_wpseo_' ) && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
            throw new \InvalidArgumentException( $this->yoast_term_key_refusal( $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
        $this->assert_term_meta_key_allowed( $key, $term, 'edit_term_meta' );

        
        $blocked_patterns = apply_filters( 'easy_mcp_ai_term_meta_blocked_key_patterns', array() );
        foreach ( $blocked_patterns as $pattern ) {
            if ( fnmatch( $pattern, $key ) ) {
                throw new \RuntimeException( 'This meta key cannot be modified via MCP.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        
        
        
        
        
        $result = update_term_meta( $term_id, $key, $this->normalize_value( $arguments['value'] ) );
        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return array(
            'updated'  => ( false !== $result ),
            'term_id'  => $term_id,
            'taxonomy' => $term->taxonomy,
            'key'      => $key,
        );
    }

    







    private function yoast_term_key_refusal( $key ) {
        $message = sprintf( 'Yoast SEO does not read term meta "%s": it keeps category and tag SEO in its own settings, so this write would change nothing on the site.', $key );
        $enabled = get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        if ( is_array( $enabled ) && in_array( 'yoast-seo', $enabled, true ) ) {
            return $message . ' Use wp_yoast_update_term_seo.';
        }
        return $message . ' Enable the Yoast SEO integration under Easy MCP AI → Tools → Plugins to get wp_yoast_update_term_seo.';
    }

    










    private function normalize_value( $value ) {
        if ( is_object( $value ) ) {
            
            
            $value = json_decode( wp_json_encode( $value ), true );
        }
        if ( is_array( $value ) ) {
            return $value;
        }
        if ( ! is_string( $value ) ) {
            return $value;
        }
        $lead = ltrim( $value );
        if ( '' === $lead || ( '[' !== $lead[0] && '{' !== $lead[0] ) ) {
            return $value;
        }
        $decoded = json_decode( $value, true );
        return is_array( $decoded ) ? $decoded : $value;
    }
}
