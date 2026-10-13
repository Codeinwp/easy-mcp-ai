<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Orders extends Base_Tool {

    public function get_name() {
        return 'wp_wc_report_orders';
    }

    public function get_description() {
        return 'Gets WooCommerce order counts broken down by status. No parameters. Returns array of { slug, name, total } — one entry per order status (e.g. pending, processing, completed, refunded, cancelled). Use for a quick overview of order pipeline health. For filtered order lists use `wp_wc_list_orders`. Requires WooCommerce active.';
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
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/reports/orders/totals' );

        return $data;
    }
}
