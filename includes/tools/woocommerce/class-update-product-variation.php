<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-product-images.php';

class Update_Product_Variation extends Base_Tool {

    use Product_Images;

    public function get_name() {
        return 'wp_wc_update_product_variation';
    }

    public function get_description() {
        return 'Updates an existing WooCommerce product variation (PUT/PATCH semantics). Required: `product_id`, `id`. Optional: `regular_price`, `sale_price` (set "" to remove a sale), `sku`, `stock_quantity`, `manage_stock`, `stock_status` (instock/outofstock/onbackorder), `status` (publish/private), `weight`, `description`, `virtual`, `downloadable`, `attributes` (array of {name, option}; [] clears them, so the variation matches any value), `image` ({id} or {src}, as for product images; {} removes it), `gallery_image_ids` (WooCommerce 11.1+: media library image IDs in order; replaces the gallery, [] clears it, the main image is left out). Returns the updated variation summary, plus `image` / `gallery_image_ids` as saved, each when sent. Requires WooCommerce active.';
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
                'product_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent variable product.',
                ),
                'id'             => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the variation to update.',
                ),
                'regular_price'  => array(
                    'type'        => 'string',
                    'description' => 'Variation regular price.',
                ),
                'sale_price'     => array(
                    'type'        => 'string',
                    'description' => 'Variation sale price. Pass "" to remove a sale.',
                ),
                'sku'            => array(
                    'type'        => 'string',
                    'description' => 'Variation SKU.',
                ),
                'stock_quantity' => array(
                    'type'        => 'integer',
                    'description' => 'Stock quantity.',
                ),
                'manage_stock'   => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to manage stock.',
                ),
                'stock_status'   => array(
                    'type'        => 'string',
                    'description' => 'Stock status.',
                    'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
                ),
                'status'         => array(
                    'type'        => 'string',
                    'description' => 'Variation status.',
                    'enum'        => array( 'publish', 'private' ),
                ),
                'weight'         => array(
                    'type'        => 'string',
                    'description' => 'Variation weight.',
                ),
                'description'    => array(
                    'type'        => 'string',
                    'description' => 'Variation description.',
                ),
                'virtual'        => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the variation is virtual.',
                ),
                'downloadable'   => array(
                    'type'        => 'boolean',
                    'description' => 'Whether the variation is downloadable.',
                ),
                'attributes'     => array(
                    'type'        => 'array',
                    'description' => 'Array of attribute objects. Each item: { "name": "pa_size", "option": "Large" }.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'name'   => array( 'type' => 'string' ),
                            'option' => array( 'type' => 'string' ),
                        ),
                    ),
                ),
                'image'          => array_merge(
                    $this->image_item_schema(),
                    array( 'description' => 'Main variation image. {} removes it.' )
                ),
                'gallery_image_ids' => array(
                    'type'        => 'array',
                    'description' => 'WooCommerce 11.1+: media library image IDs, in order. Replaces the gallery; [] clears it.',
                    'items'       => array( 'type' => 'integer' ),
                ),
            ),
            'required'   => array( 'product_id', 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'product_id', 'id' ) );
        $product_id = $this->parse_required_id( $arguments['product_id'], 'product_id' );
        $id         = $this->parse_required_id( $arguments['id'], 'id' );

        $params = array();

        if ( isset( $arguments['regular_price'] ) ) {
            $params['regular_price'] = sanitize_text_field( $arguments['regular_price'] );
        }
        if ( isset( $arguments['sale_price'] ) ) {
            $params['sale_price'] = sanitize_text_field( $arguments['sale_price'] );
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
        if ( isset( $arguments['weight'] ) ) {
            $params['weight'] = sanitize_text_field( $arguments['weight'] );
        }
        if ( isset( $arguments['description'] ) ) {
            $params['description'] = sanitize_textarea_field( $arguments['description'] );
        }
        if ( isset( $arguments['virtual'] ) ) {
            $params['virtual'] = (bool) $arguments['virtual'];
        }
        if ( isset( $arguments['downloadable'] ) ) {
            $params['downloadable'] = (bool) $arguments['downloadable'];
        }
        if ( isset( $arguments['attributes'] ) ) {
            $params['attributes'] = $this->parse_json_param( $arguments['attributes'], 'attributes' );
        }
        if ( isset( $arguments['image'] ) ) {
            $params['image'] = $this->image_param( $arguments['image'], 'image', true );
        }
        if ( isset( $arguments['gallery_image_ids'] ) ) {
            $params['gallery_image_ids'] = $this->variation_gallery_param( $arguments['gallery_image_ids'] );
            $this->require_variation_gallery_support( $product_id );
        }

        $data = $this->rest_request( 'PUT', '/wc/v3/products/' . $product_id . '/variations/' . $id, $params );

        return $this->with_variation_images( $params, $data, array(
            'id'             => $data['id'],
            'product_id'     => $product_id,
            'sku'            => $data['sku'] ?? '',
            'regular_price'  => $data['regular_price'] ?? '',
            'sale_price'     => $data['sale_price'] ?? '',
            'status'         => $data['status'] ?? '',
            'stock_status'   => $data['stock_status'] ?? 'instock',
            'stock_quantity' => $data['stock_quantity'] ?? null,
            'attributes'     => $data['attributes'] ?? array(),
        ) );
    }
}
