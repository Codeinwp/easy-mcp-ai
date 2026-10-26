<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Shipping_Zones extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_list_shipping_zones';
    }

    public function get_description() {
        return 'Lists all WooCommerce shipping zones. No parameters. Returns the array of zones from the WooCommerce API, each including id, name, order (display sort order) and locations (each { code, type }: country "US", state "US:CA", continent "EU" or postcode); `_links` is omitted. Zone id=0 is the special "Rest of the World" fallback zone: it covers every location no other zone does, so its locations list is empty. Use the `id` with `wp_wc_list_shipping_methods` to see what shipping options are configured per zone. Requires WooCommerce active.';
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
        $this->require_woocommerce();

        $zones = $this->strip_response_noise( $this->rest_request( 'GET', '/wc/v3/shipping/zones' ) );
        if ( ! is_array( $zones ) ) {
            return $zones;
        }

        foreach ( $zones as $index => $zone ) {
            if ( is_array( $zone ) && isset( $zone['id'] ) ) {
                $zones[ $index ]['locations'] = $this->zone_locations( (int) $zone['id'] );
            }
        }
        return $zones;
    }
}
