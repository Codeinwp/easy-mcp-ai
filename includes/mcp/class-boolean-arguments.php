<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Boolean_Arguments {

    public static function normalize( array $arguments, array $schema ): array {
        $normalized = self::at( $arguments, $schema );
        return is_array( $normalized ) ? $normalized : $arguments;
    }

    private static function at( $value, $node ) {
        if ( ! is_array( $node ) ) {
            return $value;
        }
        if ( isset( $node['type'] ) && 'boolean' === $node['type'] ) {
            return is_string( $value ) ? self::boolean( $value ) : $value;
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
            foreach ( $node['properties'] as $name => $property ) {
                if ( array_key_exists( $name, $value ) ) {
                    $value[ $name ] = self::at( $value[ $name ], $property );
                }
            }
        }
        if ( isset( $node['items'] ) && is_array( $node['items'] ) && self::is_list( $value ) ) {
            foreach ( $value as $index => $item ) {
                $value[ $index ] = self::at( $item, $node['items'] );
            }
        }
        return $value;
    }

    private static function boolean( string $value ) {
        if ( '' === trim( $value ) ) {
            return $value;
        }
        $flag = filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
        return null === $flag ? $value : $flag;
    }

    private static function is_list( array $value ): bool {
        $expected = 0;
        foreach ( $value as $key => $unused ) {
            if ( $key !== $expected ) {
                return false;
            }
            ++$expected;
        }
        return true;
    }
}
