<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Tag extends Base_Tool {

    public function get_name() {
        return 'wp_create_tag';
    }

    public function get_description() {
        return 'Creates a new WordPress tag (flat taxonomy — no hierarchy, no `parent`). Required: `name`. Optional: `slug` (auto-derived from name if omitted), `description`. Returns { id, name, slug }. Tags are unique by slug; a duplicate slug triggers an error. For hierarchical terms use `wp_create_category` instead.';
    }

    public function get_category() {
        return 'taxonomy';
    }

    public function get_required_capability() {
        return 'manage_categories';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'The name for the tag.',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'The slug for the tag.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'The description for the tag.',
                ),
            ),
            'required'   => array( 'name' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'name' ) );

        $params = array(
            'name' => sanitize_text_field( $arguments['name'] ),
        );

        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }

        if ( isset( $arguments['description'] ) ) {
            $params['description'] = sanitize_text_field( $arguments['description'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/tags', $params );

        return array(
            'id'   => $data['id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
        );
    }
}
