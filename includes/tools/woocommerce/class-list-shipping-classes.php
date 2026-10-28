<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Shipping_Classes extends Base_Tool {

    use Store_Setup;

    const PER_PAGE = 100;

    public function get_name() {
        return 'wp_wc_list_shipping_classes';
    }

    public function get_description() {
        return 'Lists WooCommerce shipping classes, which group products for shipping costs (a flat rate method can charge per class). No arguments. Returns every class, sorted by name: { shipping_classes: [{ id, name, slug, description, count }] }, where `count` is the number of products in the class. Create, change or delete them with wp_wc_create_shipping_class, wp_wc_update_shipping_class and wp_wc_delete_shipping_class. Requires WooCommerce active.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $classes = array();
        for ( $page = 1; ; $page++ ) {
            $data    = $this->rest_request( 'GET', '/wc/v3/products/shipping_classes', array( 'per_page' => self::PER_PAGE, 'page' => $page ) );
            $rows    = is_array( $data ) ? $data : array();
            $classes = array_merge( $classes, $rows );
            if ( count( $rows ) < self::PER_PAGE ) {
                break;
            }
        }
        return array( 'shipping_classes' => $this->strip_response_noise( $classes ) );
    }
}
