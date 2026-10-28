<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Speedycache extends Cache_Adapter {

    const DELETE = 'SpeedyCache\\Delete';

    const UTIL = 'SpeedyCache\\Util';

    const MIN_VERSION = '1.2.2';

    const GLOB_CHARS = '?[]*\\';

    public function id(): string {
        return 'speedycache';
    }

    public function label(): string {
        return 'SpeedyCache';
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
        return $calls->defined( 'SPEEDYCACHE_VERSION' ) && $calls->class_exists( self::DELETE ) && $calls->class_exists( self::UTIL );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE === $request->scope && $this->cdn_on() && ( $request->include_cdn || ! $this->has_page_only_purge() ) ) {
            return array( self::ALSO_CDN );
        }
        return array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        if ( Purge_Request::SCOPE_SITE !== $request->scope || ! $calls->is_multisite() ) {
            return false;
        }
        return true !== $calls->constant( 'SUBDOMAIN_INSTALL' ) || ( $request->include_cdn && $this->cdn_on() );
    }

    public function noise_options(): array {
        return array( 'speedycache_html_size' );
    }

    public function purge( Purge_Request $request ): Layer_Result {
        $version = $this->calls()->constant( 'SPEEDYCACHE_VERSION' );
        $version = is_string( $version ) ? $version : '0';
        if ( version_compare( $version, self::MIN_VERSION, '<' ) ) {
            return $this->skip( 'api_missing', sprintf( 'SpeedyCache %s is older than 1.2.2, whose purge methods mean something else, so nothing was called.', $version ) );
        }
        return parent::purge( $request );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( $this->cdn_on() && ! $request->include_cdn && $this->has_page_only_purge() ) {
            $calls->call( array( self::DELETE, 'all_cache' ) );
            $calls->call( array( self::DELETE, 'purge_varnish' ) );
            return $this->sent( 'SpeedyCache cleared its whole page cache and its Varnish server; not its CDN zone, which needs include_cdn.' );
        }
        if ( ! $calls->method_exists( self::DELETE, 'run' ) ) {
            return $this->helper_missing( 'SpeedyCache\\Delete::run()' );
        }
        $calls->call( array( self::DELETE, 'run' ), array( array() ) );
        return $this->sent( 'SpeedyCache cleared its whole cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::DELETE, 'url' ) ) {
            return $this->helper_missing( 'SpeedyCache\\Delete::url()' );
        }
        foreach ( $request->urls as $url ) {
            if ( false !== strpbrk( urldecode( $this->path_of( $url ) ), self::GLOB_CHARS ) ) {
                return $this->skip( 'not_deliverable', sprintf( 'SpeedyCache treats ? [ ] * and backslashes in a path as file patterns, so %s cannot be cleared safely and nothing was called.', $url ) );
            }
        }
        $targets = $this->targets( $request->urls );
        if ( array() !== $targets ) {
            $this->calls()->call( array( self::DELETE, 'url' ), array( $targets ) );
        }
        return $this->sent( sprintf( 'SpeedyCache cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $result = $this->purge_urls( $request );
        if ( Layer_Result::SKIPPED === $result->status() ) {
            return $result;
        }
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::DELETE, 'on_status_change' ) ) {
            return $result;
        }
        foreach ( $request->post_ids as $post_id ) {
            $post = $calls->call( 'get_post', array( $post_id ) );
            if ( is_object( $post ) ) {
                $status = isset( $post->post_status ) && is_string( $post->post_status ) ? $post->post_status : 'publish';
                $calls->call( array( self::DELETE, 'on_status_change' ), array( $status, 'publish', $post ) );
            }
        }
        return $this->sent( sprintf( 'SpeedyCache cleared %d post(s) with their related pages, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }

    private function targets( array $urls ): array {
        $targets = array();
        foreach ( $urls as $url ) {
            $path    = $this->path_of( $url );
            $decoded = urldecode( $path );
            if ( false === strpos( $decoded, '.' ) ) {
                $targets[] = $path;
                $targets[] = $decoded;
            }
        }
        return array_values( array_unique( $targets ) );
    }

    private function has_page_only_purge(): bool {
        $calls = $this->calls();
        return $calls->method_exists( self::DELETE, 'all_cache' ) && $calls->method_exists( self::DELETE, 'purge_varnish' );
    }

    private function cdn_on(): bool {
        $core = $this->calls()->global_value( 'speedycache' );
        $cdn  = is_object( $core ) && isset( $core->cdn ) && is_array( $core->cdn ) ? $core->cdn : array();
        return ! empty( $cdn['enabled'] ) && ! empty( $cdn['cdn_key'] ) && in_array( $cdn['cdn_type'] ?? '', array( 'cloudflare', 'bunny' ), true );
    }
}
