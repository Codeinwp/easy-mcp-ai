<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_CPT_Item extends Base_Tool {

    public function get_name() {
        return 'wp_delete_cpt_item';
    }

    public function get_description() {
        return 'Deletes a custom post type (CPT) item. Required: `rest_base` + `item_id`. Optional: `force` — false (default) moves to trash if the CPT supports trashing (most do); true permanently deletes (irreversible). Trashed items can be restored via `wp_update_cpt_item` setting status="draft" or "publish". CPTs registered without trash support (rare) are always permanently deleted regardless of `force`. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces the CPT\'s own write capability on the underlying REST request (some require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error even though the tool is visible.';
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
            'destructiveHint' => true,
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
                    'description' => 'The ID of the item to delete.',
                ),
                'force'     => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to bypass the trash and force deletion.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'rest_base', 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base', 'item_id' ) );

        $rest_base = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );
        $item_id   = $this->parse_required_id( $arguments['item_id'], 'item_id' );

        $request = new \WP_REST_Request( 'DELETE', '/wp/v2/' . $rest_base . '/' . $item_id );
        if ( isset( $arguments['force'] ) ) {
            $request->set_param( 'force', (bool) $arguments['force'] );
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
                sprintf( 'Failed to delete item in "%s": %s', $rest_base, $wp_error->get_error_message() ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $data = $response->get_data();

        
        
        $id = isset( $data['deleted'] ) ? ( $data['previous']['id'] ?? null ) : ( $data['id'] ?? null );

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }
}
