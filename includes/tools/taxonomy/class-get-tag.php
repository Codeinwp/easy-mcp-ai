<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Tag extends Base_Tool {

    public function get_name() {
        return 'wp_get_tag';
    }

    public function get_description() {
        return 'Gets a single WordPress tag by ID. Returns { id, name, slug, description, count (number of posts tagged) }. Tags are flat (no parent). To look up a tag by name/slug, use `wp_list_tags` with `search` parameter.';
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
                'tag_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the tag to retrieve.',
                ),
            ),
            'required'   => array( 'tag_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'tag_id' ) );

        $tag_id = $this->parse_required_id( $arguments['tag_id'], 'tag_id' );
        $data   = $this->rest_request( 'GET', '/wp/v2/tags/' . $tag_id );

        return array(
            'id'          => $data['id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
            'count'       => $data['count'],
        );
    }
}
