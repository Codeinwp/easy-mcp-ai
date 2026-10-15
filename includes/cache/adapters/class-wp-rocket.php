<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';
































final class Wp_Rocket extends Cache_Adapter {

    
    private $label = '';

    public function id(): string {
        return 'wp-rocket';
    }

    




    public function label(): string {
        if ( '' !== $this->label ) {
            return $this->label;
        }
        if ( ! $this->calls()->function_exists( 'rocket_clean_domain' ) ) {
            return 'WP Rocket';
        }
        $file        = str_replace( '\\', '/', (string) $this->calls()->call( array( self::class, 'defining_file' ), array( 'rocket_clean_domain' ) ) );
        $this->label = false !== strpos( $file, '/clsop/' ) ? 'AccelerateWP' : 'WP Rocket';
        return $this->label;
    }

    






    public static function defining_file( string $name ): string {
        try {
            $file = ( new \ReflectionFunction( $name ) )->getFileName();
        } catch ( \ReflectionException $e ) {
            return '';
        }
        return is_string( $file ) ? $file : '';
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
        return $calls->function_exists( 'rocket_clean_domain' ) && $calls->function_exists( 'rocket_is_importing' );
    }

    public function also_clears( Purge_Request $request ): array {
        $sucuri = (bool) $this->rocket_option( 'sucury_waf_cache_sync' );
        if ( Purge_Request::SCOPE_SITE === $request->scope ) {
            return $sucuri || $this->cloudflare_on() ? array( self::ALSO_CDN ) : array();
        }
        $also = $sucuri ? array( self::ALSO_CDN ) : array();
        if ( $this->wipes_a_host( $request ) ) {
            $also[] = self::ALSO_SITE;
        }
        return $also;
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        if ( Purge_Request::SCOPE_SITE !== $request->scope || ! $calls->is_multisite() ) {
            return false;
        }
        if ( $this->polylang_wipes_every_host() || $this->cascade_reaches_network() ) {
            return true;
        }
        return true !== $calls->constant( 'SUBDOMAIN_INSTALL' ) && true === $calls->call( 'is_main_site' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $this->calls()->call( 'rocket_clean_domain' );
        return $this->sent( sprintf( '%s cleared this site\'s cache.', $this->label() ) );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        foreach ( array( 'rocket_clean_home', 'rocket_clean_files' ) as $helper ) {
            if ( ! $calls->function_exists( $helper ) ) {
                return $this->helper_missing( $helper . '()' );
            }
        }
        $pages = array();
        $home  = false;
        foreach ( $request->urls as $url ) {
            if ( $this->is_vendor_home( $url ) ) {
                $home = true;
            } else {
                $pages[] = $url;
            }
        }
        if ( $home ) {
            $calls->call( 'rocket_clean_home' );
        }
        if ( array() !== $pages ) {
            $calls->call( 'rocket_clean_files', array( $pages ) );
        }
        return $this->sent( sprintf( '%s cleared %d page(s).', $this->label(), count( $request->urls ) ) );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        if ( ! $this->uses_post_helper() || $this->always_purges_root() ) {
            return $this->purge_urls( $request );
        }
        $declined = 0;
        foreach ( $request->post_ids as $post_id ) {
            if ( true !== $this->calls()->call( 'rocket_clean_post', array( $post_id ) ) ) {
                ++$declined;
            }
        }
        if ( $declined > 0 ) {
            return $this->purge_urls( $request );
        }
        return $this->sent( sprintf( '%s cleared %d post(s) with their home page, archives and parent pages.', $this->label(), count( $request->post_ids ) ) );
    }

    





    private function uses_post_helper(): bool {
        $calls = $this->calls();
        return $calls->function_exists( 'rocket_clean_post' ) && '' !== (string) $calls->get_option( 'permalink_structure', '' );
    }

    







    private function wipes_a_host( Purge_Request $request ): bool {
        foreach ( $request->urls as $url ) {
            if ( '/' === $this->path_of( $url ) && ! $this->is_vendor_home( $url ) ) {
                return true;
            }
        }
        return false;
    }

    




    private function always_purges_root(): bool {
        $pages = $this->rocket_option( 'cache_purge_pages' );
        foreach ( is_array( $pages ) ? $pages : array() as $page ) {
            if ( is_string( $page ) && false === strpos( $page, '*' ) && '' === trim( $page, '/ ' ) ) {
                return true;
            }
        }
        return false;
    }

    






    private function is_vendor_home( string $url ): bool {
        $calls = $this->calls();
        $home  = $calls->function_exists( 'get_rocket_i18n_home_url' ) ? $calls->call( 'get_rocket_i18n_home_url' ) : '';
        $home  = is_string( $home ) && '' !== $home ? $home : (string) \home_url( '/' );
        $host  = \wp_parse_url( $home, PHP_URL_HOST );
        $mine  = \wp_parse_url( $url, PHP_URL_HOST );
        return is_string( $host ) && is_string( $mine ) && strtolower( $host ) === strtolower( $mine )
            && untrailingslashit( $this->path_of( $url ) ) === untrailingslashit( $this->path_of( $home ) );
    }

    






    private function cloudflare_on(): bool {
        if ( $this->rocket_option( 'do_cloudflare' ) ) {
            return true;
        }
        $calls = $this->calls();
        if ( ! $calls->defined( 'CLOUDFLARE_PLUGIN_DIR' ) ) {
            return false;
        }
        foreach ( array( 'cloudflare_api_email', 'cloudflare_api_key', 'cloudflare_cached_domain_name' ) as $option ) {
            if ( empty( $calls->get_option( $option, '' ) ) ) {
                return false;
            }
        }
        return true;
    }

    





    private function polylang_wipes_every_host(): bool {
        $calls = $this->calls();
        if ( ! $calls->constant( 'POLYLANG_VERSION' ) ) {
            return false;
        }
        $pll = $calls->function_exists( 'PLL' ) ? $calls->call( 'PLL' ) : $calls->global_value( 'polylang' );
        return is_object( $pll ) && isset( $pll->options ) && is_array( $pll->options ) && isset( $pll->options['force_lang'] ) && 0 === $pll->options['force_lang'];
    }

    






    private function cascade_reaches_network(): bool {
        $calls = $this->calls();
        if ( $calls->class_exists( 'NinukisCaching', false ) ) {
            return true;
        }
        if ( null === $calls->global_value( 'nginx_helper' ) ) {
            return false;
        }
        $admin = $calls->global_value( 'nginx_helper_admin' );
        return ! is_object( $admin ) || ! isset( $admin->options['cache_method'] ) || 'enable_redis' !== $admin->options['cache_method'];
    }

    






    private function rocket_option( string $name ) {
        $calls = $this->calls();
        if ( $calls->function_exists( 'get_rocket_option' ) ) {
            return $calls->call( 'get_rocket_option', array( $name, false ) );
        }
        $settings = $calls->get_option( 'wp_rocket_settings', array() );
        return is_array( $settings ) && isset( $settings[ $name ] ) ? $settings[ $name ] : false;
    }
}
