<?php























namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-config.php';

class Site_Host {

    
    const SETTING = 'api_key_site_host';

    
    const FILTER = 'easy_mcp_ai_api_key_site_host';

    

















    public static function current() {
        $configured = Config::get( self::SETTING );
        $candidate  = ( is_string( $configured ) && '' !== $configured ) ? $configured : (string) \home_url();
        return self::normalize( (string) \apply_filters( self::FILTER, $candidate ) );
    }

    

























    public static function normalize( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '';
        }
        
        
        
        $to_parse = ( false === strpos( $value, '//' ) ) ? '//' . $value : $value;
        $host     = \wp_parse_url( $to_parse, PHP_URL_HOST );
        if ( ! is_string( $host ) || '' === $host ) {
            return '';
        }
        $host = strtolower( rtrim( $host, '.' ) );
        
        
        if ( ! preg_match( '/^(?:\[[0-9a-f:.]+\]|[\p{L}\p{N}_-]+(?:\.[\p{L}\p{N}_-]+)*)$/u', $host ) ) {
            return '';
        }
        while ( 0 === strpos( $host, 'www.' ) && strlen( $host ) > 4 ) {
            $host = substr( $host, 4 );
        }
        return $host;
    }

    








    public static function matches( $host, $current = null ) {
        $host    = self::normalize( (string) $host );
        $current = null === $current ? self::current() : self::normalize( (string) $current );
        return '' !== $host && '' !== $current && $host === $current;
    }

    


















    public static function hash( $host ) {
        $host = self::normalize( (string) $host );
        return '' === $host ? '' : hash( 'sha256', $host );
    }

    
    public static function current_hash() {
        return self::hash( self::current() );
    }

    








    public static function matches_hash( $stored_hash, $current = null ) {
        $stored_hash = strtolower( trim( (string) $stored_hash ) );
        if ( ! preg_match( '/^[0-9a-f]{64}$/', $stored_hash ) ) {
            return false;
        }
        $current_hash = null === $current ? self::current_hash() : self::hash( $current );
        return '' !== $current_hash && hash_equals( $current_hash, $stored_hash );
    }
}
