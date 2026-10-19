<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


























final class Jetpack_Boost extends Cache_Adapter {

    const BOOST_CACHE = 'Automattic\\Jetpack_Boost\\Modules\\Optimizations\\Page_Cache\\Pre_WordPress\\Boost_Cache';
    const ACTION_ALL    = 'jetpack_boost_clear_page_cache_all';
    const ACTION_HOME   = 'jetpack_boost_clear_page_cache_home';
    const ACTION_URL    = 'jetpack_boost_clear_page_cache_url';
    const ACTION_POST   = 'jetpack_boost_clear_page_cache_post';

    
    const SAFE_HOME_VERSION = '3.13.1';

    public function id(): string {
        return 'jetpack-boost';
    }

    public function label(): string {
        return 'Jetpack Boost Page Cache';
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
        return $this->calls()->class_exists( self::BOOST_CACHE, false ) || $this->calls()->defined( 'JETPACK_BOOST_VERSION' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE !== $request->scope && '' === (string) $this->calls()->get_option( 'permalink_structure', '' ) ) {
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
        if ( false === $this->calls()->has_action( self::ACTION_ALL ) ) {
            return $this->helper_missing( self::ACTION_ALL . ' action' );
        }
        $this->calls()->do_action( self::ACTION_ALL );
        return $this->sent( 'Jetpack Boost cleared its whole page cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        if ( false === $this->calls()->has_action( self::ACTION_URL ) ) {
            return $this->helper_missing( self::ACTION_URL . ' action' );
        }
        list( $sent, $left ) = $this->send_urls( $request->urls );
        return $this->report( sprintf( 'Jetpack Boost cleared %d page(s).', $sent ), $sent, $left );
    }

    protected function purge_posts( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        foreach ( array( self::ACTION_POST, self::ACTION_URL ) as $action ) {
            if ( false === $calls->has_action( $action ) ) {
                return $this->helper_missing( $action . ' action' );
            }
        }
        $posts = 0;
        $left  = array();
        foreach ( $request->post_ids as $post_id ) {
            if ( $this->post_widens( $post_id ) ) {
                $left[] = 'post ' . $post_id;
                continue;
            }
            $calls->do_action( self::ACTION_POST, $post_id );
            ++$posts;
        }
        list( $pages, $more ) = $this->send_urls( $request->urls );
        $detail               = sprintf( 'Jetpack Boost cleared %d post(s) and %d page(s).', $posts, $pages );
        return $this->report( $detail, $posts + $pages, array_merge( $left, $more ) );
    }

    






    private function send_urls( array $urls ): array {
        $calls = $this->calls();
        $sent  = 0;
        $left  = array();
        $home  = false;
        foreach ( $urls as $url ) {
            if ( $this->is_stripped( $url ) ) {
                $left[] = $url;
            } elseif ( ! $this->is_home_url( $url ) ) {
                $calls->do_action( self::ACTION_URL, $url );
                ++$sent;
            } elseif ( ! $this->home_is_narrow() ) {
                $left[] = $url;
            } elseif ( ! $home ) {
                $calls->do_action( self::ACTION_HOME );
                $home = true;
                ++$sent;
            }
        }
        return array( $sent, $left );
    }

    








    private function report( string $detail, int $sent, array $left ): Layer_Result {
        if ( array() === $left ) {
            return $this->sent( $detail );
        }
        $note = sprintf( ' Left out, because Jetpack Boost would clear its whole cache for them: %s. Use scope=site to clear them.', implode( ', ', $left ) );
        if ( 0 === $sent ) {
            return $this->skip( 'would_purge_site', 'Nothing was sent to Jetpack Boost.' . $note, array( 'needs' => 'scope=site' ) );
        }
        return $this->sent( $detail . $note )->with( array( 'needs' => 'scope=site' ) );
    }

    







    private function post_widens( int $post_id ): bool {
        $link = $this->calls()->call( 'get_permalink', array( $post_id ) );
        if ( ! is_string( $link ) || '' === $link || false !== strpos( $link, '?' ) || $this->is_stripped( $link ) ) {
            return true;
        }
        return $this->is_home_url( $link ) && ! $this->is_safe_version();
    }

    






    private function home_is_narrow(): bool {
        $calls = $this->calls();
        if ( ! $this->is_safe_version() || false === $calls->has_action( self::ACTION_HOME ) ) {
            return false;
        }
        $page = $calls->get_option( 'page_for_posts', 0 );
        if ( 'page' !== $calls->get_option( 'show_on_front', '' ) || empty( $page ) ) {
            return true;
        }
        $link = $calls->call( 'get_permalink', array( $page ) );
        return is_string( $link ) && '' !== $link && false === strpos( $link, '?' ) && ! $this->is_stripped( $link ) && ! $this->is_home_url( $link );
    }

    







    private function is_stripped( string $url ): bool {
        $path = $this->path_of( $url );
        return preg_replace( '/[ <>\'"\r\n\t()]/', '', $path ) !== $path;
    }

    





    private function is_safe_version(): bool {
        $version = $this->calls()->constant( 'JETPACK_BOOST_VERSION' );
        return is_string( $version ) && version_compare( $version, self::SAFE_HOME_VERSION, '>=' );
    }
}
