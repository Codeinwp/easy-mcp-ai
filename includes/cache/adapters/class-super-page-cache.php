<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































final class Super_Page_Cache extends Cache_Adapter {

    const CONTROLLER = 'SPC\\Modules\\Cache_Controller';
    const STORE      = 'SPC\\Services\\Settings_Store';
    const HOOKS      = 'SPC\\Modules\\Cache_Invalidation_Hooks';
    const LOADER     = 'SPC\\Loader';
    const VARNISH    = 'SPC\\Services\\Varnish';

    
    const MIN_VERSION = '5.3.0';

    
    const CHUNK = 30;

    public function id(): string {
        return 'super-page-cache';
    }

    public function label(): string {
        return 'Super Page Cache';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    





    public function tier(): int {
        return self::TIER_CDN;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'SWCFPC_VERSION' ) && is_object( $calls->global_value( 'sw_cloudflare_pagecache' ) );
    }

    public function also_clears( Purge_Request $request ): array {
        $calls = $this->calls();
        $also  = array();
        if ( Purge_Request::SCOPE_SITE === $request->scope ) {
            if ( ! $this->uses_facade( $request ) ) {
                return array();
            }
            if ( ! $this->on( 'cf_purge_only_html' ) ) {
                $also[] = self::ALSO_CDN;
            }
        } elseif ( $this->siteground_on() ) {
            $also[] = self::ALSO_SITE;
        }
        $kinsta = $this->on( 'cf_kinsta_purge_on_flush' ) && is_object( $calls->global_value( 'kinsta_cache' ) );
        if ( $kinsta ) {
            $also[] = self::ALSO_CDN;
        }
        if ( $kinsta
            || ( $this->on( 'cf_object_cache_purge_on_flush' ) && $calls->using_ext_object_cache() )
            || ( $this->on( 'cf_wpengine_purge_on_flush' ) && $calls->class_exists( 'WpeCommon' ) ) ) {
            $also[] = self::ALSO_OBJECT_CACHE;
        }
        return array_values( array_unique( $also ) );
    }

    public function reaches_network( Purge_Request $request ): bool {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return false;
        }
        $also = $this->also_clears( $request );
        return in_array( self::ALSO_CDN, $also, true )
            || in_array( self::ALSO_OBJECT_CACHE, $also, true )
            || $this->varnish_on();
    }

    





    public function noise_options(): array {
        return array( 'swcfpc_fc_ttl_registry', 'swcfpc_preloader_lock' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $missing = $this->api_missing();
        if ( null !== $missing ) {
            return $missing;
        }
        if ( ! $this->uses_facade( $request ) ) {
            return $this->purge_local();
        }
        if ( false === $this->calls()->call( array( self::CONTROLLER, 'purge_all' ), array( true, false ) ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'Super Page Cache stopped before clearing its caches: Cloudflare refused the purge, or Cloudflare is not connected in its "purge HTML pages only" mode. It writes the reason to its own log.' ) );
        }
        return $this->sent( 'Super Page Cache cleared its whole cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        return $this->send_urls( $request->urls );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $urls  = $request->urls;
        $calls = $this->calls();
        if ( $calls->method_exists( self::HOOKS, 'get_post_related_links' ) ) {
            foreach ( $request->post_ids as $post_id ) {
                $links = $calls->call( array( self::HOOKS, 'get_post_related_links' ), array( $post_id ) );
                $urls  = array_merge( $urls, is_array( $links ) ? array_filter( $links, 'is_string' ) : array() );
            }
        }
        return $this->send_urls( $urls );
    }

    





    private function send_urls( array $urls ): Layer_Result {
        $missing = $this->api_missing();
        if ( null !== $missing ) {
            return $missing;
        }
        $sent   = 0;
        $failed = 0;
        foreach ( array_chunk( array_values( array_unique( $urls ) ), $this->chunk_size() ) as $chunk ) {
            if ( false === $this->calls()->call( array( self::CONTROLLER, 'purge_urls' ), array( $chunk, false ) ) ) {
                $failed += count( $chunk );
            } else {
                $sent += count( $chunk );
            }
        }
        $refused = sprintf( 'Cloudflare refused %d URL(s), so Super Page Cache did not clear them from its own caches either; it writes the reason to its own log.', $failed );
        if ( $failed > 0 && 0 === $sent ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => $refused ) );
        }
        $detail = sprintf( 'Super Page Cache cleared %d URL(s) from Cloudflare and its own caches.', $sent );
        return $this->sent( $failed > 0 ? $detail . ' ' . $refused : $detail );
    }

    




    private function api_missing(): ?Layer_Result {
        $version = $this->calls()->constant( 'SWCFPC_VERSION' );
        if ( ! is_string( $version ) || version_compare( $version, self::MIN_VERSION, '<' ) ) {
            return $this->skip( 'api_missing', sprintf( 'Super Page Cache %s is older than %s, whose purge functions this tool calls; update Super Page Cache to clear it here.', is_string( $version ) ? $version : '(unknown version)', self::MIN_VERSION ) );
        }
        foreach ( array( 'purge_all', 'purge_urls' ) as $method ) {
            if ( ! $this->calls()->method_exists( self::CONTROLLER, $method ) ) {
                return $this->helper_missing( 'Cache_Controller::' . $method . '()' );
            }
        }
        return null;
    }

    







    private function uses_facade( Purge_Request $request ): bool {
        return $this->cloudflare_on() && ( $request->include_cdn || $this->on( 'cf_purge_only_html' ) );
    }

    





    private function purge_local(): Layer_Result {
        $calls  = $this->calls();
        $module = $this->disk_module();
        $done   = array();
        if ( null !== $module && $this->on( 'cf_fallback_cache' ) ) {
            $calls->call( array( $module, 'fallback_cache_purge_all' ) );
            $done[] = 'its disk cache';
        }
        if ( $this->varnish_on() && $calls->class_exists( self::VARNISH ) ) {
            $varnish = $calls->instantiate( self::VARNISH );
            if ( $calls->method_exists( $varnish, 'purge_whole_cache' ) ) {
                $error = '';
                $calls->call( array( $varnish, 'purge_whole_cache' ), array( &$error ) );
                $done[] = 'Varnish';
            }
        }
        if ( array() === $done ) {
            return $this->helper_missing( 'disk-cache or Varnish purge' );
        }
        $note = $this->cloudflare_on() ? ' Cloudflare was not cleared: Super Page Cache clears it here only by purging the whole zone, which needs include_cdn=true.' : '';
        return $this->sent( sprintf( 'Super Page Cache cleared %s.%s', implode( ' and ', $done ), $note ) );
    }

    




    private function disk_module(): ?object {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::LOADER, 'get' ) ) {
            return null;
        }
        $loader = $calls->call( array( self::LOADER, 'get' ) );
        if ( ! is_object( $loader ) || ! $calls->method_exists( $loader, 'fallback_cache' ) ) {
            return null;
        }
        $module = $calls->call( array( $loader, 'fallback_cache' ) );
        return is_object( $module ) && $calls->method_exists( $module, 'fallback_cache_purge_all' ) ? $module : null;
    }

    




    private function varnish_on(): bool {
        return $this->on( 'cf_varnish_support' ) && $this->on( 'cf_varnish_auto_purge' );
    }

    






    private function chunk_size(): int {
        return $this->on( 'cf_spinupwp_purge_on_flush' ) && $this->calls()->function_exists( 'spinupwp_purge_site' ) ? 1 : self::CHUNK;
    }

    




    private function siteground_on(): bool {
        return $this->on( 'cf_siteground_purge_on_flush' ) && is_object( $this->calls()->global_value( 'sg_cachepress_supercacher' ) );
    }

    




    private function cloudflare_on(): bool {
        $store = $this->store();
        return null !== $store
            && $this->ask( $store, 'is_cloudflare_connected' )
            && ! $this->ask( $store, 'has_unreadable_active_cloudflare_credentials' );
    }

    




    private function store(): ?object {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::STORE, 'get_instance' ) ) {
            return null;
        }
        $store = $calls->call( array( self::STORE, 'get_instance' ) );
        return is_object( $store ) ? $store : null;
    }

    





    private function on( string $key ): bool {
        $store = $this->store();
        if ( null === $store || ! $this->calls()->method_exists( $store, 'get' ) ) {
            return false;
        }
        $value = $this->calls()->call( array( $store, 'get' ), array( $key, 0 ) );
        return is_scalar( $value ) && (int) $value > 0;
    }

    






    private function ask( object $store, string $method ): bool {
        return $this->calls()->method_exists( $store, $method ) && true === (bool) $this->calls()->call( array( $store, $method ) );
    }
}
