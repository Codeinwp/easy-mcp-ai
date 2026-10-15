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

    













    protected static function is_outside_printable_ascii( string $key ): bool {
        return 1 === preg_match( '/[^\x20-\x7E]/', $key );
    }

    






    protected static function outside_printable_ascii_refusal( string $key, string $verb ): string {
        return sprintf( 'Meta key "%1$s" has a character outside printable ASCII, and such keys cannot be %2$s via this tool: the database compares user meta keys ignoring accents, character width and case, so the key could reach a protected key (wp_capabilities, session_tokens) under another spelling.', $key, $verb );
    }

    













    protected static function is_privilege_key( string $key ): bool {
        
        
        
        
        global $wpdb;
        $prefixes = array( 'wp_' );
        if ( isset( $wpdb ) && ! empty( $wpdb->base_prefix ) ) {
            $prefixes[] = $wpdb->base_prefix;
        }
        $prefixes = array_unique( $prefixes );
        
        
        
        
        
        
        foreach ( $prefixes as $prefix ) {
            $pattern = '/^' . preg_quote( $prefix, '/' ) . '(\d+_)?(capabilities|user_level)$/i';
            if ( (bool) preg_match( $pattern, $key ) ) {
                return true;
            }
        }
        
        
        
        
        
        
        if ( preg_match( '/[^\x21-\x7E]/', $key ) ) {
            $folded = self::fold_for_collation( $key );
            foreach ( $prefixes as $prefix ) {
                $loose = str_replace( '_', '_?', preg_quote( strtolower( $prefix ), '/' ) );
                if ( preg_match( '/^' . $loose . '(\d+_?)?(capabilities|user_?level)\z/', $folded ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    








    private static function fold_for_collation( string $key ): string {
        $chars = preg_split( '//u', $key, -1, PREG_SPLIT_NO_EMPTY );
        if ( false === $chars ) {
            
            return strtolower( (string) preg_replace( '/[^\x21-\x7E]/', '', $key ) );
        }
        $out = '';
        foreach ( $chars as $char ) {
            $out .= self::fold_character( $char );
        }
        return strtolower( $out );
    }

    







    private static function fold_character( string $char ): string {
        if ( 1 === strlen( $char ) ) {
            return preg_match( '/[\x21-\x7E]/', $char ) ? $char : '';
        }
        
        if ( 3 !== strlen( $char ) || "\xEF" !== $char[0] || ( "\xBC" !== $char[1] && "\xBD" !== $char[1] ) ) {
            return '';
        }
        $code = ( ( ord( $char[1] ) & 0x3F ) << 6 ) | ( ord( $char[2] ) & 0x3F ) | 0xF000;
        return ( $code >= 0xFF01 && $code <= 0xFF5E ) ? chr( $code - 0xFEE0 ) : '';
    }
}
