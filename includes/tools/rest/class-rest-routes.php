<?php
namespace Easy_MCP_AI\Tools\Rest;

use Easy_MCP_AI\Rest_Routes\Namespace_State;
use Easy_MCP_AI\Rest_Routes\Route_Catalogue;
use Easy_MCP_AI\Rest_Routes\Route_Gate;
use Easy_MCP_AI\Tools\Rest_Route_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Routes extends Rest_Route_Tool {

    const CALL_TOOLS = array(
        'wp_rest_read'   => array( 'GET' ),
        'wp_rest_write'  => array( 'POST', 'PUT', 'PATCH' ),
        'wp_rest_delete' => array( 'DELETE' ),
    );

    const LIFECYCLE_ROUTES = array(
        '/wp/v2/plugins' => array( 'wp_install_plugin', 'wp_delete_plugin', 'wp_uninstall_plugin' ),
        '/wp/v2/themes'  => array( 'wp_install_theme', 'wp_delete_theme' ),
    );

    public function get_name() {
        return 'wp_rest_routes';
    }

    public function get_description() {
        return 'Lists the WordPress REST routes plugins and core register, for wp_rest_read, wp_rest_write and wp_rest_delete. No arguments: every namespace (e.g. "redirection/v1", "wc/v3", "contact-form-7/v1") with its route count and the tools this key can use on it. `namespace`: that namespace\'s routes and methods. `route` (e.g. "/redirection/v1/redirect" or "/wp/v2/posts/{id}"): that route\'s arguments per method; add `include_fields: true` for the fields its responses carry. `search` (e.g. "redirect"): routes whose path, arguments or descriptions contain every word, at most 25.';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'namespace'      => array(
                    'type'        => 'string',
                    'description' => 'List this namespace\'s routes, e.g. "wc/v3".',
                ),
                'route'          => array(
                    'type'        => 'string',
                    'description' => 'Show one route\'s arguments: its display form ("/wp/v2/posts/{id}"), its pattern, or a concrete path ("/wp/v2/posts/12").',
                ),
                'search'         => array(
                    'type'        => 'string',
                    'description' => 'Words to find in route paths, argument names and descriptions, e.g. "shipping zone".',
                ),
                'include_fields' => array(
                    'type'        => 'boolean',
                    'description' => 'With `route`: also list the top-level fields its responses carry.',
                ),
            ),
        );
    }

    protected function method_for( array $arguments ): string {
        return 'GET';
    }

    public function precheck( array $arguments ): void {
    }

    public function execute( array $arguments ) {
        $server = \rest_get_server();
        if ( isset( $arguments['route'] ) && is_string( $arguments['route'] ) && '' !== trim( $arguments['route'] ) ) {
            return $this->describe_route( $server, $arguments['route'], ! empty( $arguments['include_fields'] ) );
        }
        if ( isset( $arguments['search'] ) && is_string( $arguments['search'] ) && '' !== trim( $arguments['search'] ) ) {
            $usable = array();
            foreach ( Route_Catalogue::namespaces( $server ) as $entry ) {
                if ( ! empty( $this->tools_on( $entry['namespace'] ) ) ) {
                    $usable[] = $entry['namespace'];
                }
            }
            $result = array( 'search' => trim( $arguments['search'] ) ) + Route_Catalogue::search( $server, $arguments['search'], $usable );
            $note   = self::switched_off_note( $result['matches'] );
            return '' === $note ? $result : $result + array( 'note' => $note );
        }
        if ( isset( $arguments['namespace'] ) && is_string( $arguments['namespace'] ) && '' !== trim( $arguments['namespace'] ) ) {
            return $this->describe_namespace( $server, $arguments['namespace'] );
        }
        $namespaces = array();
        foreach ( Route_Catalogue::namespaces( $server ) as $entry ) {
            $entry['tools'] = $this->tools_on( $entry['namespace'] );
            $namespaces[]   = $entry;
        }
        return array(
            'namespaces' => $namespaces,
            'next'       => 'Pass `namespace` to list its routes, `route` for one route\'s arguments, or `search`.',
        );
    }

    private function describe_namespace( $server, string $namespace ): array {
        $registered = Route_Catalogue::find_namespace( $server, $namespace );
        if ( null === $registered || in_array( Namespace_State::normalize( $registered ), Route_Gate::OWN_NAMESPACES, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'No REST namespace "%s" is registered. Call wp_rest_routes with no arguments to list them.', $namespace ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $result = array(
            'namespace' => $registered,
            'tools'     => $this->tools_on( $registered ),
        );
        if ( empty( $result['tools'] ) ) {
            $result['unavailable'] = $this->unavailable_reason( $registered );
        }
        return $result + Route_Catalogue::routes_in( $server, $registered );
    }

    private function describe_route( $server, string $route, bool $include_fields ): array {
        $detail = Route_Catalogue::route_detail( $server, $route, $include_fields );
        if ( null === $detail || in_array( Namespace_State::normalize( (string) $detail['namespace'] ), Route_Gate::OWN_NAMESPACES, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'No REST route matches "%s". Use `search` or `namespace` to find it.', $route ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $detail['tools'] = isset( $detail['refused'] ) ? array() : $this->tools_on( (string) $detail['namespace'] );
        return $detail;
    }

    private function tools_on( string $namespace ): array {
        if ( '' === $namespace || ! Namespace_State::is_enabled( $namespace ) ) {
            return array();
        }
        $tools = array();
        foreach ( array_keys( self::CALL_TOOLS ) as $tool ) {
            if ( self::caller_may_use( $tool ) ) {
                $tools[] = $tool;
            }
        }
        return $tools;
    }

    private function unavailable_reason( string $namespace ): string {
        $refusal = $this->namespace_refusal( $namespace );
        if ( null !== $refusal ) {
            return $refusal;
        }
        return 'This key cannot use wp_rest_read, wp_rest_write or wp_rest_delete here: each is switched off on the site or outside this key\'s tools.';
    }

    private static function switched_off_note( array $matches ): string {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_tool_status' ) ) {
            return '';
        }
        $tools = array();
        foreach ( $matches as $match ) {
            $route = isset( $match['route'] ) ? (string) $match['route'] : '';
            foreach ( self::LIFECYCLE_ROUTES as $base => $names ) {
                if ( $route === $base || 0 === strpos( $route, $base . '/' ) ) {
                    $tools = array_merge( $tools, $names );
                }
            }
        }
        $off    = array();
        $locked = array();
        foreach ( array_unique( $tools ) as $name ) {
            $status = \Easy_MCP_AI\MCP\Server::current_call_tool_status( $name );
            if ( null !== $status && 'off' === $status['status'] ) {
                $off[] = $name;
            } elseif ( null !== $status && 'off_locked' === $status['status'] ) {
                $locked[] = $name;
            }
        }
        if ( array() === $off && array() === $locked ) {
            return '';
        }
        $named = array_merge( $off, $locked );
        return sprintf(
            'This site installs and deletes plugins and themes with its own tools, and %s %s switched off: %s.',
            count( $named ) > 1 ? 'these' : 'this one',
            count( $named ) > 1 ? 'are' : 'is',
            \Easy_MCP_AI\MCP\Tool_Status::names( $named )
        ) . ' Tell the user which tool to switch on, by its name, and that ' . \Easy_MCP_AI\MCP\Tool_Status::who_switches_on( $off, $locked, \Easy_MCP_AI\MCP\Tool_Status::switch_path( $named[0], true ) ) . '.';
    }

    private static function caller_may_use( string $tool ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true;
        }
        $may = \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
        return null === $may || $may;
    }
}
