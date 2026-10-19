<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
























final class W3_Total_Cache extends Cache_Adapter {

    const DISPATCHER = 'W3TC\\Dispatcher';

    public function id(): string {
        return 'w3-total-cache';
    }

    public function label(): string {
        return 'W3 Total Cache';
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
        return $this->calls()->defined( 'W3TC' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return array();
        }
        $cdn = $this->setting( 'get_boolean', 'cdnfsd.enabled' ) && ! $this->setting( 'get_boolean', 'cdn.flush_manually' );
        return $cdn || $this->cloudflare_page_caching() ? array( self::ALSO_CDN ) : array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return Purge_Request::SCOPE_SITE === $request->scope
            && $calls->is_multisite()
            && ( ! $calls->constant( 'SUBDOMAIN_INSTALL' ) || array() !== $this->also_clears( $request ) );
    }

    








    public function stale_seconds(): int {
        return 30;
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'w3tc_flush_posts' ) ) {
            return $this->helper_missing( 'w3tc_flush_posts()' );
        }
        $this->calls()->call( 'w3tc_flush_posts' );
        return $this->run_delayed( 'W3 Total Cache purged its page cache for the whole site.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->function_exists( 'w3tc_flush_url' ) ) {
            return $this->helper_missing( 'w3tc_flush_url()' );
        }
        $this->flush_urls( $request->urls );
        return $this->run_delayed( sprintf( 'W3 Total Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( $this->cloudflare_page_caching() ) {
            return $this->purge_urls( $request );
        }
        $calls = $this->calls();
        if ( ! $calls->function_exists( 'w3tc_flush_post' ) ) {
            return $this->helper_missing( 'w3tc_flush_post()' );
        }
        foreach ( $request->post_ids as $post_id ) {
            $calls->call( 'w3tc_flush_post', array( $post_id, true ) );
        }
        if ( $calls->function_exists( 'w3tc_flush_url' ) ) {
            $this->flush_urls( $request->urls );
        }
        return $this->run_delayed( sprintf( 'W3 Total Cache cleared %d post(s) with their related pages, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }

    





    private function flush_urls( array $urls ): void {
        foreach ( $urls as $url ) {
            $this->calls()->call( 'w3tc_flush_url', array( $this->is_home_url( $url ) ? rtrim( $url, '/' ) . '/' : $url ) );
        }
    }

    






    private function run_delayed( string $detail ): Layer_Result {
        $flush = $this->cache_flush();
        if ( null === $flush || ! $this->calls()->method_exists( $flush, 'execute_delayed_operations' ) ) {
            return $this->sent( $detail, 'after_response' );
        }
        $this->calls()->call( array( $flush, 'execute_delayed_operations' ) );
        return $this->sent( $detail );
    }

    





    private function cloudflare_page_caching(): bool {
        $active = $this->setting( 'get_array', 'extensions.active' );
        return is_array( $active ) && array_key_exists( 'cloudflare', $active ) && ! in_array( $active['cloudflare'], array( false, null, 0, '' ), true )
            && $this->setting( 'get_boolean', array( 'cloudflare', 'pagecache' ) );
    }

    






    private function setting( string $getter, $key ) {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::DISPATCHER, 'config' ) ) {
            return null;
        }
        $config = $calls->call( array( self::DISPATCHER, 'config' ) );
        if ( ! is_object( $config ) || ! $calls->method_exists( $config, $getter ) ) {
            return null;
        }
        return $calls->call( array( $config, $getter ), array( $key ) );
    }

    




    private function cache_flush(): ?object {
        if ( ! $this->calls()->method_exists( self::DISPATCHER, 'component' ) ) {
            return null;
        }
        $flush = $this->calls()->call( array( self::DISPATCHER, 'component' ), array( 'CacheFlush' ) );
        return is_object( $flush ) ? $flush : null;
    }
}
