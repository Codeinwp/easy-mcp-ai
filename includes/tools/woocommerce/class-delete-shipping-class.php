<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Shipping_Class extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_delete_shipping_class';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce shipping class. Required: `id` (wp_wc_list_shipping_classes). Its products are left with no shipping class. There is no trash and no undo. Returns { deleted: true, id, name, slug }. Requires WooCommerce active.';
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
                    'description' => 'Shipping class id.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function describe( array $arguments ) {
        $id    = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        $class = $id > 0 ? $this->peek( '/wc/v3/products/shipping_classes/' . $id, 'id,name,count' ) : null;
        if ( ! $class ) {
            return '';
        }
        return sprintf(
            /* translators: 1: class name, 2: class id, 3: number of products */
            __( "Permanently delete shipping class '%1\$s' (ID %2\$d); %3\$d product(s) will have no shipping class.", 'easy-mcp-ai' ),
            (string) ( $class['name'] ?? '' ),
            $id,
            (int) ( $class['count'] ?? 0 )
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $class = $this->rest_request( 'DELETE', '/wc/v3/products/shipping_classes/' . $id, array( 'force' => true ) );

        return array(
            'deleted' => true,
            'id'      => $id,
            'name'    => (string) ( $class['name'] ?? '' ),
            'slug'    => (string) ( $class['slug'] ?? '' ),
        );
    }
}
