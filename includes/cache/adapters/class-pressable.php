<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';






















final class Pressable extends Cache_Adapter {

    const PLUGIN = 'Edge_Cache_Plugin';

    const REASON = 'easy-mcp-ai-clear-cache';

    public function id(): string {
        return 'pressable';
    }

    public function label(): string {
        return 'Pressable Edge Cache';
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
        return $this->calls()->class_exists( self::PLUGIN );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $edge = $this->edge( 'purge_domain_now' );
        if ( null === $edge ) {
            return $this->helper_missing( 'Edge_Cache_Plugin::get_instance()->purge_domain_now()' );
        }
        if ( ! $this->calls()->call( array( $edge, 'purge_domain_now' ), array( self::REASON ) ) ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => 'Pressable Edge Cache refused the purge; Pressable says it might be disabled or rate-limited.' ) );
        }
        return $this->sent( 'Pressable Edge Cache cleared the whole domain.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $edge = $this->edge( 'purge_uris_now' );
        if ( null === $edge ) {
            return $this->helper_missing( 'Edge_Cache_Plugin::get_instance()->purge_uris_now()' );
        }
        $this->calls()->call( array( $edge, 'purge_uris_now' ), array( $request->urls ) );
        return $this->sent( sprintf( 'Pressable Edge Cache cleared %d page(s).', count( $request->urls ) ) );
    }

    





    private function edge( string $method ): ?object {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::PLUGIN, 'get_instance' ) ) {
            return null;
        }
        $edge = $calls->call( array( self::PLUGIN, 'get_instance' ) );
        return is_object( $edge ) && $calls->method_exists( $edge, $method ) ? $edge : null;
    }
}
