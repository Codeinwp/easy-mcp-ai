<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Tax_Rate extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_create_tax_rate';
    }

    public function get_description() {
        return 'Creates a WooCommerce tax rate. Required: `rate` (percentage as a decimal string, e.g. "20") and `name` (shown to customers, e.g. "VAT"). Optional: `country` (two-letter code; empty for every country), `state` (e.g. "CA"), `postcodes` and `cities` (lists), `priority` (default 1; only one matching rate per priority applies), `compound` (default false), `shipping` (also tax shipping, default true), `order`, `class` (tax class slug from wp_wc_list_tax_classes, default "standard"). Taxes apply only while "woocommerce_calc_taxes" is "yes" (wp_wc_get_store_settings group "general"). Returns the rate { id, country, state, postcodes, cities, rate, name, priority, compound, shipping, order, class }. Requires WooCommerce active.';
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
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => self::tax_rate_properties(),
            'required'   => array( 'rate', 'name' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'rate', 'name' ) );
        $params = $this->tax_rate_params( $arguments );

        return self::present_tax_rate( $this->rest_request( 'POST', '/wc/v3/taxes', $params ) );
    }
}
