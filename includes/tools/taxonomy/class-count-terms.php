<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Cli_Router\Capability_Grants;
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
        $this->require_countable( $taxonomy );
        $count = wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
        if ( is_wp_error( $count ) ) {
            throw new \RuntimeException( $count->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( 'taxonomy' => $taxonomy, 'total' => (int) $count );
    }

    private function require_countable( string $taxonomy ): void {
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! empty( $tax_obj->public ) || ! empty( $tax_obj->show_in_rest ) ) {
            return;
        }
        $manage_terms = (string) $tax_obj->cap->manage_terms;
        if ( ! self::holds( $manage_terms ) ) {
            throw new \RuntimeException(
                sprintf( 'Insufficient capability for taxonomy %1$s: it is neither public nor exposed in the REST API, so counting its terms needs the %2$s capability.', $taxonomy, $manage_terms ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    private static function holds( string $cap ): bool {
        if ( 'manage_links' === $cap ) {
            return Capability_Grants::holds( $cap );
        }
        return current_user_can( $cap );
    }
}
