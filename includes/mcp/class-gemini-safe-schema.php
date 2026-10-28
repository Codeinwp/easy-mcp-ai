<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gemini_Safe_Schema {

    public static function sanitize( array $schema ) {
        $map    = array();
        $budget = self::MAX_TOTAL_NODES;
        $safe   = self::walk( $schema, '', $map, self::collect_defs( $schema ), 0, array(), $budget );
        self::warn_about_uncoercible_paths( $map );
        return array( 'schema' => $safe, 'map' => $map );
    }

    private static function is_ambiguous_property_name( $name ) {
        if ( '' === $name ) {
            return true;
        }
        if ( false !== strpos( $name, '.' ) || false !== strpos( $name, '[' ) || false !== strpos( $name, ']' ) ) {
            return true;
        }
        return in_array(
            $name,
            array( 'items', 'not', 'contains', 'propertyNames', 'if', 'then', 'else', 'additionalItems' ),
            true
        );
    }

    public static function is_uncoercible_path( $path ) {
        if ( self::UNCOERCIBLE_PATH === $path ) {
            return true;
        }
        foreach ( explode( '.', $path ) as $segment ) {
            if ( 'items' === $segment ) {
                return true;
            }
            if ( false !== strpos( $segment, '[' ) ) {
                return true;
            }
            if ( in_array( $segment, array( 'not', 'contains', 'propertyNames', 'if', 'then', 'else', 'additionalItems' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    public static function unreachable_paths( array $map ): array {
        $unreachable = array();
        foreach ( $map as $path => $descriptors ) {
            if ( ! self::is_uncoercible_path( (string) $path ) ) {
                continue;
            }
            foreach ( (array) $descriptors as $descriptor ) {
                $kind = is_array( $descriptor ) && isset( $descriptor['kind'] ) ? $descriptor['kind'] : '';
                if ( in_array( $kind, self::CALL_TIME_KINDS, true ) ) {
                    $unreachable[] = (string) $path;
                    break;
                }
            }
        }
        return $unreachable;
    }

    private static function warn_about_uncoercible_paths( array $map ) {
        static $already_logged = array();

        $unreachable = array();
        foreach ( self::unreachable_paths( $map ) as $path ) {
            if ( isset( $already_logged[ $path ] ) ) {
                continue;
            }
            $unreachable[]           = $path;
            $already_logged[ $path ] = true;
        }
        if ( empty( $unreachable ) ) {
            return;
        }
        $readable = array_map(
            static function ( $path ) {
                return self::UNCOERCIBLE_PATH === $path
                    ? '(a property name that cannot be addressed by a dot-path)'
                    : $path;
            },
            $unreachable
        );

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( sprintf(
            'Easy MCP AI: schema transform recorded at %s, which tools/call cannot reverse — the value is advertised converted but reaches the ability unconverted, so the ability may reject it. This covers array-item paths, schema-structure paths (oneOf/anyOf/allOf/not, patternProperties, if/then/else) and property names containing "." or empty. Path(s): %s. See findings A1/A11/A12 in docs/plans/2026-07-22-gemini-schema-compatibility.md.',
            1 === count( $unreachable ) ? 'a path coerce() cannot reach' : 'paths coerce() cannot reach',
            implode( ', ', $readable )
        ) );
    }

    const MAX_WALK_DEPTH = 64;

    const MAX_TOTAL_NODES = 20000;

    private static function is_list_array( $value ) {
        if ( ! is_array( $value ) ) {
            return false;
        }
        if ( array() === $value ) {
            return false;
        }
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    const MAX_REF_HOPS = 16;

    const GEMINI_REJECTED_KEYWORDS = array(
        'patternProperties',
        'multipleOf',
        'uniqueItems',
        'exclusiveMinimum',
        'exclusiveMaximum',
        'dependentSchemas',
        'dependentRequired',
        'if',
        'then',
        'else',
        'contains',
        'minContains',
        'maxContains',
        'propertyNames',
        'prefixItems',
        'additionalItems',
        'unevaluatedProperties',
        'unevaluatedItems',
        'readOnly',
        'writeOnly',
        'deprecated',
        'examples',
        '$comment',
        '$schema',
        '$id',
    );

    const UNCOERCIBLE_PATH = "\0uncoercible";

    private const CALL_TIME_KINDS = array( 'stringify_enum', 'string_union', 'unaddressable_property_name' );

    private static function collect_defs( array $schema ) {
        $defs = array();
        foreach ( array( '$defs', 'definitions' ) as $key ) {
            if ( isset( $schema[ $key ] ) && is_array( $schema[ $key ] ) ) {
                foreach ( $schema[ $key ] as $name => $sub ) {
                    if ( is_array( $sub ) ) {
                        $defs[ $key . '/' . $name ] = $sub;
                    }
                }
            }
        }
        return $defs;
    }

    private static function resolve_ref( $ref, array $defs ) {
        foreach ( array( '#/$defs/', '#/definitions/' ) as $prefix ) {
            if ( 0 === strpos( $ref, $prefix ) ) {
                $name = substr( $ref, strlen( $prefix ) );
                $key  = ( '#/$defs/' === $prefix ? '$defs/' : 'definitions/' ) . $name;
                return isset( $defs[ $key ] ) ? $defs[ $key ] : null;
            }
        }
        return null;
    }

    private static function summarize_unwalkable( $node ) {
        if ( ! is_array( $node ) ) {
            return $node;
        }

        $safe = array();

        if ( isset( $node['type'] ) ) {
            $type = $node['type'];
            if ( is_array( $type ) ) {
                foreach ( $type as $candidate ) {
                    if ( 'null' === $candidate ) {
                        $safe['nullable'] = true;
                    } elseif ( is_string( $candidate ) && ! isset( $safe['type'] ) ) {
                        $safe['type'] = $candidate;
                    }
                }
            } elseif ( is_string( $type ) ) {
                $safe['type'] = $type;
            }
        }

        if ( isset( $node['description'] ) && is_string( $node['description'] ) ) {
            $safe['description'] = $node['description'];
        }
        if ( isset( $node['nullable'] ) ) {
            $safe['nullable'] = (bool) $node['nullable'];
        }

        if ( isset( $safe['type'] ) && 'array' === $safe['type'] ) {
            $safe['items'] = new \stdClass();
        }

        return array() === $safe ? new \stdClass() : $safe;
    }

    private static function walk( $node, $path, array &$map, array $defs, $depth, array $ancestors, &$budget ) {
        if ( ! is_array( $node ) ) {
            return $node;
        }

        if ( $depth > self::MAX_WALK_DEPTH || --$budget < 0 ) {
            return self::summarize_unwalkable( $node );
        }

        $ref_unresolved = false;

        $ref_hops = 0;
        while ( isset( $node['$ref'] ) && is_string( $node['$ref'] ) && $ref_hops++ < self::MAX_REF_HOPS ) {
            $rest = $node;
            unset( $rest['$ref'] );
            if ( in_array( $node['$ref'], $ancestors, true ) ) {
                $node           = $rest;
                $ref_unresolved = true;
                break;
            }
            $resolved = self::resolve_ref( $node['$ref'], $defs );
            if ( null === $resolved ) {
                $node           = $rest;
                $ref_unresolved = true;
                break;
            }
            $ancestors[] = $node['$ref'];
            $node        = array_merge( $resolved, $rest );
        }
        if ( isset( $node['$ref'] ) ) {
            $ref_unresolved = true;
        }
        unset( $node['$ref'] );

        unset( $node['$defs'], $node['definitions'] );

        if ( array_key_exists( 'additionalProperties', $node ) ) {
            $ap = $node['additionalProperties'];
            if ( false === $ap ) {
                $allowed_keys = isset( $node['properties'] ) && is_array( $node['properties'] )
                    ? array_keys( $node['properties'] )
                    : array();

                $has_dynamic_keys = $ref_unresolved;
                foreach ( array( 'patternProperties', 'dependentSchemas', 'allOf', 'anyOf', 'oneOf' ) as $key_source ) {
                    if ( isset( $node[ $key_source ] ) && is_array( $node[ $key_source ] ) && array() !== $node[ $key_source ] ) {
                        $has_dynamic_keys = true;
                        break;
                    }
                }

                if ( ! $has_dynamic_keys ) {
                    $map[ $path ][] = array( 'kind' => 'drop_additional_properties', 'allowed_keys' => $allowed_keys );
                }
            }
            unset( $node['additionalProperties'] );
        }

        if ( isset( $node['type'] ) && is_array( $node['type'] ) ) {
            $types = array_values( array_filter(
                array_values( $node['type'] ),
                static function ( $t ) {
                    return is_string( $t );
                }
            ) );
            if ( array() === $types ) {
                $node['type'] = 'string';
            } elseif ( in_array( 'null', $types, true ) ) {
                $non_null = array_values( array_diff( $types, array( 'null' ) ) );
                $node['type']     = isset( $non_null[0] ) ? $non_null[0] : 'string';
                $node['nullable'] = true;
                $map[ $path ][]   = array( 'kind' => 'nullable_union' );
            } else {
                $node['type'] = 'string';
                if ( ! in_array( 'string', $types, true ) ) {
                    $map[ $path ][] = array( 'kind' => 'string_union', 'original_types' => $types );
                }
            }
        }

        if ( array_key_exists( 'const', $node ) ) {
            $const_value = $node['const'];
            unset( $node['const'] );

            $representable = false;
            if ( is_scalar( $const_value ) ) {
                $stringified   = is_string( $const_value ) ? $const_value : (string) $const_value;
                $representable = '' !== $stringified;
            }

            if ( $representable ) {
                $node['enum'] = array( $const_value );
            } elseif ( isset( $node['enum'] ) ) {
                unset( $node['enum'] );
            }
        }

        if ( isset( $node['enum'] ) && is_array( $node['enum'] ) ) {
            $original     = array_values( $node['enum'] );
            $needs_stringify = false;
            $new_enum     = array();
            foreach ( $original as $member ) {
                if ( ! is_scalar( $member ) && ! ( is_object( $member ) && method_exists( $member, '__toString' ) ) ) {
                    continue;
                }
                $stringified = is_string( $member ) ? $member : (string) $member;
                if ( '' === $stringified ) {
                    continue;
                }
                if ( ! is_string( $member ) ) {
                    $needs_stringify = true;
                }
                $new_enum[] = $stringified;
            }
            $node['enum'] = array_values( $new_enum );
            if ( $needs_stringify ) {
                $map[ $path ][] = array( 'kind' => 'stringify_enum', 'original_members' => $original );
                if ( isset( $node['type'] ) && in_array( $node['type'], array( 'integer', 'number', 'boolean' ), true ) ) {
                    $node['type'] = 'string';
                }
            }
        }

        $lifted = array();
        if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
            foreach ( $node['properties'] as $name => $sub ) {
                if ( is_array( $sub ) && isset( $sub['required'] ) && true === $sub['required'] ) {
                    $lifted[] = (string) $name;
                }
            }
        }

        if ( isset( $node['properties'] ) && is_array( $node['properties'] ) ) {
            foreach ( $node['properties'] as $name => $sub ) {
                $ambiguous = self::is_ambiguous_property_name( (string) $name );

                if ( $ambiguous ) {
                    $discard = array();
                    $node['properties'][ $name ] = self::walk( $sub, self::UNCOERCIBLE_PATH, $discard, $defs, $depth + 1, $ancestors, $budget );
                    if ( array() !== $discard ) {
                        $map[ self::UNCOERCIBLE_PATH ][] = array(
                            'kind'     => 'unaddressable_property_name',
                            'property' => (string) $name,
                        );
                    }
                    continue;
                }

                $child_path = '' === $path ? (string) $name : $path . '.' . $name;
                $node['properties'][ $name ] = self::walk( $sub, $child_path, $map, $defs, $depth + 1, $ancestors, $budget );
            }
        }

        if ( array_key_exists( 'required', $node ) || array() !== $lifted ) {
            $declared = array();
            if ( isset( $node['required'] ) && is_array( $node['required'] ) ) {
                foreach ( $node['required'] as $member ) {
                    if ( is_string( $member ) ) {
                        $declared[] = $member;
                    }
                }
            }
            $names = array_values( array_unique( array_merge( $declared, $lifted ) ) );
            if ( ! ( isset( $node['required'] ) && $node['required'] === $names ) ) {
                if ( array() === $names ) {
                    unset( $node['required'] );
                } else {
                    $node['required'] = $names;
                }
            }
        }

        if ( ! array_key_exists( 'items', $node )
            && isset( $node['type'] ) && 'array' === $node['type'] ) {
            $node['items'] = new \stdClass();
        }

        if ( isset( $node['items'] ) ) {
            $items_path = '' === $path ? 'items' : $path . '.items';
            if ( self::is_list_array( $node['items'] ) ) {
                foreach ( $node['items'] as $i => $sub ) {
                    if ( ! is_array( $sub ) ) {
                        continue;
                    }
                    self::walk( $sub, $items_path . '[' . $i . ']', $map, $defs, $depth + 1, $ancestors, $budget );
                }
                $node['items'] = (object) array();
            } else {
                $node['items'] = self::walk( $node['items'], $items_path, $map, $defs, $depth + 1, $ancestors, $budget );
            }
        }

        foreach ( array( 'oneOf', 'anyOf', 'allOf', 'prefixItems' ) as $combinator ) {
            if ( isset( $node[ $combinator ] ) && is_array( $node[ $combinator ] ) ) {
                foreach ( $node[ $combinator ] as $i => $sub ) {
                    $sub_path = ( '' === $path ? $combinator : $path . '.' . $combinator ) . '[' . $i . ']';
                    $node[ $combinator ][ $i ] = self::walk( $sub, $sub_path, $map, $defs, $depth + 1, $ancestors, $budget );
                }
            }
        }

        foreach ( array( 'not', 'contains', 'propertyNames', 'if', 'then', 'else', 'additionalItems' ) as $sub_key ) {
            if ( isset( $node[ $sub_key ] ) && is_array( $node[ $sub_key ] ) ) {
                $node[ $sub_key ] = self::walk(
                    $node[ $sub_key ],
                    ( '' === $path ? $sub_key : $path . '.' . $sub_key ),
                    $map,
                    $defs,
                    $depth + 1,
                    $ancestors,
                    $budget
                );
            }
        }

        foreach ( array( 'patternProperties', 'dependentSchemas' ) as $map_key ) {
            if ( isset( $node[ $map_key ] ) && is_array( $node[ $map_key ] ) ) {
                foreach ( $node[ $map_key ] as $name => $sub ) {
                    if ( ! is_array( $sub ) ) {
                        continue;
                    }
                    $sub_path = ( '' === $path ? $map_key : $path . '.' . $map_key ) . '[' . $name . ']';
                    $node[ $map_key ][ $name ] = self::walk( $sub, $sub_path, $map, $defs, $depth + 1, $ancestors, $budget );
                }
            }
        }

        foreach ( self::GEMINI_REJECTED_KEYWORDS as $rejected ) {
            unset( $node[ $rejected ] );
        }

        if ( array() === $node ) {
            return new \stdClass();
        }

        return $node;
    }

    public static function coerce( array $arguments, array $map ) {
        foreach ( $map as $path => $transforms ) {
            if ( self::is_uncoercible_path( (string) $path ) ) {
                continue;
            }
            foreach ( $transforms as $transform ) {
                if ( 'drop_additional_properties' === $transform['kind'] ) {
                    $arguments = self::apply_at_path( $arguments, $path, function ( $value ) use ( $transform ) {
                        if ( ! is_array( $value ) ) {
                            return $value;
                        }
                        return array_intersect_key( $value, array_flip( $transform['allowed_keys'] ) );
                    } );
                }
            }
        }

        foreach ( $map as $path => $transforms ) {
            if ( self::is_uncoercible_path( (string) $path ) ) {
                continue;
            }
            foreach ( $transforms as $transform ) {
                switch ( $transform['kind'] ) {
                    case 'nullable_union':
                        break;

                    case 'string_union':
                        $arguments = self::apply_at_path( $arguments, $path, function ( $value ) use ( $transform ) {
                            return self::coerce_string_union( $value, $transform['original_types'] );
                        } );
                        break;

                    case 'stringify_enum':
                        $arguments = self::apply_at_path( $arguments, $path, function ( $value ) use ( $transform ) {
                            return self::coerce_stringify_enum( $value, $transform['original_members'] );
                        } );
                        break;
                }
            }
        }

        return $arguments;
    }

    private static function apply_at_path( array $arguments, $path, callable $fn ) {
        if ( '' === $path ) {
            $result = $fn( $arguments );
            return is_array( $result ) ? $result : $arguments;
        }
        $segments = explode( '.', $path );
        $cursor   = &$arguments;
        foreach ( $segments as $i => $segment ) {
            if ( ! is_array( $cursor ) || ! array_key_exists( $segment, $cursor ) ) {
                return $arguments;
            }
            if ( $i === count( $segments ) - 1 ) {
                $cursor[ $segment ] = $fn( $cursor[ $segment ] );
                break;
            }
            $cursor = &$cursor[ $segment ];
        }
        return $arguments;
    }

    private static function coerce_string_union( $value, array $original_types ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }
        if ( in_array( 'array', $original_types, true ) || in_array( 'object', $original_types, true ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                return $decoded;
            }
        }
        if ( in_array( 'boolean', $original_types, true ) && ( 'true' === $value || 'false' === $value ) ) {
            return 'true' === $value;
        }
        if ( ! is_numeric( $value ) ) {
            return $value;
        }
        $looks_like_integer = ( false === strpos( $value, '.' ) )
            && ( false === stripos( $value, 'e' ) )
            && ctype_digit( ltrim( $value, '-' ) );

        if ( $looks_like_integer && in_array( 'integer', $original_types, true ) ) {
            return (int) $value;
        }
        if ( in_array( 'number', $original_types, true ) || in_array( 'float', $original_types, true ) ) {
            return (float) $value;
        }
        return $value;
    }

    private static function coerce_stringify_enum( $value, array $original_members ) {
        if ( ! is_string( $value ) ) {
            return $value;
        }
        foreach ( $original_members as $member ) {
            if ( ! is_string( $member ) && (string) $member === $value ) {
                return $member;
            }
        }
        return $value;
    }

    public static function conform( $result, array $map ) {
        if ( ! is_array( $result ) || empty( $map ) ) {
            return $result;
        }

        foreach ( $map as $path => $transforms ) {
            if ( self::is_uncoercible_path( (string) $path ) ) {
                continue;
            }
            foreach ( $transforms as $transform ) {
                switch ( $transform['kind'] ) {
                    case 'stringify_enum':
                        $result = self::apply_at_path( $result, $path, function ( $value ) use ( $transform ) {
                            return self::conform_stringify_enum( $value, $transform['original_members'] );
                        } );
                        break;

                    case 'string_union':
                        $result = self::apply_at_path( $result, $path, function ( $value ) {
                            return self::conform_string_union( $value );
                        } );
                        break;
                }
            }
        }

        return $result;
    }

    private static function conform_stringify_enum( $value, array $original_members ) {
        if ( is_string( $value ) || is_array( $value ) || is_object( $value ) || null === $value ) {
            return $value;
        }
        foreach ( $original_members as $member ) {
            if ( ! is_string( $member ) && $member === $value ) {
                return is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;
            }
        }
        return $value;
    }

    private static function conform_string_union( $value ) {
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }
        return $value;
    }
}
