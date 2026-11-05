<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Hidden_Post_Types;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Taxonomies extends Base_Tool {

    public function get_name() {
        return 'wp_get_taxonomies';
    }

    public function get_description() {
        return 'Lists the registered WordPress taxonomies (built-in and plugin-added) the tools can reach: those with REST API access enabled, then public taxonomies hidden from the REST API (`rest_api: false`) that belong to a hidden post type the CPT tools open; the term tools reach those by their slug. Returns { taxonomies: [{ slug, name, description, rest_base, types (post types it applies to), hierarchical, rest_api }] }. `hierarchical=true` means terms support parent/child nesting (like categories); `hierarchical=false` means flat (like tags). Use `rest_base` when calling REST taxonomy endpoints directly.';
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
                'rest_api'     => true,
            );
        }
        foreach ( Hidden_Post_Types::reachable_taxonomies() as $slug => $tax ) {
            $taxonomies[] = array(
                'slug'         => $slug,
                'name'         => (string) $tax->label,
                'description'  => (string) $tax->description,
                'rest_base'    => Hidden_Post_Types::taxonomy_field( $slug, $tax ),
                'types'        => array_values( (array) $tax->object_type ),
                'hierarchical' => ! empty( $tax->hierarchical ),
                'rest_api'     => false,
            );
        }

        return array(
            'taxonomies' => $taxonomies,
        );
    }
}
