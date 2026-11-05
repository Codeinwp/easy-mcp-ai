<?php
namespace Easy_MCP_AI\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Preview_Values {

    const SHORT_MAX = 200;

    const MASK = '[redacted]';

    const SECRET_NAMES = array( 'user_activation_key', 'user_pass' );

    const RISKY = array( 'capabilities', 'email', 'password', 'role', 'roles', 'status', 'user_email', 'user_pass' );

    public static function inline( $value ): string {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( null === $value ) {
            return 'null';
        }
        if ( is_scalar( $value ) ) {
            return (string) $value;
        }
        $json = \wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        return is_string( $json ) ? $json : '…';
    }

    public static function full( $value ): string {
        if ( is_array( $value ) || is_object( $value ) ) {
            $json = \wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
            return is_string( $json ) ? $json : '…';
        }
        return self::inline( $value );
    }

    public static function is_long( $value ): bool {
        return self::length( self::inline( $value ) ) > self::SHORT_MAX;
    }

    public static function size( $value ): string {
        return '[' . number_format( self::length( self::inline( $value ) ) ) . ' characters]';
    }

    public static function shown( $value ): string {
        return self::is_long( $value ) ? self::size( $value ) : self::inline( $value );
    }

    public static function mask( array $values, array $top_level = array() ): array {
        foreach ( $values as $key => $value ) {
            if ( in_array( $key, $top_level, true ) || ( is_string( $key ) && self::is_secret_name( $key ) ) ) {
                $values[ $key ] = self::MASK;
            } elseif ( is_array( $value ) ) {
                $values[ $key ] = self::mask( $value );
            }
        }
        return $values;
    }

    public static function before( $value ): array {
        if ( ! is_array( $value ) ) {
            return array( 'before' => self::full( $value ) );
        }
        $masked = self::mask( $value );
        $before = array( 'before' => self::full( $masked ) );
        if ( $masked !== $value ) {
            $before['before_hidden'] = hash_hmac( 'sha256', self::full( $value ), \wp_salt( 'auth' ) );
        }
        return $before;
    }

    private static function is_secret_name( string $key ): bool {
        return 1 === preg_match( \Easy_MCP_AI\MCP\Server::SENSITIVE_ARG_PATTERN, $key ) || in_array( strtolower( $key ), self::SECRET_NAMES, true );
    }

    public static function split( array $values ): array {
        $short = array();
        $long  = array();
        foreach ( $values as $key => $value ) {
            if ( self::is_long( $value ) ) {
                $long[ $key ] = $value;
            } else {
                $short[ $key ] = $value;
            }
        }
        return array( $short, $long );
    }

    public static function long_details( array $values, string $prefix = '' ): array {
        $details = array();
        foreach ( self::split( $values )[1] as $key => $value ) {
            $details[] = array(
                'label' => $prefix . $key,
                'after' => self::full( $value ),
            );
        }
        return $details;
    }

    public static function risk_note( array $values ): string {
        $parts = array();
        foreach ( $values as $key => $value ) {
            if ( ! is_string( $key ) || ! in_array( strtolower( $key ), self::RISKY, true ) ) {
                continue;
            }
            $parts[] = self::MASK === $value ? $key . ' (hidden)' : $key . ' to ' . ( is_string( $value ) && ! self::is_long( $value ) ? '"' . $value . '"' : self::shown( $value ) );
        }
        return empty( $parts ) ? '' : 'Changes ' . implode( '; ', $parts ) . '.';
    }

    public static function is_list( array $value ): bool {
        return array() === $value || array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    private static function length( string $text ): int {
        return function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
    }
}
