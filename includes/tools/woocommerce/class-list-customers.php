<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Customers extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_customers';
    }

    public function get_description() {
        return 'Lists WooCommerce customers. Optional: `search` (name/email), `email` (exact email filter), `role` (WordPress role slug — default "all" which returns all roles; use "customer" to filter to WooCommerce customers only), `orderby` (id/include/name/registered_date — default registered_date; these are the only values the WooCommerce API accepts), `order` (asc/desc — default desc), `per_page` (default 10), `page`. Returns { customers: [ { id, email, first_name, last_name, username, date_created } ], page }. Order counts and lifetime spend are NOT available — the WooCommerce v3 customers API does not return them. Requires WooCommerce active.';
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
                    'description' => 'Number of customers per page.',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number.',
                    'default'     => 1,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search by name/email.',
                ),
                'email'    => array(
                    'type'        => 'string',
                    'description' => 'Filter by email address.',
                ),
                'role'     => array(
                    'type'        => 'string',
                    'description' => 'Filter by WordPress user role. Default "all" returns all roles; use "customer" to filter to WooCommerce customers only.',
                    'default'     => 'all',
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'description' => 'Sort field.',
                    'enum'        => array( 'id', 'include', 'name', 'registered_date' ),
                    'default'     => 'registered_date',
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
            'role'     => isset( $arguments['role'] ) ? sanitize_text_field( $arguments['role'] ) : 'all',
            'orderby'  => isset( $arguments['orderby'] ) ? sanitize_text_field( $arguments['orderby'] ) : 'registered_date',
            'order'    => isset( $arguments['order'] ) ? sanitize_text_field( $arguments['order'] ) : 'desc',
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }
        if ( isset( $arguments['email'] ) ) {
            $params['email'] = sanitize_text_field( $arguments['email'] );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/customers', $params );

        $customers = array_map( function( $customer ) {
            return array(
                'id'           => $customer['id'],
                'email'        => $customer['email'],
                'first_name'   => $customer['first_name'],
                'last_name'    => $customer['last_name'],
                'username'     => $customer['username'],
                'date_created' => $customer['date_created'],
                
                
                
                
                
            );
        }, $data );

        return array(
            'customers' => $customers,
            'page'      => $page,
        );
    }
}
