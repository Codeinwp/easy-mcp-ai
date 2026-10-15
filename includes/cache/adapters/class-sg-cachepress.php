<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';



























final class Sg_Cachepress extends Cache_Adapter {

    const SUPERCACHER = 'SiteGround_Optimizer\\Supercacher\\Supercacher';
    const FILE_CACHER = 'SiteGround_Optimizer\\File_Cacher\\File_Cacher';
    const HELPER      = 'SiteGround_Helper\\Helper_Service';

    public function id(): string {
        return 'sg-cachepress';
    }

    public function label(): string {
        return 'SiteGround Speed Optimizer';
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
        return is_object( $this->calls()->global_value( 'siteground_optimizer_loader' ) )
            && $this->calls()->class_exists( self::SUPERCACHER );
    }

    






    public function also_clears( Purge_Request $request ): array {
        $home = false;
        foreach ( $request->urls as $url ) {
            if ( $this->is_home_url( $url ) ) {
                $home = true;
            } elseif ( ! $this->has_dot_segment( $url ) ) {
                return array();
            }
        }
        return $home ? array( self::ALSO_SITE ) : array();
    }

    public function reaches_network( Purge_Request $request ): bool {
        if ( ! $this->calls()->is_multisite() ) {
            return false;
        }
        if ( $this->file_on() ) {
            return true;
        }
        return Purge_Request::SCOPE_SITE === $request->scope
            && $this->dynamic_on()
            && true !== $this->calls()->constant( 'SUBDOMAIN_INSTALL' )
            && true === $this->calls()->call( 'is_main_site' );
    }

    public function noise_options(): array {
        return array( 'siteground_optimizer_file_cache_secret' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        if ( $this->dynamic_on() ) {
            if ( ! $calls->method_exists( self::SUPERCACHER, 'purge_cache' ) ) {
                return $this->helper_missing( 'Supercacher::purge_cache()' );
            }
            if ( true !== $calls->call( array( self::SUPERCACHER, 'purge_cache' ) ) ) {
                return $this->layer( Layer_Result::FAILED, array( 'detail' => 'SiteGround\'s Site Tools did not accept the purge: its socket is missing, unreachable, or answered with an error.' ) );
            }
            return $this->sent( 'SiteGround Speed Optimizer cleared SiteGround\'s dynamic cache and its own file cache for the whole site.' );
        }
        $cacher = $calls->method_exists( self::FILE_CACHER, 'get_instance' ) ? $calls->call( array( self::FILE_CACHER, 'get_instance' ) ) : null;
        if ( ! is_object( $cacher ) || ! $calls->method_exists( $cacher, 'purge_everything' ) ) {
            return $this->helper_missing( 'File_Cacher::get_instance()->purge_everything()' );
        }
        $calls->call( array( $cacher, 'purge_everything' ) );
        return $this->sent( 'SiteGround Speed Optimizer cleared its file cache.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $calls = $this->calls();
        $class = $this->dynamic_on() ? self::SUPERCACHER : self::FILE_CACHER;
        if ( ! $calls->method_exists( $class, 'purge_cache_request' ) ) {
            return $this->helper_missing( substr( (string) strrchr( $class, '\\' ), 1 ) . '::purge_cache_request()' );
        }
        $sent   = 0;
        $failed = 0;
        $home   = false;
        $unsafe = 0;
        foreach ( $request->urls as $url ) {
            if ( $this->has_dot_segment( $url ) ) {
                ++$unsafe;
            } elseif ( $this->is_home_url( $url ) ) {
                $home = true;
            } elseif ( true === $calls->call( array( $class, 'purge_cache_request' ), array( $url, false ) ) ) {
                ++$sent;
            } else {
                ++$failed;
            }
        }
        if ( 0 === $sent + $failed ) {
            return $this->skip( 'not_deliverable', 'No address in this call can be sent to SiteGround Speed Optimizer: an address with a "." or ".." segment would resolve outside its cache folder.' );
        }
        if ( 0 === $sent ) {
            return $this->layer( Layer_Result::FAILED, array( 'detail' => sprintf( 'SiteGround Speed Optimizer refused all %d URL(s): its Site Tools socket did not accept them, or its file cache refuses single pages in this request (during WP-Cron or AJAX, or for an excluded address).', $failed ) ) );
        }
        $detail = sprintf( 'SiteGround Speed Optimizer cleared %d page(s).', $sent );
        if ( $failed > 0 ) {
            $detail .= sprintf( ' It refused %d other URL(s).', $failed );
        }
        if ( $unsafe > 0 ) {
            $detail .= sprintf( ' %d address(es) with a "." or ".." segment were left out.', $unsafe );
        }
        if ( ! $home ) {
            return $this->sent( $detail );
        }
        return $this->sent( $detail . ' The home page was left out: SiteGround Speed Optimizer clears its whole cache when asked for it, so use scope=site.' )->with( array( 'needs' => 'scope=site' ) );
    }

    





    private function has_dot_segment( string $url ): bool {
        $segments = explode( '/', rawurldecode( $this->path_of( $url ) ) );
        return in_array( '.', $segments, true ) || in_array( '..', $segments, true );
    }

    




    private function file_on(): bool {
        return 1 === (int) $this->calls()->get_option( 'siteground_optimizer_file_caching' );
    }

    





    private function dynamic_on(): bool {
        $calls = $this->calls();
        return 1 === (int) $calls->get_option( 'siteground_optimizer_enable_cache' )
            && $calls->method_exists( self::HELPER, 'is_siteground' )
            && 1 === (int) $calls->call( array( self::HELPER, 'is_siteground' ) );
    }
}
