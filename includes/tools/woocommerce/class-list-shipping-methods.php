<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Shipping_Methods extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_shipping_methods';
    }

    public function get_description() {
        return 'Lists the shipping methods configured for a specific WooCommerce shipping zone. Required: `zone_id` (get it from `wp_wc_list_shipping_zones`). Returns { methods: [...], zone_id } where each method includes id, instance_id, title, method_id (e.g. flat_rate/free_shipping/local_pickup), enabled (boolean), settings (cost, requires, etc.). Use `wp_wc_list_shipping_zones` first to discover zone IDs. Requires WooCommerce active.';
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
                'zone_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the shipping zone.',
                ),
            ),
            'required'   => array( 'zone_id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        if ( ! array_key_exists( 'zone_id', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: zone_id' );
        }
        $zone_id = (int) $arguments['zone_id'];
        $data    = $this->rest_request( 'GET', '/wc/v3/shipping/zones/' . $zone_id . '/methods' );

        return array(
            'methods' => $data,
            'zone_id' => $zone_id,
        );
    }
}
