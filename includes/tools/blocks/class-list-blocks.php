<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Blocks extends Base_Tool {

    public function get_name() {
        return 'wp_list_blocks';
    }

    public function get_description() {
        return 'Lists all reusable blocks (synced patterns / wp_block post type). Optional: `search`, `status` (publish/draft/trash — default publish), `per_page` (default 10, max 100), `page`. Returns { blocks: [{ id, title, content, status, date }], total, total_pages, page, per_page }. Reusable blocks are shared across posts — a single block can be embedded in many posts simultaneously.';
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
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Items per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search by keyword.',
                ),
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Filter by status.',
                    'enum'        => array( 'publish', 'draft', 'trash' ),
                    'default'     => 'publish',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $request = new \WP_REST_Request( 'GET', '/wp/v2/blocks' );
        $request->set_param( 'per_page', $per_page );
        $request->set_param( 'page', $page );
        $request->set_param( 'status', isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'publish' );
        $request->set_param( 'context', 'edit' );

        if ( ! empty( $arguments['search'] ) ) {
            $request->set_param( 'search', sanitize_text_field( $arguments['search'] ) );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'blocks' => array() ),
                    $this->pagination_meta( null, $page, $per_page, 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $blocks  = $response->get_data();

        $result = array();
        foreach ( $blocks as $block ) {
            $result[] = array(
                'id'      => $block['id'],
                'title'   => $block['title']['raw'] ?? wp_strip_all_tags( $block['title']['rendered'] ?? '' ),
                'content' => $block['content']['raw'] ?? $block['content']['rendered'] ?? '',
                'status'  => $block['status'],
                'date'    => $block['date'],
            );
        }

        return array_merge(
            array( 'blocks' => $result ),
            $this->pagination_meta( $response, $page, $per_page, count( $blocks ) )
        );
    }
}
