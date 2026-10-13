<?php
namespace Easy_MCP_AI\Tools\Users;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







trait User_Meta_Auth_Guard {

    private static function auth_protected_pattern(): string {
        return '/(_session|_token|_password|_app(lication)?_password|_reset|_login_attempts|_2fa|_totp|_otp|_recovery|_new_email|_passkey|_webauthn)/i';
    }

    private static function auth_protected_keys(): array {
        return array(
            'session_tokens',
            '_password_reset_key',
            '_application_passwords',
            'default_password_nonce',
        );
    }

    protected static function is_auth_protected( string $key ): bool {
        if ( in_array( $key, self::auth_protected_keys(), true ) ) {
            return true;
        }
        return (bool) preg_match( self::auth_protected_pattern(), $key );
    }

    protected static function is_blocked_by_filter( string $key ): bool {
        static $blocked_patterns = null;
        if ( null === $blocked_patterns ) {
            $blocked_patterns = apply_filters( 'easy_mcp_ai_user_meta_blocked_key_patterns', array() );
        }
        foreach ( $blocked_patterns as $pattern ) {
            if ( fnmatch( $pattern, $key ) ) {
                return true;
            }
        }
        return false;
    }

    protected static function is_privilege_key( string $key ): bool {
        
        
        
        
        global $wpdb;
        $prefixes = array( 'wp_' );
        if ( isset( $wpdb ) && ! empty( $wpdb->base_prefix ) ) {
            $prefixes[] = $wpdb->base_prefix;
        }
        $prefixes = array_unique( $prefixes );
        foreach ( $prefixes as $prefix ) {
            $pattern = '/^' . preg_quote( $prefix, '/' ) . '(\d+_)?(capabilities|user_level)$/';
            if ( (bool) preg_match( $pattern, $key ) ) {
                return true;
            }
        }
        return false;
    }
}
