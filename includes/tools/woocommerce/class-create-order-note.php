<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Order_Note extends Base_Tool {

    public function get_name() {
        return 'wp_wc_create_order_note';
    }

    public function get_description() {
        return 'Adds a note to a WooCommerce order. Required: `order_id`, `note` (the note text). Optional: `customer_note` (boolean, default false — true makes the note visible to the customer in their order emails and account page; false = private/internal note only). Returns the created note object including id, note content, date_created, customer_note flag. Requires WooCommerce active.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'order_id'      => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the order.',
                ),
                'note'          => array(
                    'type'        => 'string',
                    'description' => 'The note content.',
                ),
                'customer_note' => array(
                    'type'        => 'boolean',
                    'description' => 'If true, the note is visible to the customer in their order emails and account.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'order_id', 'note' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'order_id', 'note' ) );
        $order_id = $this->parse_required_id( $arguments['order_id'], 'order_id' );

        $params = array(
            'note'          => sanitize_text_field( $arguments['note'] ),
            'customer_note' => (bool) ( $arguments['customer_note'] ?? false ),
        );

        $data = $this->rest_request( 'POST', '/wc/v3/orders/' . $order_id . '/notes', $params );

        return $data;
    }
}
