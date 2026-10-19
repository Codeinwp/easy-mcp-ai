<?php






namespace Easy_MCP_AI\Rest_Routes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








final class Route_Catalogue {

    
    const MAX_MATCHES = 25;

    
    const MAX_ROUTES = 300;

    
    const MAX_DESCRIPTION = 200;

    








    public static function display( string $pattern ): string {
        $out    = '';
        $length = strlen( $pattern );
        $i      = 0;
        while ( $i < $length ) {
            if ( '(' === $pattern[ $i ] && '?P<' === substr( $pattern, $i + 1, 3 ) ) {
                $close = strpos( $pattern, '>', $i + 4 );
                $end   = self::group_end( $pattern, $i );
                if ( false !== $close && null !== $end ) {
                    $name     = substr( $pattern, $i + 4, $close - $i - 4 );
                    $optional = $end + 1 < $length && '?' === $pattern[ $end + 1 ];
                    $out     .= '{' . $name . ( $optional ? '?' : '' ) . '}';
                    $i        = $end + ( $optional ? 2 : 1 );
                    continue;
                }
            }
            $out .= $pattern[ $i ];
            ++$i;
        }
        return $out;
    }

    






    private static function group_end( string $pattern, int $start ): ?int {
        $depth    = 0;
        $in_class = false;
        $length   = strlen( $pattern );
        for ( $i = $start; $i < $length; $i++ ) {
            $char = $pattern[ $i ];
            if ( '\\' === $char ) {
                ++$i;
                continue;
            }
            if ( $in_class ) {
                $in_class = ']' !== $char;
                continue;
            }
            if ( '[' === $char ) {
                $in_class = true;
            } elseif ( '(' === $char ) {
                ++$depth;
            } elseif ( ')' === $char && 0 === --$depth ) {
                return $i;
            }
        }
        return null;
    }

    





    public static function namespaces( $server ): array {
        $list = array();
        foreach ( (array) $server->get_namespaces() as $namespace ) {
            $namespace = (string) $namespace;
            if ( '' === $namespace || in_array( Namespace_State::normalize( $namespace ), Route_Gate::OWN_NAMESPACES, true ) ) {
                continue;
            }
            $list[] = array(
                'namespace' => $namespace,
                'routes'    => count( self::listed_routes( $server, $namespace ) ),
            );
        }
        usort(
            $list,
            static function ( $a, $b ) {
                return strcmp( $a['namespace'], $b['namespace'] );
            }
        );
        return $list;
    }

    






    public static function find_namespace( $server, string $namespace ): ?string {
        $wanted = Namespace_State::normalize( $namespace );
        foreach ( (array) $server->get_namespaces() as $registered ) {
            if ( Namespace_State::normalize( (string) $registered ) === $wanted ) {
                return (string) $registered;
            }
        }
        return null;
    }

    







    public static function routes_in( $server, string $namespace ): array {
        $routes = array();
        foreach ( self::listed_routes( $server, $namespace ) as $pattern => $handlers ) {
            $entry   = array(
                'route'   => self::display( (string) $pattern ),
                'methods' => self::methods_of( (array) $handlers ),
            );
            $refusal = Route_Gate::refusal( 'GET', array( 'route' => (string) $pattern, 'namespace' => $namespace ), array() );
            if ( null !== $refusal ) {
                $entry['refused'] = $refusal;
            }
            $routes[] = $entry;
        }
        return array(
            'routes'    => array_slice( $routes, 0, self::MAX_ROUTES ),
            'truncated' => count( $routes ) > self::MAX_ROUTES,
        );
    }

    









    public static function route_detail( $server, string $route, bool $include_fields ): ?array {
        $pattern = self::find_pattern( $server, $route );
        if ( null === $pattern ) {
            return null;
        }
        $routes    = (array) $server->get_routes();
        $namespace = Route_Gate::namespace_of( $server, $pattern );
        $endpoints = array();
        foreach ( (array) $routes[ $pattern ] as $handler ) {
            $endpoints[] = array(
                'methods' => self::methods_of( array( $handler ) ),
                'args'    => self::args_of( isset( $handler['args'] ) && is_array( $handler['args'] ) ? $handler['args'] : array() ),
            );
        }
        $detail  = array(
            'route'     => self::display( $pattern ),
            'pattern'   => $pattern,
            'namespace' => $namespace,
            'endpoints' => $endpoints,
        );
        $refusal = Route_Gate::refusal( 'GET', array( 'route' => $pattern, 'namespace' => $namespace ), array() );
        if ( null !== $refusal ) {
            $detail['refused'] = $refusal;
        }
        if ( $include_fields ) {
            $fields = self::response_fields( $server, $pattern );
            if ( null !== $fields ) {
                $detail['response_fields'] = $fields;
            }
        }
        return $detail;
    }

    








    public static function search( $server, string $search, array $namespaces ): array {
        $terms = array_values( array_filter( preg_split( '/\s+/', strtolower( trim( $search ) ) ) ) );
        $ranked = array( array(), array() );
        foreach ( $namespaces as $namespace ) {
            foreach ( self::listed_routes( $server, $namespace ) as $pattern => $handlers ) {
                $display = self::display( (string) $pattern );
                $path    = strtolower( $namespace . ' ' . $display );
                $text    = $path . ' ' . strtolower( self::argument_text( (array) $handlers ) );
                if ( ! self::contains_all( $text, $terms ) ) {
                    continue;
                }
                $in_path = self::contains_all( $path, $terms );
                $ranked[ $in_path ? 0 : 1 ][] = array(
                    'route'      => $display,
                    'namespace'  => $namespace,
                    'methods'    => self::methods_of( (array) $handlers ),
                    'matched_in' => $in_path ? 'route' : 'arguments',
                );
            }
        }
        $matches = array_merge( $ranked[0], $ranked[1] );
        return array(
            'matches'   => array_slice( $matches, 0, self::MAX_MATCHES ),
            'truncated' => count( $matches ) > self::MAX_MATCHES,
        );
    }

    










    private static function listed_routes( $server, string $namespace ): array {
        $routes = (array) $server->get_routes( $namespace );
        unset( $routes[ '/' . $namespace ] );
        return $routes;
    }

    






    private static function find_pattern( $server, string $route ): ?string {
        $routes = (array) $server->get_routes();
        $wanted = strtolower( rtrim( trim( $route ), '/' ) );
        foreach ( array_keys( $routes ) as $pattern ) {
            if ( strtolower( (string) $pattern ) === $wanted || strtolower( self::display( (string) $pattern ) ) === $wanted ) {
                return (string) $pattern;
            }
        }
        foreach ( array_keys( $routes ) as $pattern ) {
            if ( '' !== $wanted && preg_match( '@^' . $pattern . '$@i', $wanted ) ) {
                return (string) $pattern;
            }
        }
        return null;
    }

    





    private static function methods_of( array $handlers ): array {
        $methods = array();
        foreach ( $handlers as $handler ) {
            if ( isset( $handler['methods'] ) && is_array( $handler['methods'] ) ) {
                $methods = array_merge( $methods, array_keys( array_filter( $handler['methods'] ) ) );
            }
        }
        $methods = array_values( array_unique( array_map( 'strval', $methods ) ) );
        sort( $methods );
        return $methods;
    }

    





    private static function args_of( array $args ): array {
        $out = array();
        foreach ( $args as $name => $options ) {
            if ( ! is_array( $options ) ) {
                continue;
            }
            $entry = array();
            foreach ( array( 'type', 'format' ) as $key ) {
                if ( isset( $options[ $key ] ) && ( is_string( $options[ $key ] ) || is_array( $options[ $key ] ) ) ) {
                    $entry[ $key ] = $options[ $key ];
                }
            }
            if ( ! empty( $options['required'] ) ) {
                $entry['required'] = true;
            }
            if ( isset( $options['enum'] ) && is_array( $options['enum'] ) ) {
                $entry['enum'] = array_slice( array_values( $options['enum'] ), 0, 30 );
            }
            if ( array_key_exists( 'default', $options ) && ( is_scalar( $options['default'] ) || null === $options['default'] ) ) {
                $entry['default'] = $options['default'];
            }
            if ( isset( $options['description'] ) && is_string( $options['description'] ) && '' !== $options['description'] ) {
                $entry['description'] = self::shorten( $options['description'] );
            }
            $out[ (string) $name ] = $entry;
        }
        return $out;
    }

    





    private static function argument_text( array $handlers ): string {
        $parts = array();
        foreach ( $handlers as $handler ) {
            foreach ( (array) ( $handler['args'] ?? array() ) as $name => $options ) {
                $parts[] = (string) $name;
                if ( is_array( $options ) && isset( $options['description'] ) && is_string( $options['description'] ) ) {
                    $parts[] = $options['description'];
                }
            }
        }
        return implode( ' ', $parts );
    }

    






    private static function response_fields( $server, string $pattern ): ?array {
        $options = $server->get_route_options( $pattern );
        if ( ! is_array( $options ) || ! isset( $options['schema'] ) || ! is_callable( $options['schema'] ) ) {
            return null;
        }
        try {
            $schema = call_user_func( $options['schema'] );
        } catch ( \Throwable $e ) {
            return null;
        }
        if ( ! is_array( $schema ) || ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
            return null;
        }
        $fields = array();
        foreach ( $schema['properties'] as $name => $property ) {
            $type            = is_array( $property ) && isset( $property['type'] ) ? $property['type'] : '';
            $fields[ $name ] = is_array( $type ) ? implode( '|', array_map( 'strval', $type ) ) : (string) $type;
        }
        return $fields;
    }

    




    private static function contains_all( string $text, array $terms ): bool {
        foreach ( $terms as $term ) {
            if ( false === strpos( $text, $term ) ) {
                return false;
            }
        }
        return true;
    }

    



    private static function shorten( string $text ): string {
        $text = trim( preg_replace( '/\s+/', ' ', $text ) );
        return strlen( $text ) > self::MAX_DESCRIPTION ? substr( $text, 0, self::MAX_DESCRIPTION - 1 ) . '…' : $text;
    }
}
