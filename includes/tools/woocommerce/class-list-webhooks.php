<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Webhooks extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_webhooks';
    }

    public function get_description() {
        return 'Lists all configured WooCommerce webhooks with their topic, delivery URL, and status.';
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
                    'description' => 'Number of webhooks per page.',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Filter by status.',
                    'enum'        => array( 'active', 'paused', 'disabled' ),
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search webhooks by name.',
                ),
                'after'    => array(
                    'type'        => 'string',
                    'description' => 'Filter webhooks created after this date (ISO 8601).',
                ),
                'before'   => array(
                    'type'        => 'string',
                    'description' => 'Filter webhooks created before this date (ISO 8601).',
                ),
                'order'    => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'description' => 'Sort field.',
                    'enum'        => array( 'id', 'date' ),
                    'default'     => 'id',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $page   = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'     => $page,
        );

        foreach ( array( 'status', 'search', 'after', 'before', 'order', 'orderby' ) as $key ) {
            if ( ! empty( $arguments[ $key ] ) ) {
                $params[ $key ] = sanitize_text_field( $arguments[ $key ] );
            }
        }

        $data = $this->rest_request( 'GET', '/wc/v3/webhooks', $params );

        return array(
            'webhooks' => $data,
            'page'     => $page,
        );
    }
}
