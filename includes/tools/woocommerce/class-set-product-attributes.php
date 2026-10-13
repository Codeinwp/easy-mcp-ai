<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Set_Product_Attributes extends Base_Tool {

    public function get_name() {
        return 'wp_wc_set_product_attributes';
    }

    public function get_description() {
        return 'Attaches attribute values to an existing WooCommerce product (REPLACES the product\'s current attribute set). Required: `product_id`, `attributes` (array of objects per WC schema, each with `id` or `name`, `position` integer, `visible` boolean, `variation` boolean, `options` array of strings). Returns the updated product object, without its SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*). Use `wp_wc_list_product_attributes` to discover available attribute IDs first.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'edit_products';
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
                'product_id' => array(
                    'type'        => 'integer',
                    'description' => 'The product ID to set attributes on.',
                ),
                'attributes' => array(
                    'type'        => 'array',
                    'description' => 'Array of attribute objects (id or name, position, visible, variation, options[]; extra/unrecognized keys are passed through).',
                    'items'       => array(
                        'type' => 'object',
                    ),
                ),
            ),
            'required'   => array( 'product_id', 'attributes' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'product_id', 'attributes' ) );
        $product_id = $this->parse_required_id( $arguments['product_id'], 'product_id' );
        $attributes = $this->parse_json_param( $arguments['attributes'], 'attributes' );

        return $this->strip_response_noise(
            $this->rest_request( 'PUT', '/wc/v3/products/' . $product_id, array(
                'attributes' => $attributes,
            ) )
        );
    }
}
