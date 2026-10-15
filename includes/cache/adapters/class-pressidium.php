<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';




















final class Pressidium extends Cache_Adapter {

    
    const CLASSES = array( 'NinukisCaching', 'Ninukis_Plugin' );

    public function id(): string {
        return 'pressidium';
    }

    public function label(): string {
        return 'Pressidium';
    }

    public function kind(): string {
        return self::KIND_HOST;
    }

    public function tier(): int {
        return self::TIER_PROXY;
    }

    public function scopes(): array {
        $plugin = $this->plugin();
        if ( null !== $plugin && $this->calls()->method_exists( $plugin, 'purge_cache' ) ) {
            return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
        }
        return array( Purge_Request::SCOPE_SITE );
    }

    public function is_present(): bool {
        return $this->calls()->defined( 'WP_NINUKIS_WP_NAME' ) && '' !== $this->singleton_class();
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope && $this->calls()->is_multisite();
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $plugin = $this->plugin();
        if ( null === $plugin || ! $this->calls()->method_exists( $plugin, 'purgeAllCaches' ) ) {
            return $this->helper_missing( 'get_instance()->purgeAllCaches()' );
        }
        $returned = $this->calls()->call( array( $plugin, 'purgeAllCaches' ) );
        return $this->outcome( $returned, 'Pressidium cleared all its caches for this site.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $plugin = $this->plugin();
        if ( null === $plugin || ! $this->calls()->method_exists( $plugin, 'purge_cache' ) ) {
            return $this->helper_missing( 'get_instance()->purge_cache()' );
        }
        if ( array() === $request->urls ) {
            return $this->skip( 'not_deliverable', 'No URL was resolved, and what Pressidium does with an empty path list is not known, so nothing was sent.' );
        }
        $paths = array();
        foreach ( $request->urls as $url ) {
            $paths[] = $this->path_of( $url );
        }
        $paths    = array_values( array_unique( $paths ) );
        $returned = $this->calls()->call( array( $plugin, 'purge_cache' ), array( $paths ) );
        return $this->outcome( $returned, sprintf( 'Pressidium cleared %d path(s).', count( $paths ) ) );
    }

    




    private function singleton_class(): string {
        foreach ( self::CLASSES as $class ) {
            if ( $this->calls()->class_exists( $class ) ) {
                return $class;
            }
        }
        return '';
    }

    




    private function plugin(): ?object {
        $class = $this->singleton_class();
        if ( '' === $class || ! $this->calls()->method_exists( $class, 'get_instance' ) ) {
            return null;
        }
        $plugin = $this->calls()->call( array( $class, 'get_instance' ) );
        return is_object( $plugin ) ? $plugin : null;
    }
}
