<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Discovery {

    




    const NAMESPACE_V1 = 'easy-mcp-ai/v1';

    








    const ISSUER_SITE     = 'site';
    const ISSUER_RESOURCE = 'resource';

    







    public static function issuer( string $variant ): ?string {
        if ( self::ISSUER_RESOURCE !== $variant ) {
            return home_url();
        }
        $url = rest_url( self::NAMESPACE_V1 . '/mcp' );
        return false === strpos( $url, '?' ) ? $url : null;
    }

    











    public static function authorization_endpoint( string $variant = self::ISSUER_SITE ): string {
        $structure = (string) get_option( 'permalink_structure' );
        $prefix    = ( '' !== $structure && 0 !== strpos( ltrim( $structure, '/' ), 'index.php' ) ) ? '' : '/index.php';
        return home_url( $prefix . '/easy-mcp-ai/authorize' . ( self::ISSUER_RESOURCE === $variant ? '/resource' : '' ) );
    }

    





    public function get_protected_resource_metadata( \WP_REST_Request $request ) {
        return $this->protected_resource_response( self::ISSUER_SITE );
    }

    





    public function get_resource_issuer_protected_resource_metadata( \WP_REST_Request $request ) {
        return $this->protected_resource_response( self::ISSUER_RESOURCE );
    }

    



    private function protected_resource_response( string $variant ) {
        $error = $this->enforce_transport_security();
        if ( is_wp_error( $error ) ) {
            return $error;
        }
        $issuer = self::issuer( $variant );
        if ( null === $issuer ) {
            return self::not_available();
        }

        $scopes = class_exists( __NAMESPACE__ . '\\Scope_Map' )
            ? Scope_Map::get_public_scopes()
            : array();

        $metadata = array(
            'resource'                 => rest_url( self::NAMESPACE_V1 . '/mcp' ),
            'authorization_servers'    => array( $issuer ),
            'bearer_methods_supported' => array( 'header' ),
            'scopes_supported'         => $scopes,
        );

        $response = new \WP_REST_Response( $metadata, 200 );
        $this->add_security_headers( $response );
        $this->add_cors_headers( $response );

        return $response;
    }

    





    public function get_authorization_server_metadata( \WP_REST_Request $request ) {
        return $this->authorization_server_response( self::ISSUER_SITE, false );
    }

    





    public function get_openid_configuration( \WP_REST_Request $request ) {
        return $this->authorization_server_response( self::ISSUER_SITE, true );
    }

    





    public function get_resource_issuer_metadata( \WP_REST_Request $request ) {
        return $this->authorization_server_response( self::ISSUER_RESOURCE, false );
    }

    





    public function get_resource_issuer_openid_configuration( \WP_REST_Request $request ) {
        return $this->authorization_server_response( self::ISSUER_RESOURCE, true );
    }

    






    public function get_jwks( \WP_REST_Request $request ) {
        $error = $this->enforce_transport_security();
        if ( is_wp_error( $error ) ) {
            return $error;
        }
        $response = new \WP_REST_Response( array( 'keys' => array() ), 200 );
        $this->add_security_headers( $response );
        $this->add_cors_headers( $response );
        return $response;
    }

    






    public function authorization_server_response( string $variant, bool $openid ) {
        $error = $this->enforce_transport_security();
        if ( is_wp_error( $error ) ) {
            return $error;
        }
        $issuer = self::issuer( $variant );
        if ( null === $issuer ) {
            return self::not_available();
        }

        $rest_base = rest_url( self::NAMESPACE_V1 );

        $scopes = class_exists( __NAMESPACE__ . '\\Scope_Map' )
            ? Scope_Map::get_public_scopes()
            : array();

        $metadata = array(
            'issuer'                                => $issuer,
            'authorization_endpoint'                => self::authorization_endpoint( $variant ),
            'token_endpoint'                        => $rest_base . '/oauth/token',
            'revocation_endpoint'                   => $rest_base . '/oauth/revoke',
            
            
            
            
            'device_authorization_endpoint'         => $rest_base . '/oauth/device',
            'response_types_supported'                     => array( 'code' ),
            'response_modes_supported'                     => array( 'query' ),
            'grant_types_supported'                        => array( 'authorization_code', 'refresh_token', Device_Authorization::GRANT_TYPE ),
            'code_challenge_methods_supported'             => array( 'S256' ),
            
            
            
            
            
            
            'token_endpoint_auth_methods_supported'        => Client_Registry::SUPPORTED_AUTH_METHODS,
            'revocation_endpoint_auth_methods_supported'   => Client_Registry::SUPPORTED_AUTH_METHODS,
            'scopes_supported'                             => $scopes,
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            'authorization_response_iss_parameter_supported' => true,
        );

        
        
        
        
        
        
        
        if ( Client_Registry::is_dcr_enabled() ) {
            $metadata['registration_endpoint']                          = $rest_base . '/oauth/register';
            $metadata['registration_endpoint_auth_methods_supported']   = array( 'none' );
        }

        
        
        
        
        
        if ( $openid ) {
            $metadata['jwks_uri']                              = $rest_base . '/oauth/jwks';
            $metadata['subject_types_supported']               = array( 'public' );
            $metadata['id_token_signing_alg_values_supported'] = array( 'RS256' );
        }

        $response = new \WP_REST_Response( $metadata, 200 );
        $this->add_security_headers( $response );
        $this->add_cors_headers( $response );

        return $response;
    }

    




    private static function not_available(): \WP_Error {
        return new \WP_Error(
            'rest_no_route',
            __( 'No discovery document is served at this address on this site.', 'easy-mcp-ai' ),
            array( 'status' => 404 )
        );
    }

    




    private function enforce_transport_security() {
        
        
        
        
        if ( null === Token_Endpoint::enforce_transport_security() ) {
            return null;
        }
        return new \WP_Error(
            'oauth_requires_https',
            __( 'OAuth metadata endpoints require HTTPS.', 'easy-mcp-ai' ),
            array( 'status' => 403 )
        );
    }

    





    private function add_security_headers( \WP_REST_Response $response ) {
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        $response->header( 'Cache-Control', 'no-store' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Strict-Transport-Security', 'max-age=31536000' );
    }

    





    private function add_cors_headers( \WP_REST_Response $response ) {
        $response->header( 'Access-Control-Allow-Origin', '*' );
        $response->header( 'Access-Control-Allow-Methods', 'GET, OPTIONS' );
        $response->header( 'Access-Control-Allow-Headers', 'Authorization, Content-Type' );
    }
}
