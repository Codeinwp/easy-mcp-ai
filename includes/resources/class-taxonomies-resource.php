<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Taxonomies_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://taxonomies/all';
    }

    public function get_name() {
        return 'All Taxonomies';
    }

    public function get_description() {
        return 'All categories and tags with IDs, names, slugs, parent relationships, and post counts.';
    }

    public function read() {
        $categories = get_terms( array(
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ) );

        $tags = get_terms( array(
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
        ) );

        return array(
            'categories' => $this->format_terms( $categories ),
            'tags'       => $this->format_terms( $tags ),
        );
    }

    private function format_terms( $terms ) {
        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return array();
        }

        return array_values( array_map( function ( $term ) {
            return array(
                'id'     => (int) $term->term_id,
                'name'   => $term->name,
                'slug'   => $term->slug,
                'parent' => (int) $term->parent,
                'count'  => (int) $term->count,
            );
        }, $terms ) );
    }
}
