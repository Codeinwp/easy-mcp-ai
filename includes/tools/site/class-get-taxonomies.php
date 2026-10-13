<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Taxonomies extends Base_Tool {

    public function get_name() {
        return 'wp_get_taxonomies';
    }

    public function get_description() {
        return 'Lists all registered WordPress taxonomies (built-in and plugin-added) that have REST API access enabled. Returns { taxonomies: [{ slug, name, description, rest_base, types (post types it applies to), hierarchical }] }. `hierarchical=true` means terms support parent/child nesting (like categories); `hierarchical=false` means flat (like tags). Use `rest_base` when calling REST taxonomy endpoints directly.';
    }

    public function get_category() {
        return 'site';
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
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $data = $this->rest_request( 'GET', '/wp/v2/taxonomies' );

        $taxonomies = array();
        foreach ( $data as $slug => $tax ) {
            $taxonomies[] = array(
                'slug'         => $slug,
                'name'         => $tax['name'],
                'description'  => $tax['description'],
                'rest_base'    => $tax['rest_base'],
                'types'        => $tax['types'],
                'hierarchical' => $tax['hierarchical'],
            );
        }

        return array(
            'taxonomies' => $taxonomies,
        );
    }
}
