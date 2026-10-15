<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';




















final class Wp_Super_Cache extends Cache_Adapter {

    public function id(): string {
        return 'wp-super-cache';
    }

    public function label(): string {
        return 'WP Super Cache';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->function_exists( 'wp_cache_clean_cache' )
            && $calls->function_exists( 'wpsc_delete_url_cache' )
            && $calls->function_exists( 'wp_cache_post_edit' );
    }


    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return Purge_Request::SCOPE_SITE === $request->scope
            && $calls->is_multisite()
            && true !== $calls->constant( 'SUBDOMAIN_INSTALL' )
            && true === $calls->call( 'is_main_site' );
    }

    public function noise_options(): array {
        return array( 'wpsc_feed_list' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $prefix = $this->calls()->global_value( 'file_prefix' );
        $this->calls()->call( 'wp_cache_clean_cache', array( is_string( $prefix ) && '' !== $prefix ? $prefix : 'wp-cache-' ) );
        return $this->sent( 'WP Super Cache cleared this site\'s cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        foreach ( $request->urls as $url ) {
            $this->calls()->call( 'wpsc_delete_url_cache', array( $url ) );
        }
        return $this->sent( sprintf( 'WP Super Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! empty( $this->calls()->global_value( 'wp_cache_clear_on_post_edit' ) ) ) {
            $this->purge_urls( $request );
            return $this->sent( sprintf( 'WP Super Cache cleared %d page(s); not its post purge, which would clear the whole site here ("clear all cache files when a post is updated" is on).', count( $request->urls ) ) );
        }
        foreach ( $request->post_ids as $post_id ) {
            $this->calls()->call( 'wp_cache_post_edit', array( $post_id ) );
        }
        $this->purge_urls( $request );
        return $this->sent( sprintf( 'WP Super Cache cleared %d post(s) with their archives, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }
}
