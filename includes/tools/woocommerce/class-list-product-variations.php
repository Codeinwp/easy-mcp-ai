<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Product_Variations extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_product_variations';
    }

    public function get_description() {
        return 'Lists variations for a WooCommerce variable product, paginated (default per_page 10 — use `per_page`/`page` to page through all variations; the response has no total/total_pages count, so callers must keep paging until a short/empty page is returned). Returns each variation\'s id, sku, price, stock_status, stock_quantity, and attributes; `_links` is omitted.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
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
                'product_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the variable product.',
                ),
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of variations per page.',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'       => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
            ),
            'required'   => array( 'product_id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'product_id' ) );
        $product_id = $this->parse_required_id( $arguments['product_id'], 'product_id' );

        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10,
            'page'     => isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1,
        );

        $data = $this->rest_request( 'GET', '/wc/v3/products/' . $product_id . '/variations', $params );

        return array(
            'variations' => $this->strip_response_noise( $data ),
            'product_id' => $product_id,
        );
    }
}
