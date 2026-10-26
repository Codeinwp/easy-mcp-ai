<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class OAuth_Routes {

    




    const NAMESPACE_V1 = 'easy-mcp-ai/v1';

    




    public function register_routes() {
        

        register_rest_route(
            self::NAMESPACE_V1,
            '/.well-known/oauth-protected-resource',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( new Discovery(), 'get_protected_resource_metadata' ),
                'permission_callback' => '__return_true',
            )
        );

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        register_rest_route(
            self::NAMESPACE_V1,
            '/.well-known/oauth-authorization-server',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( new Discovery(), 'get_authorization_server_metadata' ),
                'permission_callback' => '__return_true',
            )
        );

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        register_rest_route(
            self::NAMESPACE_V1,
            '/discovery/oauth-protected-resource',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( new Discovery(), 'get_protected_resource_metadata' ),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::NAMESPACE_V1,
            '/discovery/oauth-authorization-server',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( new Discovery(), 'get_authorization_server_metadata' ),
                'permission_callback' => '__return_true',
            )
        );

        
        
        
        
        
        
        
        
        
        foreach ( array(
            '/mcp/.well-known/oauth-protected-resource'   => 'get_resource_issuer_protected_resource_metadata',
            '/mcp/.well-known/oauth-authorization-server' => 'get_resource_issuer_metadata',
            '/mcp/.well-known/openid-configuration'       => 'get_resource_issuer_openid_configuration',
            '/discovery/openid-configuration'             => 'get_openid_configuration',
            
            '/oauth/jwks'                                 => 'get_jwks',
        ) as $route => $callback ) {
            register_rest_route(
                self::NAMESPACE_V1,
                $route,
                array(
                    'methods'             => \WP_REST_Server::READABLE,
                    'callback'            => array( new Discovery(), $callback ),
                    'permission_callback' => '__return_true',
                )
            );
        }

        

        register_rest_route(
            self::NAMESPACE_V1,
            '/oauth/register',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( new Client_Registry(), 'handle_register' ),
                'permission_callback' => '__return_true',
            )
        );

        
        
        
        
        
        
        
        
        
        
        
        register_rest_route(
            self::NAMESPACE_V1,
            '/oauth/authorize',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_authorize_redirect' ),
                'permission_callback' => '__return_true',
            )
        );

        

        register_rest_route(
            self::NAMESPACE_V1,
            '/oauth/token',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( new Token_Endpoint(), 'handle' ),
                'permission_callback' => '__return_true',
            )
        );

        
        
        
        
        
        
        
        register_rest_route(
            self::NAMESPACE_V1,
            '/oauth/device',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( new Device_Authorization(), 'handle_device_request' ),
                'permission_callback' => '__return_true',
            )
        );

        

        register_rest_route(
            self::NAMESPACE_V1,
            '/oauth/revoke',
            array(
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_revoke' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    











    public function handle_authorize_redirect( \WP_REST_Request $request ) {
        $url = self::build_authorize_redirect_url( (array) $request->get_query_params() );
        
        
        
        
        
        \Easy_MCP_AI\Cache\Page_Cache_Opt_Out::apply_to_direct_response();
        \nocache_headers();
        
        
        
        \wp_safe_redirect( $url, 302 );
        exit;
    }

    



















    public static function build_authorize_redirect_url( array $query_params ) {
        
        unset( $query_params['rest_route'], $query_params['_locale'] );

        
        $query_params['easy_mcp_ai_oauth'] = 'authorize';

        return \home_url( '/' ) . '?' . http_build_query( $query_params );
    }

    








    public function handle_revoke( \WP_REST_Request $request ) {
        
        
        $transport_error = Token_Endpoint::enforce_transport_security();
        if ( null !== $transport_error ) {
            return $transport_error;
        }

        
        
        
        
        
        
        
        $ip = \Easy_MCP_AI\Client_IP::get();

        $per_ip_key    = 'easy_mcp_ai_revoke_rl_' . md5( $ip );
        $global_key    = 'easy_mcp_ai_revoke_rl_global';
        $per_ip_limit  = 60;
        $global_limit  = 600;

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $per_ip_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $ip_count = \wp_cache_incr( $per_ip_key, 1, 'easy_mcp_ai' );

            \wp_cache_add( $global_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $global_count = \wp_cache_incr( $global_key, 1, 'easy_mcp_ai' );
        } else {
            
            $ip_count     = Token_Endpoint::rl_transient_increment( $per_ip_key );
            $global_count = Token_Endpoint::rl_transient_increment( $global_key );
        }

        if ( $ip_count > $per_ip_limit || $global_count > $global_limit ) {
            return new \WP_REST_Response( null, 429 );
        }

        
        
        
        
        
        
        
        $query_error = Token_Endpoint::reject_query_credentials( $request, array( 'token', 'client_secret' ) );
        if ( null !== $query_error ) {
            return $query_error;
        }
        $token     = Token_Endpoint::body_param( $request, 'token' );
        $hint      = sanitize_text_field( $request->get_param( 'token_type_hint' ) );
        
        
        
        $basic     = Token_Endpoint::read_basic_credentials( $request );
        $client_id = Token_Endpoint::resolve_client_id( $request, $basic );
        if ( $client_id instanceof \WP_REST_Response ) {
            return $client_id;
        }

        
        
        
        
        
        if ( '' === $token || '' === $client_id ) {
            $response = new \WP_REST_Response( '', 200 );
            $response->header( 'Content-Length', '0' );
            $response->header( 'Referrer-Policy', 'no-referrer' );
            $response->header( 'X-Content-Type-Options', 'nosniff' );
            $response->header( 'Cache-Control', 'no-store' );
            return $response;
        }

        
        
        
        
        
        
        
        
        $client = ( new Client_Registry() )->find_client( $client_id );
        if ( null !== $client ) {
            $auth_error = Token_Endpoint::authenticate_client( $request, $client, $basic );
            if ( null !== $auth_error ) {
                return $auth_error;
            }
        }
        
        
        $client_id = Client_Metadata::storage_id( $client_id );

        
        
        
        $token_hash = OAuth_Token_Manager::hash_presented( $token );
        $manager    = new OAuth_Token_Manager();

        
        $looks_like_access = ( 0 === strpos( $token, 'wpmcp_oat_' ) );

        
        
        
        
        
        if ( null !== $token_hash ) {
            if ( 'refresh_token' === $hint || ! $looks_like_access ) {
                if ( ! $manager->revoke_by_refresh_hash( $token_hash, $client_id ) ) {
                    $manager->revoke_token( $token_hash, $client_id );
                }
            } else {
                if ( ! $manager->revoke_token( $token_hash, $client_id ) ) {
                    $manager->revoke_by_refresh_hash( $token_hash, $client_id );
                }
            }
        }

        
        $response = new \WP_REST_Response( '', 200 );
        $response->header( 'Content-Length', '0' );
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        $response->header( 'Cache-Control', 'no-store' );

        return $response;
    }
}
