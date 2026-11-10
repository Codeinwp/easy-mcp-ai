<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Batch_Update_Products extends Base_Tool {

    use Product_Terms;

    public function get_name() {
        return 'wp_wc_batch_update_products';
    }

    public function get_description() {
        return 'Create, update, and/or delete up to 100 WooCommerce products in a single REST batch call. Provide any combination of `create` (array of product objects), `update` (array of product objects with `id`), `delete` (array of integer IDs). Default soft cap is 25 items per branch (raise via `easy_mcp_ai_wc_batch_soft_cap` filter). WooCommerce caps the combined total of create+update+delete at 100 items per request (not per branch) via `woocommerce_rest_batch_items_limit` filter. Returns WC\'s response { create: [...], update: [...], delete: [...] } with each item\'s SEO head markup (yoast_head*, aioseo_head*, aioseo_breadcrumb*) and `_links` omitted. WC REST permission_callback enforces per-item edit_product cap — partial failures are possible (failed items appear with error key). Each create and update item\'s `categories` must be product categories of this site as `{ id }` objects, and its `tags` product tags of this site as `{ id }` objects, or `{ name }` objects, which WooCommerce assigns, creating a tag that does not exist yet: WooCommerce drops an ID that names nothing and saves the rest, so one refuses the whole call before anything is sent, naming the item. Each item\'s `upsell_ids` and `cross_sell_ids` are checked the same way: every ID must be a product or a variation of this site. If **Easy MCP AI → Settings → Force draft on create** is enabled, every `create` item has its status silently overridden to `draft` regardless of the value supplied; `update` items are unaffected.';
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

    public function precheck( array $arguments ): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }
        foreach ( array( 'create', 'update' ) as $branch ) {
            if ( empty( $arguments[ $branch ] ) || ! is_array( $arguments[ $branch ] ) ) {
                continue;
            }
            foreach ( $arguments[ $branch ] as $key => $item ) {
                if ( is_array( $item ) ) {
                    $this->refuse_unknown_item_attributes( $item, $branch . '[' . $key . ']' );
                }
            }
        }
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
            foreach ( $arguments['create'] as $key => $item ) {
                if ( is_array( $item ) ) {
                    $this->maybe_force_draft( $item );
                    $item = $this->checked_product_ids( $this->checked_terms( $item, 'create[' . $key . ']' ), 'create[' . $key . ']' );
                    $this->refuse_unknown_item_attributes( $item, 'create[' . $key . ']' );
                }
                $create_items[] = $item;
            }
            $body['create'] = $create_items;
        }
        if ( ! empty( $arguments['update'] ) ) {
            if ( count( $arguments['update'] ) > $soft_cap ) {
                throw new \InvalidArgumentException( sprintf( 'update exceeds the %d-item soft cap. Use the easy_mcp_ai_wc_batch_soft_cap filter to raise the limit.', $soft_cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $update_items = array();
            foreach ( (array) $arguments['update'] as $key => $item ) {
                if ( is_array( $item ) ) {
                    $item = $this->checked_product_ids( $this->checked_terms( $item, 'update[' . $key . ']' ), 'update[' . $key . ']' );
                    $this->refuse_unknown_item_attributes( $item, 'update[' . $key . ']' );
                }
                $update_items[] = $item;
            }
            $body['update'] = $update_items;
            $this->refuse_builder_owned_product_writes( $body['update'] );
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

    private function checked_terms( array $item, string $label ): array {
        if ( isset( $item['categories'] ) ) {
            $item['categories'] = $this->product_terms_param( $item['categories'], $label . '.categories', 'product_cat', 'product category' );
        }
        if ( isset( $item['tags'] ) ) {
            $item['tags'] = $this->product_terms_param( $item['tags'], $label . '.tags', 'product_tag', 'product tag' );
        }
        return $item;
    }

    private function checked_product_ids( array $item, string $label ): array {
        foreach ( array( 'upsell_ids', 'cross_sell_ids' ) as $field ) {
            if ( isset( $item[ $field ] ) ) {
                $item[ $field ] = $this->product_ids_param( $this->parse_json_param( $item[ $field ], $label . '.' . $field ), $label . '.' . $field );
            }
        }
        return $item;
    }

    private function refuse_unknown_item_attributes( array $item, string $label ): void {
        if ( isset( $item['attributes'] ) && is_array( $item['attributes'] ) ) {
            $this->refuse_unknown_attribute_ids( $item['attributes'], $label . '.attributes', false, 'Nothing was changed.' );
        }
    }
}
