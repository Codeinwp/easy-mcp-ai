<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Enabler extends Cache_Adapter {

    const VENDOR = 'Cache_Enabler';

    public function id(): string {
        return 'cache-enabler';
    }

    public function label(): string {
        return 'Cache Enabler';
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
        return $calls->class_exists( self::VENDOR ) && false !== $calls->has_action( 'cache_enabler_clear_site_cache' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $this->calls()->do_action( 'cache_enabler_clear_site_cache' );
        return $this->sent( 'Cache Enabler cleared this site\'s cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls  = $this->calls();
        $method = $calls->method_exists( self::VENDOR, 'clear_page_cache_by_url' );
        foreach ( $request->urls as $url ) {
            if ( $method ) {
                $calls->call( array( self::VENDOR, 'clear_page_cache_by_url' ), array( $url, array() ) );
            } else {
                $calls->do_action( 'cache_enabler_clear_page_cache_by_url', $url );
            }
        }
        return $this->sent( sprintf( 'Cache Enabler cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( $calls->method_exists( self::VENDOR, 'clear_post_cache' ) ) {
            foreach ( $request->post_ids as $post_id ) {
                $post = $calls->call( 'get_post', array( $post_id ) );
                if ( is_object( $post ) ) {
                    $calls->call( array( self::VENDOR, 'clear_post_cache' ), array( $post ) );
                }
            }
        }
        $this->purge_urls( $request );
        return $this->sent( sprintf( 'Cache Enabler cleared %d post(s) with their archives, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }
}
