<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;
use Easy_MCP_AI\Cache\Url_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Rank_Math_Sitemap extends Cache_Adapter {

    const CACHE  = 'RankMath\\Sitemap\\Cache';
    const MAP    = 'RankMath\\Sitemap\\Sitemap';
    const ROUTER = 'RankMath\\Sitemap\\Router';

    const INDEX = '1';

    const VETO = 'rank_math/pre_clear_cache';

    public function id(): string {
        return 'rank-math-sitemap';
    }

    public function label(): string {
        return 'Rank Math sitemap cache';
    }

    public function kind(): string {
        return self::KIND_DATA;
    }

    public function tier(): int {
        return self::TIER_DATA;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        return $this->calls()->defined( 'RANK_MATH_VERSION' );
    }

    public function noise_options(): array {
        return array( 'rank_math_sitemap_cache_files' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->invalidate( array( null ) ) ) {
            return $this->helper_missing( 'Sitemap\\Cache::invalidate_storage()' );
        }
        return $this->sent( 'Rank Math cleared its whole sitemap cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::ROUTER, 'get_sitemap_base' ) || ! $calls->method_exists( self::MAP, 'get_sitemap_index_slug' ) ) {
            return $this->helper_missing( 'Sitemap\\Router::get_sitemap_base()' );
        }
        return $this->purge_types( $this->url_types( $request->urls ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $types = $this->post_types( $request->post_ids );
        return $this->purge_types( array() !== $types ? $types : $this->url_types( $request->urls ) );
    }

    private function purge_types( array $types ): Layer_Result {
        if ( array() === $types ) {
            return $this->skip( 'no_url_api', 'Rank Math caches only its XML sitemaps, and nothing in this request is one of them (one of its sitemap URLs, or an existing post).', array( 'needs' => 'scope=site' ) );
        }
        $types = array_values( array_unique( array_merge( array( self::INDEX ), $types ) ) );
        if ( ! $this->invalidate( $types ) ) {
            return $this->helper_missing( 'Sitemap\\Cache::invalidate_storage()' );
        }
        $names = array_map(
            static function ( string $type ): string {
                return self::INDEX === $type ? 'index' : $type;
            },
            $types
        );
        return $this->sent( sprintf( 'Rank Math cleared the %s sitemap cache.', implode( ', ', $names ) ) );
    }

    private function invalidate( array $types ): bool {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::CACHE, 'invalidate_storage' ) ) {
            return false;
        }
        $veto = static function (): bool {
            return true;
        };
        $calls->add_filter( self::VETO, $veto, PHP_INT_MAX );
        try {
            foreach ( $types as $type ) {
                $calls->call( array( self::CACHE, 'invalidate_storage' ), null === $type ? array() : array( $type ) );
            }
        } finally {
            $calls->remove_filter( self::VETO, $veto, PHP_INT_MAX );
        }
        return true;
    }

    private function post_types( array $post_ids ): array {
        $types = array();
        foreach ( $post_ids as $post_id ) {
            $type = $this->calls()->call( 'get_post_type', array( $post_id ) );
            if ( is_string( $type ) && '' !== $type && 'revision' !== $type ) {
                $types[] = $type;
            }
        }
        return array_values( array_unique( $types ) );
    }

    private function url_types( array $urls ): array {
        $calls = $this->calls();
        if ( array() === $urls || ! $calls->method_exists( self::ROUTER, 'get_sitemap_base' ) || ! $calls->method_exists( self::MAP, 'get_sitemap_index_slug' ) ) {
            return array();
        }
        $base  = (string) $calls->call( array( self::ROUTER, 'get_sitemap_base' ) );
        $index = (string) $calls->call( array( self::MAP, 'get_sitemap_index_slug' ) );
        $home  = rtrim( Url_Guard::home()['path'], '/' );
        $types = array();
        foreach ( $urls as $url ) {
            $relative = ltrim( (string) substr( $this->path_of( $url ), strlen( $home ) ), '/' );
            if ( $base . $index . '.xml' === $relative ) {
                $types[] = self::INDEX;
            } elseif ( 1 === preg_match( '#^' . preg_quote( $base, '#' ) . '([^/]+?)-sitemap([0-9]+)?\.xml$#', $relative, $match ) ) {
                $types[] = $match[1];
            }
        }
        return array_values( array_unique( $types ) );
    }
}
