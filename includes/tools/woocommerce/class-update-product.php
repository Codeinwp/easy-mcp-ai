<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Product extends Base_Tool {

    public function get_name() {
        return 'wp_wc_update_product';
    }

    public function get_description() {
        return 'Updates an existing WooCommerce product (PATCH semantics). Required: `id`. Optional: `name`, `status` (draft/publish/pending/private), `regular_price` (string, e.g. "29.99"), `sale_price` (string — set to "" to remove a sale), `description`, `short_description`, `sku`, `manage_stock` (boolean), `stock_quantity` (integer), `stock_status` (instock/outofstock/onbackorder), `categories` (array of { id } — replaces existing), `tags` (array of { id }), `images` (array of { src }), `featured` (boolean), `catalog_visibility` (visible/catalog/search/hidden), `tax_status` (taxable/shipping/none), `tax_class`, `date_on_sale_from`, `date_on_sale_to`, `backorders` (no/notify/yes), `sold_individually` (boolean), `weight`, `dimensions` (object with length/width/height), `attributes`, `upsell_ids` (array of product IDs), `cross_sell_ids` (array of product IDs). Returns a summary of the updated product: `id`, `name`, `status`, `permalink`. To unpublish/hide without deleting set status="draft". Requires WooCommerce active.';
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
                'id'                => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the product to update.',
                ),
                'name'              => array(
                    'type'        => 'string',
                    'description' => 'Product name.',
                ),
                'regular_price'     => array(
                    'type'        => 'string',
                    'description' => 'Product regular price.',
                ),
                'sale_price'        => array(
                    'type'        => 'string',
                    'description' => 'Product sale price.',
                ),
                'description'       => array(
                    'type'        => 'string',
                    'description' => 'Product description.',
                ),
                'short_description' => array(
                    'type'        => 'string',
                    'description' => 'Product short description.',
                ),
                'sku'               => array(
                    'type'        => 'string',
                    'description' => 'Unique product SKU.',
                ),
                'stock_quantity'    => array(
                    'type'        => 'integer',
                    'description' => 'Stock quantity.',
                ),
                'manage_stock'      => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to manage stock.',
                ),
                'stock_status'      => array(
                    'type'        => 'string',
                    'description' => 'Stock status.',
                    'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
                ),
                'status'            => array(
                    'type'        => 'string',
                    'description' => 'Product status.',
                    'enum'        => array( 'draft', 'publish', 'pending', 'private' ),
                ),
                'categories'        => array(
                    'type'        => 'array',
                    'description' => 'Array of category objects with id.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'tags'              => array(
                    'type'        => 'array',
                    'description' => 'Array of tag objects with id.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id' => array( 'type' => 'integer' ),
                        ),
                    ),
                ),
                'images'            => array(
                    'type'        => 'array',
                    'description' => 'Array of image objects with src.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'src' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'featured'           => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the product is featured.',
                ),
                'catalog_visibility' => array(
                    'type'        => 'string',
                    'description' => 'Catalog visibility.',
                    'enum'        => array( 'visible', 'catalog', 'search', 'hidden' ),
                ),
                'tax_status'         => array(
                    'type'        => 'string',
                    'description' => 'Tax status.',
                    'enum'        => array( 'taxable', 'shipping', 'none' ),
                ),
                'tax_class'          => array(
                    'type'        => 'string',
                    'description' => 'Tax class slug.',
                ),
                'date_on_sale_from'  => array(
                    'type'        => 'string',
                    'description' => 'Sale start date in ISO 8601 format.',
                ),
                'date_on_sale_to'    => array(
                    'type'        => 'string',
                    'description' => 'Sale end date in ISO 8601 format.',
                ),
                'backorders'         => array(
                    'type'        => 'string',
                    'description' => 'Backorders policy.',
                    'enum'        => array( 'no', 'notify', 'yes' ),
                ),
                'sold_individually'  => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to limit purchases to one per order.',
                ),
                'weight'             => array(
                    'type'        => 'string',
                    'description' => 'Product weight.',
                ),
                'dimensions'         => array(
                    'type'        => 'object',
                    'description' => 'Product dimensions object with length, width, height (strings).',
                    'properties'  => array(
                        'length' => array( 'type' => 'string' ),
                        'width'  => array( 'type' => 'string' ),
                        'height' => array( 'type' => 'string' ),
                    ),
                ),
                'attributes'         => array(
                    'type'        => 'array',
                    'description' => 'Array of product attribute objects.',
                    'items'       => array( 'type' => 'object' ),
                ),
                'upsell_ids'         => array(
                    'type'        => 'array',
                    'description' => 'Array of upsell product IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'cross_sell_ids'     => array(
                    'type'        => 'array',
                    'description' => 'Array of cross-sell product IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id     = $this->parse_required_id( $arguments['id'], 'id' );
        $params = array();

        if ( isset( $arguments['name'] ) ) {
            $params['name'] = sanitize_text_field( $arguments['name'] );
        }
        if ( isset( $arguments['regular_price'] ) ) {
            $params['regular_price'] = sanitize_text_field( $arguments['regular_price'] );
        }
        if ( isset( $arguments['sale_price'] ) ) {
            $params['sale_price'] = sanitize_text_field( $arguments['sale_price'] );
        }
        if ( isset( $arguments['description'] ) ) {
            $params['description'] = wp_kses_post( $arguments['description'] );
        }
        if ( isset( $arguments['short_description'] ) ) {
            $params['short_description'] = wp_kses_post( $arguments['short_description'] );
        }
        if ( isset( $arguments['sku'] ) ) {
            $params['sku'] = sanitize_text_field( $arguments['sku'] );
        }
        if ( isset( $arguments['stock_quantity'] ) ) {
            $params['stock_quantity'] = (int) $arguments['stock_quantity']; 
        }
        if ( isset( $arguments['manage_stock'] ) ) {
            $params['manage_stock'] = (bool) $arguments['manage_stock'];
        }
        if ( isset( $arguments['stock_status'] ) ) {
            $params['stock_status'] = sanitize_text_field( $arguments['stock_status'] );
        }
        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }
        if ( ! empty( $arguments['categories'] ) ) {
            $params['categories'] = $this->parse_json_param( $arguments['categories'], 'categories' );
        }
        if ( ! empty( $arguments['tags'] ) ) {
            $params['tags'] = $this->parse_json_param( $arguments['tags'], 'tags' );
        }
        if ( ! empty( $arguments['images'] ) ) {
            $params['images'] = $this->parse_json_param( $arguments['images'], 'images' );
        }
        if ( isset( $arguments['featured'] ) ) {
            $params['featured'] = (bool) $arguments['featured'];
        }
        if ( isset( $arguments['catalog_visibility'] ) ) {
            $params['catalog_visibility'] = sanitize_text_field( $arguments['catalog_visibility'] );
        }
        if ( isset( $arguments['tax_status'] ) ) {
            $params['tax_status'] = sanitize_text_field( $arguments['tax_status'] );
        }
        if ( isset( $arguments['tax_class'] ) ) {
            $params['tax_class'] = sanitize_text_field( $arguments['tax_class'] );
        }
        if ( isset( $arguments['date_on_sale_from'] ) ) {
            $params['date_on_sale_from'] = sanitize_text_field( $arguments['date_on_sale_from'] );
        }
        if ( isset( $arguments['date_on_sale_to'] ) ) {
            $params['date_on_sale_to'] = sanitize_text_field( $arguments['date_on_sale_to'] );
        }
        if ( isset( $arguments['backorders'] ) ) {
            $params['backorders'] = sanitize_text_field( $arguments['backorders'] );
        }
        if ( isset( $arguments['sold_individually'] ) ) {
            $params['sold_individually'] = (bool) $arguments['sold_individually'];
        }
        if ( isset( $arguments['weight'] ) ) {
            $params['weight'] = sanitize_text_field( $arguments['weight'] );
        }
        if ( ! empty( $arguments['dimensions'] ) ) {
            $params['dimensions'] = array_map( 'sanitize_text_field', $this->parse_json_param( $arguments['dimensions'], 'dimensions' ) );
        }
        if ( ! empty( $arguments['attributes'] ) ) {
            $params['attributes'] = $this->parse_json_param( $arguments['attributes'], 'attributes' );
        }
        if ( ! empty( $arguments['upsell_ids'] ) ) {
            $upsell_ids = $this->parse_json_param( $arguments['upsell_ids'], 'upsell_ids' );
            $params['upsell_ids'] = array_map( 'absint', $upsell_ids );
        }
        if ( ! empty( $arguments['cross_sell_ids'] ) ) {
            $cross_sell_ids = $this->parse_json_param( $arguments['cross_sell_ids'], 'cross_sell_ids' );
            $params['cross_sell_ids'] = array_map( 'absint', $cross_sell_ids );
        }

        $data = $this->rest_request( 'PUT', '/wc/v3/products/' . $id, $params );

        return array(
            'id'        => $data['id'],
            'name'      => $data['name'],
            'status'    => $data['status'],
            'permalink' => $data['permalink'],
        );
    }
}
