<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Argument_Schema_Check {

    const TYPES = array( 'array', 'object', 'string', 'number', 'integer', 'boolean', 'null' );

    const MAX_NODES = 10000;

    const MAX_PASSES = 8;

    const NARROWER_IN_CORE = 'x-easy-mcp-narrower-in-core';

    const SCHEMA_MAPS = array( 'properties', 'patternProperties' );

    const SCHEMA_LISTS = array( 'anyOf', 'oneOf' );

    public static function off_schema( \Easy_MCP_AI\Tools\Base_Tool $tool, array $arguments ): array {
        if ( array() === $arguments || $tool instanceof \Easy_MCP_AI\Tools\Dynamic_Tool ) {
            return array();
        }
        set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- swallowed only while the report-only check reads the schema.
            static function () {
                return true;
            }
        );
        try {
            $schema = $tool->get_input_schema();
        } catch ( \Throwable $e ) {
            return array();
        } finally {
            restore_error_handler();
        }
        return self::check( $arguments, $schema );
    }

    public static function check( array $arguments, $schema ): array {
        if ( ! function_exists( 'rest_validate_value_from_schema' ) || ! function_exists( 'rest_get_best_type_for_value' ) || ! function_exists( 'is_wp_error' ) || ! function_exists( 'wp_is_numeric_array' ) ) {
            return array();
        }
        $found = array();
        set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- swallowed only while the report-only check runs.
            static function () {
                return true;
            }
        );
        try {
            $schema     = $schema instanceof \stdClass ? (array) $schema : $schema;
            $properties = is_array( $schema ) && isset( $schema['properties'] ) ? $schema['properties'] : array();
            $properties = $properties instanceof \stdClass ? (array) $properties : $properties;
            if ( ! is_array( $properties ) ) {
                $properties = array();
            }
            foreach ( $arguments as $name => $value ) {
                if ( null === $value || ! array_key_exists( $name, $properties ) ) {
                    continue;
                }
                try {
                    $budget   = self::MAX_NODES;
                    $copy     = self::plain( $properties[ $name ], $budget );
                    $declared = self::reduce( $copy, null, true );
                    $relaxed  = self::reduce( $copy, null, false );
                    $sent     = self::detached( $value );
                    $decoded  = false;
                    $view     = self::view( $sent, self::closure( array( $relaxed ) ), $decoded );
                    if ( ! $decoded && true === \rest_validate_value_from_schema( $sent, $declared, (string) $name ) ) {
                        continue;
                    }
                    $valid = \rest_validate_value_from_schema( $view, $relaxed, (string) $name );
                    if ( \is_wp_error( $valid ) && $view !== $sent && true === \rest_validate_value_from_schema( $view, $declared, (string) $name ) ) {
                        continue;
                    }
                } catch ( \Throwable $e ) {
                    continue;
                }
                if ( \is_wp_error( $valid ) ) {
                    $found[] = array(
                        'name'    => (string) $name,
                        'code'    => (string) $valid->get_error_code(),
                        'message' => (string) $valid->get_error_message(),
                    );
                }
            }
        } catch ( \Throwable $e ) {
            return array();
        } finally {
            restore_error_handler();
        }
        return $found;
    }

    public static function accepted_as_sent( $schema, $value ): bool {
        if ( ! function_exists( 'rest_validate_value_from_schema' ) || ! function_exists( 'rest_get_best_type_for_value' ) || ! function_exists( 'wp_is_numeric_array' ) ) {
            return true;
        }
        return self::quietly(
            static function () use ( $schema, $value ) {
                $budget = self::MAX_NODES;
                return \rest_validate_value_from_schema( self::detached( $value ), self::reduce( self::plain( $schema, $budget ), null, true ), 'value' );
            }
        );
    }

    public static function quietly( callable $validate ): bool {
        set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- swallowed only while the validator reads the value.
            static function () {
                return true;
            }
        );
        try {
            return true === $validate();
        } catch ( \Throwable $e ) {
            return true;
        } finally {
            restore_error_handler();
        }
    }

    private static function plain( $node, int &$budget, array $path = array() ) {
        if ( $node instanceof \stdClass ) {
            $path = self::enter( $node, $path );
            $node = (array) $node;
        }
        if ( ! is_array( $node ) ) {
            return $node;
        }
        if ( --$budget < 0 ) {
            throw new \LengthException( 'The schema holds more nodes than the check reads.' );
        }
        $out = array();
        foreach ( $node as $key => $child ) {
            $below = self::step( $node, $key, $path );
            if ( in_array( $key, self::SCHEMA_MAPS, true ) || in_array( $key, self::SCHEMA_LISTS, true ) || ( 'items' === $key && self::is_list_of_schemas( $child ) ) ) {
                $out[ $key ] = self::plain_each( $child, $budget, $below );
            } elseif ( 'items' === $key || ( 'additionalProperties' === $key && ( is_array( $child ) || $child instanceof \stdClass ) ) ) {
                $out[ $key ] = self::plain( $child, $budget, $below );
            } else {
                $out[ $key ] = self::data( $child, $below, 'enum' === $key );
            }
        }
        return $out;
    }

    private static function plain_each( $children, int &$budget, array $path ) {
        if ( $children instanceof \stdClass ) {
            $children = (array) $children;
        }
        if ( ! is_array( $children ) ) {
            return null;
        }
        $out = array();
        foreach ( $children as $key => $child ) {
            $out[ $key ] = self::plain( $child, $budget, self::step( $children, $key, $path ) );
        }
        return $out;
    }

    private static function detached( $value, array $path = array() ) {
        if ( is_object( $value ) ) {
            throw new \DomainException( 'The value holds a PHP object.' );
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $out = array();
        foreach ( $value as $key => $item ) {
            $out[ $key ] = self::detached( $item, self::step( $value, $key, $path ) );
        }
        return $out;
    }

    private static function refuse_cycle( $value, array $path ): void {
        if ( is_object( $value ) ) {
            $path  = self::enter( $value, $path );
            $value = (array) $value;
        }
        if ( is_array( $value ) ) {
            foreach ( $value as $key => $item ) {
                self::refuse_cycle( $item, self::step( $value, $key, $path ) );
            }
        }
    }

    private static function step( array $array, $key, array $path ): array {
        $reference = \ReflectionReference::fromArrayElement( $array, $key );
        return null === $reference ? $path : self::mark( 'r' . $reference->getId(), $path );
    }

    private static function enter( object $node, array $path ): array {
        return self::mark( 'o' . spl_object_id( $node ), $path );
    }

    private static function mark( string $id, array $path ): array {
        if ( isset( $path[ $id ] ) ) {
            throw new \LengthException( 'It refers to itself.' );
        }
        $path[ $id ] = true;
        return $path;
    }

    private static function is_list_of_schemas( $items ): bool {
        return is_array( $items ) && array() !== $items && \wp_is_numeric_array( $items );
    }

    private static function data( $value, array $path, bool $objects ) {
        if ( is_object( $value ) && ( $objects || ! $value instanceof \stdClass ) ) {
            self::refuse_cycle( $value, $path );
            return $value;
        }
        if ( $value instanceof \stdClass ) {
            $path  = self::enter( $value, $path );
            $value = (array) $value;
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $out = array();
        foreach ( $value as $key => $item ) {
            $out[ $key ] = self::data( $item, self::step( $value, $key, $path ), $objects );
        }
        return $out;
    }

    private static function reduce( $node, ?array $inherits, bool $strict ): array {
        if ( ! is_array( $node ) ) {
            return array( 'type' => null === $inherits ? self::TYPES : $inherits );
        }
        $declared = array_key_exists( 'type', $node );
        $types    = $declared ? self::declared_types( $node ) : $inherits;
        $groups   = self::branch_groups( $node, $types, $strict );
        self::refuse_unreadable_branches( $groups, null === $types && ! $declared );
        if ( null === $types && ( array() === $groups || $declared ) ) {
            $marker = array() === $groups && self::checked_without_type( $node ) ? array( self::NARROWER_IN_CORE => true ) : array();
            return array( 'type' => self::TYPES ) + $marker + $groups;
        }
        $out = array();
        if ( $strict ) {
            foreach ( $node as $key => $value ) {
                if ( ! in_array( $key, array( 'type', 'enum', 'properties', 'patternProperties', 'items', 'additionalProperties', 'anyOf', 'oneOf' ), true ) ) {
                    $out[ $key ] = $value;
                }
            }
            if ( isset( $node['additionalProperties'] ) && ! is_array( $node['additionalProperties'] ) ) {
                $out['additionalProperties'] = $node['additionalProperties'];
            }
        }
        if ( null !== $types ) {
            $out['type'] = 1 === count( $types ) ? $types[0] : $types;
        }
        $out    += $groups;
        $declare = $strict && self::reads_undeclared( $node );
        foreach ( $strict ? self::SCHEMA_MAPS : array( 'properties' ) as $keyword ) {
            foreach ( isset( $node[ $keyword ] ) && is_array( $node[ $keyword ] ) ? $node[ $keyword ] : array() as $key => $child ) {
                $reduced = self::reduce( $child, null, $strict );
                if ( $declare || ! self::accepts_any( $reduced ) ) {
                    $out[ $keyword ][ $key ] = $reduced;
                }
            }
        }
        $children = array( 'items' => isset( $node['items'] ) ? $node['items'] : null );
        if ( $strict ) {
            $children['additionalProperties'] = isset( $node['additionalProperties'] ) && is_array( $node['additionalProperties'] ) ? $node['additionalProperties'] : null;
        }
        foreach ( $children as $keyword => $child ) {
            if ( is_array( $child ) && ! self::is_list_of_schemas( $child ) ) {
                $reduced = self::reduce( $child, null, $strict );
                if ( ! self::accepts_any( $reduced ) ) {
                    $out[ $keyword ] = $reduced;
                }
            }
        }
        if ( isset( $node['enum'] ) && is_array( $node['enum'] ) && array() !== $node['enum'] && ! self::every_type_below( $out ) ) {
            $out['enum'] = array_values( $node['enum'] );
        }
        return $out;
    }

    private static function checked_without_type( array $node ): bool {
        if ( ( isset( $node['enum'] ) && is_array( $node['enum'] ) && array() !== $node['enum'] ) || isset( $node['format'] ) ) {
            return true;
        }
        return isset( $node['type'] ) && is_array( $node['type'] ) && array() !== array_intersect( self::TYPES, array_filter( $node['type'], 'is_string' ) );
    }

    private static function reads_undeclared( array $node ): bool {
        if ( isset( $node['patternProperties'] ) && is_array( $node['patternProperties'] ) && array() !== $node['patternProperties'] ) {
            return true;
        }
        return isset( $node['additionalProperties'] ) && ( false === $node['additionalProperties'] || is_array( $node['additionalProperties'] ) );
    }

    private static function every_type_below( array $node ): bool {
        $below = self::branches( $node );
        foreach ( self::SCHEMA_MAPS as $keyword ) {
            if ( isset( $node[ $keyword ] ) ) {
                $below = array_merge( $below, array_values( $node[ $keyword ] ) );
            }
        }
        foreach ( array( 'items', 'additionalProperties' ) as $keyword ) {
            if ( isset( $node[ $keyword ] ) && is_array( $node[ $keyword ] ) ) {
                $below[] = $node[ $keyword ];
            }
        }
        foreach ( $below as $schema ) {
            if ( ( isset( $schema['type'] ) && self::TYPES === $schema['type'] ) || self::every_type_below( $schema ) ) {
                return true;
            }
        }
        return false;
    }

    private static function branch_groups( array $node, ?array $types, bool $strict ): array {
        $groups = array();
        foreach ( self::SCHEMA_LISTS as $keyword ) {
            if ( ! isset( $node[ $keyword ] ) || ! is_array( $node[ $keyword ] ) ) {
                continue;
            }
            $read_as = 'oneOf' === $keyword && ! $strict && ! isset( $groups['anyOf'] ) ? 'anyOf' : $keyword;
            foreach ( array_values( $node[ $keyword ] ) as $index => $branch ) {
                $groups[ $read_as ][ $index ] = self::reduce( $branch, $types, $strict );
            }
            if ( ! isset( $groups[ $read_as ] ) ) {
                $groups[ $read_as ] = array();
            }
        }
        return $groups;
    }

    private static function refuse_unreadable_branches( array $groups, bool $takes_type ): void {
        foreach ( $groups as $keyword => $branches ) {
            if ( 'oneOf' !== $keyword && ! $takes_type ) {
                continue;
            }
            foreach ( $branches as $branch ) {
                if ( ! isset( $branch['type'] ) || self::TYPES === $branch['type'] || self::every_type_below( $branch ) ) {
                    throw new \DomainException( 'A branch the check cannot read as WordPress does decides the outcome.' );
                }
            }
        }
    }

    private static function accepts_any( array $node ): bool {
        return array( 'type' => self::TYPES ) === $node;
    }

    private static function declared_types( array $node ): ?array {
        if ( ! isset( $node['type'] ) ) {
            return null;
        }
        $types = is_array( $node['type'] ) ? array_values( $node['type'] ) : array( $node['type'] );
        if ( array() === $types ) {
            return null;
        }
        foreach ( $types as $type ) {
            if ( ! is_string( $type ) || ! in_array( $type, self::TYPES, true ) ) {
                return null;
            }
        }
        return array_values( array_unique( $types ) );
    }

    private static function view( $value, array $schemas, bool &$decoded ) {
        for ( $pass = 0; $pass < self::MAX_PASSES; $pass++ ) {
            $next = self::view_once( $value, $schemas, $decoded );
            if ( $next === $value ) {
                return $value;
            }
            $value = $next;
        }
        throw new \LengthException( 'The value still reads differently.' );
    }

    private static function view_once( $value, array $schemas, bool &$decoded ) {
        $types = self::types_of( $schemas );
        if ( is_string( $value ) ) {
            if ( ! in_array( 'string', $types, true ) && ( in_array( 'array', $types, true ) || in_array( 'object', $types, true ) ) ) {
                $json = json_decode( $value, true );
                if ( is_array( $json ) ) {
                    $decoded = true;
                    return $json;
                }
            }
            return $value;
        }
        if ( ( is_int( $value ) || is_float( $value ) ) && in_array( 'string', $types, true ) && array() === array_intersect( array( 'number', 'integer' ), $types ) ) {
            return (string) $value;
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $list = \wp_is_numeric_array( $value );
        $out  = array();
        foreach ( $value as $key => $child ) {
            list( $properties, $items ) = self::children( $schemas, $value, $list, $key );
            $declared                   = self::closure( array_merge( $properties, $items ) );
            if ( array() === $declared ) {
                $out[ $key ] = $child;
                continue;
            }
            if ( null === $child && array() !== $properties && ! in_array( 'null', self::types_of( $declared ), true ) ) {
                continue;
            }
            $out[ $key ] = self::view_once( $child, $declared, $decoded );
        }
        return $out;
    }

    private static function closure( array $schemas ): array {
        $out = array();
        foreach ( $schemas as $schema ) {
            $out = array_merge( $out, array( $schema ), self::closure( self::branches( $schema ) ) );
        }
        return $out;
    }

    private static function branches( array $schema ): array {
        $out = array();
        foreach ( self::SCHEMA_LISTS as $keyword ) {
            if ( isset( $schema[ $keyword ] ) ) {
                $out = array_merge( $out, $schema[ $keyword ] );
            }
        }
        return $out;
    }

    private static function types_of( array $schemas ): array {
        $types = array();
        foreach ( $schemas as $schema ) {
            if ( isset( $schema['type'] ) ) {
                $types = array_merge( $types, (array) $schema['type'] );
            }
        }
        return array_values( array_unique( $types ) );
    }

    private static function children( array $schemas, array $value, bool $list, $key ): array {
        $properties = array();
        $items      = array();
        foreach ( $schemas as $schema ) {
            $types = isset( $schema['type'] ) ? (array) $schema['type'] : self::types_of( self::branches( $schema ) );
            $type  = null;
            if ( array() !== $types ) {
                $type = 1 === count( $types ) ? $types[0] : \rest_get_best_type_for_value( $value, $types );
            }
            if ( ( null === $type || 'object' === $type ) && isset( $schema['properties'][ $key ] ) ) {
                $properties[] = $schema['properties'][ $key ];
            }
            if ( $list && ( null === $type || 'array' === $type ) && isset( $schema['items'] ) ) {
                $items[] = $schema['items'];
            }
        }
        return array( $properties, $items );
    }
}
