<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Products extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_products';
    }

    public function get_description() {
        return 'Lists WooCommerce products with optional filtering by status, category, price range, stock status, and search. Returns { products: [ { id, name, sku, status, price, regular_price, sale_price, stock_status, stock_quantity, type, permalink } ], page }.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
    }

    public function get_annotations() {
        return array( 'title' => $this->get_title(), 'readOnlyHint' => true, 'destructiveHint' => false, 'openWorldHint' => false );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'per_page'     => array( 'type' => 'integer', 'description' => 'Number of products per page (1–100).', 'default' => 10, 'minimum' => 1, 'maximum' => 100 ),
                'page'         => array( 'type' => 'integer', 'description' => 'Page number.', 'default' => 1 ),
                'search'       => array( 'type' => 'string',  'description' => 'Search term to filter products by name or SKU.' ),
                'status'       => array( 'type' => 'string',  'description' => 'Filter by product status.', 'enum' => array( 'publish', 'draft', 'pending', 'private', 'any' ) ),
                'category'     => array( 'type' => 'string',  'description' => 'Filter by product category term ID (numeric, not the slug) — WooCommerce\'s REST layer resolves this value as a term ID; a slug string does not match any product. Use wp_list_categories or wp_get_category to look up the ID.' ),
                'stock_status' => array( 'type' => 'string',  'description' => 'Filter by stock status.', 'enum' => array( 'instock', 'outofstock', 'onbackorder' ) ),
                'orderby'      => array( 'type' => 'string',  'description' => 'Sort products by field.', 'enum' => array( 'date', 'id', 'title', 'slug', 'price', 'popularity', 'rating' ), 'default' => 'date' ),
                'order'        => array( 'type' => 'string',  'description' => 'Sort direction.', 'enum' => array( 'asc', 'desc' ), 'default' => 'desc' ),
                'type'         => array( 'type' => 'string',  'description' => 'Filter by product type.', 'enum' => array( 'simple', 'variable', 'grouped', 'external' ) ),
                'sku'          => array( 'type' => 'string',  'description' => 'Filter by product SKU (exact match).' ),
                'on_sale'      => array( 'type' => 'boolean', 'description' => 'Filter to only products currently on sale.' ),
                'min_price'    => array( 'type' => 'string',  'description' => 'Filter products with price greater than or equal to this value.' ),
                'max_price'    => array( 'type' => 'string',  'description' => 'Filter products with price less than or equal to this value.' ),
                'after'        => array( 'type' => 'string',  'description' => 'Filter products created after this date (ISO 8601 format).' ),
                'before'       => array( 'type' => 'string',  'description' => 'Filter products created before this date (ISO 8601 format).' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active on this site. Please install and activate WooCommerce to use this tool.' );
        }
        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10,
            'page'     => isset( $arguments['page'] )     ? absint( $arguments['page'] )     : 1,
        );
        foreach ( array( 'search', 'status', 'category', 'stock_status', 'orderby', 'order', 'type', 'sku', 'min_price', 'max_price', 'after', 'before' ) as $key ) {
            if ( ! empty( $arguments[ $key ] ) ) {
                $params[ $key ] = sanitize_text_field( $arguments[ $key ] );
            }
        }
        if ( isset( $arguments['on_sale'] ) ) {
            $params['on_sale'] = (bool) $arguments['on_sale'];
        }
        $data   = $this->rest_request( 'GET', '/wc/v3/products', $params );
        $result = array();
        foreach ( $data as $p ) {
            $result[] = array(
                'id'             => $p['id'],
                'name'           => $p['name'],
                'sku'            => $p['sku'],
                'status'         => $p['status'],
                'price'          => $p['price'],
                'regular_price'  => $p['regular_price'],
                'sale_price'     => $p['sale_price'],
                'stock_status'   => $p['stock_status'],
                'stock_quantity' => $p['stock_quantity'],
                'type'           => $p['type'],
                'permalink'      => $p['permalink'],
            );
        }
        return array( 'products' => $result, 'page' => $params['page'] );
    }
}
