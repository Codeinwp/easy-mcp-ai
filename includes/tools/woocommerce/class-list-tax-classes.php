<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




class List_Tax_Classes extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_list_tax_classes';
    }

    public function get_description() {
        return 'Lists WooCommerce tax classes (e.g. standard, reduced-rate, zero-rate). No arguments. Returns { tax_classes: [{ slug, name }] }. A tax rate belongs to one class by its slug (wp_wc_list_tax_rates, wp_wc_create_tax_rate). Create or delete classes with wp_wc_create_tax_class and wp_wc_delete_tax_class. Requires WooCommerce active.';
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
        $data = $this->rest_request( 'GET', '/wc/v3/taxes/classes' );
        return array( 'tax_classes' => $this->strip_response_noise( is_array( $data ) ? $data : array() ) );
    }
}
