<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Count_Terms extends Base_Tool {

    public function get_name() {
        return 'wp_count_terms';
    }

    public function get_description() {
        return 'Counts the terms in a taxonomy and returns the total. Required: `taxonomy` (slug). Returns { taxonomy, total }. For built-in taxonomies use \'category\' or \'post_tag\'; for WooCommerce use \'product_cat\' or \'product_tag\'; check `wp_get_taxonomies` for available slugs.';
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
                'taxonomy' => array(
                    'type'        => 'string',
                    'description' => 'Taxonomy slug.',
                ),
            ),
            'required'   => array( 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'taxonomy' ) );
        $taxonomy = sanitize_key( (string) $arguments['taxonomy'] );
        if ( ! taxonomy_exists( $taxonomy ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown taxonomy: %s', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        if ( is_wp_error( $count ) ) {
            throw new \RuntimeException( $count->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( 'taxonomy' => $taxonomy, 'total' => (int) $count );
    }
}
