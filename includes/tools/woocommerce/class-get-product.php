<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Product extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_product';
    }

    public function get_description() {
        return 'Gets a single WooCommerce product by ID. Required: `id`. Returns the full WooCommerce REST API v3 product object — key fields: id, name, slug, permalink, type (simple/variable/grouped/external), status, featured, description, short_description, sku, price, regular_price, sale_price, on_sale, manage_stock, stock_quantity, stock_status (instock/outofstock/onbackorder), weight, dimensions (object with length/width/height), categories (array of {id,name,slug}), tags, images (array), attributes (array), variations (array of IDs — fetch each with wp_wc_get_product_variation), meta_data. SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) is omitted. Requires WooCommerce active.';
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the product to retrieve.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id   = $this->parse_required_id( $arguments['id'], 'id' );
        $data = $this->rest_request( 'GET', '/wc/v3/products/' . $id );

        return $this->strip_response_noise( $data );
    }
}
