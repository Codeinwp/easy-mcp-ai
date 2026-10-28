<?php

namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Wordpress_Vip extends Cache_Adapter {

    const MANAGER = 'WPCOM_VIP_Cache_Manager';

    public function id(): string {
        return 'wordpress-vip';
    }

    public function label(): string {
        return 'WordPress VIP edge cache';
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
        return ( $calls->class_exists( self::MANAGER ) || '' !== $this->api( 'url' ) ) && true === $calls->constant( 'WPCOM_IS_VIP_ENV' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE === $request->scope ) {
            return '' === $this->api( 'origin_content' ) ? array( self::ALSO_CDN ) : array();
        }
        if ( '' !== (string) $this->calls()->get_option( 'permalink_structure', '' ) ) {
            return array();
        }
        if ( Purge_Request::SCOPE_POSTS === $request->scope ) {
            return array( self::ALSO_SITE );
        }
        foreach ( $request->urls as $url ) {
            if ( $this->is_home_url( $url ) ) {
                return array( self::ALSO_SITE );
            }
        }
        return array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( $request->include_cdn ) {
            $ban = $this->api( 'site' );
            if ( '' === $ban ) {
                return $this->helper_missing( 'wpvip_purge_edge_cache_for_site()' );
            }
            $calls->call( $ban );
            return $this->parked( 'WordPress VIP queued a ban of every cached response of the site, uploads and static assets included; it sends it when this request ends.' );
        }
        $origin = $this->api( 'origin_content' );
        if ( '' === $origin ) {
            return $this->helper_missing( 'wpvip_purge_edge_cache_for_origin_content()' );
        }
        if ( true !== $calls->call( $origin ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'WordPress VIP refused to queue the purge of the site\'s WordPress pages.' ) );
        }
        return $this->parked( 'WordPress VIP queued a purge of every cached WordPress page of the site; it sends it when this request ends.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $api = $this->api( 'url' );
        if ( '' === $api ) {
            return $this->helper_missing( 'wpvip_purge_edge_cache_for_url()' );
        }
        $refused = $this->queue_urls( $api, $request->urls );
        return $this->queued_row( count( $request->urls ) - count( $refused ), $refused, sprintf( '%d URL(s)', count( $request->urls ) - count( $refused ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $api = $this->api( 'url' );
        if ( '' === $api ) {
            return $this->helper_missing( 'wpvip_purge_edge_cache_for_url()' );
        }
        $refused  = $this->queue_urls( $api, $request->urls );
        $post_api = $this->api( 'post' );
        $posts    = 0;
        if ( '' !== $post_api ) {
            foreach ( $request->post_ids as $post_id ) {
                $posts += true === $this->calls()->call( $post_api, array( $post_id ) ) ? 1 : 0;
            }
        }
        $queued = count( $request->urls ) - count( $refused );
        return $this->queued_row( $queued + $posts, $refused, sprintf( '%d URL(s) and %d post(s) with their related pages', $queued, $posts ) );
    }

    private function queue_urls( string $api, array $urls ): array {
        $refused = array();
        foreach ( $urls as $url ) {
            if ( true !== $this->calls()->call( $api, array( $url ) ) ) {
                $refused[] = $url;
            }
        }
        return $refused;
    }

    private function queued_row( int $queued, array $refused, string $what ): Layer_Result {
        $rejected = array() === $refused ? '' : sprintf( ' It refused %d URL(s) as invalid: %s.', count( $refused ), implode( ', ', $refused ) );
        if ( 0 === $queued && '' !== $rejected ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'WordPress VIP queued nothing.' . $rejected ) );
        }
        return $this->parked( sprintf( 'WordPress VIP queued a purge of %s; it sends it when this request ends.%s', $what, $rejected ) );
    }

    private function api( string $what ): string {
        foreach ( array( 'wpvip_purge_edge_cache_for_', 'wpcom_vip_purge_edge_cache_for_' ) as $prefix ) {
            if ( $this->calls()->function_exists( $prefix . $what ) ) {
                return $prefix . $what;
            }
        }
        return '';
    }
}
