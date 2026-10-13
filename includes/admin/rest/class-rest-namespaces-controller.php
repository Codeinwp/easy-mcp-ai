<?php









namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Rest_Routes\Namespace_State;
use Easy_MCP_AI\Rest_Routes\Route_Catalogue;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-admin-rest-controller.php';
require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/rest-routes/class-namespace-state.php';
require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/rest-routes/class-route-catalogue.php';







class Rest_Namespaces_Controller extends Admin_Rest_Controller {

    
    const NAMESPACE_PATTERN = '#^[A-Za-z0-9][A-Za-z0-9._\-/]{0,99}$#';

    public function register_routes() {
        $this->register_route( '/rest-namespaces', \WP_REST_Server::READABLE, array( $this, 'get_namespaces' ) );
        $this->register_route( '/rest-namespaces', \WP_REST_Server::CREATABLE, array( $this, 'save_namespaces' ) );
    }

    


    public function get_namespaces() {
        return $this->ok( $this->state() );
    }

    



    public function save_namespaces( $request ) {
        $lists = array();
        foreach ( array( 'off', 'on' ) as $field ) {
            $raw = $request->get_param( $field );
            if ( null === $raw ) {
                $raw = array();
            }
            if ( ! is_array( $raw ) ) {
                return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Expected a list of namespaces.', 'easy-mcp-ai' ), 400, array( 'field' => $field ) );
            }
            $lists[ $field ] = array();
            foreach ( $raw as $namespace ) {
                if ( ! is_string( $namespace ) || 1 !== preg_match( self::NAMESPACE_PATTERN, trim( $namespace, " /" ) ) ) {
                    return $this->fail(
                        'easy_mcp_ai_invalid_param',
                        /* translators: %s: the rejected value */
                        sprintf( \__( '%s is not a REST namespace.', 'easy-mcp-ai' ), is_string( $namespace ) ? $namespace : \wp_json_encode( $namespace ) ),
                        400,
                        array( 'field' => $field )
                    );
                }
                $lists[ $field ][] = $namespace;
            }
        }
        Namespace_State::apply_choices( $lists['off'], $lists['on'] );
        return $this->ok( $this->state() );
    }

    





    private function state(): array {
        $server     = \rest_get_server();
        $namespaces = array();
        $registered = array();
        foreach ( Route_Catalogue::namespaces( $server ) as $entry ) {
            $override               = Namespace_State::override_of( $entry['namespace'] );
            $registered[]           = Namespace_State::normalize( $entry['namespace'] );
            $namespaces[]           = array(
                'namespace'  => $entry['namespace'],
                'routes'     => $entry['routes'],
                'enabled'    => Namespace_State::is_enabled( $entry['namespace'] ),
                'locked'     => null !== $override,
            );
        }
        $stored = Namespace_State::stored_off();
        return array(
            'namespaces'     => $namespaces,
            
            'inactiveOff'    => null === $stored ? array() : array_values( array_diff( $stored, $registered ) ),
            'invalidStored'  => null === $stored,
            'overrideSource' => Namespace_State::override_source(),
        );
    }
}
