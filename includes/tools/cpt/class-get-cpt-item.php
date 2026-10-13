<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_CPT_Item extends Base_Tool {

    public function get_name() {
        return 'wp_get_cpt_item';
    }

    public function get_description() {
        return 'Gets a single custom post type (CPT) item by REST base + item ID. Required: `rest_base` (the CPT\'s rest_base, NOT its post_type slug — call `wp_get_post_types` to discover) and `item_id`. Returns a fixed set of fields: { id, title, content, excerpt, status, date, modified, slug, link }. It does NOT include custom `register_rest_field` extensions (e.g. ACF fields) — those are silently discarded. For built-in post types use `wp_get_post` / `wp_get_page` instead — those have richer schemas. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces edit-level access on the underlying REST request (some CPTs require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error on specific CPTs even though the tool itself is visible.';
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
            'readOnlyHint'    => true,
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
                    'description' => 'The ID of the item to retrieve.',
                ),
            ),
            'required'   => array( 'rest_base', 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base', 'item_id' ) );

        $rest_base = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );
        $item_id   = $this->parse_required_id( $arguments['item_id'], 'item_id' );

        $request = new \WP_REST_Request( 'GET', '/wp/v2/' . $rest_base . '/' . $item_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    sprintf( 'No REST API route found for post type "%s". This post type may not exist, may not have show_in_rest enabled, or the rest_base may be incorrect. Use wp_get_post_types to discover available post types.', $rest_base ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException(
                sprintf( 'Failed to retrieve item in "%s": %s', $rest_base, $wp_error->get_error_message() ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $data = $response->get_data();

        return array(
            'id'       => $data['id'],
            'title'    => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
            'content'  => $data['content']['raw'] ?? wp_strip_all_tags( $data['content']['rendered'] ?? '' ),
            'excerpt'  => $data['excerpt']['raw'] ?? wp_strip_all_tags( $data['excerpt']['rendered'] ?? '' ),
            'status'   => $data['status'],
            'date'     => $data['date'],
            'modified' => $data['modified'] ?? '',
            'slug'     => $data['slug'] ?? '',
            'link'     => $data['link'] ?? '',
        );
    }
}
