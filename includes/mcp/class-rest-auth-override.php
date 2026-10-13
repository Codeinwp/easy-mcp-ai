<?php









































namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Auth_Override {

    
    const ROUTE_PREFIX = '/easy-mcp-ai/v1/';

    
    const ENABLED_FILTER = 'easy_mcp_ai_rest_auth_override';

    



    const PRESERVED_ERROR_CODES = array(
        'rest_cookie_invalid_nonce',
    );

    





    const CLEARED_STATUS = 401;

    
    const OBSERVED_GLOBAL = 'easy_mcp_ai_rest_auth_seen';

    








    public static function register() {
        \add_filter( 'rest_authentication_errors', array( __CLASS__, 'filter' ), PHP_INT_MAX );
    }

    




    public static function enabled() {
        return (bool) \apply_filters( self::ENABLED_FILTER, true );
    }

    





    public static function filter( $result ) {
        $resolved = self::resolve( $result, self::current_route(), self::enabled(), self::is_core_application_password_error( $result ) );

        
        
        
        
        
        if ( \is_wp_error( $result ) && self::is_own_route( self::current_route() ) ) {
            $GLOBALS[ self::OBSERVED_GLOBAL ] = array(
                'code'    => (string) $result->get_error_code(),
                'status'  => self::error_status( $result ),
                'cleared' => ( null === $resolved ),
            );
        }

        return $resolved;
    }

    


















    public static function is_core_application_password_error( $result ) {
        return \is_wp_error( $result )
            && isset( $GLOBALS['wp_rest_application_password_status'] )
            && $GLOBALS['wp_rest_application_password_status'] === $result;
    }

    




    public static function observed() {
        return ( isset( $GLOBALS[ self::OBSERVED_GLOBAL ] ) && is_array( $GLOBALS[ self::OBSERVED_GLOBAL ] ) )
            ? $GLOBALS[ self::OBSERVED_GLOBAL ]
            : null;
    }

    











    public static function resolve( $result, $route, $enabled, $core_verdict = false ) {
        if ( ! $enabled || ! \is_wp_error( $result ) || $core_verdict ) {
            return $result;
        }
        if ( ! self::is_own_route( $route ) ) {
            return $result;
        }
        if ( in_array( (string) $result->get_error_code(), self::PRESERVED_ERROR_CODES, true ) ) {
            return $result;
        }
        if ( self::CLEARED_STATUS !== self::error_status( $result ) ) {
            return $result;
        }
        return null;
    }

    







    public static function error_status( $error ) {
        $data = $error->get_error_data();
        if ( ! is_array( $data ) || ! isset( $data['status'] ) || ! is_numeric( $data['status'] ) ) {
            return null;
        }
        return (int) $data['status'];
    }

    









    public static function is_own_route( $route ) {
        $route = '/' . ltrim( (string) $route, '/' );
        return 0 === strpos( $route, self::ROUTE_PREFIX );
    }

    











    public static function current_route() {
        if ( ! isset( $GLOBALS['wp'] ) || ! is_object( $GLOBALS['wp'] ) ) {
            return '';
        }
        $vars = isset( $GLOBALS['wp']->query_vars ) ? $GLOBALS['wp']->query_vars : null;
        if ( ! is_array( $vars ) || empty( $vars['rest_route'] ) || ! is_string( $vars['rest_route'] ) ) {
            return '';
        }
        return $vars['rest_route'];
    }
}
