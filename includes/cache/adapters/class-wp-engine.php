<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Wp_Engine extends Cache_Adapter {

    const COMMON = 'WpeCommon';

    const IDENTIFIER = 'easy-mcp-ai:urls';

    const PATHS_FILTER = 'wpe_purge_varnish_cache_paths';

    public function id(): string {
        return 'wp-engine';
    }

    public function label(): string {
        return 'WP Engine';
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
        if ( ! $calls->class_exists( self::COMMON ) || ! $calls->function_exists( 'is_wpe' ) || ! $calls->call( 'is_wpe' ) ) {
            return false;
        }
        return ! ( $calls->function_exists( 'is_wpe_snapshot' ) && $calls->call( 'is_wpe_snapshot' ) );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls   = $this->calls();
        $methods = $request->include_cdn ? array( 'clear_cdn_cache', 'purge_varnish_cache' ) : array( 'purge_varnish_cache' );
        foreach ( $methods as $method ) {
            if ( ! $calls->method_exists( self::COMMON, $method ) ) {
                return $this->helper_missing( 'WpeCommon::' . $method . '()' );
            }
        }
        $cdn = '';
        if ( $request->include_cdn ) {
            $cdn = true === $calls->call( array( self::COMMON, 'clear_cdn_cache' ) ) ? ' and its CDN' : '; its CDN clear failed (WP Engine\'s API returned an error)';
        }
        $sent = $calls->call( array( self::COMMON, 'purge_varnish_cache' ), array( null, true ) );
        return $this->answer( $sent, sprintf( 'WP Engine cleared every cached page of the install%s.', $cdn ) );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::COMMON, 'purge_varnish_cache' ) ) {
            return $this->helper_missing( 'WpeCommon::purge_varnish_cache()' );
        }
        if ( array() === $request->urls ) {
            return $this->skip( 'not_deliverable', 'No URL was given, and WP Engine turns an empty path list into a purge of every page, so nothing was sent.' );
        }
        $paths  = $this->path_patterns( $request->urls );
        $filter = static function ( $current, $identifier = null ) use ( $paths ) {
            return self::IDENTIFIER === $identifier ? $paths : $current;
        };
        $calls = $this->calls();
        $calls->add_filter( self::PATHS_FILTER, $filter, PHP_INT_MAX, 2 );
        try {
            $sent = $calls->call( array( self::COMMON, 'purge_varnish_cache' ), array( self::IDENTIFIER, true ) );
        } finally {
            $calls->remove_filter( self::PATHS_FILTER, $filter, PHP_INT_MAX );
        }
        return $this->answer( $sent, sprintf( 'WP Engine cleared %d path(s).', count( $paths ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::COMMON, 'purge_varnish_cache' ) ) {
            return $this->helper_missing( 'WpeCommon::purge_varnish_cache()' );
        }
        $posts = 0;
        foreach ( $request->post_ids as $post_id ) {
            if ( $this->has_exact_post_purge( $post_id ) && false !== $calls->call( array( self::COMMON, 'purge_varnish_cache' ), array( $post_id, true ) ) ) {
                ++$posts;
            }
        }
        $what = sprintf( 'WP Engine cleared %d post(s).', $posts );
        if ( array() === $request->urls ) {
            return $posts > 0 ? $this->sent( $what ) : $this->skip( 'not_deliverable', 'No post WP Engine can purge exactly was given and no URL was resolved for them, so nothing was sent.' );
        }
        $result = $this->purge_urls( $request );
        return 0 === $posts ? $result : $result->with( array( 'detail' => $what . ' ' . (string) $result->get( 'detail' ) ) );
    }

    private function answer( $sent, string $detail ): Layer_Result {
        if ( false === $sent ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'WP Engine sent nothing: purge_varnish_cache() returned false (an autosave, or purging switched off).' ) );
        }
        return $this->sent( $detail );
    }

    private function has_exact_post_purge( int $post_id ): bool {
        $post = $post_id > 1 ? $this->calls()->call( 'get_post', array( $post_id ) ) : null;
        if ( ! is_object( $post ) || ! isset( $post->post_status ) || 'publish' !== $post->post_status ) {
            return false;
        }
        if ( isset( $post->post_type ) && in_array( $post->post_type, array( 'attachment', 'revision' ), true ) ) {
            return false;
        }
        $permalink = $this->calls()->call( 'get_permalink', array( $post_id ) );
        return is_string( $permalink ) && '' !== $permalink && ! $this->is_home_url( $permalink );
    }

    private function path_patterns( array $urls ): array {
        $paths = array();
        foreach ( $urls as $url ) {
            $path  = '^' . preg_quote( rtrim( $this->path_of( $url ), '/' ) ) . '/?'; // phpcs:ignore WordPress.PHP.PregQuoteDelimiter.Missing -- a Varnish regex, not a PHP pattern.
            $query = \wp_parse_url( $url, PHP_URL_QUERY );
            if ( is_string( $query ) && '' !== $query ) {
                $paths[] = $path . '\\?' . preg_quote( $query ) . '$'; // phpcs:ignore WordPress.PHP.PregQuoteDelimiter.Missing -- a Varnish regex, not a PHP pattern.
            } elseif ( $this->is_home_url( $url ) ) {
                $paths[] = $path . '$';
            } else {
                $paths[] = $path . '(\\?.*)?$';
            }
        }
        return array_values( array_unique( $paths ) );
    }
}
