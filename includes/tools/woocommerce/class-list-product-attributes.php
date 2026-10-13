<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Product_Attributes extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_product_attributes';
    }

    public function get_description() {
        return 'Lists global WooCommerce product attributes (e.g. Color, Size, Material) from the attribute registry. Returns array of { id, name, slug, type, order_by, has_archives }. Use these slugs as taxonomy names (prefixed `pa_`) when building variable products.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        return $this->rest_request( 'GET', '/wc/v3/products/attributes' );
    }
}
