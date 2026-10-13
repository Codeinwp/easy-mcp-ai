<?php
namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Session {
    private $ttl_seconds;

    public function __construct() {
        $this->ttl_seconds = 15 * 60; 
    }

    public function create( $token_id, $wp_user_id, $protocol_version = '2025-03-26', $auth_source = 'legacy', $client_capabilities = array() ) {
        $session_id = bin2hex( random_bytes( 32 ) );
        $session_data = array(
            'token_id'         => $token_id,
            'wp_user_id'       => $wp_user_id,
            'initialized'      => true,
            'created_at'       => time(),
            'protocol_version' => $protocol_version,
            'auth_source'      => 'oauth' === $auth_source ? 'oauth' : 'legacy',
            
            
            
            'client_capabilities' => is_array( $client_capabilities ) ? $client_capabilities : array(),
        );
        \set_transient( 'easy_mcp_ai_session_' . $session_id, $session_data, $this->ttl_seconds );
        return $session_id;
    }

    public function validate( $session_id ) {
        if ( ! self::is_valid_format( $session_id ) ) {
            return false;
        }
        $session_data = \get_transient( 'easy_mcp_ai_session_' . $session_id );
        if ( false === $session_data || ! is_array( $session_data ) ) {
            return false;
        }
        
        if ( empty( $session_data['token_id'] ) || empty( $session_data['wp_user_id'] ) ) {
            \delete_transient( 'easy_mcp_ai_session_' . $session_id );
            return false;
        }
        return $session_data;
    }

    public function destroy( $session_id ) {
        if ( ! self::is_valid_format( $session_id ) ) {
            return;
        }
        \delete_transient( 'easy_mcp_ai_session_' . $session_id );
    }

    public function touch( $session_id ) {
        if ( ! self::is_valid_format( $session_id ) ) {
            return;
        }
        
        
        $session_data = $this->validate( $session_id );
        if ( false !== $session_data ) {
            \set_transient( 'easy_mcp_ai_session_' . $session_id, $session_data, $this->ttl_seconds );
        }
    }

    


    private static function is_valid_format( $session_id ) {
        return is_string( $session_id ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $session_id );
    }
}
