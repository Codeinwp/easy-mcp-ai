<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Hidden_Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Post_Types extends Base_Tool {

    public function get_name() {
        return 'wp_get_post_types';
    }

    public function get_description() {
        return 'Lists the registered WordPress post types the tools can reach: those with REST API access enabled, then custom types hidden from the REST API (`rest_api: false`) that are public with an admin screen. Returns { post_types: [{ slug, name, description, rest_base, hierarchical, rest_api }] }. The CPT tools (`wp_list_cpt_items`, `wp_get_cpt_item`, etc.) and every tool with a `post_type` argument accept either a type\'s `rest_base` or its `slug`; a hidden type is reached by its slug, and only through the CPT tools. Use this to find which post types exist.';
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
                'rest_api'     => true,
            );
        }
        foreach ( Hidden_Post_Types::reachable_types() as $slug => $type ) {
            $post_types[] = array(
                'slug'         => $slug,
                'name'         => (string) $type->label,
                'description'  => (string) $type->description,
                'rest_base'    => $slug,
                'hierarchical' => ! empty( $type->hierarchical ),
                'rest_api'     => false,
            );
        }

        return array(
            'post_types' => $post_types,
        );
    }
}
