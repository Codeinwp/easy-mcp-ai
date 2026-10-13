<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Order extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_order';
    }

    public function get_description() {
        return 'Gets a single WooCommerce order by ID. Required: `id`. Returns the full WooCommerce REST API v3 order object — key fields: id, number, status, currency, date_created, date_modified, customer_id, customer_note, billing (address object), shipping (address object), payment_method, payment_method_title, transaction_id, date_paid, date_completed, discount_total, shipping_total, cart_tax, total, total_tax, line_items (array with product_id/name/quantity/total), shipping_lines, coupon_lines, fee_lines, tax_lines, refunds, meta_data. Requires WooCommerce active.';
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
                    'description' => 'The ID of the order to retrieve.',
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
        $data = $this->rest_request( 'GET', '/wc/v3/orders/' . $id );

        return $data;
    }
}
