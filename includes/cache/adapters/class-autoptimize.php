<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















final class Autoptimize extends Cache_Adapter {

    
    const VENDOR = 'autoptimizeCache';

    public function id(): string {
        return 'autoptimize';
    }

    public function label(): string {
        return 'Autoptimize';
    }

    public function kind(): string {
        return self::KIND_ASSET;
    }

    public function tier(): int {
        return self::TIER_ASSET;
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) && $calls->class_exists( self::VENDOR );
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        if ( ! $calls->is_multisite() ) {
            return false;
        }
        
        if ( ! empty( $calls->apply_filters( 'autoptimize_filter_cache_clear_advanced', false ) ) ) {
            return true;
        }
        $dir    = $calls->constant( 'AUTOPTIMIZE_CACHE_DIR' );
        $suffix = '/' . (int) $calls->call( 'get_current_blog_id' ) . '/';
        $dir    = rtrim( is_string( $dir ) ? $dir : '', '/' ) . '/';
        return substr( $dir, -strlen( $suffix ) ) !== $suffix;
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( $calls->defined( 'AUTOPTIMIZE_CACHE_READONLY' ) ) {
            return $this->skip( 'read_only', 'Autoptimize\'s cache is declared read-only (AUTOPTIMIZE_CACHE_READONLY), so Autoptimize cannot write new files: clearing it would leave every page linking CSS and JavaScript that no longer exist.' );
        }
        if ( ! $calls->method_exists( self::VENDOR, 'clearall_actionless' ) ) {
            return $this->helper_missing( 'autoptimizeCache::clearall_actionless()' );
        }
        if ( false === $calls->call( array( self::VENDOR, 'clearall_actionless' ) ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'Autoptimize refused to clear its cache: its cache folder is missing or not writable, or a filter on autoptimize_filter_cache_clearall_disabled switches clearing off.' ) );
        }
        return $this->sent( 'Autoptimize cleared its CSS and JavaScript cache.' );
    }
}
