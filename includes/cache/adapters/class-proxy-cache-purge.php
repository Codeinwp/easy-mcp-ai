<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}























final class Proxy_Cache_Purge extends Cache_Adapter {

    const VENDOR = 'VarnishPurger';

    public function id(): string {
        return 'proxy-cache-purge';
    }

    public function label(): string {
        return 'Proxy Cache Purge';
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
        return $this->calls()->class_exists( self::VENDOR );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_POSTS === $request->scope && $this->tags_mode() && $this->is_nginx_backend() ) {
            return array( self::ALSO_SITE );
        }
        return array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return Purge_Request::SCOPE_SITE === $request->scope
            && true !== $calls->constant( 'SUBDOMAIN_INSTALL' )
            && true === $calls->call( 'is_main_site' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::VENDOR, 'purge_url' ) || ! $calls->method_exists( self::VENDOR, 'the_home_url' ) ) {
            return $this->helper_missing( 'VarnishPurger::purge_url()' );
        }
        $home = (string) $calls->call( array( self::VENDOR, 'the_home_url' ) );
        $calls->call( array( self::VENDOR, 'purge_url' ), array( $home . '/?vhp-regex' ) );
        return $this->sent( 'Proxy Cache Purge cleared its whole cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::VENDOR, 'purge_url' ) ) {
            return $this->helper_missing( 'VarnishPurger::purge_url()' );
        }
        $this->send( $request->urls );
        return $this->sent( sprintf( 'Proxy Cache Purge cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::VENDOR, 'purge_url' ) ) {
            return $this->helper_missing( 'VarnishPurger::purge_url()' );
        }
        $purger = $this->post_purger();
        if ( null === $purger ) {
            $this->send( $request->urls );
            return $this->sent( sprintf( 'Proxy Cache Purge cleared %d page(s), not the posts\' related URLs.', count( $request->urls ) ) );
        }
        $queue = array();
        foreach ( $request->post_ids as $post_id ) {
            $queue = $calls->call( array( $purger, 'generate_urls' ), array( $post_id ) );
        }
        $queue = is_array( $queue ) ? array_map( 'strval', $queue ) : array();
        $this->send( array_values( array_diff( self::encoded( $request->urls ), $queue ) ) );
        $detail = sprintf( 'Proxy Cache Purge cleared %d post(s) with their related URLs, and %d page(s).', count( $request->post_ids ), count( $request->urls ) );
        if ( $this->cron_mode() ) {
            return $this->parked( $detail . ' It sends the posts\' related URLs on the next WP-Cron run.' );
        }
        if ( $this->tags_mode() ) {
            return $this->sent( $detail . ' It purged the posts by their cache tags.' );
        }
        return $this->sent( $detail . ' It sends the posts\' related URLs when this request ends, after the reply.', 'after_response' );
    }

    





    private function send( array $urls ): void {
        foreach ( self::encoded( $urls ) as $url ) {
            $this->calls()->call( array( self::VENDOR, 'purge_url' ), array( $url ) );
        }
    }

    






    private static function encoded( array $urls ): array {
        return array_values(
            array_map(
                static function ( string $url ): string {
                    return (string) preg_replace_callback(
                        '/[\x80-\xFF]/',
                        static function ( array $byte ): string {
                            return rawurlencode( $byte[0] );
                        },
                        $url
                    );
                },
                $urls
            )
        );
    }

    





    private function post_purger(): ?object {
        $calls  = $this->calls();
        $purger = $calls->global_value( 'purger' );
        if ( is_object( $purger ) && true === $calls->call( 'is_a', array( $purger, self::VENDOR ) ) && $calls->method_exists( $purger, 'generate_urls' ) ) {
            return $purger;
        }
        return null;
    }

    




    private function tags_mode(): bool {
        return ! empty( $this->calls()->call( 'get_site_option', array( 'vhp_varnish_use_tags' ) ) ) && $this->calls()->class_exists( 'VarnishTags' );
    }

    




    private function cron_mode(): bool {
        $calls = $this->calls();
        return $calls->method_exists( self::VENDOR, 'is_cron_purging_enabled_static' )
            && true === (bool) $calls->call( array( self::VENDOR, 'is_cron_purging_enabled_static' ) );
    }

    




    private function is_nginx_backend(): bool {
        $calls = $this->calls();
        return $calls->method_exists( self::VENDOR, 'is_nginx_backend' )
            && true === (bool) $calls->call( array( self::VENDOR, 'is_nginx_backend' ) );
    }
}
