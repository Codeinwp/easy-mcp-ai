<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Product_Variation extends Base_Tool {

    public function get_name() {
        return 'wp_wc_delete_product_variation';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce product variation by parent product ID and variation ID. Required: `product_id`, `id`. Deletion is immediate and irreversible (force=true). Orders that included this variation are preserved. Returns { deleted: true, id, product_id }. Requires WooCommerce active.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'delete_products';
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
                'product_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent variable product.',
                ),
                'id'         => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the variation to delete.',
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

        $this->rest_request( 'DELETE', '/wc/v3/products/' . $product_id . '/variations/' . $id, array( 'force' => true ) );

        return array(
            'deleted'    => true,
            'id'         => $id,
            'product_id' => $product_id,
        );
    }
}
