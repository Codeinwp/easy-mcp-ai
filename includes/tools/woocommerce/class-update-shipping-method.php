<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Update_Shipping_Method extends Base_Tool {

    use Store_Setup;

    const FIELDS = array( 'enabled', 'order', 'settings' );

    public function get_name() {
        return 'wp_wc_update_shipping_method';
    }

    public function get_description() {
        return 'Changes a shipping method in a WooCommerce shipping zone. Required: `zone_id` and `instance_id` (both from wp_wc_list_shipping_methods; zone 0 is "Rest of the world"). Optional: `enabled` (true/false), `order` (integer) and `settings`, an object of setting id to value, e.g. { "cost": "7.50" }; settings not sent keep their value, and a setting the method does not have is refused. Returns { zone_id, instance_id, method_id, title, enabled, order, settings, changed }, where `changed` lists what differs from before ("enabled", "order", "settings.cost"); it is empty when the method already had these values. Requires WooCommerce active.';
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
                'zone_id'     => array(
                    'type'        => 'integer',
                    'description' => 'Zone id; 0 is "Rest of the world".',
                ),
                'instance_id' => array(
                    'type'        => 'integer',
                    'description' => 'The method\'s instance id in the zone.',
                ),
                'enabled'     => array(
                    'type'        => 'boolean',
                    'description' => 'Whether customers can choose it.',
                ),
                'order'       => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'Sort order within the zone.',
                ),
                'settings'    => array(
                    'type'        => 'object',
                    'description' => 'Setting id to new value, e.g. { "cost": "7.50" }.',
                ),
            ),
            'required'   => array( 'zone_id', 'instance_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        if ( ! array_key_exists( 'zone_id', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: zone_id' );
        }
        $zone_id = $this->parse_zone_id( $arguments['zone_id'] );
        $this->validate_required( $arguments, array( 'instance_id' ) );
        $instance_id = $this->parse_required_id( $arguments['instance_id'], 'instance_id' );
        $route       = '/wc/v3/shipping/zones/' . $zone_id . '/methods/' . $instance_id;

        $params = array();
        if ( array_key_exists( 'enabled', $arguments ) ) {
            $params['enabled'] = $this->parse_bool( $arguments['enabled'], 'enabled' );
        }
        if ( isset( $arguments['order'] ) ) {
            $params['order'] = $this->parse_non_negative_int( $arguments['order'], 'order' );
        }
        $sends_settings = array_key_exists( 'settings', $arguments ) && array() !== $arguments['settings'];
        $this->refuse_empty_update( $sends_settings ? $params + array( 'settings' => true ) : $params, self::FIELDS );

        $raw = $this->rest_request( 'GET', $route );
        if ( $sends_settings ) {
            $params['settings'] = $this->method_settings( $arguments['settings'], self::method_fields( $raw ), (string) ( $raw['method_id'] ?? '' ) );
        }
        $before = self::present_method( $raw, $zone_id );

        $after            = self::present_method( $this->rest_request( 'PUT', $route, $params ), $zone_id );
        $after['changed'] = array_merge(
            self::changed_fields( $before, $after, array( 'enabled', 'order' ) ),
            array_map(
                static function ( $key ) {
                    return 'settings.' . $key;
                },
                self::changed_fields( $before['settings'], $after['settings'], array_keys( $after['settings'] ) )
            )
        );
        return $after;
    }
}
