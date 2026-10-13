<?php






namespace Easy_MCP_AI\Rest_Routes;

use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../tools/class-tool-state.php';




















final class Namespace_State {

    
    const OPTION = 'easy_mcp_ai_rest_disabled_namespaces';

    
    const OVERRIDE_NAME = 'EASY_MCP_AI_REST_NAMESPACES';

    
    const STRICT_ENTRY = '*';

    





    public static function normalize( string $namespace ): string {
        return strtolower( trim( trim( $namespace ), '/' ) );
    }

    




    public static function override_source(): string {
        if ( defined( self::OVERRIDE_NAME ) ) {
            return 'constant';
        }
        return false !== getenv( self::OVERRIDE_NAME ) ? 'environment' : '';
    }

    




    private static function parsed(): array {
        $source = self::override_source();
        if ( '' === $source ) {
            return array();
        }
        $raw = 'constant' === $source ? constant( self::OVERRIDE_NAME ) : getenv( self::OVERRIDE_NAME );
        $map = array();
        foreach ( Tool_State::parse( $raw ) as $name => $on ) {
            $key         = self::STRICT_ENTRY === $name ? self::STRICT_ENTRY : self::normalize( (string) $name );
            $map[ $key ] = isset( $map[ $key ] ) ? $map[ $key ] && $on : $on;
        }
        return $map;
    }

    






    public static function override_of( string $namespace ): ?bool {
        $map = self::parsed();
        $key = self::normalize( $namespace );
        if ( isset( $map[ $key ] ) ) {
            return $map[ $key ];
        }
        return isset( $map[ self::STRICT_ENTRY ] ) ? false : null;
    }

    





    public static function stored_off(): ?array {
        $stored = \get_option( self::OPTION, array() );
        if ( ! is_array( $stored ) ) {
            return null;
        }
        $off = array();
        foreach ( $stored as $entry ) {
            if ( ! is_string( $entry ) ) {
                return null;
            }
            $entry = self::normalize( $entry );
            if ( '' !== $entry ) {
                $off[] = $entry;
            }
        }
        return array_values( array_unique( $off ) );
    }

    





    public static function is_enabled( string $namespace ): bool {
        $override = self::override_of( $namespace );
        if ( null !== $override ) {
            return $override;
        }
        $off = self::stored_off();
        return null !== $off && ! in_array( self::normalize( $namespace ), $off, true );
    }

    






    public static function off_by( string $namespace ): string {
        return null !== self::override_of( $namespace ) ? 'constant' : 'option';
    }

    








    public static function apply_choices( array $off, array $on ): bool {
        $current = self::stored_off();
        $current = null === $current ? array() : $current;
        foreach ( $on as $namespace ) {
            $key = self::normalize( (string) $namespace );
            if ( null === self::override_of( $key ) ) {
                $current = array_values( array_diff( $current, array( $key ) ) );
            }
        }
        foreach ( $off as $namespace ) {
            $key = self::normalize( (string) $namespace );
            if ( '' !== $key && null === self::override_of( $key ) ) {
                $current[] = $key;
            }
        }
        $current = array_values( array_unique( $current ) );
        sort( $current );
        return \update_option( self::OPTION, $current, false );
    }
}
