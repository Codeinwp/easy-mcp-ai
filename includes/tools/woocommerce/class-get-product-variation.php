<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Product_Variation extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_product_variation';
    }

    public function get_description() {
        return 'Gets a single WooCommerce product variation by parent product ID and variation ID. Required: `product_id`, `id`. Returns the full WC REST API v3 variation object — key fields: id, sku, regular_price, sale_price, price, on_sale, status, stock_status, stock_quantity, manage_stock, weight, dimensions, attributes (array of {id,name,option}), image, description, downloadable, virtual, meta_data. Requires WooCommerce active.';
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
                    'description' => 'The ID of the parent variable product.',
                ),
                'id'         => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the variation to retrieve.',
                ),
            ),
            'required'   => array( 'product_id', 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'product_id', 'id' ) );
        $product_id = $this->parse_required_id( $arguments['product_id'], 'product_id' );
        $id         = $this->parse_required_id( $arguments['id'], 'id' );

        return $this->rest_request( 'GET', '/wc/v3/products/' . $product_id . '/variations/' . $id );
    }
}
