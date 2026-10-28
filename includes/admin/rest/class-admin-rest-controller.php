<?php

namespace Easy_MCP_AI\Admin\Rest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Admin_Rest_Controller {

    const REST_NAMESPACE = 'easy-mcp-ai-admin/v1';

    const CAPABILITY = 'manage_options';

    const UPDATABLE = 'POST, PATCH';

    abstract public function register_routes();

    public function permission() {
        if ( true === \Easy_MCP_AI\Config::get( 'hide_admin' ) ) {
            return $this->fail(
                'easy_mcp_ai_admin_hidden',
                \__( 'This administration page is disabled by the site configuration.', 'easy-mcp-ai' ),
                403
            );
        }
        if ( \current_user_can( self::CAPABILITY ) ) {
            return true;
        }
        return $this->fail(
            'easy_mcp_ai_forbidden',
            \__( 'Sorry, you are not allowed to do that.', 'easy-mcp-ai' ),
            403
        );
    }

    protected function ok( $data, $status = 200 ) {
        return new \WP_REST_Response( array( 'data' => $data ), $status );
    }

    protected function fail( $code, $message, $status, array $extra = array() ) {
        return new \WP_Error( $code, $message, array_merge( $extra, array( 'status' => (int) $status ) ) );
    }

    protected static function id_args() {
        return array(
            'id' => array(
                'type'     => 'integer',
                'required' => true,
                'minimum'  => 1,
            ),
        );
    }

    protected function register_route( $route, $methods, $callback, array $args = array() ) {
        \register_rest_route(
            self::REST_NAMESPACE,
            $route,
            array(
                'methods'             => $methods,
                'callback'            => $callback,
                'permission_callback' => array( $this, 'permission' ),
                'args'                => $args,
            )
        );
    }

    protected function register_delete_route( string $route, callable $callback, array $args = array() ): void {
        $this->register_route( $route, \WP_REST_Server::DELETABLE, $callback, $args );
        $this->register_route( $route . '/delete', \WP_REST_Server::CREATABLE, $callback, $args );
    }
}
