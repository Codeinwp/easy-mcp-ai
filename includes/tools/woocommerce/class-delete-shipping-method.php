<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Shipping_Method extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_delete_shipping_method';
    }

    public function get_description() {
        return 'Permanently removes a shipping method from a WooCommerce shipping zone, with its settings. Required: `zone_id` and `instance_id` (wp_wc_list_shipping_methods; zone 0 is "Rest of the world"). To stop offering it but keep its settings, use wp_wc_update_shipping_method with enabled=false instead. Returns { deleted: true, zone_id, instance_id, method_id, title }. Requires WooCommerce active.';
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
            'destructiveHint' => true,
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
            ),
            'required'   => array( 'zone_id', 'instance_id' ),
        );
    }

    public function describe( array $arguments ) {
        $zone_id     = isset( $arguments['zone_id'] ) ? (int) $arguments['zone_id'] : -1;
        $instance_id = isset( $arguments['instance_id'] ) ? (int) $arguments['instance_id'] : 0;
        $method      = $zone_id >= 0 && $instance_id > 0 ? $this->peek( '/wc/v3/shipping/zones/' . $zone_id . '/methods/' . $instance_id, 'title,method_id' ) : null;
        if ( ! $method ) {
            return '';
        }
        return sprintf(
            /* translators: 1: method title, 2: method type, 3: instance id, 4: zone id */
            __( "Permanently remove shipping method '%1\$s' (%2\$s, instance %3\$d) from shipping zone %4\$d.", 'easy-mcp-ai' ),
            (string) ( $method['title'] ?? '' ),
            (string) ( $method['method_id'] ?? '' ),
            $instance_id,
            $zone_id
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

        $method = $this->rest_request( 'DELETE', '/wc/v3/shipping/zones/' . $zone_id . '/methods/' . $instance_id, array( 'force' => true ) );

        return array(
            'deleted'     => true,
            'zone_id'     => $zone_id,
            'instance_id' => $instance_id,
            'method_id'   => (string) ( $method['method_id'] ?? '' ),
            'title'       => (string) ( $method['title'] ?? '' ),
        );
    }
}
