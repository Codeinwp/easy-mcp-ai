<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










































class Auth_Header {

    






















    public static function recover_bearer( array $headers ) {
        return self::find_authorization( $headers, 'Bearer' );
    }

    









    public static function find_authorization( array $headers, $scheme ) {
        foreach ( $headers as $name => $value ) {
            if ( 0 !== strcasecmp( (string) $name, 'Authorization' ) ) {
                continue;
            }
            if ( is_string( $value ) && 0 === stripos( $value, $scheme . ' ' ) ) {
                return $value;
            }
            return null;
        }
        return null;
    }

    






    const API_KEY_PATTERN = '/^wpmcp_(?:[a-f0-9]{6}_)?[a-f0-9]{64}$/';

    




    public static function is_api_key( $value ) {
        return is_string( $value ) && 1 === preg_match( self::API_KEY_PATTERN, $value );
    }

    










    public static function pair_has_plugin_credential_shape( $user, $pass ) {
        $user = is_string( $user ) ? $user : '';
        $pass = is_string( $pass ) ? $pass : '';
        if ( 0 === strpos( $pass, 'wpmcp_' ) ) {
            return true;
        }
        return '' === $pass && 0 === strpos( $user, 'wpmcp_' );
    }

    















    public static function api_key_from_pair( $user, $pass ) {
        $user = is_string( $user ) ? $user : '';
        $pass = is_string( $pass ) ? $pass : '';
        if ( self::is_api_key( $pass ) ) {
            return $pass;
        }
        if ( '' === $pass && self::is_api_key( $user ) ) {
            return $user;
        }
        return null;
    }

    









    public static function api_key_from_basic( $header ) {
        if ( ! is_string( $header ) || 0 !== stripos( $header, 'Basic ' ) ) {
            return null;
        }
        $token = trim( substr( $header, 6 ) );
        if ( '' === $token || ! preg_match( '%^[a-z\d/+]*={0,2}$%i', $token ) ) {
            return null;
        }
        $decoded = base64_decode( $token, true );
        if ( ! is_string( $decoded ) || false === strpos( $decoded, ':' ) ) {
            return null;
        }
        list( $user, $pass ) = explode( ':', $decoded, 2 );
        return self::api_key_from_pair( $user, $pass );
    }

    









    public static function recover_basic_api_key( array $headers ) {
        $value = self::find_authorization( $headers, 'Basic' );
        if ( null === $value || null === self::api_key_from_basic( $value ) ) {
            return null;
        }
        return $value;
    }

    








    public static function request_headers() {
        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
        } elseif ( function_exists( 'apache_request_headers' ) ) {
            $headers = apache_request_headers();
        } else {
            return null;
        }
        return is_array( $headers ) ? $headers : null;
    }
}
