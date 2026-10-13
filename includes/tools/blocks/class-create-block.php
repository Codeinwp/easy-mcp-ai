<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Block extends Base_Tool {

    public function get_name() {
        return 'wp_create_block';
    }

    public function get_description() {
        return 'Creates a new reusable block (synced pattern) with the given title and block markup content. Required: `title`, `content`. Optional: `status` (publish/draft — default publish), `slug` (alphanumeric identifier), `meta` (object of meta fields to set). If **Easy MCP AI → Settings → Force draft on create** is enabled, `status` is silently overridden to `draft` regardless of the value supplied. Content must be valid block markup (e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->). Plain HTML will be stored but renders as a Classic block.';
    }

    public function get_category() {
        return 'blocks';
    }

    public function get_required_capability() {
        return 'publish_posts'; 
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
                'title'   => array(
                    'type'        => 'string',
                    'description' => 'The title for the reusable block.',
                ),
                'content' => array(
                    'type'        => 'string',
                    'description' => 'Block markup content (e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->).',
                ),
                'status'  => array(
                    'type'        => 'string',
                    'description' => 'The status for the block.',
                    'enum'        => array( 'publish', 'draft' ),
                    'default'     => 'publish',
                ),
                'slug'    => array(
                    'type'        => 'string',
                    'description' => 'An alphanumeric identifier for the block (slug).',
                ),
                'meta'    => array(
                    'type'        => 'object',
                    'description' => 'Meta fields to set on the block.',
                ),
            ),
            'required'   => array( 'title', 'content' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'title', 'content' ) );
        $this->validate_title_length( $arguments['title'] );

        $params = array(
            'title'   => sanitize_text_field( $arguments['title'] ),
            'content' => $arguments['content'],
            'status'  => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'publish',
        );

        if ( isset( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }
        if ( isset( $arguments['meta'] ) ) {
            $params['meta'] = $this->parse_json_param( $arguments['meta'], 'meta' );
        }

        $this->maybe_force_draft( $params );

        $data = $this->rest_request( 'POST', '/wp/v2/blocks', $params );

        return array(
            'id'             => $data['id'],
            'title'          => $data['title']['raw'] ?? $data['title']['rendered'],
            'status'         => $data['status'],
            'content_format' => 'block_markup',
            'content_hint'   => 'Content should be valid block markup (e.g. <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->). Plain HTML will render as a Classic block.',
        );
    }
}
