<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Theme_Templates_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/theme-templates';
    }

    public function get_name() {
        return 'Theme Templates';
    }

    public function get_description() {
        return 'Available block templates and template parts from the active theme.';
    }

    public function read() {
        $templates = get_block_templates( array(), 'wp_template' );
        $parts     = get_block_templates( array(), 'wp_template_part' );

        return array(
            'templates'      => $this->format_items( $templates ),
            'template_parts' => $this->format_items( $parts ),
        );
    }

    private function format_items( $items ) {
        if ( ! is_array( $items ) ) {
            return array();
        }

        return array_values( array_map( function ( $item ) {
            return array(
                'id'     => $item->id,
                'slug'   => $item->slug,
                'title'  => isset( $item->title ) ? $item->title : $item->slug,
                'type'   => $item->type,
                'source' => isset( $item->source ) ? $item->source : 'theme',
            );
        }, $items ) );
    }
}
