<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















class Get_Coupon extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_coupon';
    }

    public function get_description() {
        return 'Gets a single WooCommerce coupon by ID. Required: `id` (get it from `wp_wc_list_coupons`). Returns WooCommerce\'s `/wc/v3/coupons/<id>` response verbatim: id, code, amount, discount_type (percent/fixed_cart/fixed_product), description, date_created, date_modified, date_expires, usage_count, individual_use, product_ids, excluded_product_ids, usage_limit, usage_limit_per_user, limit_usage_to_x_items, free_shipping, product_categories, excluded_product_categories, exclude_sale_items, minimum_amount, maximum_amount, email_restrictions, used_by, meta_data. Use this to verify fields that `wp_wc_update_coupon` accepts but does not echo back. Requires WooCommerce active.';
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the coupon to retrieve.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        return $this->rest_request( 'GET', '/wc/v3/coupons/' . $id );
    }
}
