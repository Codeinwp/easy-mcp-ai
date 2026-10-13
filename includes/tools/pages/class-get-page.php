<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Page extends Base_Tool {

    public function get_name() {
        return 'wp_get_page';
    }

    public function get_description() {
        return 'Gets a single WordPress page by ID. Returns { id, title, content, excerpt, status, date, modified, slug, link, parent (0 if top-level), menu_order, template, author, meta }. Content returned as raw Gutenberg/HTML markup. For CPT items use `wp_get_cpt_item` instead.';
    }

    public function get_category() {
        return 'pages';
    }

    public function get_required_capability() {
        return 'edit_pages'; 
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
                'page_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the page to retrieve.',
                ),
            ),
            'required'   => array( 'page_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'page_id' ) );

        $page_id = $this->parse_required_id( $arguments['page_id'], 'page_id' );
        $data    = $this->rest_request( 'GET', '/wp/v2/pages/' . $page_id, array( 'context' => 'edit' ) );

        return array(
            'id'         => $data['id'],
            'title'      => $data['title']['raw'],
            'content'    => $data['content']['raw'],
            'excerpt'    => $data['excerpt']['raw'],
            'status'     => $data['status'],
            'date'       => $data['date'],
            'modified'   => $data['modified'],
            'slug'       => $data['slug'],
            'parent'     => $data['parent'],
            'menu_order' => $data['menu_order'],
            'template'   => $data['template'],
            'author'     => $data['author'],
            'link'       => $data['link'],
            'meta'       => $data['meta'] ?? array(),
        );
    }
}
