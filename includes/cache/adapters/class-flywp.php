<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Flywp extends Cache_Adapter {

    public function id(): string {
        return 'flywp';
    }

    public function label(): string {
        return 'FlyWP page cache';
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
        $calls = $this->calls();
        return $calls->function_exists( 'flywp' ) && $calls->defined( 'FLYWP_VERSION' ) && '' !== (string) $calls->constant( 'FLYWP_API_KEY' );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $cache = $this->cache();
        if ( null === $cache || ! $this->calls()->method_exists( $cache, 'clear_cache' ) ) {
            return $this->helper_missing( 'flywp()->fastcgi->clear_cache()' );
        }
        $returned = $this->calls()->call( array( $cache, 'clear_cache' ) );
        return $this->outcome( $returned, 'FlyWP page cache cleared its whole cache folder.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $cache = $this->cache();
        if ( null === $cache || ! $this->calls()->method_exists( $cache, 'purge_cache_by_url' ) ) {
            return $this->helper_missing( 'flywp()->fastcgi->purge_cache_by_url()' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->call( array( $cache, 'purge_cache_by_url' ), array( $url ) );
        }
        return $this->sent( sprintf( 'FlyWP page cache cleared %d page(s).', count( $request->urls ) ) );
    }

    private function cache(): ?object {
        $plugin = $this->calls()->call( 'flywp' );
        if ( ! is_object( $plugin ) || ! ( property_exists( $plugin, 'fastcgi' ) || $this->calls()->method_exists( $plugin, '__get' ) ) ) {
            return null;
        }
        $cache = $plugin->fastcgi;
        return is_object( $cache ) ? $cache : null;
    }
}
