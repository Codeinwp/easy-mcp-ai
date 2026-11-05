<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Elementor_Tree {

    public static function elements( array $data ): array {
        $all = array();
        foreach ( $data as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $element['settings'] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
            $all[]               = $element;
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $all = array_merge( $all, self::elements( $element['elements'] ) );
            }
        }
        return $all;
    }

    public static function path( array $data, string $id ): ?array {
        foreach ( $data as $key => $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) && (string) $element['id'] === $id ) {
                return array( $key );
            }
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $below = self::path( $element['elements'], $id );
                if ( null !== $below ) {
                    return array_merge( array( $key ), $below );
                }
            }
        }
        return null;
    }

    public static function at( array $data, array $path ): array {
        $element = array();
        foreach ( $path as $key ) {
            $element = is_array( $data[ $key ] ?? null ) ? $data[ $key ] : array();
            $data    = isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array();
        }
        $element['settings'] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
        $element['elements'] = $data;
        return $element;
    }

    public static function replace( array $data, array $path, array $element ): array {
        $key = array_shift( $path );
        if ( array() === $path ) {
            $data[ $key ] = $element;
            return $data;
        }
        $children                  = isset( $data[ $key ]['elements'] ) && is_array( $data[ $key ]['elements'] ) ? $data[ $key ]['elements'] : array();
        $data[ $key ]['elements'] = self::replace( $children, $path, $element );
        return $data;
    }

    public static function children( array $data, array $path ): array {
        return array_values( array() === $path ? $data : self::at( $data, $path )['elements'] );
    }

    public static function with_children( array $data, array $path, array $children ): array {
        if ( array() === $path ) {
            return $children;
        }
        $parent             = self::at( $data, $path );
        $parent['elements'] = $children;
        return self::replace( $data, $path, $parent );
    }

    public static function ids( array $data ): array {
        $ids = array();
        foreach ( self::elements( $data ) as $element ) {
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
                $ids[ (string) $element['id'] ] = true;
            }
            foreach ( $element['settings'] as $value ) {
                if ( ! is_array( $value ) ) {
                    continue;
                }
                foreach ( $value as $row ) {
                    if ( is_array( $row ) && isset( $row['_id'] ) && is_scalar( $row['_id'] ) ) {
                        $ids[ (string) $row['_id'] ] = true;
                    }
                }
            }
        }
        return $ids;
    }

    public static function new_id( array &$taken ): string {
        do {
            $id = substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
        } while ( isset( $taken[ $id ] ) );
        $taken[ $id ] = true;
        return $id;
    }
}
