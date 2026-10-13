<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Customer extends Base_Tool {

    public function get_name() {
        return 'wp_wc_create_customer';
    }

    public function get_description() {
        return 'Creates a new WooCommerce customer account. Required: email. Optional: first_name, last_name, username, password, billing, shipping.';
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
                'username'   => array(
                    'type'        => 'string',
                    'description' => 'Customer username.',
                ),
                'password'   => array(
                    'type'        => 'string',
                    'description' => 'Customer password.',
                ),
                'billing'    => array(
                    'type'        => 'object',
                    'description' => 'Billing address object.',
                ),
                'shipping'   => array(
                    'type'        => 'object',
                    'description' => 'Shipping address object.',
                ),
            ),
            'required'   => array( 'email' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'email' ) );

        $params = array(
            'email' => sanitize_text_field( $arguments['email'] ),
        );

        if ( isset( $arguments['first_name'] ) ) {
            $params['first_name'] = sanitize_text_field( $arguments['first_name'] );
        }
        if ( isset( $arguments['last_name'] ) ) {
            $params['last_name'] = sanitize_text_field( $arguments['last_name'] );
        }
        if ( isset( $arguments['username'] ) ) {
            $params['username'] = sanitize_text_field( $arguments['username'] );
        }
        if ( isset( $arguments['password'] ) ) {
            $params['password'] = $arguments['password'];
        }
        if ( isset( $arguments['billing'] ) ) {
            $params['billing'] = $this->parse_json_param( $arguments['billing'], 'billing' );
        }
        if ( isset( $arguments['shipping'] ) ) {
            $params['shipping'] = $this->parse_json_param( $arguments['shipping'], 'shipping' );
        }

        $data = $this->rest_request( 'POST', '/wc/v3/customers', $params );

        return array(
            'id'         => $data['id'],
            'email'      => $data['email'],
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
        );
    }
}
