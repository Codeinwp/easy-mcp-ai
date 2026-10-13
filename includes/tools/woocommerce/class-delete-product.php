<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Product extends Base_Tool {

    public function get_name() {
        return 'wp_wc_delete_product';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce product by ID. Required: `id`. Deletion is immediate and irreversible — the product, its images (media records), and all its variations are deleted. Orders that included this product are preserved. To hide without deleting, use `wp_wc_update_product` with status="draft" instead. Returns { deleted: true, id }. Requires WooCommerce active.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'delete_products';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the product to delete.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        $product = $id > 0 ? $this->peek( '/wc/v3/products/' . $id, 'id,name,status,sku' ) : null;
        if ( ! $product ) {
            return '';
        }
        return sprintf(
            /* translators: 1: product name, 2: product id, 3: status */
            __( "Permanently delete product '%1\$s' (ID %2\$d, %3\$s), bypassing the trash.", 'easy-mcp-ai' ),
            self::rest_title( $product, 'name' ), $id, isset( $product['status'] ) ? $product['status'] : 'unknown'
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $this->rest_request( 'DELETE', '/wc/v3/products/' . $id, array( 'force' => true ) );

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }
}
