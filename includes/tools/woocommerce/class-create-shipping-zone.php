<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Create_Shipping_Zone extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_create_shipping_zone';
    }

    public function get_description() {
        return 'Creates a WooCommerce shipping zone: a set of locations that share shipping methods. Required: `name` and `locations` (list of { code, type }: country "US", state "US:CA", continent "EU", postcode "90210", "902*" or "90210...90299"; codes from wp_wc_list_countries), not empty: WooCommerce matches a zone with no locations to every address. A zone of postcodes only matches those postcodes in every country. Optional: `order` (whole number; WooCommerce matches a customer\'s address to the first zone in this order). Add shipping methods to it with wp_wc_add_shipping_method. Returns { id, name, order, locations }. Requires WooCommerce active.';
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
                'name'      => array(
                    'type'        => 'string',
                    'description' => 'Zone name, e.g. "Europe".',
                ),
                'order'     => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'Sort order; lower matches first.',
                ),
                'locations' => self::locations_schema(),
            ),
            'required'   => array( 'name', 'locations' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $name = self::text_arg( $arguments, 'name' );
        if ( '' === $name ) {
            throw new \InvalidArgumentException( 'Missing required parameters: name' );
        }
        if ( ! array_key_exists( 'locations', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameters: locations' );
        }
        $locations = $this->parse_locations( $arguments['locations'] );
        $this->refuse_catch_all_zone( $locations );
        $this->refuse_unknown_location_codes( $locations );

        $params = array( 'name' => sanitize_text_field( $name ) );
        if ( isset( $arguments['order'] ) ) {
            $params['order'] = $this->parse_non_negative_int( $arguments['order'], 'order' );
        }
        $zone = $this->rest_request( 'POST', '/wc/v3/shipping/zones', $params );
        $id   = (int) ( $zone['id'] ?? 0 );
        if ( $id < 1 ) {
            throw new \RuntimeException( 'WooCommerce did not return the new zone\'s id; check wp_wc_list_shipping_zones before trying again.' );
        }

        try {
            $this->rest_request( 'PUT', '/wc/v3/shipping/zones/' . $id . '/locations', $locations );
        } catch ( \RuntimeException $e ) {
            throw new \RuntimeException( sprintf( 'Zone %1$d "%2$s" was created, but its locations were not saved, so it matches every address: %3$s Set them with wp_wc_update_shipping_zone or delete it with wp_wc_delete_shipping_zone.', $id, $params['name'], $e->getMessage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $this->read_zone( $id );
    }
}
