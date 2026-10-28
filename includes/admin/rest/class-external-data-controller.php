<?php

namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\External_Data_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class External_Data_Controller extends Admin_Rest_Controller {

    private $service;

    public function __construct( External_Data_Service $service ) {
        $this->service = $service;
    }

    public function register_routes() {
        $provider = array(
            'provider' => array(
                'type'     => 'string',
                'required' => true,
            ),
        );
        $this->register_route( '/external-data', \WP_REST_Server::READABLE, array( $this, 'get_all' ) );
        $this->register_route( '/external-data/(?P<provider>[a-z]+)', self::UPDATABLE, array( $this, 'patch_provider' ), $provider );
        $this->register_route( '/external-data/(?P<provider>[a-z]+)/test', \WP_REST_Server::CREATABLE, array( $this, 'post_test' ), $provider );
        $this->register_route( '/external-data/(?P<provider>[a-z]+)/remove-key', \WP_REST_Server::CREATABLE, array( $this, 'post_remove_key' ), $provider );
        $this->register_route( '/external-data/(?P<provider>[a-z]+)/clear-cache', \WP_REST_Server::CREATABLE, array( $this, 'post_clear_cache' ), $provider );
        $this->register_route( '/external-data/(?P<provider>[a-z]+)/refresh-balance', \WP_REST_Server::CREATABLE, array( $this, 'post_refresh_balance' ), $provider );
    }

    public function get_all( $request ) {
        return $this->ok( $this->service->describe_all() );
    }

    public function patch_provider( $request ) {
        $key = $this->provider_of( $request );
        if ( \is_wp_error( $key ) ) {
            return $key;
        }
        $body = $request->get_json_params();
        if ( ! is_array( $body ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Expected a JSON object.', 'easy-mcp-ai' ), 400, array( 'field' => '' ) );
        }
        $saved = $this->service->save( $key, $body );
        if ( \is_wp_error( $saved ) ) {
            return $saved;
        }
        return $this->ok( $this->service->describe( $key ) );
    }

    public function post_test( $request ) {
        $key = $this->provider_of( $request );
        if ( \is_wp_error( $key ) ) {
            return $key;
        }
        return $this->ok( $this->service->test( $key ) );
    }

    public function post_remove_key( $request ) {
        $key = $this->provider_of( $request );
        if ( \is_wp_error( $key ) ) {
            return $key;
        }
        $removed = $this->service->remove_key( $key );
        if ( \is_wp_error( $removed ) ) {
            return $removed;
        }
        return $this->ok( $this->service->describe( $key ) );
    }

    public function post_clear_cache( $request ) {
        $key = $this->provider_of( $request );
        if ( \is_wp_error( $key ) ) {
            return $key;
        }
        $cleared = $this->service->clear_cache( $key );
        if ( \is_wp_error( $cleared ) ) {
            return $cleared;
        }
        return $this->ok( $this->service->describe( $key ) );
    }

    public function post_refresh_balance( $request ) {
        $key = $this->provider_of( $request );
        if ( \is_wp_error( $key ) ) {
            return $key;
        }
        $result = $this->service->refresh_balance( $key );
        if ( \is_wp_error( $result ) ) {
            return $result;
        }
        return $this->ok( $result );
    }

    private function provider_of( $request ) {
        $key = (string) $request->get_param( 'provider' );
        if ( ! External_Data_Service::is_provider( $key ) ) {
            return $this->fail( 'easy_mcp_ai_not_found', \__( 'Unknown provider.', 'easy-mcp-ai' ), 404 );
        }
        return $key;
    }
}
