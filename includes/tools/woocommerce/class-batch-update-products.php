<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Batch_Update_Products extends Base_Tool {

    public function get_name() {
        return 'wp_wc_batch_update_products';
    }

    public function get_description() {
        return 'Create, update, and/or delete up to 100 WooCommerce products in a single REST batch call. Provide any combination of `create` (array of product objects), `update` (array of product objects with `id`), `delete` (array of integer IDs). Default soft cap is 25 items per branch (raise via `easy_mcp_ai_wc_batch_soft_cap` filter). WooCommerce caps the combined total of create+update+delete at 100 items per request (not per branch) via `woocommerce_rest_batch_items_limit` filter. Returns WC\'s response { create: [...], update: [...], delete: [...] } with each item\'s SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) and `_links` omitted. WC REST permission_callback enforces per-item edit_product cap — partial failures are possible (failed items appear with error key). If **Easy MCP AI → Settings → Force draft on create** is enabled, every `create` item has its status silently overridden to `draft` regardless of the value supplied; `update` items are unaffected.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'edit_products';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'create' => array(
                    'type'        => 'array',
                    'description' => 'Array of product objects to create (any field accepted by the WC REST product schema, e.g. name, regular_price, sku, categories; extra/unrecognized keys are passed through).',
                    'items'       => array(
                        'type' => 'object',
                    ),
                ),
                'update' => array(
                    'type'        => 'array',
                    'description' => 'Array of product objects to update, each with `id` (any field accepted by the WC REST product schema; extra/unrecognized keys are passed through).',
                    'items'       => array(
                        'type' => 'object',
                    ),
                ),
                'delete' => array(
                    'type'        => 'array',
                    'description' => 'Array of product IDs to delete.',
                    'items'       => array( 'type' => 'integer' ),
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $soft_cap = (int) apply_filters( 'easy_mcp_ai_wc_batch_soft_cap', 25 );

        $body = array();
        if ( ! empty( $arguments['create'] ) ) {
            if ( count( $arguments['create'] ) > $soft_cap ) {
                throw new \InvalidArgumentException( sprintf( 'create exceeds the %d-item soft cap. Use the easy_mcp_ai_wc_batch_soft_cap filter to raise the limit.', $soft_cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $create_items = array();
            foreach ( $arguments['create'] as $item ) {
                if ( is_array( $item ) ) {
                    $this->maybe_force_draft( $item );
                }
                $create_items[] = $item;
            }
            $body['create'] = $create_items;
        }
        if ( ! empty( $arguments['update'] ) ) {
            if ( count( $arguments['update'] ) > $soft_cap ) {
                throw new \InvalidArgumentException( sprintf( 'update exceeds the %d-item soft cap. Use the easy_mcp_ai_wc_batch_soft_cap filter to raise the limit.', $soft_cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $body['update'] = $arguments['update'];
        }
        if ( ! empty( $arguments['delete'] ) ) {
            if ( count( $arguments['delete'] ) > $soft_cap ) {
                throw new \InvalidArgumentException( sprintf( 'delete exceeds the %d-item soft cap. Use the easy_mcp_ai_wc_batch_soft_cap filter to raise the limit.', $soft_cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $body['delete'] = array_map( 'absint', $arguments['delete'] );
        }

        if ( empty( $body ) ) {
            throw new \InvalidArgumentException( 'At least one of `create`, `update`, or `delete` must be provided and non-empty.' );
        }

        wp_raise_memory_limit( 'admin' );
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
        }

        return $this->strip_response_noise( $this->rest_request( 'POST', '/wc/v3/products/batch', $body ) );
    }
}
