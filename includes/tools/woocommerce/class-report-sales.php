<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Report_Sales extends Base_Tool {

    public function get_name() {
        return 'wp_wc_report_sales';
    }

    public function get_description() {
        return 'Gets the WooCommerce sales summary. Optional: `period` (week/month/last_month/year — defaults to month when no dates given), `date_min` (YYYY-MM-DD), `date_max` (YYYY-MM-DD). Use `date_min`/`date_max` for custom ranges; use `period` for predefined windows — do not combine both. Returns { total_sales, net_sales, average_sales, total_orders, total_items, total_tax, total_shipping, total_refunds, total_discount, orders (daily breakdown array) }. Requires WooCommerce active.';
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
                'date_min' => array(
                    'type'        => 'string',
                    'description' => 'Start date YYYY-MM-DD.',
                ),
                'date_max' => array(
                    'type'        => 'string',
                    'description' => 'End date YYYY-MM-DD.',
                ),
                'period'   => array(
                    'type'        => 'string',
                    'description' => 'Reporting period.',
                    'enum'        => array( 'week', 'month', 'last_month', 'year' ),
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $params = array();

        if ( isset( $arguments['date_min'] ) ) {
            $params['date_min'] = sanitize_text_field( $arguments['date_min'] );
        }
        if ( isset( $arguments['date_max'] ) ) {
            $params['date_max'] = sanitize_text_field( $arguments['date_max'] );
        }
        if ( isset( $arguments['period'] ) ) {
            $params['period'] = sanitize_text_field( $arguments['period'] );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/reports/sales', $params );

        return $data[0] ?? $data;
    }
}
