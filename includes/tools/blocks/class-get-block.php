<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Block extends Base_Tool {

    public function get_name() {
        return 'wp_get_block';
    }

    public function get_description() {
        return 'Gets a single reusable block (synced pattern / wp_block) by ID. Returns { id, title, content (raw Gutenberg block markup), status, date, modified, slug }. Reusable blocks are embedded by reference in posts — editing one here updates every post that uses it. Use `wp_list_blocks` to find the ID.';
    }

    public function get_category() {
        return 'blocks';
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
                'block_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the reusable block to retrieve.',
                ),
            ),
            'required'   => array( 'block_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'block_id' ) );

        $block_id = $this->parse_required_id( $arguments['block_id'], 'block_id' );
        $data     = $this->rest_request( 'GET', '/wp/v2/blocks/' . $block_id, array( 'context' => 'edit' ) );

        return array(
            'id'       => $data['id'],
            'title'    => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
            'content'  => $data['content']['raw'] ?? $data['content']['rendered'] ?? '',
            'status'   => $data['status'],
            'date'     => $data['date'],
            'modified' => $data['modified'] ?? '',
            'slug'     => $data['slug'] ?? '',
        );
    }
}
