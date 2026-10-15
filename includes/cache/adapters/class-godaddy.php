<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';






















final class Godaddy extends Cache_Adapter {

    
    const CACHE_CLASS = 'WPaaS\\Cache_V2';

    
    const PLUGIN_CLASS = 'WPaaS\\Plugin';

    
    const MAX_PURGE_URLS = 20;

    
    const BAN_KEY = 'wpaas_cache_cnt';

    public function id(): string {
        return 'godaddy';
    }

    public function label(): string {
        return 'GoDaddy Managed WordPress cache';
    }

    public function kind(): string {
        return self::KIND_HOST;
    }

    public function tier(): int {
        return self::TIER_PROXY;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->class_exists( self::CACHE_CLASS ) && null !== $this->cache() && $this->plugin_says( 'is_wpaas' );
    }

    public function also_clears( Purge_Request $request ): array {
        $cdn = $this->full_page_cdn();
        if ( Purge_Request::SCOPE_SITE === $request->scope ) {
            return $cdn ? array( self::ALSO_CDN, self::ALSO_OBJECT_CACHE ) : array( self::ALSO_OBJECT_CACHE );
        }
        return $cdn || count( $request->urls ) > self::MAX_PURGE_URLS ? array( self::ALSO_SITE ) : array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    public function noise_options(): array {
        return array( self::BAN_KEY, 'gd_system_last_cache_flush', 'gd_system_polling_invalidation_id' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $cache = $this->cache();
        if ( null === $cache || ! $this->calls()->method_exists( $cache, 'do_ban' ) ) {
            return $this->helper_missing( 'Cache_V2::do_ban()' );
        }
        $this->calls()->call( array( $cache, 'do_ban' ) );
        return $this->parked( 'GoDaddy queued a flush of this site\'s whole cache, the object cache and, when it is on, its full-page CDN; it runs when this request ends.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        $cache = $this->cache();
        if ( null === $cache || ! $calls->method_exists( $cache, 'purge' ) ) {
            return $this->helper_missing( 'Cache_V2::purge()' );
        }
        if ( array() === $request->urls ) {
            return $this->skip( 'not_deliverable', 'No URL was resolved, and GoDaddy\'s purge of an empty list sends its own pending list instead, so nothing was queued.' );
        }
        $urls = $request->urls;
        $calls->add_filter(
            'shutdown',
            static function () use ( $calls, $cache, $urls ): void {
                $calls->call( array( $cache, 'purge' ), array( $urls ) );
            },
            PHP_INT_MAX,
            0
        );
        return $this->parked( sprintf( 'GoDaddy queued an exact purge of %d page(s) over http and https; it runs when this request ends.', count( $urls ) ) );
    }

    





    private function full_page_cdn(): bool {
        return $this->plugin_says( 'is_wpaas_v2' ) || (bool) $this->calls()->constant( 'GD_CDN_FULLPAGE' );
    }

    





    private function plugin_says( string $method ): bool {
        return $this->calls()->method_exists( self::PLUGIN_CLASS, $method ) && (bool) $this->calls()->call( array( self::PLUGIN_CLASS, $method ) );
    }

    




    private function cache(): ?object {
        $cache = $this->calls()->global_value( 'wpaas_cache_class' );
        return is_object( $cache ) ? $cache : null;
    }
}
