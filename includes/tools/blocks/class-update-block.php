<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Block extends Base_Tool {

    public function get_name() {
        return 'wp_update_block';
    }

    public function get_description() {
        return 'Updates an existing reusable block (synced pattern) by ID (PATCH semantics). Required: `block_id`. Optional: `title`, `content` (must be valid Gutenberg block markup — plain HTML becomes a Classic block), `status` (publish/draft). WARNING: changes propagate immediately to every post that embeds this block. Note: unlike `wp_update_post`/`wp_update_page`, passing an empty string for `title` here DOES blank the field rather than preserving the existing value. Returns { id, title, status } — when `content` is supplied, also returns content_format and content_hint fields.';
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
            'readOnlyHint'    => false,
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
                    'description' => 'The ID of the reusable block to update.',
                ),
                'title'    => array(
                    'type'        => 'string',
                    'description' => 'New title for the block.',
                ),
                'content'  => array(
                    'type'        => 'string',
                    'description' => 'New block markup content.',
                ),
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'New status for the block.',
                    'enum'        => array( 'publish', 'draft' ),
                ),
            ),
            'required'   => array( 'block_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'block_id' ) );
        $this->validate_title_length( isset( $arguments['title'] ) ? $arguments['title'] : null );

        $block_id = $this->parse_required_id( $arguments['block_id'], 'block_id' );
        $params   = array();

        if ( isset( $arguments['title'] ) ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }

        if ( isset( $arguments['content'] ) ) {
            $params['content'] = $arguments['content'];
        }

        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/blocks/' . $block_id, $params );

        $result = array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
        );

        if ( isset( $arguments['content'] ) ) {
            $result['content_format'] = 'block_markup';
            $result['content_hint']   = 'Content should be valid block markup (e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->). Plain HTML will render as a Classic block.';
        }

        return $result;
    }
}
