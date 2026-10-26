<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Update_Shipping_Class extends Base_Tool {

    use Store_Setup;

    const FIELDS = array( 'name', 'slug', 'description' );

    public function get_name() {
        return 'wp_wc_update_shipping_class';
    }

    public function get_description() {
        return 'Changes a WooCommerce shipping class. Required: `id` (wp_wc_list_shipping_classes). Optional: `name`, `slug`, `description`. Products keep their class when its slug changes. Returns { id, name, slug, description, count, changed }, where `changed` lists the fields that differ from before (empty when the class already had these values). Requires WooCommerce active.';
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
                'id'          => array(
                    'type'        => 'integer',
                    'description' => 'Shipping class id.',
                ),
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'New name.',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'New slug.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'New description; an empty string clears it.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $params = array();
        foreach ( array( 'name', 'slug' ) as $field ) {
            if ( array_key_exists( $field, $arguments ) ) {
                $value = self::text_arg( $arguments, $field );
                if ( '' === $value ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: it cannot be empty.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( 'name' === $field ) {
                    
                    $this->refuse_backslash( $value, 'name' );
                }
                $params[ $field ] = 'slug' === $field ? sanitize_title( $value ) : sanitize_text_field( $value );
            }
        }
        if ( array_key_exists( 'description', $arguments ) ) {
            if ( ! is_string( $arguments['description'] ) ) {
                throw new \InvalidArgumentException( 'Invalid description: must be a string.' );
            }
            $this->refuse_backslash( $arguments['description'], 'description' );
            $params['description'] = sanitize_textarea_field( $arguments['description'] );
        }
        $this->refuse_empty_update( $params, self::FIELDS );

        $route            = '/wc/v3/products/shipping_classes/' . $id;
        $before           = self::present_shipping_class( $this->rest_request( 'GET', $route ) );
        $after            = self::present_shipping_class( $this->rest_request( 'PUT', $route, $params ) );
        $after['changed'] = self::changed_fields( $before, $after, self::FIELDS );
        return $after;
    }
}
