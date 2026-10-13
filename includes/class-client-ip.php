<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






















require_once __DIR__ . '/class-config.php';

class Client_IP {

    







    const CONSTANT_TRUSTED_PROXIES = 'EASY_MCP_AI_TRUSTED_PROXIES';

    
    const CONSTANT_HEADER = 'EASY_MCP_AI_CLIENT_IP_HEADER';

    
    const DOC_URL = 'https://docs.easymcpai.com/trusted-proxies';

    
    const HEADER_DEFAULT = 'X-Forwarded-For';

    



    const HEADERS = array(
        'X-Forwarded-For'  => 'HTTP_X_FORWARDED_FOR',
        'CF-Connecting-IP' => 'HTTP_CF_CONNECTING_IP',
        'X-Real-IP'        => 'HTTP_X_REAL_IP',
        'True-Client-IP'   => 'HTTP_TRUE_CLIENT_IP',
    );

    



































    public static function get(): string {
        $remote = isset( $_SERVER['REMOTE_ADDR'] )
            ? trim( \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), '[]' )
            : '';

        $trusted = self::trusted_proxies();
        if ( ! empty( $trusted ) && '' !== $remote ) {
            $remote = self::resolve( $remote, $_SERVER, $trusted, self::header_name() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- resolve() validates every value it reads.
        }

        
















        $filtered = \apply_filters( 'easy_mcp_ai_client_ip', $remote );

        if ( is_string( $filtered ) && '' !== $filtered ) {
            $candidate = trim( $filtered, '[]' );
            if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                return $candidate;
            }
        }

        return '' !== $remote ? $remote : 'unknown';
    }

    













    public static function is_private_or_reserved( string $ip ): bool {
        if ( '' === $ip ) {
            return false;
        }

        $ip = self::unwrap_v4_mapped( $ip );

        if ( in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) {
            return true;
        }
        return false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
    }

    


















    private static function unwrap_v4_mapped( string $ip ): string {
        if ( 0 !== stripos( $ip, '::ffff:' ) ) {
            return $ip;
        }

        $candidate = substr( $ip, 7 );

        
        
        
        return ( false !== filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) )
            ? $candidate
            : $ip;
    }

    















    public static function describe( string $ip ): string {
        if ( '' === $ip ) {
            return 'Unknown';
        }
        
        
        if ( in_array( self::unwrap_v4_mapped( $ip ), array( '127.0.0.1', '::1' ), true ) ) {
            return 'Loopback (site is behind a same-host proxy)';
        }
        if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return 'Not a valid IP address';
        }
        if ( self::is_private_or_reserved( $ip ) ) {
            return 'Private/reserved range (site is behind a proxy or load balancer)';
        }
        return 'Public address';
    }

    

    







    public static function trusted_proxies(): array {
        $raw     = Config::get( 'trusted_proxies' );
        $entries = self::parse_trusted_proxies( $raw );

        




        $filtered = \apply_filters( 'easy_mcp_ai_trusted_proxies', $entries );

        return self::parse_trusted_proxies( is_array( $filtered ) ? $filtered : $entries );
    }

    








    public static function parse_trusted_proxies( $raw ): array {
        $entries = array();
        if ( is_string( $raw ) ) {
            $lines = preg_split( '/\r\n|\r|\n/', $raw );
            $raw   = array();
            foreach ( $lines as $line ) {
                list( $line ) = explode( '#', $line, 2 );
                foreach ( preg_split( '/[\s,]+/', $line ) as $piece ) {
                    $raw[] = $piece;
                }
            }
        }
        if ( ! is_array( $raw ) ) {
            return array();
        }
        foreach ( $raw as $entry ) {
            if ( ! is_string( $entry ) ) {
                continue;
            }
            $entry = trim( $entry, " \t[]" );
            if ( '' !== $entry && self::is_ip_or_cidr( $entry ) ) {
                $entries[] = $entry;
            }
        }
        return array_values( array_unique( $entries ) );
    }

    




    public static function header_name(): string {
        $raw  = Config::get( 'client_ip_header' );
        $name = self::sanitize_header_name( $raw );

        




        return self::sanitize_header_name( \apply_filters( 'easy_mcp_ai_client_ip_header', $name ) );
    }

    
    public static function sanitize_header_name( $raw ): string {
        if ( ! is_string( $raw ) ) {
            return self::HEADER_DEFAULT;
        }
        foreach ( array_keys( self::HEADERS ) as $name ) {
            if ( 0 === strcasecmp( trim( $raw ), $name ) ) {
                return $name;
            }
        }
        return self::HEADER_DEFAULT;
    }

    










    public static function resolve( string $remote, array $server, array $trusted, string $header ): string {
        if ( empty( $trusted ) || ! self::matches_any( $remote, $trusted ) ) {
            return $remote;
        }
        $key = isset( self::HEADERS[ $header ] ) ? self::HEADERS[ $header ] : self::HEADERS[ self::HEADER_DEFAULT ];
        if ( ! isset( $server[ $key ] ) || ! is_string( $server[ $key ] ) || '' === trim( $server[ $key ] ) ) {
            return $remote;
        }
        $value = \sanitize_text_field( \wp_unslash( $server[ $key ] ) );

        if ( self::HEADER_DEFAULT === $header ) {
            
            
            
            
            
            
            
            $parts = array_reverse( array_map( 'trim', explode( ',', $value ) ) );
            foreach ( $parts as $candidate ) {
                $candidate = trim( $candidate, '[]' );
                if ( '' === $candidate || false === filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    return $remote; 
                }
                if ( ! self::matches_any( $candidate, $trusted ) ) {
                    return $candidate;
                }
            }
            return $remote;
        }

        $candidate = trim( $value, '[]' );
        return ( false !== filter_var( $candidate, FILTER_VALIDATE_IP ) ) ? $candidate : $remote;
    }

    

    








    public static function matches_any( string $ip, array $entries ): bool {
        $raw = trim( $ip );
        $ip  = self::normalize_ip( $raw );
        if ( false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return false;
        }
        foreach ( $entries as $entry ) {
            $entry = trim( (string) $entry );
            if ( false !== strpos( $entry, '/' ) ) {
                $cidr = self::normalize_cidr_entry( $entry );
                if ( self::ip_in_cidr( $ip, $cidr ) ) {
                    return true;
                }
                
                
                
                
                
                
                
                if ( $raw !== $ip && self::ip_in_cidr( $raw, $cidr ) ) {
                    return true;
                }
                continue;
            }
            $normalized_entry = self::normalize_ip( $entry );
            if ( false === filter_var( $normalized_entry, FILTER_VALIDATE_IP ) ) {
                continue;
            }
            
            if ( inet_pton( $ip ) === inet_pton( $normalized_entry ) ) {
                return true;
            }
        }
        return false;
    }

    
    public static function is_ip_or_cidr( string $entry ): bool {
        if ( false === strpos( $entry, '/' ) ) {
            return false !== filter_var( $entry, FILTER_VALIDATE_IP );
        }
        if ( 1 !== substr_count( $entry, '/' ) ) {
            return false;
        }
        list( $subnet, $prefix ) = explode( '/', $entry, 2 );
        if ( ! ctype_digit( $prefix ) ) {
            return false;
        }
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            return (int) $prefix <= 32;
        }
        if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return (int) $prefix <= 128;
        }
        return false;
    }

    




    public static function normalize_ip( string $ip ): string {
        if ( false === strpos( $ip, ':' ) ) {
            return $ip;
        }
        $bin = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? inet_pton( $ip ) : false;
        if ( false !== $bin && 16 === strlen( $bin ) ) {
            $prefix = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff";
            if ( 0 === strncmp( $bin, $prefix, 12 ) ) {
                $ipv4 = inet_ntop( substr( $bin, 12 ) );
                if ( false !== $ipv4 && filter_var( $ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                    return $ipv4;
                }
            }
        }
        return $ip;
    }

    




















    public static function normalize_cidr_entry( string $cidr ): string {
        if ( 1 !== substr_count( $cidr, '/' ) ) {
            return $cidr;
        }
        list( $subnet, $prefix ) = explode( '/', $cidr, 2 );
        $normalized = self::normalize_ip( $subnet );
        if ( $normalized === $subnet || ! ctype_digit( $prefix ) ) {
            return $normalized . '/' . $prefix;
        }
        $bits = (int) $prefix;
        if ( $bits >= 96 ) {
            return $normalized . '/' . ( $bits - 96 );
        }
        if ( $bits <= 32 ) {
            return $normalized . '/' . $bits;
        }
        return $cidr;
    }

    



    public static function ip_in_cidr( string $ip, string $cidr ): bool {
        if ( 1 !== substr_count( $cidr, '/' ) ) {
            return false;
        }
        list( $subnet, $raw_prefix ) = explode( '/', $cidr, 2 );
        if ( ! ctype_digit( $raw_prefix ) ) {
            return false;
        }
        $prefix = (int) $raw_prefix;
        $is_v6  = false !== strpos( $ip, ':' );
        $flag   = $is_v6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
        $width  = $is_v6 ? 16 : 4;
        if ( $prefix > $width * 8 ) {
            return false;
        }
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, $flag ) || ! filter_var( $subnet, FILTER_VALIDATE_IP, $flag ) ) {
            return false;
        }
        $ip_bin     = inet_pton( $ip );
        $subnet_bin = inet_pton( $subnet );
        if ( false === $ip_bin || false === $subnet_bin ) {
            return false;
        }
        $mask = self::build_mask_bin( $prefix, $width );
        return ( $ip_bin & $mask ) === ( $subnet_bin & $mask );
    }

    private static function build_mask_bin( int $prefix, int $total_bytes ): string {
        $full_bytes   = (int) ( $prefix / 8 );
        $partial_bits = $prefix % 8;
        $mask_bin     = str_repeat( "\xFF", $full_bytes );
        if ( $partial_bits > 0 ) {
            $mask_bin .= chr( ( 0xFF << ( 8 - $partial_bits ) ) & 0xFF );
        }
        return str_pad( $mask_bin, $total_bytes, "\x00" );
    }
}
