<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Get_Term extends Base_Tool {

    public function get_name() {
        return 'wp_get_term';
    }

    public function get_description() {
        return 'Gets a single term from any taxonomy. Required: `term_id`, `taxonomy` (the taxonomy slug, e.g. category, post_tag, product_cat, product_brand). Returns { id, name, slug, description, parent, count, taxonomy }. Capability resolved dynamically per taxonomy via cap->assign_terms. Use this for custom taxonomies that the category/tag-specific tools cannot reach.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'term_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term to retrieve.',
                ),
                'taxonomy' => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug (e.g. category, post_tag, product_cat).',
                ),
            ),
            'required'   => array( 'term_id', 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'term_id', 'taxonomy' ) );
        $term_id  = $this->parse_required_id( $arguments['term_id'], 'term_id' );
        $taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );

        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown taxonomy: %s', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
            throw new \RuntimeException(
                sprintf( 'Insufficient capability for taxonomy %s.', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $term = get_term( $term_id, $taxonomy );
        if ( ! $term || is_wp_error( $term ) ) {
            throw new \InvalidArgumentException( 'Term not found.' );
        }

        return array(
            'id'          => (int) $term->term_id,
            'name'        => (string) $term->name,
            'slug'        => (string) $term->slug,
            'description' => (string) $term->description,
            'parent'      => (int) $term->parent,
            'count'       => (int) $term->count,
            'taxonomy'    => (string) $term->taxonomy,
        );
    }
}
