<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Breeze extends Cache_Adapter {

    const CDN_CONSTANTS = array( 'CDN_SITE_ID', 'CDN_SITE_TOKEN' );

    public function id(): string {
        return 'breeze';
    }

    public function label(): string {
        return 'Breeze';
    }

    public function kind(): string {
        return self::KIND_PAGE;
    }

    public function tier(): int {
        return self::TIER_PAGE;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'BREEZE_VERSION' )
            && $calls->class_exists( 'Breeze_PurgeCache' )
            && $calls->class_exists( 'Breeze_Options_Reader' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return array();
        }
        $also = array( self::ALSO_OBJECT_CACHE );
        if ( $this->cloudflare_on() ) {
            $also[] = self::ALSO_CDN;
        }
        return $also;
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        if ( Purge_Request::SCOPE_SITE !== $request->scope || ! $calls->is_multisite() ) {
            return false;
        }
        if ( $calls->using_ext_object_cache() || true !== $calls->constant( 'SUBDOMAIN_INSTALL' ) ) {
            return true;
        }
        return true !== $calls->call( 'is_main_site' ) && filter_var( $calls->get_option( 'breeze_inherit_settings', '0' ), FILTER_VALIDATE_BOOLEAN );
    }

    public function purge( Purge_Request $request ): Layer_Result {
        if ( $this->request_widens_to_network() ) {
            return $this->skip( 'network_scope', 'This request carries Breeze\'s is-network flag or a network-admin referer, which would make Breeze purge every site of the network.' );
        }
        return parent::purge( $request );
    }

    public function noise_options(): array {
        return array( 'breeze_minify_purged_at', 'breeze_minified_hashes', 'breeze_minify_unwritable_file', 'breeze_preload_queue', 'breeze_preload_queue_', 'breeze_preload_last_warm', 'breeze_preload_last_warm_' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->has_action( 'breeze_clear_all_cache' ) ) {
            return $this->helper_missing( 'breeze_clear_all_cache action' );
        }
        $this->calls()->do_action( 'breeze_clear_all_cache' );
        return $this->sent( 'Breeze cleared its whole cache.' );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->has_action( 'purge_post_cache' ) ) {
            return $this->helper_missing( 'purge_post_cache action (Breeze 2.4.0 and later)' );
        }
        foreach ( $request->post_ids as $post_id ) {
            $this->calls()->do_action( 'purge_post_cache', $post_id );
        }
        return $this->sent( sprintf( 'Breeze cleared %d post(s) with their related pages.', count( $request->post_ids ) ) );
    }

    private function cloudflare_on(): bool {
        $calls = $this->calls();
        if ( ! $calls->class_exists( 'Breeze_CloudFlare_Helper' ) ) {
            return false;
        }
        foreach ( self::CDN_CONSTANTS as $constant ) {
            if ( ! $calls->defined( $constant ) ) {
                return false;
            }
        }
        return true;
    }

    private function request_widens_to_network(): bool {
        $calls = $this->calls();
        if ( ! $calls->is_super_admin() && ! $calls->current_user_can( 'manage_network_options' ) ) {
            return false;
        }
        $query = array();
        parse_str( $calls->server( 'QUERY_STRING' ), $query );
        if ( isset( $query['is-network'] ) && is_string( $query['is-network'] ) && filter_var( $query['is-network'], FILTER_VALIDATE_BOOLEAN ) ) {
            return true;
        }
        return false !== strpos( $calls->server( 'HTTP_REFERER' ), '/wp-admin/network/' );
    }
}
