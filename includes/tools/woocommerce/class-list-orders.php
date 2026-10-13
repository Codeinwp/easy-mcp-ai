<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Orders extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_orders';
    }

    public function get_description() {
        return 'Lists WooCommerce orders with optional filtering by status, customer, and date range. Returns id, number, status, total, customer_id, billing (name, email), date_created, and rest_url (the REST API self-link for the order, not a public permalink).';
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
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Filter by order status.',
                    'enum'        => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'trash', 'any' ),
                    'default'     => 'any',
                ),
                'customer' => array(
                    'type'        => 'integer',
                    'description' => 'Customer user ID.',
                ),
                'after'    => array(
                    'type'        => 'string',
                    'description' => 'ISO 8601 date. Filter orders created after this date.',
                ),
                'before'   => array(
                    'type'        => 'string',
                    'description' => 'ISO 8601 date. Filter orders created before this date.',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of orders per page.',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'description' => 'Sort field.',
                    'enum'        => array( 'date', 'id', 'title', 'modified' ),
                    'default'     => 'date',
                ),
                'order'    => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
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
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10,
            'page'     => $page,
            'status'   => isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'any',
            'orderby'  => isset( $arguments['orderby'] ) ? sanitize_text_field( $arguments['orderby'] ) : 'date',
            'order'    => isset( $arguments['order'] ) ? sanitize_text_field( $arguments['order'] ) : 'desc',
        );

        if ( isset( $arguments['customer'] ) ) {
            $params['customer'] = absint( $arguments['customer'] );
        }
        if ( isset( $arguments['after'] ) ) {
            $params['after'] = sanitize_text_field( $arguments['after'] );
        }
        if ( isset( $arguments['before'] ) ) {
            $params['before'] = sanitize_text_field( $arguments['before'] );
        }

        
        
        
        
        $request = new \WP_REST_Request( 'GET', '/wc/v3/orders' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'orders' => array() ),
                    $this->pagination_meta( null, $page, $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data = $response->get_data();

        $orders = array_map( function( $order ) {
            return array(
                'id'           => $order['id'],
                'number'       => $order['number'],
                'status'       => $order['status'],
                'total'        => $order['total'],
                'customer_id'  => $order['customer_id'],
                'billing'      => array(
                    'name'  => ( $order['billing']['first_name'] ?? '' ) . ' ' . ( $order['billing']['last_name'] ?? '' ),
                    'email' => $order['billing']['email'] ?? '',
                ),
                'date_created' => $order['date_created'],
                'rest_url'     => $order['_links']['self'][0]['href'] ?? '',
            );
        }, $data );

        return array_merge(
            array( 'orders' => $orders ),
            $this->pagination_meta( $response, $page, $params['per_page'], count( $data ) )
        );
    }
}
