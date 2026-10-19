<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






















final class Kinsta extends Cache_Adapter {

    public function id(): string {
        return 'kinsta';
    }

    public function label(): string {
        return 'Kinsta';
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
        return is_object( $this->calls()->global_value( 'kinsta_cache' ) ) || $this->calls()->defined( 'KINSTAMU_VERSION' );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls   = $this->calls();
        $purger  = $this->purger();
        $methods = $request->include_cdn ? array( 'purge_complete_site_cache', 'purge_complete_cdn_cache' ) : array( 'purge_complete_site_cache' );
        foreach ( $methods as $method ) {
            if ( null === $purger || ! $calls->method_exists( $purger, $method ) ) {
                return $this->helper_missing( 'Cache_Purge::' . $method . '()' );
            }
        }
        $returned = $calls->call( array( $purger, 'purge_complete_site_cache' ) );
        if ( $returned instanceof \WP_Error || ! $request->include_cdn ) {
            return $this->outcome( $returned, 'Kinsta cleared its server cache for this site.' );
        }
        $cdn = $calls->call( array( $purger, 'purge_complete_cdn_cache' ) );
        if ( $cdn instanceof \WP_Error ) {
            return $this->sent( sprintf( 'Kinsta cleared its server cache for this site, but its CDN refused the purge: %s', $cdn->get_error_message() ) );
        }
        return $this->sent( 'Kinsta cleared its server cache and its CDN for this site.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls  = $this->calls();
        $purger = $this->purger();
        foreach ( array( 'convert_purge_list_to_request', 'send_cache_purge_request' ) as $method ) {
            if ( null === $purger || ! $calls->method_exists( $purger, $method ) ) {
                return $this->helper_missing( 'Cache_Purge::' . $method . '()' );
            }
        }
        $endpoint = $this->immediate_path( $purger );
        if ( '' === $endpoint ) {
            return $this->skip( 'api_missing', 'Kinsta is active but has no immediate purge endpoint configured, so nothing was sent.' );
        }
        $single = array();
        foreach ( $request->urls as $i => $url ) {
            $single[ 'easy-mcp-ai-' . $i ] = $url;
        }
        $body   = $calls->call( array( $purger, 'convert_purge_list_to_request' ), array( array( 'single' => $single ) ) );
        $answer = $calls->call( array( $purger, 'send_cache_purge_request' ), array( $endpoint, is_array( $body ) ? $body : array() ) );
        if ( is_array( $answer ) && ! empty( $answer['error_code'] ) ) {
            $message = isset( $answer['error_message'] ) && is_string( $answer['error_message'] ) ? $answer['error_message'] : 'no message';
            return $this->layer( Layer_Result::FAILED, array( 'detail' => sprintf( 'Kinsta\'s purge endpoint could not be reached: %s', $message ) ) );
        }
        return $this->sent( sprintf( 'Kinsta cleared %d page(s).', count( $request->urls ) ) );
    }

    




    private function purger(): ?object {
        $kinsta = $this->calls()->global_value( 'kinsta_cache' );
        if ( ! is_object( $kinsta ) || ! isset( $kinsta->kinsta_cache_purge ) || ! is_object( $kinsta->kinsta_cache_purge ) ) {
            return null;
        }
        return $kinsta->kinsta_cache_purge;
    }

    





    private function immediate_path( object $purger ): string {
        $cache  = isset( $purger->kinsta_cache ) && is_object( $purger->kinsta_cache ) ? $purger->kinsta_cache : null;
        $config = null !== $cache && isset( $cache->config ) && is_array( $cache->config ) ? $cache->config : array();
        return isset( $config['immediate_path'] ) && is_string( $config['immediate_path'] ) ? $config['immediate_path'] : '';
    }
}
