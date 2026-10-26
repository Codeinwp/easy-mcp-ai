<?php

namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Config {
    















    const VALIDATE_STORED = array(
        'oauth_enabled',
        'change_log_db_rows_per_call',
        'oauth_allow_http',
        'hide_admin',
        'hide_plugin_row',
        'brand_name',
        'self_service_keys',
        'self_service_max_keys',
        
        
        'api_key_site_host',
        
        'paused',
        
        'tasks_tick_budget_seconds',
        'tasks_retention_days',
        'tasks_background',
        'tasks_max_per_credential',
        'tasks_max_concurrent_ticks',
        
        
        
        'approval_required',
        'approval_always',
        'approval_never',
        
        'oauth_root_fallback',
        
        'connect_roles',
        
        'skills_sync',
        
        'api_key_header_only',
        
        'oauth_cimd',
    );

    






    const BUCKET_PATTERN = '/^(?:easy_mcp_ai_)?disabled_(ga|gsc|dfs|semrush|ahrefs|seranking|plugin)_tools$/';

    
    public static function settings() {
        return array(
            'rate_limit_per_minute' => array( 60, 'integer', 1, 1000 ),
            'force_draft_on_create' => array( false, 'boolean' ),
            'max_title_length' => array( 0, 'integer', 0, 2000 ),
            'audit_log_enabled' => array( true, 'boolean' ),
            'audit_log_retention' => array( 30, 'integer', 1, 365 ),
            'disabled_tools' => array( array(), 'array' ),
            'allowed_tool_patterns' => array( array(), 'array' ),
            'ip_whitelist' => array( '', 'ip_list' ),
            'self_service_keys' => array( false, 'boolean' ),
            'self_service_max_keys' => array( 10, 'integer', 1, 1000 ),
            
            
            'api_key_site_host' => array( '', 'host' ),
            
            
            
            
            'api_key_header_only' => array( false, 'hardening' ),
            'oauth_min_capability' => array( 'publish_posts', 'capability' ),
            
            'connect_roles' => array( array(), 'array' ),
            'external_data_min_capability' => array( 'manage_options', 'capability' ),
            'trusted_proxies' => array( array(), 'proxies' ),
            'client_ip_header' => array( 'X-Forwarded-For', 'header' ),
            'oauth_access_token_ttl' => array( 3600, 'integer', 60, PHP_INT_MAX ),
            'oauth_refresh_token_ttl' => array( 2592000, 'integer', 60, PHP_INT_MAX ),
            'oauth_dcr_enabled' => array( true, 'boolean' ),
            
            
            
            
            
            'oauth_cimd' => array( true, 'opt_out' ),
            'oauth_max_clients' => array( 5000, 'integer', 0, PHP_INT_MAX ),
            'oauth_client_retention' => array( 7, 'integer', 0, 3650 ),
            'oauth_enabled' => array( true, 'boolean' ),
            'oauth_allow_http' => array( false, 'boolean' ),
            
            
            
            
            'oauth_root_fallback' => array( 'auto', 'fallback_mode' ),
            'change_log_enabled' => array( true, 'boolean' ),
            'change_log_retention' => array( 30, 'integer', 0, PHP_INT_MAX ),
            'change_log_option_mode' => array( 'all_except_denylist', 'mode' ),
            'change_log_capture_meta' => array( true, 'boolean' ),
            'change_log_capture_db' => array( false, 'boolean' ),
            'change_log_db_retention' => array( 7, 'integer', 0, PHP_INT_MAX ),
            'change_log_db_rows_per_call' => array( 200, 'integer', 0, PHP_INT_MAX ),
            'change_log_external_intent' => array( true, 'boolean' ),
            
            'tasks_tick_budget_seconds' => array( 0, 'integer', 0, 300 ),
            'tasks_retention_days' => array( 7, 'integer', 1, 365 ),
            
            'tasks_background' => array( true, 'boolean' ),
            
            'tasks_max_per_credential' => array( 5, 'integer', 1, 100 ),
            'tasks_max_concurrent_ticks' => array( 3, 'integer', 1, 50 ),
            
            
            
            
            
            'approval_required' => array( false, 'boolean' ),
            'approval_always' => array( array(), 'array' ),
            'approval_never' => array( array(), 'array' ),
            'hide_admin' => array( false, 'visibility' ),
            'hide_plugin_row' => array( false, 'boolean' ),
            'brand_name' => array( 'Easy MCP AI', 'brand' ),
            
            
            'paused' => array( false, 'paused' ),
            
            
            
            
            'skills_sync' => array( true, 'opt_out' ),
            
            
            
            'ga_default_property_id' => array( '', 'ga_property' ),
            'gsc_default_site_url' => array( '', 'gsc_site' ),
        );
    }

    public static function brand( $text ) {
        return str_replace( 'Easy MCP AI', self::get( 'brand_name' ), $text );
    }

    private static function suffix( $option ) {
        return preg_replace( '/^easy_mcp_ai_/', '', $option );
    }

    public static function constant_name( $option ) {
        return 'EASY_MCP_AI_' . strtoupper( self::suffix( $option ) );
    }

    
    public static function source( $option ) {
        if ( ! isset( self::settings()[ self::suffix( $option ) ] ) ) {
            return '';
        }
        $name = self::constant_name( $option );
        if ( defined( $name ) ) {
            return 'constant';
        }
        return false !== getenv( $name ) ? 'environment' : '';
    }

    




    public static function is_white_labelled() {
        return self::is_locked( 'brand_name' );
    }

    public static function is_locked( $option ) {
        
        if ( preg_match( self::BUCKET_PATTERN, $option ) ) {
            $option = 'disabled_tools';
        }
        return '' !== self::source( $option );
    }

    
    public static function get( $option, $default = false ) {
        $suffix = self::suffix( $option );
        $spec = self::settings()[ $suffix ] ?? null;
        if ( preg_match( self::BUCKET_PATTERN, $suffix ) && self::is_locked( 'disabled_tools' ) ) {
            return self::get( 'disabled_tools' );
        }
        if ( null === $spec ) {
            return \get_option( $option, $default );
        }
        $source = self::source( $suffix );
        if ( '' === $source ) {
            return self::stored( $suffix, \get_option( 'easy_mcp_ai_' . $suffix, func_num_args() > 1 ? $default : $spec[0] ) );
        }
        $name = self::constant_name( $suffix );
        $raw = 'constant' === $source ? constant( $name ) : getenv( $name );
        return self::validate( $suffix, $raw );
    }

    





    private static function stored( $suffix, $stored ) {
        return in_array( $suffix, self::VALIDATE_STORED, true ) ? self::validate_stored( $suffix, $stored ) : $stored;
    }

    
    private static function validate_stored( $suffix, $stored ) {
        
        
        if ( 'boolean' === self::settings()[ $suffix ][1] && '' === $stored ) {
            return false;
        }
        return self::validate( $suffix, $stored );
    }

    
    public static function validate( $suffix, $raw, &$valid = null ) {
        $spec = self::settings()[ $suffix ];
        $valid = true;
        switch ( $spec[1] ) {
            case 'visibility':
                if ( 'menu' === $raw ) {
                    return 'menu';
                }
                
            case 'boolean':
                if ( in_array( $raw, array( true, 1, '1', 'true' ), true ) ) {
                    return true;
                }
                if ( in_array( $raw, array( false, 0, '0', 'false' ), true ) ) {
                    return false;
                }
                break;
            case 'opt_out':
                if ( in_array( $raw, array( true, 1, '1', 'true' ), true ) ) {
                    return true;
                }
                if ( in_array( $raw, array( null, false, 0, '0', '', 'false' ), true ) ) {
                    return false;
                }
                
                
                $valid = false;
                return false;
            case 'paused':
            case 'hardening':
                if ( in_array( $raw, array( null, false, 0, '0', '', 'false' ), true ) ) {
                    return false;
                }
                if ( in_array( $raw, array( true, 1, '1', 'true' ), true ) ) {
                    return true;
                }
                
                
                
                $valid = false;
                return true;
            case 'integer':
                if ( ( is_int( $raw ) || ( is_string( $raw ) && preg_match( '/^\d+$/D', $raw ) ) )
                    && $raw >= $spec[2] && $raw <= $spec[3] ) {
                    if ( in_array( $suffix, array( 'oauth_access_token_ttl', 'oauth_refresh_token_ttl' ), true ) ) {
                        
                        
                        
                        $max_timestamp = min( PHP_INT_MAX, 253402300799 );
                        if ( $raw > $max_timestamp - time() ) {
                            break;
                        }
                    }
                    if ( in_array( $suffix, array( 'change_log_retention', 'change_log_db_retention' ), true ) ) {
                        
                        
                        $max_days = min( intdiv( PHP_INT_MAX, 86400 ), floor( ( time() + 30610224000 ) / 86400 ) );
                        if ( $raw > $max_days ) {
                            break;
                        }
                    }
                    return (int) $raw;
                }
                break;
            case 'capability':
                if ( in_array( $raw, array( 'publish_posts', 'edit_others_posts', 'manage_options' ), true ) ) {
                    return $raw;
                }
                break;
            case 'mode':
                if ( in_array( $raw, array( 'all_except_denylist', 'allowlist' ), true ) ) {
                    return $raw;
                }
                break;
            case 'fallback_mode':
                if ( in_array( $raw, array( 'auto', 'on', 'off' ), true ) ) {
                    return $raw;
                }
                break;
            case 'header':
                if ( is_string( $raw ) ) {
                    foreach ( array_keys( Client_IP::HEADERS ) as $header ) {
                        if ( 0 === strcasecmp( trim( $raw ), $header ) ) {
                            return $header;
                        }
                    }
                }
                break;
            case 'brand':
                if ( is_string( $raw ) && '' !== trim( $raw ) && strip_tags( $raw ) === $raw ) {
                    return trim( $raw );
                }
                break;
            case 'host':
                if ( is_string( $raw ) ) {
                    if ( '' === trim( $raw ) ) {
                        return ''; 
                    }
                    $host = Site_Host::normalize( $raw );
                    if ( '' !== $host ) {
                        return $host;
                    }
                }
                break;
            case 'ga_property':
            case 'gsc_site':
                if ( is_string( $raw ) ) {
                    if ( '' === trim( $raw ) ) {
                        return ''; 
                    }
                    try {
                        return 'ga_property' === $spec[1]
                            ? GA\GA_Client::normalize_property( $raw )
                            : GSC\GSC_Client::validate_site_url( $raw );
                    } catch ( \Exception $e ) {
                        break;
                    }
                }
                break;
            case 'proxies':
                if ( is_string( $raw ) || is_array( $raw ) ) {
                    $entries = is_array( $raw ) ? $raw : preg_split( '/[\s,]+/', preg_replace( '/#[^\r\n]*/', '', $raw ) );
                    foreach ( $entries as $entry ) {
                        if ( ! is_string( $entry ) || ( '' !== trim( $entry, " \t[]" ) && ! Client_IP::is_ip_or_cidr( trim( $entry, " \t[]" ) ) ) ) {
                            $valid = false;
                            break;
                        }
                    }
                    
                    
                    
                    
                    
                    return Client_IP::parse_trusted_proxies( $raw );
                }
                break;
            case 'array':
            case 'ip_list':
                $values = is_string( $raw ) ? preg_split( '/[,\r\n]+/', $raw ) : $raw;
                if ( is_array( $values ) ) {
                    $clean = array();
                    foreach ( $values as $value ) {
                        if ( ! is_string( $value ) ) {
                            $valid = false;
                            break;
                        }
                        $value = trim( $value );
                        if ( 'ip_list' === $spec[1] ) {
                            $value = trim( explode( '#', $value, 2 )[0] );
                            if ( '' !== $value && ! Client_IP::is_ip_or_cidr( $value ) ) {
                                $valid = false;
                                break;
                            }
                        }
                        if ( '' !== $value ) {
                            $clean[] = $value;
                        }
                    }
                    if ( $valid ) {
                        return 'ip_list' === $spec[1] ? implode( "\n", $clean ) : array_values( array_unique( $clean ) );
                    }
                }
                break;
        }
        $valid = false;
        
        return 'ip_list' === $spec[1] ? 'invalid-config-deny-all' : $spec[0];
    }

    
    public static function tool_policy_valid() {
        foreach ( array( 'disabled_tools', 'allowed_tool_patterns' ) as $suffix ) {
            $source = self::source( $suffix );
            if ( '' !== $source ) {
                $name = self::constant_name( $suffix );
                self::validate( $suffix, 'constant' === $source ? constant( $name ) : getenv( $name ), $valid );
                if ( ! $valid ) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function delete( $option ) {
        return self::is_locked( $option ) ? false : \delete_option( $option );
    }

    
    public static function update( $option, $value ) {
        if ( self::is_locked( $option ) ) {
            return false;
        }
        
        
        if ( false === $value && null === \get_option( $option, null ) ) {
            return \add_option( $option, false );
        }
        return \update_option( $option, $value );
    }

    
    public static function controlled() {
        $result = array();
        foreach ( self::settings() as $suffix => $spec ) {
            if ( self::is_locked( $suffix ) ) {
                $result[ self::constant_name( $suffix ) ] = self::source( $suffix );
            }
        }
        return $result;
    }

    
    public static function export() {
        $lines = array();
        foreach ( self::settings() as $suffix => $spec ) {
            
            
            $stored = self::stored( $suffix, \get_option( 'easy_mcp_ai_' . $suffix, $spec[0] ) );
            
            if ( 'boolean' === $spec[1] || 'opt_out' === $spec[1] ) {
                $stored = (bool) $stored;
            }
            $lines[] = 'define( ' . var_export( self::constant_name( $suffix ), true ) . ', '
                . var_export( $stored, true ) . ' );';
        }
        return implode( "\n", $lines ) . "\n";
    }
}
