<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Product_Attribute extends Base_Tool {

    public function get_name() {
        return 'wp_wc_create_product_attribute';
    }

    public function get_description() {
        return 'Creates a new global WooCommerce product attribute. Required: `name` (display label). Optional: `slug` (taxonomy slug — WC auto-generates if omitted, will be prefixed with `pa_`), `type` (default \'select\'), `order_by` (menu_order|name|name_num|id), `has_archives` (boolean). Returns the created attribute.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_product_terms';
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
                'name'         => array(
                    'type'        => 'string',
                    'description' => 'Attribute display label.',
                ),
                'slug'         => array(
                    'type'        => 'string',
                    'description' => 'Taxonomy slug (WC will prefix with `pa_`).',
                ),
                'type'         => array(
                    'type'        => 'string',
                    'description' => 'Attribute type (e.g. select, button, color, image). WooCommerce validates this server-side; custom types from swatch plugins are also accepted.',
                ),
                'order_by'     => array(
                    'type'        => 'string',
                    'description' => 'Default term sort order.',
                    'enum'        => array( 'menu_order', 'name', 'name_num', 'id' ),
                ),
                'has_archives' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to enable attribute archives on the front-end.',
                ),
            ),
            'required'   => array( 'name' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'name' ) );

        $params = array(
            'name' => sanitize_text_field( $arguments['name'] ),
        );

        if ( isset( $arguments['slug'] ) && '' !== $arguments['slug'] ) {
            $params['slug'] = sanitize_title( $arguments['slug'] );
        }
        if ( isset( $arguments['type'] ) && '' !== $arguments['type'] ) {
            $params['type'] = sanitize_text_field( $arguments['type'] );
        }
        if ( isset( $arguments['order_by'] ) && '' !== $arguments['order_by'] ) {
            $params['order_by'] = sanitize_text_field( $arguments['order_by'] );
        }
        if ( isset( $arguments['has_archives'] ) ) {
            $params['has_archives'] = (bool) $arguments['has_archives'];
        }

        return $this->rest_request( 'POST', '/wc/v3/products/attributes', $params );
    }
}
