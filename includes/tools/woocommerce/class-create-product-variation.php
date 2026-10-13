<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Create_Product_Variation extends Base_Tool {

	public function get_name() {
		return 'wp_wc_create_product_variation';
	}

	public function get_description() {
		return 'Creates a variation for a WooCommerce variable product. Required: product_id, attributes (array of {name, option}). Optional: regular_price, sale_price, sku, stock_quantity, manage_stock, stock_status, weight, description, status, virtual, downloadable, image ({src}). If **Easy MCP AI → Settings → Force draft on create** is enabled, status is silently overridden to `draft` regardless of the value supplied — outside the declared publish/private enum.';
	}

	public function get_category() {
		return 'woocommerce';
	}

	public function get_required_capability() {
		return 'publish_products';
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
				'regular_price'  => array(
					'type'        => 'string',
					'description' => 'Variation regular price.',
				),
				'sale_price'     => array(
					'type'        => 'string',
					'description' => 'Variation sale price.',
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
				'weight'         => array(
					'type'        => 'string',
					'description' => 'Variation weight.',
				),
				'description'    => array(
					'type'        => 'string',
					'description' => 'Variation description.',
				),
				'status'         => array(
					'type'        => 'string',
					'description' => 'Variation status.',
					'enum'        => array( 'publish', 'private' ),
					'default'     => 'publish',
				),
				'virtual'        => array(
					'type'        => 'boolean',
					'description' => 'Whether the variation is virtual.',
				),
				'downloadable'   => array(
					'type'        => 'boolean',
					'description' => 'Whether the variation is downloadable.',
				),
				'image'          => array(
					'type'        => 'object',
					'description' => 'Variation image. Object with "src" key.',
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
		if ( empty( $attributes ) ) {
			throw new \InvalidArgumentException( 'attributes must contain at least one { name, option } object. Variable products require attribute-based variations.' );
		}

		$params = array(
			'attributes' => $attributes,
			'status'     => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'publish',
		);

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
		if ( isset( $arguments['image'] ) ) {
			$params['image'] = $this->parse_json_param( $arguments['image'], 'image' );
		}

		
		
		$this->maybe_force_draft( $params );

		$data = $this->rest_request( 'POST', '/wc/v3/products/' . $product_id . '/variations', $params );

		return array(
			'id'             => $data['id'],
			'product_id'     => $product_id,
			'sku'            => $data['sku'] ?? '',
			'regular_price'  => $data['regular_price'] ?? '',
			'sale_price'     => $data['sale_price'] ?? '',
			'status'         => $data['status'],
			'stock_status'   => $data['stock_status'],
			'stock_quantity' => $data['stock_quantity'] ?? null,
			'attributes'     => $data['attributes'] ?? array(),
		);
	}
}
