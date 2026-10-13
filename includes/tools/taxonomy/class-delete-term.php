<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Delete_Term extends Base_Tool {

    public function get_name() {
        return 'wp_delete_term';
    }

    public function get_description() {
        return 'Deletes a term from any taxonomy exposed in the REST API (show_in_rest); WooCommerce attribute taxonomies (pa_*) are not. Required: `term_id`, `taxonomy`. Returns { deleted: true, term_id, taxonomy }. Capability resolved dynamically via taxonomy cap->delete_terms. Note: deleting a term removes the term-object relationships but does not delete the related posts.';
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
                'term_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the term to delete.',
                ),
                'taxonomy' => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug.',
                ),
            ),
            'required'   => array( 'term_id', 'taxonomy' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id       = isset( $arguments['term_id'] ) ? (int) $arguments['term_id'] : 0;
        $taxonomy = isset( $arguments['taxonomy'] ) ? (string) $arguments['taxonomy'] : '';
        $tax_obj  = '' !== $taxonomy && function_exists( 'get_taxonomy' ) ? \get_taxonomy( $taxonomy ) : null;
        if ( $id <= 0 || ! $tax_obj ) {
            return '';
        }
        $rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
        if ( ! preg_match( '/^[A-Za-z0-9_-]+$/', $rest_base ) ) {
            return '';
        }
        $term = $this->peek( '/wp/v2/' . $rest_base . '/' . $id, 'id,name,count' );
        if ( ! $term ) {
            return '';
        }
        return sprintf(
            /* translators: 1: term name, 2: taxonomy, 3: term id, 4: number of posts */
            __( "Permanently delete the %2\$s '%1\$s' (ID %3\$d), currently on %4\$d post(s); this cannot be undone.", 'easy-mcp-ai' ),
            self::rest_title( $term, 'name' ), $taxonomy, $id, isset( $term['count'] ) ? (int) $term['count'] : 0
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
        $this->refuse_non_rest_taxonomy( $taxonomy, $tax_obj );
        
        
        $rest_base = ! empty( $tax_obj->rest_base ) ? $tax_obj->rest_base : $taxonomy;
        $rest_base = $this->validate_rest_route_segment( $rest_base, 'rest_base' );

        $this->rest_request( 'DELETE', '/wp/v2/' . $rest_base . '/' . $term_id, array( 'force' => true ) );

        return array(
            'deleted'  => true,
            'term_id'  => $term_id,
            'taxonomy' => $taxonomy,
        );
    }
}
