<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Tax_Rates extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_tax_rates';
    }

    public function get_description() {
        return 'Lists all WooCommerce tax rates with country, state, rate, name, and tax class.';
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
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of tax rates per page.',
                    'default'     => 100,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'class'    => array(
                    'type'        => 'string',
                    'description' => 'Filter by tax class slug (e.g. standard, reduced-rate, zero-rate).',
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'country'  => array(
                    'type'        => 'string',
                    'description' => 'Filter by country code (ISO 3166-1 alpha-2, e.g. US).',
                ),
                'state'    => array(
                    'type'        => 'string',
                    'description' => 'Filter by state code (e.g. CA).',
                ),
                'postcode' => array(
                    'type'        => 'string',
                    'description' => 'Filter by postcode.',
                ),
                'city'     => array(
                    'type'        => 'string',
                    'description' => 'Filter by city name.',
                ),
                'order'    => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'asc',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 100,
        );

        foreach ( array( 'class', 'country', 'state', 'postcode', 'city', 'order' ) as $key ) {
            if ( ! empty( $arguments[ $key ] ) ) {
                $params[ $key ] = sanitize_text_field( $arguments[ $key ] );
            }
        }
        if ( isset( $arguments['page'] ) ) {
            $params['page'] = absint( $arguments['page'] );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/taxes', $params );

        return $data;
    }
}
