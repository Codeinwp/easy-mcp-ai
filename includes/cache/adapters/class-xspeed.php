<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';






























final class Xspeed extends Cache_Adapter {

    
    const CAUSE = 'easy-mcp-ai';

    
    const MAX_DEFERRED_URLS = 100;

    const RUNNER = 'XSpeed\\Purge_Runner';
    const CACHE  = 'XSpeed\\Cache';
    const MODULES  = 'XSpeed\\Module_Registry';

    public function id(): string {
        return 'xspeed';
    }

    public function label(): string {
        return 'xSpeed Cache';
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
        return $this->calls()->defined( 'XSPEED_VERSION' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE === $request->scope ) {
            return $this->edge_listens( 'xspeed_after_purge_all', 'on_xspeed_purge' ) ? array( self::ALSO_CDN ) : array();
        }
        if ( count( $request->urls ) > $this->max_deferred_urls() && $this->edge_listens( 'xspeed_after_purge_url', 'on_xspeed_purge_url' ) ) {
            return array( self::ALSO_CDN );
        }
        return array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    





    public function noise_options(): array {
        return array( 'xspeed_activity_log', 'xspeed_stats', 'xspeed_cloudflare_health' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::RUNNER, 'run' ) ) {
            return $this->helper_missing( 'XSpeed\\Purge_Runner::run()' );
        }
        $types  = $request->include_cdn && $this->edge_ready() ? array( 'page', 'cloudflare' ) : array( 'page' );
        $report = $this->calls()->call( array( self::RUNNER, 'run' ), array( $types, self::CAUSE ) );
        $rows   = is_array( $report ) && isset( $report['types'] ) && is_array( $report['types'] ) ? $report['types'] : array();
        $page   = isset( $rows['page'] ) && is_array( $rows['page'] ) ? $rows['page'] : array();
        if ( 'cleared' !== ( $page['status'] ?? '' ) ) {
            $reason = (string) ( $page['reason'] ?? '' );
            return $this->layer( Layer_Result::FAILED, array( 'detail' => sprintf( 'xSpeed reported its page sweep as %s%s.', (string) ( $page['status'] ?? 'missing' ), '' !== $reason ? ': ' . $reason : '' ) ) );
        }
        if ( 1 === count( $types ) ) {
            return $this->sent( 'xSpeed Cache cleared its page cache.' );
        }
        $edge   = isset( $rows['cloudflare'] ) && is_array( $rows['cloudflare'] ) ? $rows['cloudflare'] : array();
        $detail = 'xSpeed Cache cleared its page cache and the whole Cloudflare zone.';
        if ( 'failed' === ( $edge['status'] ?? '' ) ) {
            $detail .= sprintf( ' Its Cloudflare zone purge failed: %s.', (string) ( $edge['reason'] ?? 'no reason given' ) );
        }
        return $this->sent( $detail );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::CACHE, 'purge_url' ) ) {
            return $this->helper_missing( 'XSpeed\\Cache::purge_url()' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->call( array( self::CACHE, 'purge_url' ), array( $url, self::CAUSE ) );
        }
        return $this->sent( sprintf( 'xSpeed Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    




    private function edge_ready(): bool {
        $calls = $this->calls();
        return $calls->method_exists( self::RUNNER, 'cloudflare_enabled' ) && true === $calls->call( array( self::RUNNER, 'cloudflare_enabled' ) );
    }

    







    private function edge_listens( string $hook, string $method ): bool {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::MODULES, 'get' ) ) {
            return false;
        }
        $module = $calls->call( array( self::MODULES, 'get' ), array( 'cloudflare' ) );
        return is_object( $module ) && false !== $calls->has_action( $hook, array( $module, $method ) ) && $this->edge_ready();
    }

    




    private function max_deferred_urls(): int {
        $max = (int) $this->calls()->apply_filters( 'xspeed_cloudflare_max_deferred_purge_urls', self::MAX_DEFERRED_URLS );
        return $max > 0 ? $max : self::MAX_DEFERRED_URLS;
    }
}
