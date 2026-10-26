<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






class List_Countries extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_list_countries';
    }

    public function get_description() {
        return 'Lists the country and state codes WooCommerce uses for shipping zone locations, tax rates and the store\'s selling and shipping countries. No arguments: every country as { code, name, states_count }. Optional: `code` (e.g. "US") for that country with its states, each { code, name }; a zone location for a state is written "US:CA". Continent codes for zone locations: AF, AN, AS, EU, NA, OC, SA. Requires WooCommerce active.';
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
                'code' => array(
                    'type'        => 'string',
                    'description' => 'Two-letter country code, e.g. "US", to list its states.',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $code = strtoupper( self::text_arg( $arguments, 'code' ) );

        if ( '' !== $code ) {
            if ( ! preg_match( '/^[A-Z]{2}$/', $code ) ) {
                throw new \InvalidArgumentException( 'Invalid code: use a two-letter country code, e.g. "US".' );
            }
            $country = $this->rest_request( 'GET', '/wc/v3/data/countries/' . $code );
            return array(
                'code'   => $country['code'] ?? $code,
                'name'   => $country['name'] ?? '',
                'states' => self::states_of( $country ),
            );
        }

        $out = array();
        foreach ( (array) $this->rest_request( 'GET', '/wc/v3/data/countries' ) as $country ) {
            if ( ! is_array( $country ) ) {
                continue;
            }
            $out[] = array(
                'code'         => $country['code'] ?? '',
                'name'         => $country['name'] ?? '',
                'states_count' => count( self::states_of( $country ) ),
            );
        }
        return array( 'countries' => $out );
    }

    





    private static function states_of( $country ): array {
        $states = array();
        foreach ( is_array( $country ) && isset( $country['states'] ) && is_array( $country['states'] ) ? $country['states'] : array() as $state ) {
            if ( is_array( $state ) && isset( $state['code'] ) ) {
                $states[] = array(
                    'code' => (string) $state['code'],
                    'name' => (string) ( $state['name'] ?? '' ),
                );
            }
        }
        return $states;
    }
}
