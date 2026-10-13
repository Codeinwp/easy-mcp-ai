<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Customer extends Base_Tool {

    public function get_name() {
        return 'wp_wc_update_customer';
    }

    public function get_description() {
        return 'Updates a WooCommerce customer account (PATCH semantics). Required: `id`. Optional: `email`, `first_name`, `last_name`, `username`, `password` (hashed on save, not returned), `billing` (object: first_name, last_name, company, address_1, address_2, city, state, postcode, country, email, phone), `shipping` (object: first_name, last_name, company, address_1, address_2, city, state, postcode, country). Returns a summary of the updated customer: `id`, `email`, `first_name`, `last_name`. Requires WooCommerce active.';
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
                'id'         => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the customer to update.',
                ),
                'email'      => array(
                    'type'        => 'string',
                    'description' => 'Customer email address.',
                ),
                'first_name' => array(
                    'type'        => 'string',
                    'description' => 'Customer first name.',
                ),
                'last_name'  => array(
                    'type'        => 'string',
                    'description' => 'Customer last name.',
                ),
                'billing'    => array(
                    'type'        => 'object',
                    'description' => 'Billing address object.',
                ),
                'shipping'   => array(
                    'type'        => 'object',
                    'description' => 'Shipping address object.',
                ),
                'password'   => array(
                    'type'        => 'string',
                    'description' => 'Customer password.',
                ),
                'username'   => array(
                    'type'        => 'string',
                    'description' => 'Customer username.',
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

        if ( isset( $arguments['email'] ) ) {
            $params['email'] = sanitize_text_field( $arguments['email'] );
        }
        if ( isset( $arguments['first_name'] ) ) {
            $params['first_name'] = sanitize_text_field( $arguments['first_name'] );
        }
        if ( isset( $arguments['last_name'] ) ) {
            $params['last_name'] = sanitize_text_field( $arguments['last_name'] );
        }
        if ( isset( $arguments['billing'] ) ) {
            $params['billing'] = $this->parse_json_param( $arguments['billing'], 'billing' );
        }
        if ( isset( $arguments['shipping'] ) ) {
            $params['shipping'] = $this->parse_json_param( $arguments['shipping'], 'shipping' );
        }
        if ( isset( $arguments['password'] ) ) {
            $params['password'] = $arguments['password'];
        }
        if ( isset( $arguments['username'] ) ) {
            $params['username'] = sanitize_text_field( $arguments['username'] );
        }

        $data = $this->rest_request( 'PUT', '/wc/v3/customers/' . $id, $params );

        return array(
            'id'         => $data['id'],
            'email'      => $data['email'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
        );
    }
}
