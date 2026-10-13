<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Category extends Base_Tool {

    public function get_name() {
        return 'wp_create_category';
    }

    public function get_description() {
        return 'Creates a new WordPress category (hierarchical taxonomy). Required: `name`. Optional: `slug` (auto-derived from name if omitted), `description`, `parent` (ID of parent category — 0 = top-level; categories support nesting unlike tags). Returns { id, name, slug }. A duplicate name under the same parent is rejected with a term_exists error; a same-named category under a different parent is allowed and its slug gets a numeric suffix (e.g. news-2) to stay unique.';
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
                    'description' => 'The name for the category.',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'The slug for the category.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'The description for the category.',
                ),
                'parent'      => array(
                    'type'        => 'integer',
                    'description' => 'The parent category ID.',
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

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/categories', $params );

        return array(
            'id'   => $data['id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
        );
    }
}
