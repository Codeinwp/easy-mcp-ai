<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Payment_Gateways extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_payment_gateways';
    }

    public function get_description() {
        return 'Lists all WooCommerce payment gateways, returning only safe metadata per gateway: id, title, description, enabled, method_title, method_description, order. The raw `settings` block (which may contain API credentials for gateways like Stripe/PayPal) is deliberately omitted.';
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
            'properties' => new \stdClass(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/payment_gateways' );

        
        
        
        return array_map( function( $gateway ) {
            return array(
                'id'                 => $gateway['id'] ?? '',
                'title'              => $gateway['title'] ?? '',
                'description'        => $gateway['description'] ?? '',
                'enabled'            => $gateway['enabled'] ?? false,
                'method_title'       => $gateway['method_title'] ?? '',
                'method_description' => $gateway['method_description'] ?? '',
                'order'              => $gateway['order'] ?? '',
            );
        }, $data );
    }
}
