<?php






namespace Easy_MCP_AI\Cache\Adapters;

use Easy_MCP_AI\Cache\Cache_Adapter;
use Easy_MCP_AI\Cache\Layer_Result;
use Easy_MCP_AI\Cache\Purge_Request;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/class-cache-adapter.php';



























final class Optimole extends Cache_Adapter {

    const SETTINGS_CLASS = 'Optml_Settings';

    
    const SETTINGS_METHODS = array( 'clear_cache', 'use_cdn' );

    
    const SUPPRESS_FILTER = 'optml_dont_trigger_settings_updated';

    
    const SUPPRESS_SINCE = '3.10.0';

    public function id(): string {
        return 'optimole';
    }

    public function label(): string {
        return 'Optimole';
    }

    public function kind(): string {
        return self::KIND_ASSET;
    }

    public function tier(): int {
        return self::TIER_ASSET;
    }

    public function scopes(): array {
        return array( Purge_Request::SCOPE_SITE );
    }

    public function is_present(): bool {
        $calls = $this->calls();
        return $calls->defined( 'OPTML_VERSION' ) && $calls->class_exists( self::SETTINGS_CLASS );
    }

    public function also_clears( Purge_Request $request ): array {
        $version = $this->calls()->constant( 'OPTML_VERSION' );
        if ( is_string( $version ) && version_compare( $version, self::SUPPRESS_SINCE, '>=' ) ) {
            return array();
        }
        return array( self::ALSO_OBJECT_CACHE );
    }

    public function reaches_network( Purge_Request $request ): bool {
        $calls = $this->calls();
        return $calls->defined( 'OPTIML_ENABLED_MU' ) && self::to_boolean( $calls->constant( 'OPTIML_ENABLED_MU' ) )
            && $calls->defined( 'OPTIML_MU_SITE_ID' ) && (bool) $calls->constant( 'OPTIML_MU_SITE_ID' );
    }

    protected function purge_site( Purge_Request $request ): Layer_Result {
        $settings = $this->settings();
        if ( null === $settings ) {
            return $this->helper_missing( 'Optml_Settings::clear_cache()' );
        }
        $calls    = $this->calls();
        $types    = $calls->call( array( $settings, 'use_cdn' ) ) ? array( 'images', 'assets' ) : array( 'images' );
        $refused  = null;
        $done     = array();
        $suppress = static function (): bool {
            return true;
        };
        $calls->add_filter( self::SUPPRESS_FILTER, $suppress, PHP_INT_MAX );
        try {
            foreach ( $types as $type ) {
                $token = $calls->call( array( $settings, 'clear_cache' ), array( $type ) );
                if ( $token instanceof \WP_Error ) {
                    $refused = null === $refused ? $token : $refused;
                    continue;
                }
                $done[] = 'assets' === $type ? 'CSS/JS files' : 'images';
            }
        } finally {
            $calls->remove_filter( self::SUPPRESS_FILTER, $suppress, PHP_INT_MAX );
        }
        $result = $this->outcome( $refused, sprintf( 'Optimole issued new cache tokens for its %s.', implode( ' and ', $done ) ) );
        if ( null !== $refused && array() !== $done ) {
            $result = $result->with( array( 'detail' => sprintf( '%s Its %s were refreshed.', (string) $result->get( 'detail' ), implode( ' and ', $done ) ) ) );
        }
        return $result;
    }

    





    private function settings(): ?object {
        $calls = $this->calls();
        if ( ! $calls->class_exists( self::SETTINGS_CLASS ) ) {
            return null;
        }
        $settings = $calls->instantiate( self::SETTINGS_CLASS );
        foreach ( self::SETTINGS_METHODS as $method ) {
            if ( ! $calls->method_exists( $settings, $method ) ) {
                return null;
            }
        }
        return $settings;
    }

    






    private static function to_boolean( $value ): bool {
        if ( in_array( $value, array( 'yes', 'enabled', 'true', '1' ), true ) ) {
            return true;
        }
        if ( in_array( $value, array( 'no', 'disabled', 'false', '0' ), true ) ) {
            return false;
        }
        return (bool) $value;
    }
}
