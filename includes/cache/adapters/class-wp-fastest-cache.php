<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















final class Wp_Fastest_Cache extends Cache_Adapter {

    public function id(): string {
        return 'wp-fastest-cache';
    }

    public function label(): string {
        return 'WP Fastest Cache';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        $scopes = array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_POSTS );
        if ( $this->has_url_api() ) {
            $scopes[] = Purge_Request::SCOPE_URLS;
        }
        return $scopes;
    }

    public function is_present(): bool {
        $calls = $this->calls();
        $core  = $calls->global_value( 'wp_fastest_cache' );
        return is_object( $core )
            && $calls->class_exists( 'WpFastestCache', false )
            && true === $calls->call( 'is_a', array( $core, 'WpFastestCache' ) );
    }

    public function also_clears( Purge_Request $request ): array {
        $site = Purge_Request::SCOPE_SITE === $request->scope;
        if ( ! $site && $this->has_url_api() ) {
            return array();
        }
        $also = $this->cloudflare_on() ? array( self::ALSO_CDN ) : array();
        if ( ! $site && $this->varnish_on() ) {
            $also[] = self::ALSO_SITE;
        }
        return $also;
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return Purge_Request::SCOPE_SITE === $request->scope && $calls->is_multisite() && ! $calls->constant( 'SUBDOMAIN_INSTALL' );
    }

    public function noise_options(): array {
        return array( 'WpFastestCacheHTML', 'WpFastestCacheHTMLSIZE', 'WpFastestCacheMOBILE', 'WpFastestCacheMOBILESIZE' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'wpfc_clear_all_cache' ) ) {
            return $this->helper_missing( 'wpfc_clear_all_cache()' );
        }
        $this->calls()->call( 'wpfc_clear_all_cache', array( false ) );
        return $this->sent( 'WP Fastest Cache cleared its whole cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        foreach ( $request->urls as $url ) {
            $this->calls()->call( 'wpfc_clear_cache_by_url', array( $url ) );
        }
        return $this->sent( sprintf( 'WP Fastest Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( $this->has_url_api() ) {
            return $this->purge_urls( $request );
        }
        if ( ! $this->calls()->function_exists( 'wpfc_clear_post_cache_by_id' ) ) {
            return $this->helper_missing( 'wpfc_clear_post_cache_by_id()' );
        }
        foreach ( $request->post_ids as $post_id ) {
            $this->calls()->call( 'wpfc_clear_post_cache_by_id', array( $post_id ) );
        }
        return $this->sent( sprintf( 'WP Fastest Cache cleared %d post(s).', count( $request->post_ids ) ) );
    }

    




    private function has_url_api(): bool {
        return $this->calls()->function_exists( 'wpfc_clear_cache_by_url' );
    }

    





    private function cloudflare_on(): bool {
        $raw  = $this->calls()->get_option( 'WpFastestCacheCDN' );
        $list = is_string( $raw ) ? json_decode( $raw ) : null;
        $list = is_object( $list ) ? get_object_vars( $list ) : $list;
        foreach ( is_array( $list ) ? $list : array() as $entry ) {
            if ( is_object( $entry ) && isset( $entry->id ) && 'cloudflare' === $entry->id ) {
                return ! empty( $entry->originurl );
            }
        }
        return false;
    }

    





    private function varnish_on(): bool {
        $data = $this->calls()->get_option( 'WpFastestCacheVarnish' );
        if ( is_array( $data ) ) {
            return ! empty( $data['server'] ) && ! ( isset( $data['status'] ) && 'pause' === $data['status'] );
        }
        return ! empty( $data );
    }
}
