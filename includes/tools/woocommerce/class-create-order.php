<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Order extends Base_Tool {

    public function get_name() {
        return 'wp_wc_create_order';
    }

    public function get_description() {
        return 'Creates a new WooCommerce order programmatically. Required: line_items (array of objects with product_id and quantity; also accepts variation_id, required when product_id refers to a variable product). Optional: status, customer_id, billing (object), shipping (object), coupon_lines (array), set_paid, currency, customer_note, shipping_lines, fee_lines, meta_data, transaction_id. Returns id, number, status, total, and rest_url (the REST API self-link for the order, not a public permalink).';
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
                'line_items'   => array(
                    'type'        => 'array',
                    'description' => 'Array of line item objects with product_id and quantity.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'product_id'   => array( 'type' => 'integer' ),
                            'variation_id' => array(
                                'type'        => 'integer',
                                'description' => 'Variation ID, required when product_id refers to a variable product.',
                            ),
                            'quantity'     => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'status'       => array(
                    'type'        => 'string',
                    'description' => 'Order status.',
                    'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
                    'default'     => 'pending',
                ),
                'customer_id'  => array(
                    'type'        => 'integer',
                    'description' => 'Customer user ID.',
                ),
                'billing'      => array(
                    'type'        => 'object',
                    'description' => 'Billing address object.',
                ),
                'shipping'      => array(
                    'type'        => 'object',
                    'description' => 'Shipping address object.',
                ),
                'coupon_lines'  => array(
                    'type'        => 'array',
                    'description' => 'Array of coupon objects. Each item: { "code": "COUPON_CODE" }.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'code' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'set_paid'      => array(
                    'type'        => 'boolean',
                    'description' => 'Mark the order as paid (true) or pending payment (false).',
                ),
                'currency'      => array(
                    'type'        => 'string',
                    'description' => 'Currency the order was created with, in ISO format (e.g. USD, EUR).',
                ),
                'customer_note' => array(
                    'type'        => 'string',
                    'description' => 'Note left by customer during checkout.',
                ),
                'shipping_lines' => array(
                    'type'        => 'array',
                    'description' => 'Array of shipping line objects (method_id, method_title, total, meta_data).',
                    'items'       => array( 'type' => 'object' ),
                ),
                'fee_lines'     => array(
                    'type'        => 'array',
                    'description' => 'Array of fee line objects (name, total, tax_status, meta_data).',
                    'items'       => array( 'type' => 'object' ),
                ),
                'meta_data'     => array(
                    'type'        => 'array',
                    'description' => 'Array of meta data objects with key and value.',
                    'items'       => array( 'type' => 'object' ),
                ),
                'transaction_id' => array(
                    'type'        => 'string',
                    'description' => 'Unique transaction ID for the order.',
                ),
            ),
            'required'   => array( 'line_items' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'line_items' ) );

        $line_items = $this->parse_json_param( $arguments['line_items'], 'line_items' );
        if ( empty( $line_items ) ) {
            throw new \InvalidArgumentException( 'line_items must contain at least one item.' );
        }

        $params = array(
            'line_items' => $line_items,
            'status'     => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'pending',
        );

        if ( isset( $arguments['customer_id'] ) ) {
            $params['customer_id'] = absint( $arguments['customer_id'] );
        }
        if ( isset( $arguments['billing'] ) ) {
            $params['billing'] = $this->parse_json_param( $arguments['billing'], 'billing' );
        }
        if ( isset( $arguments['shipping'] ) ) {
            $params['shipping'] = $this->parse_json_param( $arguments['shipping'], 'shipping' );
        }
        if ( isset( $arguments['coupon_lines'] ) ) {
            $params['coupon_lines'] = $this->parse_json_param( $arguments['coupon_lines'], 'coupon_lines' );
        }
        if ( isset( $arguments['set_paid'] ) ) {
            $params['set_paid'] = (bool) $arguments['set_paid'];
        }
        if ( isset( $arguments['currency'] ) ) {
            $params['currency'] = sanitize_text_field( $arguments['currency'] );
        }
        if ( isset( $arguments['customer_note'] ) ) {
            $params['customer_note'] = sanitize_textarea_field( $arguments['customer_note'] );
        }
        if ( ! empty( $arguments['shipping_lines'] ) ) {
            $params['shipping_lines'] = $this->parse_json_param( $arguments['shipping_lines'], 'shipping_lines' );
        }
        if ( ! empty( $arguments['fee_lines'] ) ) {
            $params['fee_lines'] = $this->parse_json_param( $arguments['fee_lines'], 'fee_lines' );
        }
        if ( ! empty( $arguments['meta_data'] ) ) {
            $params['meta_data'] = $this->parse_json_param( $arguments['meta_data'], 'meta_data' );
        }
        if ( isset( $arguments['transaction_id'] ) ) {
            $params['transaction_id'] = sanitize_text_field( $arguments['transaction_id'] );
        }

        $data = $this->rest_request( 'POST', '/wc/v3/orders', $params );

        return array(
            'id'       => $data['id'],
            'number'   => $data['number'],
            'status'   => $data['status'],
            'total'    => $data['total'],
            'rest_url' => $data['_links']['self'][0]['href'] ?? '',
        );
    }
}
