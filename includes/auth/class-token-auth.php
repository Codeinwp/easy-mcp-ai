<?php
namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-client-ip.php';

class Token_Auth {
    private $token_manager;

    public function __construct( Token_Manager $token_manager ) {
        $this->token_manager = $token_manager;
    }

    public function authenticate( \WP_REST_Request $request ) {
        $auth_header = $request->get_header( 'authorization' );
        if ( empty( $auth_header ) ) {
            return new \WP_Error( 'no_auth', __( 'Missing Authorization header.', 'easy-mcp-ai' ) );
        }
        if ( 0 !== stripos( $auth_header, 'Bearer ' ) ) {
            return new \WP_Error( 'invalid_auth', __( 'Authorization header must use Bearer scheme.', 'easy-mcp-ai' ) );
        }
        $raw_token = substr( $auth_header, 7 );
        if ( empty( $raw_token ) ) {
            return new \WP_Error( 'empty_token', __( 'Bearer token is empty.', 'easy-mcp-ai' ) );
        }
        $token = $this->token_manager->validate_token( $raw_token );
        if ( false === $token ) {
            
            
            
            
            $reason = $this->token_manager->get_last_validation_error();
            if ( \is_wp_error( $reason ) && Token_Manager::ERROR_SITE_MISMATCH === $reason->get_error_code() ) {
                return $reason;
            }
            return new \WP_Error( 'invalid_token', __( 'Invalid or expired token.', 'easy-mcp-ai' ) );
        }
        
        
        
        
        
        
        
        return array( 'token_id' => (int) $token['id'], 'wp_user_id' => (int) $token['wp_user_id'] );
    }

    























    public function check_ip_allowed( $ip = null ) {
        return $this->is_ip_allowed( $ip );
    }

    private function is_ip_allowed( $ip = null ) {
        $whitelist_raw = \Easy_MCP_AI\Config::get( 'easy_mcp_ai_ip_whitelist', '' );
        if ( empty( trim( $whitelist_raw ) ) ) {
            return true; 
        }
        
        
        
        $cache_key = 'ip_wl_' . substr( md5( $whitelist_raw ), 0, 12 );
        $entries   = \wp_cache_get( $cache_key, 'easy_mcp_ai' );
        if ( false === $entries ) {
            $entries = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $whitelist_raw ) ) );
            \wp_cache_set( $cache_key, $entries, 'easy_mcp_ai', 300 );
        }
        if ( null === $ip ) {
            $ip = (string) \Easy_MCP_AI\Client_IP::get();
        }
        
        
        
        
        
        return \Easy_MCP_AI\Client_IP::matches_any( (string) $ip, $entries );
    }
}
