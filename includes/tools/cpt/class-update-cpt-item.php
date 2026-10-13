<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_CPT_Item extends Base_Tool {

    public function get_name() {
        return 'wp_update_cpt_item';
    }

    public function get_description() {
        return 'Updates an existing custom post type (CPT) item. Required: `rest_base` + `item_id`. Only fields you provide are changed (PATCH semantics). Common: `title`, `content`, `status` (publish/draft/pending/private), `slug`, `excerpt`, `meta` (only meta keys registered with `show_in_rest=true` will persist). Status transitions follow normal WordPress rules. Note: unlike `wp_update_post`/`wp_update_page`, passing an empty string for `title`, `content`, or `excerpt` here DOES blank the field rather than preserving the existing value. Discover the CPT\'s `rest_base` via `wp_get_post_types`. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces the CPT\'s own write capability on the underlying REST request (some require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error even though the tool is visible.';
    }

    public function get_category() {
        return 'cpt';
    }

    
    public function get_required_capability() {
        return 'read';
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
                'rest_base' => array(
                    'type'        => 'string',
                    'description' => 'The REST API base slug for the post type.',
                ),
                'item_id'   => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the item to update.',
                ),
                'title'     => array(
                    'type'        => 'string',
                    'description' => 'New title for the item.',
                ),
                'content'   => array(
                    'type'        => 'string',
                    'description' => 'New content for the item.',
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => 'New status for the item.',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
                ),
                'excerpt'   => array(
                    'type'        => 'string',
                    'description' => 'New excerpt for the item. HTML is accepted and sanitized by WordPress per the calling user\'s capability.',
                ),
                'slug'      => array(
                    'type'        => 'string',
                    'description' => 'URL-friendly slug for the item.',
                ),
                'meta'      => array(
                    'type'        => 'object',
                    'description' => 'Meta fields to set (only keys registered with show_in_rest=true will persist).',
                ),
            ),
            'required'   => array( 'rest_base', 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base', 'item_id' ) );
        $this->validate_title_length( isset( $arguments['title'] ) ? $arguments['title'] : null );

        $rest_base = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );
        $item_id   = $this->parse_required_id( $arguments['item_id'], 'item_id' );

        $params = array();
        if ( isset( $arguments['title'] ) ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }
        if ( isset( $arguments['content'] ) ) {
            
            
            
            
            $params['content'] = $arguments['content'];
        }
        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }
        if ( isset( $arguments['excerpt'] ) ) {
            
            
            
            
            
            $params['excerpt'] = $arguments['excerpt'];
        }
        if ( ! empty( $arguments['slug'] ) ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }
        if ( isset( $arguments['meta'] ) ) {
            $params['meta'] = $this->parse_json_param( $arguments['meta'], 'meta' );
        }

        $request = new \WP_REST_Request( 'POST', '/wp/v2/' . $rest_base . '/' . $item_id );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    sprintf( 'No REST API route found for post type "%s". This post type may not exist, may not have show_in_rest enabled, or the rest_base may be incorrect. Use wp_get_post_types to discover available post types.', $rest_base ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException(
                sprintf( 'Failed to update item in "%s": %s', $rest_base, $wp_error->get_error_message() ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $data = $response->get_data();

        return array(
            'id'     => $data['id'],
            'title'  => $data['title']['raw'] ?? $data['title']['rendered'],
            'status' => $data['status'],
            'link'   => $data['link'] ?? '',
        );
    }
}
