<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Hummingbird extends Cache_Adapter {

    const CLEAR = 'clear_cache';

    const CORE = 'Hummingbird\\WP_Hummingbird';

    const UTILS = 'Hummingbird\\Core\\Utils';

    const ZONE_HOOK = 'wphb_cache_directory_cleared';

    public function id(): string {
        return 'hummingbird';
    }

    public function label(): string {
        return 'Hummingbird';
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
        return $calls->class_exists( self::CORE ) && $calls->did_action( 'wp_hummingbird_loaded' ) > 0;
    }

    public function also_clears( Purge_Request $request ): array {
        $calls = $this->calls();
        if ( Purge_Request::SCOPE_SITE === $request->scope && ! $calls->is_multisite() && false !== $calls->has_action( self::ZONE_HOOK ) ) {
            return array( self::ALSO_CDN );
        }
        return array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return Purge_Request::SCOPE_SITE === $request->scope
            && $calls->is_multisite()
            && true !== $calls->constant( 'SUBDOMAIN_INSTALL' )
            && true === $calls->call( 'is_main_site' );
    }

    public function noise_options(): array {
        return array( 'wphb-notice-cache-cleaned-show' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $module = $this->module_with( self::CLEAR );
        if ( null === $module ) {
            return $this->helper_missing( 'page-cache module purge method' );
        }
        if ( $this->calls()->is_multisite() && $this->from_network_admin() ) {
            return $this->skip( 'network_scope', 'This request carries a Referer from the network admin, and Hummingbird then clears every site of the network instead of this one, so nothing was called.' );
        }
        if ( false === $this->calls()->call( array( $module, self::CLEAR ) ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'Hummingbird reported a file-system error, so its cache may not have been cleared.' ) );
        }
        return $this->sent( 'Hummingbird cleared its whole page cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $module = $this->module_with( self::CLEAR );
        if ( null === $module ) {
            return $this->helper_missing( 'page-cache module purge method' );
        }
        $this->clear_paths( $module, $request->urls );
        return $this->sent( sprintf( 'Hummingbird cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $module = $this->module_with( 'clear_cache_action' );
        if ( null === $module || ! $this->calls()->method_exists( $module, self::CLEAR ) ) {
            return $this->helper_missing( 'page-cache module clear_cache_action()' );
        }
        foreach ( $request->post_ids as $post_id ) {
            if ( $post_id > 0 ) {
                $this->calls()->call( array( $module, 'clear_cache_action' ), array( (int) $post_id ) );
            }
        }
        $this->clear_paths( $module, $request->urls );
        return $this->sent( sprintf( 'Hummingbird cleared %d post(s) with their category, tag and author archives, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }

    private function clear_paths( object $module, array $urls ): void {
        $paths = array();
        foreach ( $urls as $url ) {
            $path    = $this->path_of( $url );
            $paths[] = $path;
            $paths[] = str_replace( '.', '-', $path );
        }
        foreach ( array_unique( $paths ) as $path ) {
            $this->calls()->call( array( $module, self::CLEAR ), array( $path ) );
        }
    }

    private function from_network_admin(): bool {
        $referer = $this->calls()->server( 'HTTP_REFERER' );
        $admin   = (string) $this->calls()->call( 'network_admin_url' );
        return '' !== $referer && '' !== $admin && 0 === stripos( $referer, $admin );
    }

    private function module_with( string $method ): ?object {
        $module = $this->module();
        return null !== $module && $this->calls()->method_exists( $module, $method ) ? $module : null;
    }

    private function module(): ?object {
        if ( ! $this->calls()->method_exists( self::UTILS, 'get_module' ) ) {
            return null;
        }
        $module = $this->calls()->call( array( self::UTILS, 'get_module' ), array( 'page_cache' ) );
        return is_object( $module ) ? $module : null;
    }
}
