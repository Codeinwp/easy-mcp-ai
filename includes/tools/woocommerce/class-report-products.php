<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Products extends Base_Tool {

    public function get_name() {
        return 'wp_wc_report_products';
    }

    public function get_description() {
        return 'Gets WooCommerce product counts broken down by type. No parameters. Returns array of { slug, name, total } — one entry per product type (e.g. simple, variable, grouped, external). Requires WooCommerce active.';
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

        $data = $this->rest_request( 'GET', '/wc/v3/reports/products/totals' );

        return $data;
    }
}
