<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Post_Types extends Base_Tool {

    public function get_name() {
        return 'wp_get_post_types';
    }

    public function get_description() {
        return 'Lists all registered WordPress post types that have REST API access enabled. Returns { post_types: [{ slug, name, description, rest_base, hierarchical }] }. The CPT tools (`wp_list_cpt_items`, `wp_get_cpt_item`, etc.) and every tool with a `post_type` argument accept either a type\'s `rest_base` or its `slug`. Use this to find which post types exist.';
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
        $data = $this->rest_request( 'GET', '/wp/v2/types' );

        $post_types = array();
        foreach ( $data as $slug => $type ) {
            $post_types[] = array(
                'slug'         => $slug,
                'name'         => $type['name'],
                'description'  => $type['description'],
                'rest_base'    => $type['rest_base'],
                'hierarchical' => $type['hierarchical'],
            );
        }

        return array(
            'post_types' => $post_types,
        );
    }
}
