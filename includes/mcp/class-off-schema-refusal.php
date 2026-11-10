<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Off_Schema_Refusal {

    const KINDS = array( 'boolean', 'type', 'enum', 'nested' );

    const FILTER = 'easy_mcp_ai_refuse_off_schema_argument';

    const TYPE_WORDS = array(
        'integer' => 'an integer',
        'number'  => 'a number',
        'string'  => 'text',
        'array'   => 'a list',
        'object'  => 'an object',
    );

    private const NUMERIC_SPACE = " \t\n\r\v\f";

    const OUTSIDE_SERVICE_CATEGORIES = array( 'ga', 'gsc', 'dfs', 'semrush', 'seranking', 'ahrefs' );

    const ENFORCED = array( 'boolean', 'type', 'enum' );

    private static $enforced = self::ENFORCED;

    private static $routes = null;

    public static function classify( \Easy_MCP_AI\Tools\Base_Tool $tool, array $arguments, array $findings ): array {
        if ( array() === $findings ) {
            return array();
        }
        list( $properties, $required ) = self::declared( $tool );
        try {
            $targets = $tool->get_argument_targets( $arguments );
        } catch ( \Throwable $e ) {
            $properties = array();
            $targets    = array();
        }
        $outside = in_array( $tool->get_category(), self::OUTSIDE_SERVICE_CATEGORIES, true );
        $out        = array();
        foreach ( $findings as $finding ) {
            $name     = $finding['name'];
            $property = is_array( $properties ) && isset( $properties[ $name ] ) ? $properties[ $name ] : null;
            $value    = array_key_exists( $name, $arguments ) ? $arguments[ $name ] : null;
            $kind     = self::kind( $property, $value, in_array( $name, $required, true ), $outside, isset( $targets[ $name ] ) ? $targets[ $name ] : null );

            $finding['refuse'] = $kind;
            $out[]             = $finding;
        }
        return $out;
    }

    public static function refused( string $tool_name, array $classified ): array {
        $out = array();
        foreach ( $classified as $finding ) {
            if ( null === $finding['refuse'] || ! in_array( $finding['refuse'], self::$enforced, true ) ) {
                continue;
            }
            if ( false === apply_filters( self::FILTER, true, $tool_name, $finding['name'], $finding['refuse'] ) ) {
                continue;
            }
            $out[] = $finding;
        }
        return $out;
    }

    public static function refusal( \Easy_MCP_AI\Tools\Base_Tool $tool, array $refused, array $arguments ): Detailed_Tool_Error {
        $tool_name          = $tool->get_name();
        list( $properties ) = self::declared( $tool );
        $ending             = ' Nothing was changed.';
        $reasons            = array();
        foreach ( $refused as $finding ) {
            $reasons[] = self::reason( $finding, $arguments, isset( $properties[ $finding['name'] ] ) ? $properties[ $finding['name'] ] : null );
        }
        list( $format, $parts, $index, $whole ) = $reasons[0];
        $message                                = Refusal_Text::fit( $format . $ending, $parts, $index );
        if ( 1 === count( $reasons ) && $whole . $ending === $message ) {
            return new Detailed_Tool_Error( $message, '' );
        }
        $header = sprintf(
            '%1$s refused %2$s outside its schema (%3$s). Call it again with values the schema accepts:',
            $tool_name,
            1 === count( $reasons ) ? 'this value' : 'these values',
            implode( ', ', array_column( $refused, 'name' ) )
        );
        $share = intdiv( Detailed_Tool_Error::MAX_DETAILS - strlen( $header ), count( $reasons ) ) - 3;
        $lines = array();
        foreach ( $reasons as $reason ) {
            $lines[] = Refusal_Text::fit( '%s', array( $reason[3] ), 0, $share );
        }
        return new Detailed_Tool_Error( $message, $header . "\n- " . implode( "\n- ", $lines ) );
    }

    private static function reason( array $finding, array $arguments, $property ): array {
        $name     = $finding['name'];
        $shown    = self::shown( $name, $arguments );
        $property = $property instanceof \stdClass ? (array) $property : $property;
        $type     = is_array( $property ) && isset( $property['type'] ) && is_string( $property['type'] ) ? $property['type'] : null;
        if ( 'boolean' === $finding['refuse'] ) {
            return self::sentence( '%1$s must be true or false, not %2$s.', array( $name, $shown ), 1 );
        }
        $listed = is_array( $property ) && isset( $property['enum'] ) && is_array( $property['enum'] ) && array() !== $property['enum'];
        if ( $listed && in_array( $finding['refuse'], array( 'type', 'enum' ), true ) ) {
            $reason       = self::sentence( '%1$s must be one of %2$s, not %3$s.', array( $name, self::listed( $property['enum'] ), $shown ), 1 );
            $reason[1][2] = Refusal_Text::fit( '%s', array( $shown ), 0, 80 );
            return $reason;
        }
        if ( 'type' === $finding['refuse'] && null !== $type && isset( self::TYPE_WORDS[ $type ] ) ) {
            return self::sentence( '%1$s must be %2$s, not %3$s.', array( $name, self::TYPE_WORDS[ $type ], $shown ), 2 );
        }
        return self::sentence( '%s', array( $finding['message'] ), 0 );
    }

    private static function sentence( string $format, array $parts, int $index ): array {
        return array( $format, $parts, $index, vsprintf( $format, $parts ) );
    }

    private static function listed( array $enum ): string {
        $words = array();
        foreach ( $enum as $member ) {
            $words[] = is_string( $member ) ? $member : (string) wp_json_encode( $member );
        }
        $last = (string) array_pop( $words );
        return array() === $words ? $last : implode( ', ', $words ) . ' or ' . $last;
    }

    private static function declared( \Easy_MCP_AI\Tools\Base_Tool $tool ): array {
        try {
            $schema = $tool->get_input_schema();
        } catch ( \Throwable $e ) {
            return array( array(), array() );
        }
        $schema     = $schema instanceof \stdClass ? (array) $schema : $schema;
        $properties = is_array( $schema ) && isset( $schema['properties'] ) ? $schema['properties'] : array();
        $properties = $properties instanceof \stdClass ? (array) $properties : $properties;
        $required   = is_array( $schema ) && isset( $schema['required'] ) && is_array( $schema['required'] ) ? array_values( $schema['required'] ) : array();
        return array( is_array( $properties ) ? $properties : array(), $required );
    }

    private static function shown( string $name, array $arguments ): string {
        $value = array_key_exists( $name, $arguments ) ? $arguments[ $name ] : null;
        $shown = wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION );
        if ( is_string( $shown ) && Server::sanitize_error_message( $shown, PHP_INT_MAX ) === $shown ) {
            return $shown;
        }
        if ( is_string( $value ) ) {
            return 'that text';
        }
        if ( is_array( $value ) ) {
            return wp_is_numeric_array( $value ) ? 'that list' : 'that object';
        }
        return gettype( $value );
    }

    private static function kind( $property, $value, bool $required, bool $outside, $target ): ?string {
        $property = $property instanceof \stdClass ? (array) $property : $property;
        if ( ! is_array( $property ) ) {
            return null;
        }
        if ( ! $required && self::is_empty( $value ) ) {
            return null;
        }
        if ( Argument_Schema_Check::accepted_as_sent( $property, $value ) ) {
            return null;
        }
        $type = isset( $property['type'] ) && is_string( $property['type'] ) ? $property['type'] : null;
        if ( 'array' === $type && is_array( $value ) && ! wp_is_numeric_array( $value ) ) {
            $value = array_values( $value );
            if ( Argument_Schema_Check::accepted_as_sent( $property, $value ) ) {
                return null;
            }
        }
        if ( ( 'integer' === $type || 'number' === $type ) && is_string( $value ) && is_numeric( trim( $value, self::NUMERIC_SPACE ) ) ) {
            $value = trim( $value, self::NUMERIC_SPACE );
            if ( Argument_Schema_Check::accepted_as_sent( $property, $value ) ) {
                return null;
            }
        }
        if ( 'boolean' === $type ) {
            return is_float( $value ) && in_array( $value, array( 0.0, 1.0 ), true ) ? null : 'boolean';
        }
        if ( 'string' === $type && ( is_int( $value ) || is_float( $value ) ) ) {
            $value = (string) $value;
        }
        if ( null !== $type && in_array( $type, Argument_Schema_Check::TYPES, true ) && ! self::fits( $value, array( 'type' => $type ) ) ) {
            $kind = 'type';
        } elseif ( isset( $property['enum'] ) && is_array( $property['enum'] ) && is_scalar( $value ) ) {
            if ( self::matches_loosely( $value, $property['enum'] ) ) {
                return null;
            }
            $kind = 'enum';
        } else {
            $kind = 'nested';
        }
        if ( $outside ) {
            return null;
        }
        return self::target_refuses( $target, $kind, $value ) ? $kind : null;
    }

    private static function is_empty( $value ): bool {
        return is_string( $value ) ? '' === trim( $value ) || '0' === $value : empty( $value );
    }

    private static function matches_loosely( $value, array $enum ): bool {
        if ( ! is_string( $value ) ) {
            return false;
        }
        $wanted = array_map(
            static function ( $reading ) {
                return strtolower( trim( $reading ) );
            },
            self::readings( $value )
        );
        foreach ( $enum as $member ) {
            if ( is_string( $member ) && in_array( strtolower( trim( $member ) ), $wanted, true ) ) {
                return true;
            }
        }
        return false;
    }

    private static function readings( $value ): array {
        if ( ! is_string( $value ) ) {
            return array( $value );
        }
        return array_values( array_unique( array( $value, strtolower( trim( $value ) ), trim( sanitize_text_field( $value ) ), sanitize_key( $value ) ) ) );
    }

    private static function target_refuses( $target, string $kind, $value ): bool {
        if ( 'own' === $target ) {
            return true;
        }
        if ( 'own_type' === $target ) {
            return 'type' === $kind;
        }
        if ( ! is_array( $target ) || ! isset( $target['method'], $target['route'], $target['param'] ) ) {
            return false;
        }
        $schema = self::route_parameter( (string) $target['method'], (string) $target['route'], (string) $target['param'] );
        if ( null === $schema ) {
            return false;
        }
        foreach ( self::readings( $value ) as $reading ) {
            if ( self::fits( $reading, $schema, (string) $target['param'] ) ) {
                return false;
            }
        }
        return true;
    }

    private static function route_parameter( string $method, string $route, string $param ): ?array {
        $routes = self::$routes;
        if ( null === $routes ) {
            if ( ! function_exists( 'rest_get_server' ) || ! did_action( 'rest_api_init' ) ) {
                return null;
            }
            $routes = rest_get_server()->get_routes();
        }
        if ( ! isset( $routes[ $route ] ) || ! is_array( $routes[ $route ] ) ) {
            return null;
        }
        $arg = self::served_parameter( $routes[ $route ], $method, $param );
        return null !== $arg && self::schema_enforced( $arg ) ? $arg : null;
    }

    public static function served_parameter( array $endpoints, string $method, string $param ): ?array {
        $method = strtoupper( $method );
        foreach ( $endpoints as $endpoint ) {
            if ( ! is_array( $endpoint ) ) {
                continue;
            }
            $checked = 'HEAD' === $method && empty( $endpoint['methods']['HEAD'] ) ? 'GET' : $method;
            if ( empty( $endpoint['methods'][ $checked ] ) ) {
                continue;
            }
            return isset( $endpoint['args'][ $param ] ) && is_array( $endpoint['args'][ $param ] ) ? $endpoint['args'][ $param ] : null;
        }
        return null;
    }

    public static function schema_enforced( array $arg ): bool {
        if ( isset( $arg['validate_callback'] ) && 'rest_validate_request_arg' === $arg['validate_callback'] ) {
            return true;
        }
        if ( array_key_exists( 'sanitize_callback', $arg ) ) {
            return 'rest_parse_request_arg' === $arg['sanitize_callback'];
        }
        return ! empty( $arg['type'] );
    }

    private static function fits( $value, array $schema, string $param = 'value' ): bool {
        return Argument_Schema_Check::quietly(
            static function () use ( $value, $schema, $param ) {
                return \rest_validate_value_from_schema( $value, $schema, $param );
            }
        );
    }
}
