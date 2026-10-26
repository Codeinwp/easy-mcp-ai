<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Row_Extension;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Customer extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_customer';
    }

    public function get_description() {
        return 'Gets a single WooCommerce customer by ID. Required: `id` (WooCommerce customer ID — same as WordPress user ID; get it from `wp_wc_list_customers`). Returns WooCommerce\'s `/wc/v3/customers/<id>` response verbatim: id, email, first_name, last_name, username, role, avatar_url, is_paying_customer, billing (address object), shipping (address object), meta_data, date_created, date_modified. Note `orders_count` and `total_spent` are NOT returned — WooCommerce dropped them from the v3 customer schema (they remain only in the deprecated v1/v2 controllers). Use `wp_wc_list_orders` filtered by customer, or `wp_wc_report_customers`, for order totals. Requires WooCommerce active.';
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
            'properties' => array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the customer to retrieve.',
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
        $id   = $this->parse_required_id( $arguments['id'], 'id' );
        $data = $this->rest_request( 'GET', '/wc/v3/customers/' . $id );
        if ( ! is_array( $data ) ) {
            return $data;
        }

        return Row_Extension::apply( Row_Extension::WC_CUSTOMER, $data, $data, $this->get_name(), false );
    }
}
