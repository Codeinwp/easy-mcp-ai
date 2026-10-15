<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';
































final class Nitropack extends Cache_Adapter {

    
    const MIN_VERSION = '1.19';

    
    const LIGHT_PURGE = 8;

    
    const REASON = 'Easy MCP AI: wp_clear_cache';

    
    const FULL_FANOUT = 'nitropack_execute_purge_all';

    
    const URL_FANOUT = 'nitropack_execute_purge_url';

    
    const OBJECT_FLUSHERS = array( 'nitropack\\integration\\hosting\\pressable' );

    
    const ZONE_PURGERS = array(
        'nitropack\\integration\\plugin\\cloudflare',
        'nitropack\\integration\\hosting\\rocketnet',
        'nitropack\\integration\\server\\nestifycdn',
    );

    
    const WP_ENGINE = 'nitropack\\integration\\hosting\\wpengine';

    






    const NETWORK_PURGERS = array(
        'nitropack\\integration\\plugin\\nginxhelper',
        'nitropack\\integration\\hosting\\wpengine',
        'nitropack\\integration\\hosting\\kinsta',
        'nitropack\\integration\\hosting\\spinupwp',
        'nitropack\\integration\\hosting\\raidboxes',
        'nitropack\\integration\\hosting\\wpx',
        'nitropack\\integration\\hosting\\savvii',
        'nitropack\\integration\\server\\nginxfastcgi',
    );

    
    const URL_WIDENERS = array(
        'nitropack\\integration\\hosting\\wpx',
        'nitropack\\integration\\hosting\\raidboxes',
        'nitropack\\integration\\hosting\\pagely',
        'nitropack\\integration\\hosting\\savvii',
    );

    public function id(): string {
        return 'nitropack';
    }

    public function label(): string {
        return 'NitroPack';
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
        return $calls->defined( 'NITROPACK_VERSION' ) && $calls->function_exists( 'get_nitropack' );
    }

    public function also_clears( Purge_Request $request ): array {
        if ( Purge_Request::SCOPE_SITE !== $request->scope ) {
            return $this->hooked( self::URL_FANOUT, self::URL_WIDENERS ) ? array( self::ALSO_SITE ) : array();
        }
        $also = array();
        if ( $this->hooked( self::FULL_FANOUT, self::ZONE_PURGERS ) || ( $this->hooked( self::FULL_FANOUT, array( self::WP_ENGINE ) ) && '' !== $this->calls()->server( 'HTTP_X_EFPC_ENABLED' ) ) ) {
            $also[] = self::ALSO_CDN;
        }
        if ( $this->hooked( self::FULL_FANOUT, self::OBJECT_FLUSHERS ) ) {
            $also[] = self::ALSO_OBJECT_CACHE;
        }
        return $also;
    }

    public function reaches_network( Purge_Request $request ): bool {
        return Purge_Request::SCOPE_SITE === $request->scope
            && $this->calls()->is_multisite()
            && $this->hooked( self::FULL_FANOUT, array_merge( self::NETWORK_PURGERS, self::OBJECT_FLUSHERS, self::ZONE_PURGERS ) );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $missing = $this->api_missing();
        if ( null !== $missing ) {
            return $missing;
        }
        $type = $this->calls()->constant( 'NitroPack\\SDK\\PurgeType::LIGHT_PURGE' );
        if ( true !== $this->calls()->call( 'nitropack_sdk_purge', array( null, null, self::REASON, is_int( $type ) ? $type : self::LIGHT_PURGE ) ) ) {
            return $this->no_sdk();
        }
        return $this->sent( 'NitroPack cleared its whole cache, locally and on its servers.' );
    }

    protected function purge_urls( Purge_Request $request ): Layer_Result {
        $missing = $this->api_missing();
        if ( null !== $missing ) {
            return $missing;
        }
        $calls  = $this->calls();
        $sent   = 0;
        $unsent = array();
        $why    = '';
        foreach ( $request->urls as $i => $url ) {
            if ( $sent > 0 && $request->deadline > 0 && $calls->now() >= $request->deadline ) {
                $why = 'this call ran out of time';
            } elseif ( true !== $calls->call( 'nitropack_sdk_purge', array( $url, null, self::REASON ) ) ) {
                $why = 'NitroPack could not load its SDK for this request\'s address';
            } else {
                ++$sent;
                continue;
            }
            $unsent = array_slice( $request->urls, $i );
            break;
        }
        if ( 0 === $sent && array() !== $unsent ) {
            return $this->no_sdk();
        }
        $detail = sprintf( 'NitroPack cleared %d URL(s), locally and on its servers.', $sent );
        if ( array() !== $unsent ) {
            $detail .= sprintf( ' %d URL(s) were NOT sent because %s; call again with them: %s', count( $unsent ), $why, implode( ', ', $unsent ) );
        }
        return $this->sent( $detail );
    }

    





    private function api_missing(): ?Layer_Result {
        $version = (string) $this->calls()->constant( 'NITROPACK_VERSION' );
        if ( version_compare( $version, self::MIN_VERSION, '<' ) ) {
            return $this->skip( 'api_missing', sprintf( 'NitroPack %s is older than %s, whose purge function this tool calls; earlier versions purge only through an AJAX handler, so nothing was called.', $version, self::MIN_VERSION ) );
        }
        if ( ! $this->calls()->function_exists( 'nitropack_sdk_purge' ) ) {
            return $this->helper_missing( 'nitropack_sdk_purge()' );
        }
        return null;
    }

    




    private function no_sdk(): Layer_Result {
        return $this->layer( Layer_Result::FAILED, array( 'detail' => 'NitroPack could not load its SDK for this request\'s address (it is not connected for it), so nothing was purged.' ) );
    }

    






    private function hooked( string $hook, array $classes ): bool {
        return array() !== array_intersect( $classes, $this->listener_classes( $hook ) );
    }

    






    private function listener_classes( string $hook ): array {
        if ( false === $this->calls()->has_action( $hook ) ) {
            return array();
        }
        $filters = $this->calls()->global_value( 'wp_filter' );
        $table   = is_array( $filters ) && isset( $filters[ $hook ] ) ? $filters[ $hook ] : null;
        if ( ! is_object( $table ) || ! isset( $table->callbacks ) || ! is_array( $table->callbacks ) ) {
            return array();
        }
        $classes = array();
        foreach ( $table->callbacks as $callbacks ) {
            foreach ( (array) $callbacks as $callback ) {
                $classes[] = self::class_of( is_array( $callback ) && isset( $callback['function'] ) ? $callback['function'] : null );
            }
        }
        return array_values( array_filter( $classes ) );
    }

    





    private static function class_of( $callable ): string {
        if ( is_string( $callable ) && false !== strpos( $callable, '::' ) ) {
            return strtolower( ltrim( (string) strstr( $callable, '::', true ), '\\' ) );
        }
        if ( is_array( $callable ) && isset( $callable[0] ) ) {
            $owner = is_object( $callable[0] ) ? get_class( $callable[0] ) : (string) $callable[0];
            return strtolower( ltrim( $owner, '\\' ) );
        }
        return '';
    }
}
