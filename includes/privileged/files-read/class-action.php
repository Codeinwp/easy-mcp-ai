<?php
namespace Easy_MCP_AI\Privileged\Files_Read;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Action {

    const ARGUMENTS = array();

    const RESPONSE_BUDGET = 60000;

    protected $resolver;

    public function __construct( Resolver $resolver ) {
        $this->resolver = $resolver;
    }

    abstract public function run( array $arguments ): array;

    protected static function default_dir(): string {
        $stylesheet = function_exists( 'get_stylesheet' ) ? (string) \get_stylesheet() : '';
        return '' === $stylesheet ? 'themes' : 'themes/' . $stylesheet;
    }

    protected static function int_argument( array $arguments, string $key, int $default, int $min, int $max ): int {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return $default;
        }
        $value = $arguments[ $key ];
        if ( is_string( $value ) && preg_match( '/^-?\d+\z/', $value ) ) {
            $value = (int) $value;
        }
        if ( ! is_int( $value ) || $value < $min || $value > $max ) {
            throw new \InvalidArgumentException( sprintf( '%1$s must be a whole number from %2$d to %3$d.', $key, $min, $max ) );
        }
        return $value;
    }

    protected static function string_argument( array $arguments, string $key, string $default ): string {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return $default;
        }
        if ( ! is_string( $arguments[ $key ] ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a string.', $key ) );
        }
        return $arguments[ $key ];
    }

    protected static function bool_argument( array $arguments, string $key, bool $default ): bool {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return $default;
        }
        return function_exists( 'rest_sanitize_boolean' ) ? (bool) \rest_sanitize_boolean( $arguments[ $key ] ) : (bool) $arguments[ $key ];
    }

    protected static function fit_list( array $response, string $key ): array {
        if ( strlen( self::json( $response ) ) <= self::RESPONSE_BUDGET ) {
            return $response;
        }
        $items = array_values( (array) $response[ $key ] );
        $low   = 0;
        $high  = count( $items ) - 1;
        while ( $low < $high ) {
            $mid              = intdiv( $low + $high + 1, 2 );
            $response[ $key ] = array_slice( $items, 0, $mid );
            if ( strlen( self::json( $response ) ) <= self::RESPONSE_BUDGET ) {
                $low = $mid;
            } else {
                $high = $mid - 1;
            }
        }
        $response[ $key ]      = array_slice( $items, 0, $low );
        $response['truncated'] = true;
        return $response;
    }

    public static function json( $value ): string {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $json  = function_exists( 'wp_json_encode' ) ? \wp_json_encode( $value, $flags ) : json_encode( $value, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
        return is_string( $json ) ? $json : '';
    }
}
