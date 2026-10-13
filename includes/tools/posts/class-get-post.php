<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Post extends Base_Tool {

    public function get_name() {
        return 'wp_get_post';
    }

    public function get_description() {
        return 'Gets a single WordPress post by ID. Returns { id, title, content, excerpt, status, date, modified, slug, link, format, categories (array of IDs), tags (array of IDs), featured_media (attachment ID), author (user ID), sticky, comment_status, ping_status, meta (object of registered post meta) }. Content is returned as raw Gutenberg/HTML markup (not rendered). For CPT items use `wp_get_cpt_item` instead.';
    }

    public function get_category() {
        return 'posts';
    }

    public function get_required_capability() {
        return 'edit_posts'; 
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
                'post_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to retrieve.',
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $data    = $this->rest_request( 'GET', '/wp/v2/posts/' . $post_id, array( 'context' => 'edit' ) );

        return array(
            'id'             => $data['id'],
            'title'          => $data['title']['raw'],
            'content'        => $data['content']['raw'],
            'excerpt'        => $data['excerpt']['raw'],
            'status'         => $data['status'],
            'date'           => $data['date'],
            'modified'       => $data['modified'],
            'slug'           => $data['slug'],
            'author'         => $data['author'],
            'categories'     => $data['categories'],
            'tags'           => $data['tags'],
            'featured_media' => $data['featured_media'],
            'format'         => $data['format'],
            'link'           => $data['link'],
            'comment_status' => $data['comment_status'],
            'ping_status'    => $data['ping_status'],
            'sticky'         => $data['sticky'],
            'meta'           => $data['meta'] ?? array(),
        );
    }
}
