<?php
namespace Easy_MCP_AI\Tools\Posts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait Post_Copy_Fields {

    private static function copied_template( object $source ): array {
        $template = (string) get_page_template_slug( (int) $source->ID );
        if ( '' === $template ) {
            return array();
        }
        if ( array_key_exists( $template, wp_get_theme()->get_page_templates( null, $source->post_type ) ) ) {
            return array( 'template' => $template );
        }
        return array( 'skipped_template' => $template );
    }

    private function copied_terms( object $source ): array {
        $rest_bases = $this->rest_taxonomies_for( $source->post_type );
        $params     = array();
        $skipped    = array();
        foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
            if ( 'post_format' === $taxonomy ) {
                continue;
            }
            $args = array( 'fields' => 'ids' );
            $tax  = get_taxonomy( $taxonomy );
            if ( is_object( $tax ) && ! empty( $tax->sort ) ) {
                $args['orderby'] = 'term_order';
            }
            $ids = wp_get_object_terms( (int) $source->ID, $taxonomy, $args );
            if ( ! is_array( $ids ) ) {
                continue;
            }
            if ( isset( $rest_bases[ $taxonomy ] ) ) {
                $params[ $rest_bases[ $taxonomy ] ] = array_map( 'intval', $ids );
            } elseif ( array() !== $ids ) {
                $skipped[] = $taxonomy;
            }
        }
        return array(
            'params'  => $params,
            'skipped' => $skipped,
        );
    }
}
