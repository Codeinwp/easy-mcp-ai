<?php

namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Vendor_Abilities {

    const PREFIX = '';

    const VENDOR = '';

    const CLASSES = array( Learnpress_Abilities::class, Elementor_Abilities::class, Breakdance_Abilities::class );

    const TICKS_SETTLED_OPTION = 'easy_mcp_ai_vendor_ability_ticks_settled';

    private static $ours = false;

    private static $failed = array();

    public static function serve( bool $ours = true ): void {
        self::$ours = $ours;
    }

    public static function served(): bool {
        return self::$ours;
    }

    public static function is_our_rest_route( string $route ): bool {
        return 1 === preg_match( '#^/?easy-mcp-ai(-admin)?/v1(/|$)#i', $route );
    }

    public static function vendor_of( string $prefix ): ?string {
        foreach ( self::CLASSES as $class ) {
            if ( $class::PREFIX === $prefix . '/' ) {
                return $class::VENDOR;
            }
        }
        return null;
    }

    public static function settle_ticks(): void {
        if ( \get_option( self::TICKS_SETTLED_OPTION ) ) {
            return;
        }
        $enabled = \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        if ( is_array( $enabled ) && count( self::untick( $enabled ) ) !== count( $enabled ) ) {
            $settled = false;
            \Easy_MCP_AI\Tools\Tool_Policy::locked( static function () use ( &$settled ) {
                $current = \Easy_MCP_AI\Config::read_uncached( 'easy_mcp_ai_enabled_abilities' );
                $keep    = is_array( $current ) ? self::untick( $current ) : array();
                if ( ! is_array( $current ) || count( $keep ) === count( $current ) ) {
                    $settled = true;
                    return;
                }
                \update_option( 'easy_mcp_ai_enabled_abilities', $keep );
                $settled = \Easy_MCP_AI\Config::read_uncached( 'easy_mcp_ai_enabled_abilities' ) === $keep;
            } );
            if ( ! $settled ) {
                return;
            }
        }
        \update_option( self::TICKS_SETTLED_OPTION, 1 );
    }

    private static function untick( array $enabled ): array {
        $keep = array();
        foreach ( $enabled as $slug ) {
            $class = is_string( $slug ) ? self::class_of( $slug ) : null;
            if ( null === $class || $class::ran_before_switches_dropped() ) {
                $keep[] = $slug;
            }
        }
        return $keep;
    }

    abstract protected static function ran_before_switches_dropped(): bool;

    private static function class_of( string $slug ): ?string {
        foreach ( self::CLASSES as $class ) {
            if ( 0 === strpos( $slug, $class::PREFIX ) ) {
                return $class;
            }
        }
        return null;
    }

    public static function reset(): void {
        self::$ours = false;
        self::$failed = array();
    }

    protected static function active(): bool {
        return self::$ours && ! isset( self::$failed[ static::class ] );
    }

    protected static function any_registered(): bool {
        foreach ( (array) wp_get_abilities() as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
            if ( 0 === strpos( $name, static::PREFIX ) && static::from_own_registration( $ability ) ) {
                return true;
            }
        }
        return false;
    }

    protected static function from_own_registration( object $ability ): bool {
        unset( $ability );
        return true;
    }

    protected static function run( callable $registration, string $what ): void {
        try {
            $registration();
        } catch ( \Throwable $e ) {
            self::$failed[ static::class ] = true;
            error_log( sprintf( 'Easy MCP AI: %s %s failed, %s abilities not registered: %s', static::VENDOR, $what, static::VENDOR, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: a vendor change must be visible
        }
    }

    protected static function has_method( string $class, string $method, bool $static ): bool {
        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            return false;
        }
        $reflection = new \ReflectionMethod( $class, $method );
        return $reflection->isPublic() && $static === $reflection->isStatic() && 0 === $reflection->getNumberOfRequiredParameters();
    }
}
