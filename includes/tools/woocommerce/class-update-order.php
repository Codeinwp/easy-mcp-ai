<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Order extends Base_Tool {

    public function get_name() {
        return 'wp_wc_update_order';
    }

    public function get_description() {
        return 'Updates a WooCommerce order (PATCH semantics). Required: `id`. Optional: `status` (pending/processing/on-hold/completed/cancelled/refunded/failed — changing to "completed" triggers completion hooks including stock reduction and download access), `customer_note` (visible to customer), `billing` (object: first_name, last_name, company, address_1, address_2, city, state, postcode, country, email, phone), `shipping` (same fields minus email/phone). This tool does not currently support editing order line items (WooCommerce itself allows it via REST, but this tool does not expose those parameters). Returns a summary of the updated order: `id`, `number`, `status`, `total`. Requires WooCommerce active.';
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
                'id'            => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the order to update.',
                ),
                'status'        => array(
                    'type'        => 'string',
                    'description' => 'New order status.',
                    'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
                ),
                'customer_note' => array(
                    'type'        => 'string',
                    'description' => 'Note left by customer during checkout.',
                ),
                'billing'       => array(
                    'type'        => 'object',
                    'description' => 'Billing address object.',
                ),
                'shipping'      => array(
                    'type'        => 'object',
                    'description' => 'Shipping address object.',
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
        $id     = $this->parse_required_id( $arguments['id'], 'id' );
        $params = array();

        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }
        if ( isset( $arguments['customer_note'] ) ) {
            $params['customer_note'] = sanitize_text_field( $arguments['customer_note'] );
        }
        if ( isset( $arguments['billing'] ) ) {
            $params['billing'] = $this->parse_json_param( $arguments['billing'], 'billing' );
        }
        if ( isset( $arguments['shipping'] ) ) {
            $params['shipping'] = $this->parse_json_param( $arguments['shipping'], 'shipping' );
        }

        $data = $this->rest_request( 'PUT', '/wc/v3/orders/' . $id, $params );

        return array(
            'id'     => $data['id'],
            'number' => $data['number'],
            'status' => $data['status'],
            'total'  => $data['total'],
        );
    }
}
