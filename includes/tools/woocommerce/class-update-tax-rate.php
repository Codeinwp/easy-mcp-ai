<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Update_Tax_Rate extends Base_Tool {

    use Store_Setup;

    const FIELDS = array( 'country', 'state', 'postcodes', 'cities', 'rate', 'name', 'priority', 'compound', 'shipping', 'order', 'class' );

    public function get_name() {
        return 'wp_wc_update_tax_rate';
    }

    public function get_description() {
        return 'Changes a WooCommerce tax rate. Required: `id` (wp_wc_list_tax_rates). Optional: `country`, `state` (an empty string means every country or state), `postcodes` and `cities` (each replaces the current list), `rate`, `name`, `priority`, `compound`, `shipping`, `order`, `class`. WooCommerce cannot remove all of a rate\'s postcodes or cities through this route, so an empty list is refused: delete the rate and create it again without them. Returns the rate with `changed`, the fields that differ from before (empty when the rate already had these values). Requires WooCommerce active.';
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
            'properties' => array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'Tax rate id.',
                ),
            ) + self::tax_rate_properties(),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id     = $this->parse_required_id( $arguments['id'], 'id' );
        $params = $this->tax_rate_params( $arguments );
        foreach ( array( 'postcodes', 'cities' ) as $field ) {
            if ( isset( $params[ $field ] ) && array() === $params[ $field ] ) {
                throw new \InvalidArgumentException( sprintf( 'An empty %1$s list cannot clear a rate\'s %1$s: WooCommerce ignores it. Delete the rate with wp_wc_delete_tax_rate and create it again without %1$s.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        $this->refuse_empty_update( $params, self::FIELDS );

        $route            = '/wc/v3/taxes/' . $id;
        $before           = self::present_tax_rate( $this->rest_request( 'GET', $route ) );
        $after            = self::present_tax_rate( $this->rest_request( 'PUT', $route, $params ) );
        $after['changed'] = self::changed_fields( $before, $after, self::FIELDS );
        return $after;
    }
}
