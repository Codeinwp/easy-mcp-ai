<?php

namespace Easy_MCP_AI\Rest_Routes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Route_Gate {

    const OWN_NAMESPACES = array( 'easy-mcp-ai/v1', 'easy-mcp-ai-admin/v1' );

    const MAX_ROUTE_LENGTH = 2048;

    const CALL_TOOL_METHODS = array(
        'wp_rest_read'   => 'GET',
        'wp_rest_write'  => 'POST',
        'wp_rest_delete' => 'DELETE',
    );

    public static function request_summary( string $tool_name, array $arguments ): ?string {
        if ( ! isset( self::CALL_TOOL_METHODS[ $tool_name ] ) ) {
            return null;
        }
        try {
            $path = self::normalize_path( $arguments['route'] ?? null );
        } catch ( \InvalidArgumentException $e ) {
            return null;
        }
        $method = self::CALL_TOOL_METHODS[ $tool_name ];
        if ( 'POST' === $method && isset( $arguments['method'] ) && is_string( $arguments['method'] ) && in_array( strtoupper( $arguments['method'] ), array( 'PUT', 'PATCH' ), true ) ) {
            $method = strtoupper( $arguments['method'] );
        }
        return $method . ' ' . $path;
    }

    public static function normalize_path( $route ): string {
        if ( ! is_string( $route ) || '' === trim( $route, ' ' ) ) {
            throw new \InvalidArgumentException( 'route is required: a REST path such as /redirection/v1/redirect.' );
        }
        $route = trim( $route, ' ' );
        if ( strlen( $route ) > self::MAX_ROUTE_LENGTH ) {
            throw new \InvalidArgumentException( 'route is longer than ' . self::MAX_ROUTE_LENGTH . ' characters.' );
        }
        if ( '/' !== $route[0] ) {
            throw new \InvalidArgumentException( 'route must start with /, e.g. /redirection/v1/redirect (not a full URL).' );
        }
        if ( preg_match( '/[?#\s\x00-\x1F\x7F]/', $route ) ) {
            throw new \InvalidArgumentException( 'route must be a path only: put query parameters in `query`, not after a ?.' );
        }
        $route = (string) preg_replace( '#/{2,}#', '/', $route );
        $route = rtrim( $route, '/' );
        return '' === $route ? '/' : $route;
    }

    public static function resolve( $server, string $method, string $path ): array {
        $routes  = self::candidate_routes( $server, $path );
        $allowed = array();
        foreach ( $routes as $route => $handlers ) {
            if ( ! preg_match( '@^' . $route . '$@i', $path, $matches ) ) {
                continue;
            }
            foreach ( (array) $handlers as $handler ) {
                $methods = isset( $handler['methods'] ) && is_array( $handler['methods'] ) ? $handler['methods'] : array();
                if ( empty( $methods[ $method ] ) ) {
                    $allowed = array_merge( $allowed, array_keys( array_filter( $methods ) ) );
                    continue;
                }
                $params = array();
                foreach ( $matches as $name => $value ) {
                    if ( ! is_int( $name ) ) {
                        $params[ $name ] = $value;
                    }
                }
                return array(
                    'route'      => (string) $route,
                    'namespace'  => self::namespace_of( $server, (string) $route ),
                    'handler'    => $handler,
                    'url_params' => $params,
                );
            }
        }
        if ( ! empty( $allowed ) ) {
            $allowed = array_values( array_unique( $allowed ) );
            sort( $allowed );
            throw new \RuntimeException( sprintf( '%s is not accepted on %s; this route accepts %s.', $method, $path, implode( ', ', $allowed ) ) );
        }
        throw new \RuntimeException( sprintf( 'No REST route matches %s. Use wp_rest_routes to list the routes of a namespace.', $path ) );
    }

    public static function namespace_of( $server, string $route ): string {
        $options = $server->get_route_options( $route );
        return is_array( $options ) && isset( $options['namespace'] ) && is_string( $options['namespace'] ) ? $options['namespace'] : '';
    }

    private static function candidate_routes( $server, string $path ): array {
        $prefix = rtrim( ltrim( $path, '/' ), '/' ) . '/';
        $sets   = array();
        foreach ( (array) $server->get_namespaces() as $namespace ) {
            if ( '' !== $namespace && 0 === strpos( $prefix, (string) $namespace ) ) {
                $sets[] = (array) $server->get_routes( $namespace );
            }
        }
        return empty( $sets ) ? (array) $server->get_routes() : array_merge( ...$sets );
    }

    public static function parameter_conflict( array $url_params, array $params ): ?string {
        foreach ( $url_params as $name => $value ) {
            if ( ! array_key_exists( $name, $params ) ) {
                continue;
            }
            $sent = $params[ $name ];
            if ( ! is_scalar( $sent ) || (string) $sent !== (string) $value ) {
                return sprintf( '%1$s is set by the route itself (%2$s); remove %1$s from query and body, or change it in the route.', $name, $value );
            }
        }
        return null;
    }

    public static function refusal( string $method, array $resolved, array $params ): ?string {
        $namespace = Namespace_State::normalize( $resolved['namespace'] );
        $route     = strtolower( $resolved['route'] );
        if ( '' === $namespace ) {
            return 'This route belongs to no namespace (the REST index or the batch route), so it cannot be called through these tools.';
        }
        if ( in_array( $namespace, self::OWN_NAMESPACES, true ) ) {
            return 'Easy MCP AI\'s own REST routes cannot be called through these tools.';
        }
        if ( 'wp-abilities/v1' === $namespace && preg_match( '#/run$#', $route ) ) {
            return 'Abilities run through their own wp_ability_* tools, which apply the site\'s ability switches; the abilities run route cannot be called here.';
        }
        if ( 'wp/v2' === $namespace && false !== strpos( $route, '/application-passwords' ) ) {
            return 'Application Password routes cannot be called through these tools: a new password would outlive this key and bypass its tool list.';
        }
        if ( 'wp/v2' === $namespace && 'GET' !== $method && '/wp/v2/settings' === $route ) {
            $blocked = array_values( array_intersect( array( 'url', 'email' ), array_keys( $params ) ) );
            if ( ! empty( $blocked ) ) {
                return sprintf( 'The site address (url) and admin email (email) cannot be changed through these tools; remove %s from the request.', implode( ' and ', $blocked ) );
            }
        }
        return null;
    }
}
