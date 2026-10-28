<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Host {

    const SETTING = 'api_key_site_host';

    const FILTER = 'easy_mcp_ai_api_key_site_host';

    public static function current() {
        $configured = Config::get( self::SETTING );
        if ( is_string( $configured ) && '' !== $configured ) {
            $candidate = $configured;
        } else {
            $stored    = \get_option( 'home' );
            $candidate = ( is_string( $stored ) && '' !== $stored ) ? $stored : (string) \home_url();
        }
        return self::normalize( (string) \apply_filters( self::FILTER, $candidate ) );
    }

    public static function request_host(): string {
        $configured = Config::get( self::SETTING );
        if ( is_string( $configured ) && '' !== $configured ) {
            return '';
        }
        return self::normalize( (string) \apply_filters( self::FILTER, (string) \home_url() ) );
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
        $hosts = null === $current ? array( self::current(), self::request_host() ) : array( $current );
        foreach ( array_unique( $hosts ) as $host ) {
            $host_hash = self::hash( $host );
            if ( '' !== $host_hash && hash_equals( $host_hash, $stored_hash ) ) {
                return true;
            }
        }
        return false;
    }
}
