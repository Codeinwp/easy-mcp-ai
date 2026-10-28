<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Add_Shipping_Method extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_add_shipping_method';
    }

    public function get_description() {
        return 'Adds a shipping method to a WooCommerce shipping zone. Required: `zone_id` (wp_wc_list_shipping_zones; 0 is "Rest of the world") and `method_id`: "flat_rate", "free_shipping", "local_pickup", or another method a shipping plugin adds (an unknown id is refused with the list). Optional: `enabled` (default true) and `settings`, an object of setting id to value, e.g. flat_rate { "title": "Standard", "cost": "5", "tax_status": "taxable" } (cost may be a formula such as "5 + 2 * [qty]"), free_shipping { "requires": "min_amount", "min_amount": "50" }. A setting the method does not have is refused. Returns { zone_id, instance_id, method_id, title, enabled, order, settings }. Change it later with wp_wc_update_shipping_method. Requires WooCommerce active.';
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
                'zone_id'   => array(
                    'type'        => 'integer',
                    'description' => 'Zone id; 0 is "Rest of the world".',
                ),
                'method_id' => array(
                    'type'        => 'string',
                    'description' => 'Method type, e.g. flat_rate, free_shipping, local_pickup.',
                ),
                'enabled'   => array(
                    'type'        => 'boolean',
                    'description' => 'Whether customers can choose it. Default true.',
                ),
                'settings'  => array(
                    'type'        => 'object',
                    'description' => 'Setting id to value, e.g. { "title": "Standard", "cost": "5" }.',
                ),
            ),
            'required'   => array( 'zone_id', 'method_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        if ( ! array_key_exists( 'zone_id', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: zone_id' );
        }
        $zone_id   = $this->parse_zone_id( $arguments['zone_id'] );
        $method_id = self::text_arg( $arguments, 'method_id' );
        if ( '' === $method_id ) {
            throw new \InvalidArgumentException( 'Missing required parameters: method_id' );
        }

        $available = $this->zone_method_fields();
        if ( ! isset( $available[ $method_id ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown method_id "%1$s". This store\'s shipping methods: %2$s.', $method_id, empty( $available ) ? 'none' : implode( ', ', array_keys( $available ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $params = array(
            'method_id' => $method_id,
            'enabled'   => array_key_exists( 'enabled', $arguments ) ? $this->parse_bool( $arguments['enabled'], 'enabled' ) : true,
        );
        if ( array_key_exists( 'settings', $arguments ) ) {
            $params['settings'] = $this->method_settings( $arguments['settings'], $available[ $method_id ], $method_id );
        }

        $method = $this->rest_request( 'POST', '/wc/v3/shipping/zones/' . $zone_id . '/methods', $params );
        return self::present_method( $method, $zone_id );
    }

    protected function zone_method_fields(): array {
        if ( ! function_exists( 'WC' ) ) {
            return array();
        }
        $methods = array();
        foreach ( WC()->shipping()->get_shipping_methods() as $id => $method ) {
            if ( ! is_object( $method ) || ! method_exists( $method, 'supports' ) || ! $method->supports( 'shipping-zones' ) ) {
                continue;
            }
            $fields = array();
            foreach ( (array) $method->get_instance_form_fields() as $key => $field ) {
                if ( is_array( $field ) && 'title' !== ( $field['type'] ?? '' ) ) {
                    $fields[ (string) $key ] = $field;
                }
            }
            $methods[ (string) $id ] = $fields;
        }
        return $methods;
    }
}
