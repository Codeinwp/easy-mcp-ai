<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Order_Notes extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_order_notes';
    }

    public function get_description() {
        return 'Lists all notes attached to a WooCommerce order. Required: `order_id`. Returns { notes: [...] } where each note includes id, note (text), date_created, customer_note (true = customer-visible, false = private/internal), added_by (system/admin username); `_links` is omitted. Includes system-generated status-change notes (e.g. "Order status changed from pending to processing"). Requires WooCommerce active.';
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
        $data     = $this->rest_request( 'GET', '/wc/v3/orders/' . $order_id . '/notes' );

        return array(
            'notes'    => $this->strip_response_noise( $data ),
            'order_id' => $order_id,
        );
    }
}
