<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Category extends Base_Tool {

    public function get_name() {
        return 'wp_get_category';
    }

    public function get_description() {
        return 'Gets a single WordPress category by ID. Returns { id, name, slug, description, parent (0 if top-level), count (number of posts assigned) }. To get children, call `wp_list_categories` with `parent` = this ID.';
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
                'category_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the category to retrieve.',
                ),
            ),
            'required'   => array( 'category_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'category_id' ) );

        $category_id = $this->parse_required_id( $arguments['category_id'], 'category_id' );
        $data        = $this->rest_request( 'GET', '/wp/v2/categories/' . $category_id );

        return array(
            'id'          => $data['id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'parent'      => $data['parent'],
            'count'       => $data['count'],
        );
    }
}
