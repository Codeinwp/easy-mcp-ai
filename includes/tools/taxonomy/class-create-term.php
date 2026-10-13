<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








class Create_Term extends Base_Tool {

    public function get_name() {
        return 'wp_create_term';
    }

    public function get_description() {
        return 'Creates a new term in any taxonomy exposed in the REST API (show_in_rest); WooCommerce attribute taxonomies (pa_*) are not. Required: `name`, `taxonomy` (e.g. category, post_tag, product_cat). Optional: `slug` (auto-derived from name if omitted), `description`, `parent` (for hierarchical taxonomies; 0 = top-level). Returns { id, name, slug, description, parent, count, taxonomy }. Capability resolved dynamically via the taxonomy\'s cap->edit_terms (e.g. manage_categories for category, manage_product_terms for product_cat).';
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
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'The name for the term.',
                ),
                'taxonomy'    => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug (e.g. category, post_tag, product_cat).',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'Optional slug (auto-derived from name if omitted).',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Optional term description.',
                ),
                'parent'      => array(
                    'type'        => 'integer',
                    'description' => 'Optional parent term ID (hierarchical taxonomies only).',
                ),
            ),
            'required'   => array( 'name', 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'name', 'taxonomy' ) );
        $taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );

        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown taxonomy: %s', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $this->refuse_non_rest_taxonomy( $taxonomy, $tax_obj );

        
        
        
        
        
        $rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
        $rest_base = $this->validate_rest_route_segment( $rest_base, 'rest_base' );

        $params = array(
            'name' => sanitize_text_field( (string) $arguments['name'] ),
        );
        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( (string) $arguments['slug'] );
        }
        if ( isset( $arguments['description'] ) ) {
            $params['description'] = wp_kses_post( (string) $arguments['description'] );
        }
        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/' . $rest_base, $params );

        return array(
            'id'          => (int) ( $data['id'] ?? 0 ),
            'name'        => (string) ( $data['name'] ?? '' ),
            'slug'        => (string) ( $data['slug'] ?? '' ),
            'description' => (string) ( $data['description'] ?? '' ),
            'parent'      => (int) ( $data['parent'] ?? 0 ),
            'count'       => (int) ( $data['count'] ?? 0 ),
            'taxonomy'    => $taxonomy,
        );
    }
}
