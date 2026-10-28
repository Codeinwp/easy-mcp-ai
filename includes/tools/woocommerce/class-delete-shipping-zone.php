<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Shipping_Zone extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_delete_shipping_zone';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce shipping zone together with its locations and every shipping method in it; addresses it covered fall to the next matching zone or to "Rest of the world". Required: `id` (wp_wc_list_shipping_zones). Zone 0 ("Rest of the world") cannot be deleted. There is no trash and no undo. Returns { deleted: true, id, name }. Requires WooCommerce active.';
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'Zone id.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function describe( array $arguments ) {
        $id   = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        $zone = $id > 0 ? $this->peek( '/wc/v3/shipping/zones/' . $id, 'id,name' ) : null;
        if ( ! $zone ) {
            return '';
        }
        $methods = $this->peek( '/wc/v3/shipping/zones/' . $id . '/methods', 'instance_id' );
        return sprintf(
            /* translators: 1: zone name, 2: zone id, 3: number of shipping methods */
            __( "Permanently delete shipping zone '%1\$s' (ID %2\$d) with its locations and %3\$d shipping method(s).", 'easy-mcp-ai' ),
            (string) ( $zone['name'] ?? '' ),
            $id,
            is_array( $methods ) ? count( $methods ) : 0
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_editable_zone_id( $arguments['id'], 'deleted' );

        $zone = $this->rest_request( 'DELETE', '/wc/v3/shipping/zones/' . $id, array( 'force' => true ) );

        return array(
            'deleted' => true,
            'id'      => $id,
            'name'    => (string) ( $zone['name'] ?? '' ),
        );
    }
}
