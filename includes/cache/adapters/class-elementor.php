<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































final class Elementor extends Cache_Adapter {

    
    const CLEAR_HOOK = 'elementor/core/files/clear_cache';

    



    const CASCADE_LISTENERS = array(
        'litespeed\\thirdparty\\elementor'         => 'LiteSpeed Cache',
        'breeze_elementor_template'                => 'Breeze',
        'spinupwp\\compatibility\\elementorplugin' => 'SpinupWP',
        'cphp_purge'                               => 'Cache Purge Helper',
        'spc\\modules\\cache_invalidation_hooks'   => 'Super Page Cache',
    );

    
    const SPC_STORE = 'SPC\\Services\\Settings_Store';

    
    const SPC_LISTENER = 'spc\\modules\\cache_invalidation_hooks';

    
    const LITESPEED_CLOUDFLARE = 'litespeed.conf.cdn-cloudflare';

    
    const POST_CSS_CLASS = 'Elementor\\Core\\Files\\CSS\\Post';

    
    const ELEMENT_CACHE_KEY = 'Elementor\\Core\\Base\\Document::CACHE_META_KEY';

    public function id(): string {
        return 'elementor';
    }

    public function label(): string {
        return 'Elementor';
    }

    public function kind(): string {
        return self::KIND_ASSET;
    }

    public function tier(): int {
        return self::TIER_ASSET;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE, Purge_Request::SCOPE_URLS, Purge_Request::SCOPE_POSTS );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->did_action( 'elementor/loaded' ) > 0 && $calls->class_exists( 'Elementor\\Plugin' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return array();
        }
        $listeners = $this->cascade_listeners();
        $also      = array();
        if ( array() !== $this->object_flushers( $listeners ) ) {
            $also[] = self::ALSO_OBJECT_CACHE;
        }
        if ( array() !== $this->zone_purgers( $listeners ) ) {
            $also[] = self::ALSO_CDN;
        }
        return $also;
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        if ( Purge_Request::SCOPE_SITE !== $request->scope || ! $calls->is_multisite() ) {
            return false;
        }
        $listeners = $this->cascade_listeners();
        return ( $calls->using_ext_object_cache() && array() !== $this->object_flushers( $listeners ) )
            || array() !== $this->zone_purgers( $listeners );
    }

    




    public function noise_options(): array {
        return array( 'elementor-custom-breakpoints-files', '_elementor_assets_data' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $manager = $this->files_manager();
        if ( null === $manager ) {
            return $this->helper_missing( 'Plugin::$instance->files_manager->clear_cache()' );
        }
        $this->calls()->call( array( $manager, 'clear_cache' ) );
        return $this->sent( 'Elementor cleared its generated CSS files and data.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $ids      = array();
        $unmapped = array();
        foreach ( $request->urls as $url ) {
            $id = $this->post_id_of( $url );
            if ( $id > 0 ) {
                $ids[] = $id;
            } else {
                $unmapped[] = $url;
            }
        }
        if ( array() === $ids ) {
            return $this->skip( 'no_url_api', 'Elementor keeps CSS per post, and none of these URLs is a single post or page, so its CSS (archive templates, the site kit) clears only with scope=site.', array( 'needs' => 'scope=site' ) );
        }
        return $this->clear_posts( array_values( array_unique( $ids ) ), $unmapped );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( array() === $request->post_ids ) {
            return $this->purge_urls( $request );
        }
        return $this->clear_posts( array_values( array_unique( $request->post_ids ) ), array() );
    }

    






    private function clear_posts( array $post_ids, array $unmapped ): Layer_Result {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::POST_CSS_CLASS, 'create' ) ) {
            return $this->helper_missing( 'Elementor\\Core\\Files\\CSS\\Post::create()' );
        }
        $element_cache_key = $this->element_cache_key();
        foreach ( $post_ids as $post_id ) {
            $file = $calls->call( array( self::POST_CSS_CLASS, 'create' ), array( $post_id ) );
            if ( is_object( $file ) ) {
                $calls->call( array( $file, 'delete' ) );
            }
            $calls->do_action( 'elementor/atomic-widgets/styles/clear', array( 'local', $post_id ) );
            if ( null !== $element_cache_key ) {
                $calls->call( 'delete_post_meta', array( $post_id, $element_cache_key ) );
            }
        }
        $detail = null === $element_cache_key
            ? sprintf( 'Elementor cleared the CSS of %d post(s).', count( $post_ids ) )
            : sprintf( 'Elementor cleared the CSS and the element cache of %d post(s).', count( $post_ids ) );
        if ( array() !== $unmapped ) {
            $detail .= sprintf( ' Not a single post or page, so not cleared here: %s (use scope=site).', implode( ', ', $unmapped ) );
        }
        return $this->sent( $detail );
    }

    





    private function element_cache_key(): ?string {
        $calls = $this->calls();
        if ( ! $calls->defined( self::ELEMENT_CACHE_KEY ) ) {
            return null;
        }
        $key = $calls->constant( self::ELEMENT_CACHE_KEY );
        return is_string( $key ) && '' !== $key ? $key : null;
    }

    




    private function files_manager(): ?object {
        $calls = $this->calls();
        if ( ! $calls->method_exists( 'Elementor\\Plugin', 'instance' ) ) {
            return null;
        }
        $plugin = $calls->call( array( 'Elementor\\Plugin', 'instance' ) );
        if ( ! is_object( $plugin ) || ! isset( $plugin->files_manager ) || ! is_object( $plugin->files_manager ) ) {
            return null;
        }
        return $calls->method_exists( $plugin->files_manager, 'clear_cache' ) ? $plugin->files_manager : null;
    }

    





    private function post_id_of( string $url ): int {
        $calls = $this->calls();
        if ( $this->is_home_url( $url ) ) {
            return 'page' === $calls->get_option( 'show_on_front' ) ? (int) $calls->get_option( 'page_on_front' ) : 0;
        }
        return (int) $calls->call( 'url_to_postid', array( $url ) );
    }

    






    private function cascade_listeners(): array {
        if ( false === $this->calls()->has_action( self::CLEAR_HOOK ) ) {
            return array();
        }
        $filters = $this->calls()->global_value( 'wp_filter' );
        $hook    = is_array( $filters ) && isset( $filters[ self::CLEAR_HOOK ] ) ? $filters[ self::CLEAR_HOOK ] : null;
        if ( ! is_object( $hook ) || ! isset( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
            return array();
        }
        $found = array();
        foreach ( $hook->callbacks as $callbacks ) {
            foreach ( (array) $callbacks as $callback ) {
                $name = self::owner_of( is_array( $callback ) && isset( $callback['function'] ) ? $callback['function'] : null );
                if ( isset( self::CASCADE_LISTENERS[ $name ] ) ) {
                    $found[ $name ] = self::CASCADE_LISTENERS[ $name ];
                }
            }
        }
        return $found;
    }

    






    private function object_flushers( array $listeners ): array {
        if ( isset( $listeners[ self::SPC_LISTENER ] ) && ! $this->spc_says( 'cf_object_cache_purge_on_flush' ) ) {
            unset( $listeners[ self::SPC_LISTENER ] );
        }
        return $listeners;
    }

    








    private function zone_purgers( array $listeners ): array {
        $calls     = $this->calls();
        $litespeed = ! empty( $calls->get_option( self::LITESPEED_CLOUDFLARE ) );
        $zone      = array(
            'litespeed\\thirdparty\\elementor' => $litespeed,
            'cphp_purge'                       => $litespeed,
            'breeze_elementor_template'        => $calls->defined( 'CDN_SITE_ID' ) && $calls->defined( 'CDN_SITE_TOKEN' ),
            self::SPC_LISTENER                 => isset( $listeners[ self::SPC_LISTENER ] ) && $this->spc_purges_zone(),
        );
        $found = array();
        foreach ( $listeners as $key => $name ) {
            if ( ! empty( $zone[ $key ] ) ) {
                $found[] = $name;
            }
        }
        return $found;
    }

    





    private function spc_purges_zone(): bool {
        $store = $this->spc_store();
        $calls = $this->calls();
        if ( null === $store || ! $calls->method_exists( $store, 'is_cloudflare_connected' ) || ! $calls->method_exists( $store, 'get' ) ) {
            return true;
        }
        return (bool) $calls->call( array( $store, 'is_cloudflare_connected' ) ) && ! $this->spc_says( 'cf_purge_only_html' );
    }

    






    private function spc_says( string $key ): bool {
        $store = $this->spc_store();
        if ( null === $store || ! $this->calls()->method_exists( $store, 'get' ) ) {
            return true;
        }
        $value = $this->calls()->call( array( $store, 'get' ), array( $key, 0 ) );
        return is_scalar( $value ) && (int) $value > 0;
    }

    




    private function spc_store(): ?object {
        $calls = $this->calls();
        if ( ! $calls->method_exists( self::SPC_STORE, 'get_instance' ) ) {
            return null;
        }
        $store = $calls->call( array( self::SPC_STORE, 'get_instance' ) );
        return is_object( $store ) ? $store : null;
    }

    






    private static function owner_of( $callable ): string {
        if ( is_string( $callable ) ) {
            $owner = false !== strpos( $callable, '::' ) ? (string) strstr( $callable, '::', true ) : $callable;
            return strtolower( ltrim( $owner, '\\' ) );
        }
        if ( is_array( $callable ) && isset( $callable[0] ) ) {
            $owner = is_object( $callable[0] ) ? get_class( $callable[0] ) : (string) $callable[0];
            return strtolower( ltrim( $owner, '\\' ) );
        }
        return '';
    }
}
