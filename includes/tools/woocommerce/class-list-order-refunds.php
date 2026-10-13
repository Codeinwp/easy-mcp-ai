<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Order_Refunds extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_order_refunds';
    }

    public function get_description() {
        return 'Lists all refunds issued for a WooCommerce order. Required: `order_id`. Returns { refunds: [...] } where each refund includes id, date_created, amount (string), reason, refunded_by (user ID), line_items (which items/quantities were refunded). Use `wp_wc_get_order` to get the parent order\'s total and status first. Requires WooCommerce active.';
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
                'order_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the order.',
                ),
            ),
            'required'   => array( 'order_id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'order_id' ) );
        $order_id = $this->parse_required_id( $arguments['order_id'], 'order_id' );

        $data = $this->rest_request( 'GET', '/wc/v3/orders/' . $order_id . '/refunds' );

        return array( 'refunds' => $data );
    }
}
