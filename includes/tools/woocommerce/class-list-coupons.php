<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Coupons extends Base_Tool {

    public function get_name() {
        return 'wp_wc_list_coupons';
    }

    public function get_description() {
        return 'Lists WooCommerce coupons. Optional: `search`, `code` (filter by exact coupon code), `per_page` (default 10), `page`. Returns `{ coupons, page }` where each coupon has { id, code, discount_type (percent/fixed_cart/fixed_product), amount, usage_count, usage_limit, date_expires }. Requires WooCommerce active.';
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
                    'description' => 'Number of coupons per page.',
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
                    'description' => 'Search term.',
                ),
                'code'     => array(
                    'type'        => 'string',
                    'description' => 'Filter by exact coupon code.',
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
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }
        if ( isset( $arguments['code'] ) ) {
            $params['code'] = sanitize_text_field( $arguments['code'] );
        }

        $data = $this->rest_request( 'GET', '/wc/v3/coupons', $params );

        $coupons = array_map( function( $coupon ) {
            return array(
                'id'            => $coupon['id'],
                'code'          => $coupon['code'],
                'discount_type' => $coupon['discount_type'],
                'amount'        => $coupon['amount'],
                'usage_count'   => $coupon['usage_count'],
                'usage_limit'   => $coupon['usage_limit'],
                'date_expires'  => $coupon['date_expires'] ?? $coupon['expiry_date'] ?? null,
            );
        }, $data );

        return array(
            'coupons' => $coupons,
            'page'    => $page,
        );
    }
}
