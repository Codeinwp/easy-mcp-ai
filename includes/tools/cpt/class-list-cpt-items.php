<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_CPT_Items extends Base_Tool {

    public function get_name() {
        return 'wp_list_cpt_items';
    }

    public function get_description() {
        return 'Lists items of any custom post type (CPT) registered with `show_in_rest=true`. Required: `rest_base` (e.g. "products", "events", "movies" — NOT the post_type slug like "product"). DISCOVERY WORKFLOW: always call `wp_get_post_types` first and read each entry\'s `rest_base` field — calling this with a wrong slug returns "rest_no_route". Optional: `per_page` (default 10, max 100), `page`, `search`, `status` (default publish), `orderby` (date/id/title/slug/modified — default date), `order` (asc/desc — default desc). Returns { items: [{ id, title, status, date, modified, link }], total, total_pages, page, per_page, rest_base }. This tool is listed as `read` for all callers regardless of the target CPT, but WordPress enforces edit-level access on the underlying REST request (some CPTs require edit_posts, others a custom capability like WooCommerce\'s edit_products) — a low-privilege caller may still see a permission error on specific CPTs even though the tool itself is visible.';
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
                    'description' => 'The REST API base slug for the post type (e.g. products, portfolio, books). Use wp_get_post_types to find this.',
                ),
                'status'    => array(
                    'type'        => 'string',
                    'description' => 'Status filter. Default: publish.',
                    'default'     => 'publish',
                ),
                'per_page'  => array(
                    'type'        => 'integer',
                    'description' => 'Items per page. Default: 10.',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'      => array(
                    'type'        => 'integer',
                    'description' => 'Page number. Default: 1.',
                    'default'     => 1,
                ),
                'search'    => array(
                    'type'        => 'string',
                    'description' => 'Search keyword.',
                ),
                'orderby'   => array(
                    'type'        => 'string',
                    'description' => 'Sort by: date, id, title, slug, modified. Default: date.',
                    'default'     => 'date',
                ),
                'order'     => array(
                    'type'        => 'string',
                    'description' => 'Sort direction: asc, desc. Default: desc.',
                    'default'     => 'desc',
                ),
            ),
            'required'   => array( 'rest_base' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base' ) );

        $rest_base = $this->validate_rest_route_segment( $arguments['rest_base'], 'rest_base' );

        $request = new \WP_REST_Request( 'GET', '/wp/v2/' . $rest_base );
        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $request->set_param( 'status', isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'publish' );
        $request->set_param( 'per_page', $per_page );
        $request->set_param( 'page', $page );
        $request->set_param( 'context', 'edit' );

        if ( ! empty( $arguments['search'] ) ) {
            $request->set_param( 'search', sanitize_text_field( $arguments['search'] ) );
        }
        if ( ! empty( $arguments['orderby'] ) ) {
            $request->set_param( 'orderby', sanitize_text_field( $arguments['orderby'] ) );
        }
        if ( ! empty( $arguments['order'] ) ) {
            $request->set_param( 'order', sanitize_text_field( $arguments['order'] ) );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    sprintf( 'No REST API route found for post type "%s". This post type may not exist, may not have show_in_rest enabled, or the rest_base may be incorrect. Use wp_get_post_types to discover available post types.', $rest_base ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            if ( $this->is_invalid_page_error( $wp_error ) ) {
                return array_merge(
                    array( 'items' => array() ),
                    $this->pagination_meta( null, $page, $per_page, 0 ),
                    array( 'rest_base' => $rest_base )
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $items   = $response->get_data();

        $result = array();
        foreach ( $items as $item ) {
            $result[] = array(
                'id'       => $item['id'],
                'title'    => $item['title']['raw'] ?? wp_strip_all_tags( $item['title']['rendered'] ?? '' ),
                'status'   => $item['status'],
                'date'     => $item['date'],
                'modified' => $item['modified'] ?? '',
                'link'     => $item['link'] ?? '',
            );
        }

        return array_merge(
            array( 'items' => $result ),
            $this->pagination_meta( $response, $page, $per_page, count( $items ) ),
            array( 'rest_base' => $rest_base )
        );
    }
}
