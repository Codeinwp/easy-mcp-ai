<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tool_State {

    const OVERRIDE_NAME  = 'EASY_MCP_AI_TOOLS';
    const ENABLED_OPTION = 'easy_mcp_ai_enabled_tools';
    const STRICT_ENTRY = '*';

    private static $parsed = null;

    private static function override_value() {
        if ( defined( self::OVERRIDE_NAME ) ) {
            return array( constant( self::OVERRIDE_NAME ), 'constant' );
        }
        $env = getenv( self::OVERRIDE_NAME );
        if ( false !== $env ) {
            return array( $env, 'environment' );
        }
        return array( null, '' );
    }

    public static function override_source() {
        return self::override_value()[1];
    }

    private static function parsed() {
        $raw = self::override_value()[0];
        if ( null === self::$parsed || self::$parsed[0] !== $raw ) {
            self::$parsed = array( $raw, self::parse( $raw ) );
        }
        return self::$parsed[1];
    }

    public static function overrides() {
        $map = self::parsed();
        unset( $map[ self::STRICT_ENTRY ] );
        return $map;
    }

    public static function is_strict() {
        return array_key_exists( self::STRICT_ENTRY, self::parsed() );
    }

    public static function override_of( $name ) {
        $overrides = self::overrides();
        if ( isset( $overrides[ $name ] ) ) {
            return $overrides[ $name ];
        }
        return self::is_strict() ? false : null;
    }

    public static function parse( $raw ) {
        if ( is_string( $raw ) ) {
            $raw = preg_split( '/[,\r\n]+/', $raw );
        }
        if ( ! is_array( $raw ) ) {
            return array();
        }
        $map = array();
        foreach ( $raw as $key => $entry ) {
            if ( is_string( $key ) ) {
                $name  = trim( $key );
                $value = $entry;
            } elseif ( is_string( $entry ) ) {
                $parts = explode( '=', $entry, 2 );
                $name  = trim( $parts[0] );
                $value = isset( $parts[1] ) ? $parts[1] : null;
            } else {
                continue;
            }
            if ( '' === $name ) {
                continue;
            }
            $on           = self::is_on( $value );
            $map[ $name ] = isset( $map[ $name ] ) ? $map[ $name ] && $on : $on;
        }
        return $map;
    }

    private static function is_on( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }
        return is_string( $value ) && 'on' === strtolower( trim( $value ) );
    }

    public static function disabled_list() {
        return array_values( array_filter( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() ), 'is_string' ) );
    }

    public static function enabled_list() {
        if ( \Easy_MCP_AI\Config::is_locked( 'easy_mcp_ai_disabled_tools' ) ) {
            return array();
        }
        return self::stored_enabled_list();
    }

    public static function stored_enabled_list() {
        $stored = \get_option( self::ENABLED_OPTION, array() );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        $names = array();
        foreach ( $stored as $name ) {
            if ( is_string( $name ) && '' !== $name && ! in_array( $name, $names, true ) ) {
                $names[] = $name;
            }
        }
        return $names;
    }

    public static function resolve( $name, $default_off ) {
        $override = self::override_of( $name );
        if ( null !== $override ) {
            return array( $override, 'override' );
        }
        if ( in_array( $name, self::disabled_list(), true ) ) {
            return array( false, 'disabled' );
        }
        if ( ! $default_off ) {
            return array( true, 'default' );
        }
        if ( in_array( $name, self::enabled_list(), true ) ) {
            return array( true, 'enabled' );
        }
        return array( false, 'default' );
    }

    public static function resolve_tool( Base_Tool $tool ) {
        return self::resolve( (string) $tool->get_name(), (bool) $tool->is_disabled_by_default() );
    }

    public static function is_enabled( Base_Tool $tool ) {
        return self::resolve_tool( $tool )[0];
    }

    public static function off_names( $registry ) {
        $off = array();
        if ( ! is_object( $registry ) || ! method_exists( $registry, 'get_all_tool_names' ) || ! method_exists( $registry, 'get_tool' ) ) {
            return self::disabled_list();
        }
        foreach ( (array) $registry->get_all_tool_names() as $name ) {
            $tool = $registry->get_tool( $name );
            if ( $tool instanceof Base_Tool ? ! self::is_enabled( $tool ) : ! self::resolve( (string) $name, false )[0] ) {
                $off[] = (string) $name;
            }
        }
        return $off;
    }

    public static function stored_off( $name, $default_off ) {
        if ( in_array( $name, self::disabled_list(), true ) ) {
            return true;
        }
        return $default_off && ! in_array( $name, self::enabled_list(), true );
    }

    public static function apply_choices( array $names, array $off, array $default_off, array $disabled, array $enabled ) {
        foreach ( $names as $name ) {
            if ( null !== self::override_of( $name ) ) {
                continue;
            }
            $was_off  = in_array( $name, $disabled, true );
            $disabled = array_values( array_diff( $disabled, array( $name ) ) );
            $enabled  = array_values( array_diff( $enabled, array( $name ) ) );
            $default  = ! empty( $default_off[ $name ] );
            if ( in_array( $name, $off, true ) ) {
                if ( ! $default || $was_off ) {
                    $disabled[] = $name;
                }
            } elseif ( $default ) {
                $enabled[] = $name;
            }
        }
        return array( $disabled, $enabled );
    }
}
