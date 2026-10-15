<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';
























final class Woocommerce extends Cache_Adapter {

    
    const PRODUCT_TYPES = array( 'product', 'product_variation' );

    
    const ANALYTICS = 'Automattic\\WooCommerce\\Admin\\API\\Reports\\Cache';

    public function id(): string {
        return 'woocommerce';
    }

    public function label(): string {
        return 'WooCommerce';
    }

    public function kind(): string {
        return self::KIND_DATA;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    





    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( 'WooCommerce' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $missing = $this->missing_helper();
        if ( '' !== $missing ) {
            return $this->helper_missing( $missing );
        }
        $calls = $this->calls();
        $calls->call( 'wc_delete_product_transients' );
        $calls->call( 'wc_delete_shop_order_transients' );
        $calls->call( array( 'WC_Cache_Helper', 'invalidate_cache_group' ), array( 'woocommerce-attributes' ) );
        $calls->call( array( 'WC_Cache_Helper', 'get_transient_version' ), array( 'shipping', true ) );
        $calls->call( array( 'WC_Cache_Helper', 'get_transient_version' ), array( 'product_query', true ) );
        if ( $calls->method_exists( self::ANALYTICS, 'invalidate' ) ) {
            $calls->call( array( self::ANALYTICS, 'invalidate' ) );
        }
        if ( $calls->using_ext_object_cache() && $calls->function_exists( 'wc_clear_template_cache' ) ) {
            $calls->call( 'wc_clear_template_cache' );
        }
        return $this->sent( 'WooCommerce cleared its product lists, on-sale and featured product lists, stock counts, order, attribute, shipping, filter and analytics caches.' );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $missing = $this->missing_helper();
        if ( '' !== $missing ) {
            return $this->helper_missing( $missing );
        }
        $calls    = $this->calls();
        $products = array();
        foreach ( array_unique( $request->post_ids ) as $post_id ) {
            if ( $post_id > 0 && in_array( $calls->call( 'get_post_type', array( $post_id ) ), self::PRODUCT_TYPES, true ) ) {
                $products[] = $post_id;
            }
        }
        if ( array() === $products ) {
            return $this->skip( 'not_deliverable', 'None of the posts is a WooCommerce product or variation, so WooCommerce holds no product data for them and nothing was called.' );
        }
        foreach ( $products as $product_id ) {
            $calls->call( 'wc_delete_product_transients', array( $product_id ) );
        }
        $calls->call( array( 'WC_Cache_Helper', 'get_transient_version' ), array( 'product_query', true ) );
        return $this->sent( sprintf( 'WooCommerce cleared the cached data of %d product(s) (variation prices, children, related products) and its product lists.', count( $products ) ) );
    }

    




    private function missing_helper(): string {
        $calls = $this->calls();
        foreach ( array( 'wc_delete_product_transients', 'wc_delete_shop_order_transients' ) as $function ) {
            if ( ! $calls->function_exists( $function ) ) {
                return $function . '()';
            }
        }
        return $calls->class_exists( 'WC_Cache_Helper' ) ? '' : 'WC_Cache_Helper';
    }
}
