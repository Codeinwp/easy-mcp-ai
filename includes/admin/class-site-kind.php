<?php










namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Kind {

    const PUBLIC_SITE = 'public';
    const LOCAL_SITE  = 'local';

    
    const LOCAL_SUFFIXES = array( '.local', '.test', '.localhost' );

    





    public static function detect( $home_url ) {
        $host = self::host_of( $home_url );
        if ( '' === $host ) {
            return self::PUBLIC_SITE;
        }

        if ( 'localhost' === $host || '::1' === $host ) {
            return self::LOCAL_SITE;
        }

        foreach ( self::LOCAL_SUFFIXES as $suffix ) {
            if ( strlen( $host ) > strlen( $suffix ) && substr( $host, -strlen( $suffix ) ) === $suffix ) {
                return self::LOCAL_SITE;
            }
        }

        if ( self::is_loopback_or_private_ipv4( $host ) ) {
            return self::LOCAL_SITE;
        }

        return self::PUBLIC_SITE;
    }

    





    private static function host_of( $url ) {
        $parts = \wp_parse_url( (string) $url );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return '';
        }
        return strtolower( trim( (string) $parts['host'], '[]' ) );
    }

    





    private static function is_loopback_or_private_ipv4( $host ) {
        if ( ! filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return false;
        }
        $long = ip2long( $host );
        if ( false === $long ) {
            return false;
        }
        
        $long = $long & 0xFFFFFFFF;

        $ranges = array(
            array( ip2long( '127.0.0.0' ), 8 ),
            array( ip2long( '10.0.0.0' ), 8 ),
            array( ip2long( '172.16.0.0' ), 12 ),
            array( ip2long( '192.168.0.0' ), 16 ),
        );
        foreach ( $ranges as $range ) {
            $mask = ( 0xFFFFFFFF << ( 32 - $range[1] ) ) & 0xFFFFFFFF;
            if ( ( $long & $mask ) === ( ( $range[0] & 0xFFFFFFFF ) & $mask ) ) {
                return true;
            }
        }
        return false;
    }
}
