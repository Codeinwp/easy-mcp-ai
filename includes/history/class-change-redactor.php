<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Change_Redactor {
    const MAX_BYTES = 262144; 

    private static $exact_keys = array(
        'user_pass', 'user_activation_key', 'session_tokens', 'user_email',
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        'customer_ip_address', 'customer_user_agent',
    );

    private static $substring_keys = array(
        '_secret', '_token', 'api_key', 'apikey', 'password', 'secret',
    );

    







    private static $pattern_keys = '/pass|token|private|_hash$|api_?key/i';

    








    private static $sensitive_option_names = array(
        'ninja_forms_settings',
        'srfm_security_settings_options',
        'nexter_extra_ext_options',
        'nexter_site_security',
    );

    


















    private static $address_blocks = array( 'billing', 'shipping' );

    private static $address_personal_keys = array(
        'first_name', 'last_name', 'company',
        'address_1', 'address_2', 'postcode',
        'email', 'phone',
    );

    






























    private static $address_flat_pattern =
        '/^_?(?:billing|shipping)_(?:first_name|last_name|company|address_1|address_2|address_index|postcode|email|phone)$/i';

    




















    private static $table_personal_columns = array(
        'wc_orders'           => array( 'ip_address', 'user_agent' ),
        'wc_order_addresses'  => array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'postcode', 'email', 'phone' ),
    );

    
    private static $sensitive_option_prefixes = array(
        'ppcp-bearer', 'wdkit_auth_',
    );

    







    










    public static function redact_for_table( $value, $table ) {
        $columns = self::personal_columns_for( $table );
        if ( ! $columns || ! is_array( $value ) ) {
            return self::redact( $value );
        }
        $out = array();
        foreach ( $value as $k => $v ) {
            $out[ $k ] = in_array( strtolower( (string) $k ), $columns, true ) ? '[REDACTED]' : $v;
        }
        return self::redact( $out );
    }

    
    private static function personal_columns_for( $table ) {
        $table = strtolower( (string) $table );
        if ( '' === $table ) {
            return array();
        }
        $map = self::$table_personal_columns;
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = \apply_filters( 'easy_mcp_ai_change_log_table_personal_columns', $map );
            if ( is_array( $filtered ) ) {
                $map = $filtered;
            }
        }
        foreach ( $map as $name => $columns ) {
            $name = strtolower( (string) $name );
            
            
            if ( $table === $name || substr( $table, -strlen( '_' . $name ) ) === '_' . $name ) {
                return array_map( 'strtolower', (array) $columns );
            }
        }
        return array();
    }

    public static function redact( $value ) {
        if ( ! is_array( $value ) ) {
            return array( 'value' => $value, 'truncated' => false, 'size_bytes' => is_string( $value ) ? strlen( $value ) : 0 );
        }
        $truncated = false;
        $size      = 0;
        
        
        
        
        
        
        
        
        
        
        
        $blocks   = self::address_blocks();
        $personal = self::address_personal_keys();
        $out      = self::walk( $value, $truncated, $size, '', $blocks, $personal );
        return array( 'value' => $out, 'truncated' => $truncated, 'size_bytes' => $size );
    }

    



    private static function walk( $value, &$truncated, &$size, $parent_key = '', $blocks = null, $personal = null ) {
        if ( is_array( $value ) ) {
            
            
            
            if ( null === $blocks ) {
                $blocks = self::address_blocks();
            }
            if ( null === $personal ) {
                $personal = self::address_personal_keys();
            }
            $in_address = in_array( (string) $parent_key, $blocks, true );

            
            
            
            
            
            
            
            
            
            
            
            
            
            
            if ( isset( $value['key'] ) && is_string( $value['key'] )
                && array_key_exists( 'value', $value )
                && self::is_sensitive( $value['key'] )
            ) {
                $pair          = $value;
                $pair['value'] = '[REDACTED]';
                return $pair;
            }

            $out = array();
            foreach ( $value as $k => $v ) {
                $key = (string) $k;
                if ( self::is_sensitive( $key ) ) {
                    $out[ $k ] = '[REDACTED]';
                    continue;
                }
                if ( $in_address && in_array( $key, $personal, true ) ) {
                    $out[ $k ] = '[REDACTED]';
                    continue;
                }
                $out[ $k ] = self::walk( $v, $truncated, $size, $key, $blocks, $personal );
            }
            return $out;
        }
        if ( is_string( $value ) ) {
            $len   = strlen( $value );
            $size += $len;
            if ( $len > self::MAX_BYTES ) {
                $truncated = true;
                return substr( $value, 0, self::MAX_BYTES );
            }
        }
        return $value;
    }

    
    private static function address_blocks() {
        $list = self::$address_blocks;
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = \apply_filters( 'easy_mcp_ai_change_log_address_blocks', $list );
            if ( is_array( $filtered ) ) {
                $list = $filtered;
            }
        }
        return array_map( 'strval', $list );
    }

    
    private static function address_personal_keys() {
        $list = self::$address_personal_keys;
        if ( function_exists( 'apply_filters' ) ) {
            $filtered = \apply_filters( 'easy_mcp_ai_change_log_address_personal_keys', $list );
            if ( is_array( $filtered ) ) {
                $list = $filtered;
            }
        }
        return array_map( 'strval', $list );
    }

    




    public static function key_is_sensitive( $key ) {
        return self::is_sensitive( (string) $key );
    }

    





    public static function option_name_is_sensitive( $name ) {
        $name = strtolower( (string) $name );
        if ( self::is_sensitive( $name ) ) {
            return true;
        }
        if ( '_key' === substr( $name, -4 ) ) {
            return true;
        }
        $known = (array) \apply_filters( 'easy_mcp_ai_change_log_sensitive_option_names', self::$sensitive_option_names );
        foreach ( $known as $entry ) {
            if ( strtolower( (string) $entry ) === $name ) {
                return true;
            }
        }
        foreach ( self::$sensitive_option_prefixes as $prefix ) {
            if ( 0 === strpos( $name, strtolower( $prefix ) ) ) {
                return true;
            }
        }
        return false;
    }

    private static function is_sensitive( $key ) {
        $lc = strtolower( $key );
        if ( in_array( $lc, self::$exact_keys, true ) ) {
            return true;
        }
        foreach ( self::$substring_keys as $needle ) {
            if ( strpos( $lc, $needle ) !== false ) {
                return true;
            }
        }
        if ( preg_match( self::$address_flat_pattern, $lc ) ) {
            return true;
        }
        return (bool) preg_match( self::$pattern_keys, $lc );
    }
}
