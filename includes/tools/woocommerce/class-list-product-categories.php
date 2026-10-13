<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Product_Categories extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_product_categories';
    }

    public function get_description() {
        return 'Lists WooCommerce product categories. Optional: `search`, `per_page` (default 100 — high default to get all categories in one call), `page`, `hide_empty` (boolean, default false — true returns only categories with products). Returns array of { id, name, slug, parent (0 if top-level), count, description, image }; each category\'s SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) and `_links` are omitted. Categories are hierarchical — use the `id` when assigning categories to products via the `categories` field. Requires WooCommerce active.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_product_terms';
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
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of categories per page.',
                    'default'     => 100,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'       => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'search'     => array(
                    'type'        => 'string',
                    'description' => 'Search term.',
                ),
                'hide_empty' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide empty categories.',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 100,
            'page'     => isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1,
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }
        if ( isset( $arguments['hide_empty'] ) ) {
            $params['hide_empty'] = (bool) $arguments['hide_empty'];
        }

        $data = $this->rest_request( 'GET', '/wc/v3/products/categories', $params );

        return $this->strip_response_noise( $data );
    }
}
