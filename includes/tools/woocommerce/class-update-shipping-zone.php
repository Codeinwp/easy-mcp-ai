<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Shipping_Zone extends Base_Tool {

    use Store_Setup;

    const FIELDS = array( 'name', 'order', 'locations' );

    public function get_name() {
        return 'wp_wc_update_shipping_zone';
    }

    public function get_description() {
        return 'Changes a WooCommerce shipping zone. Required: `id` (wp_wc_list_shipping_zones). Optional: `name`, `order` (whole number; lower matches first) and `locations`, the zone\'s FULL list of { code, type }: it replaces every current location. To add one location, send the current list plus the new one. The list cannot be empty, because WooCommerce matches a zone with no locations to every address; to stop using a zone, delete it. Zone 0 ("Rest of the world") cannot be changed. Returns { id, name, order, locations, changed }, where `changed` lists the fields whose value differs from before (empty when the zone already had these values). Requires WooCommerce active.';
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
                'id'        => array(
                    'type'        => 'integer',
                    'description' => 'Zone id.',
                ),
                'name'      => array(
                    'type'        => 'string',
                    'description' => 'New zone name.',
                ),
                'order'     => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'New sort order.',
                ),
                'locations' => self::locations_schema(),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_editable_zone_id( $arguments['id'], 'changed' );

        $params = array();
        if ( array_key_exists( 'name', $arguments ) ) {
            $name = self::text_arg( $arguments, 'name' );
            if ( '' === $name ) {
                throw new \InvalidArgumentException( 'Invalid name: a zone name cannot be empty.' );
            }
            $params['name'] = sanitize_text_field( $name );
        }
        if ( isset( $arguments['order'] ) ) {
            $params['order'] = $this->parse_non_negative_int( $arguments['order'], 'order' );
        }
        $locations = array_key_exists( 'locations', $arguments ) ? $this->parse_locations( $arguments['locations'] ) : null;
        $this->refuse_empty_update( null === $locations ? $params : $params + array( 'locations' => true ), self::FIELDS );
        if ( null !== $locations ) {
            $this->refuse_catch_all_zone( $locations );
            $this->refuse_unknown_location_codes( $locations );
        }

        $before = $this->read_zone( $id );
        if ( ! empty( $params ) ) {
            $this->rest_request( 'PUT', '/wc/v3/shipping/zones/' . $id, $params );
        }
        if ( null !== $locations ) {
            $this->rest_request( 'PUT', '/wc/v3/shipping/zones/' . $id . '/locations', $locations );
        }

        $after            = $this->read_zone( $id );
        $after['changed'] = self::changed_fields( $before, $after, self::FIELDS );
        return $after;
    }
}
