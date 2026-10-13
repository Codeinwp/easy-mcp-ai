<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Customers extends Base_Tool {

    public function get_name() {
        return 'wp_wc_report_customers';
    }

    public function get_description() {
        return 'Gets WooCommerce customer counts broken down by customer type. No parameters. Returns array of { slug, name, total } — typically entries for "paying_customer" and "non_paying_customer". Requires WooCommerce active.';
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

        $data = $this->rest_request( 'GET', '/wc/v3/reports/customers/totals' );

        return $data;
    }
}
