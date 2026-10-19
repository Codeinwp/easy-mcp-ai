<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}























final class Nginx_Helper extends Cache_Adapter {

    public function id(): string {
        return 'nginx-helper';
    }

    public function label(): string {
        return 'Nginx Helper';
    }

    public function kind(): string {
        return self::KIND_PROXY;
    }

    public function tier(): int {
        return self::TIER_PROXY;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'NGINX_HELPER_BASEPATH' ) && $calls->class_exists( 'Nginx_Helper', false );
    }

    public function reaches_network( Purge_Request $request ): bool {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return false;
        }
        return ! $this->redis_mode() || true !== $this->calls()->constant( 'SUBDOMAIN_INSTALL' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $purger = $this->purger( 'purge_all' );
        if ( null === $purger ) {
            return $this->helper_missing( '$nginx_purger->purge_all()' );
        }
        $this->calls()->call( array( $purger, 'purge_all' ) );
        return $this->sent( 'Nginx Helper cleared its whole cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $purger = $this->purger( 'purge_url' );
        if ( null === $purger ) {
            return $this->helper_missing( '$nginx_purger->purge_url()' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->call( array( $purger, 'purge_url' ), array( $url, false ) );
        }
        return $this->sent( sprintf( 'Nginx Helper cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $purger = $this->purger( 'purge_post', 'purge_url' );
        if ( null === $purger ) {
            return $this->helper_missing( '$nginx_purger->purge_post()' );
        }
        foreach ( $request->post_ids as $post_id ) {
            $this->calls()->call( array( $purger, 'purge_post' ), array( $post_id ) );
        }
        $this->purge_urls( $request );
        return $this->sent( sprintf( 'Nginx Helper cleared %d post(s) with their related pages, and %d page(s).', count( $request->post_ids ), count( $request->urls ) ) );
    }

    





    private function purger( string ...$methods ): ?object {
        $purger = $this->calls()->global_value( 'nginx_purger' );
        if ( ! is_object( $purger ) ) {
            return null;
        }
        foreach ( $methods as $method ) {
            if ( ! $this->calls()->method_exists( $purger, $method ) ) {
                return null;
            }
        }
        return $purger;
    }

    




    private function redis_mode(): bool {
        $options = $this->options();
        return isset( $options['cache_method'] ) && 'enable_redis' === $options['cache_method'];
    }

    




    private function options(): array {
        $admin = $this->calls()->global_value( 'nginx_helper_admin' );
        return is_object( $admin ) && isset( $admin->options ) && is_array( $admin->options ) ? $admin->options : array();
    }
}
