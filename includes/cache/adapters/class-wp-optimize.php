<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';























final class Wp_Optimize extends Cache_Adapter {

    const PAGE_CACHE = 'WPO_Page_Cache';

    public function id(): string {
        return 'wp-optimize';
    }

    public function label(): string {
        return 'WP-Optimize';
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
        return $calls->defined( 'WPO_VERSION' ) && $calls->class_exists( self::PAGE_CACHE, false );
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope;
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $cache = $this->page_cache();
        if ( null === $cache || ! $this->calls()->method_exists( $cache, 'purge' ) ) {
            return $this->helper_missing( 'WPO_Page_Cache::instance()->purge()' );
        }
        $this->calls()->call( array( $cache, 'purge' ) );
        return $this->sent( 'WP-Optimize cleared its whole cache folder.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( ! $this->calls()->method_exists( self::PAGE_CACHE, 'delete_cache_by_url' ) ) {
            return $this->helper_missing( 'WPO_Page_Cache::delete_cache_by_url()' );
        }
        foreach ( $request->urls as $url ) {
            $this->calls()->call( array( self::PAGE_CACHE, 'delete_cache_by_url' ), array( $this->vendor_url( $url ), false ) );
        }
        return $this->sent( sprintf( 'WP-Optimize cleared %d page(s).', count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        foreach ( array( 'delete_single_post_cache', 'delete_cache_by_url' ) as $method ) {
            if ( ! $calls->method_exists( self::PAGE_CACHE, $method ) ) {
                return $this->helper_missing( 'WPO_Page_Cache::' . $method . '()' );
            }
        }
        $own = 0;
        foreach ( $request->post_ids as $post_id ) {
            $permalink = $calls->call( 'get_permalink', array( $post_id ) );
            if ( is_string( $permalink ) && ! $this->is_home_url( $permalink ) ) {
                $calls->call( array( self::PAGE_CACHE, 'delete_single_post_cache' ), array( $post_id ) );
                ++$own;
            }
        }
        $this->purge_urls( $request );
        return $this->sent( sprintf( 'WP-Optimize cleared %d post(s) at their own address, and %d page(s).', $own, count( $request->urls ) ) );
    }

    




    private function page_cache(): ?object {
        if ( ! $this->calls()->method_exists( self::PAGE_CACHE, 'instance' ) ) {
            return null;
        }
        $cache = $this->calls()->call( array( self::PAGE_CACHE, 'instance' ) );
        return is_object( $cache ) ? $cache : null;
    }

    








    private function vendor_url( string $url ): string {
        $host = \wp_parse_url( $url, PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            return $url;
        }
        $at = strpos( $url, $host );
        return false === $at ? $url : substr_replace( $url, strtolower( $host ), $at, strlen( $host ) );
    }
}
