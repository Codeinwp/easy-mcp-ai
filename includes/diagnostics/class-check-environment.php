<?php















namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Environment {

    
    const MIN_EXECUTION_TIME = 60;

    
    const MIN_MEMORY_BYTES = 134217728; 

    const MIN_POST_MAX_SIZE = 8388608; 
    const MIN_INPUT_VARS    = 1000;

    



    const KEY_PLACEHOLDER = 'put your unique phrase here';

    


    public static function run() {
        return array(
            self::evaluate_max_execution_time( (int) ini_get( 'max_execution_time' ) ),
            self::evaluate_memory_limit( self::rest_memory_limit_bytes() ),
            self::evaluate_input_limits(
                self::bytes_from_ini( ini_get( 'post_max_size' ) ),
                (int) ini_get( 'max_input_vars' )
            ),
            self::evaluate_cipher_support( self::cipher_available() ),
            self::evaluate_auth_keys(
                defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '',
                defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '',
                self::undecryptable_credentials()
            ),
        );
    }

    



    public static function evaluate_max_execution_time( $seconds ) {
        $label = __( 'PHP max execution time', 'easy-mcp-ai' );

        if ( 0 === (int) $seconds ) {
            return Diagnostic_Result::pass( 'g1', Diagnostic_Result::TIER_WARNING, $label, __( 'No limit set.', 'easy-mcp-ai' ), array( 'max_execution_time' => 0 ) );
        }

        if ( (int) $seconds < self::MIN_EXECUTION_TIME ) {
            return Diagnostic_Result::warn(
                'g1',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %d: configured max_execution_time in seconds. */
                    __( 'Set to %d seconds. Writing a large page or post through MCP can exceed this and fail midway.', 'easy-mcp-ai' ),
                    (int) $seconds
                ),
                __( 'Raise max_execution_time to 60 seconds or more in php.ini, or ask your host to.', 'easy-mcp-ai' ),
                array( 'max_execution_time' => (int) $seconds )
            );
        }

        return Diagnostic_Result::pass( 'g1', Diagnostic_Result::TIER_WARNING, $label, sprintf( '%ds', (int) $seconds ), array( 'max_execution_time' => (int) $seconds ) );
    }

    






















    public static function rest_memory_limit_bytes() {
        $captured = isset( $GLOBALS['easy_mcp_ai_ini_memory_limit'] )
            ? (string) $GLOBALS['easy_mcp_ai_ini_memory_limit']
            : '';

        if ( '' !== $captured ) {
            return self::bytes_from_ini( $captured );
        }

        return self::bytes_from_ini( ini_get( 'memory_limit' ) );
    }

    





    public static function bytes_from_ini( $value ) {
        return self::parse_ini_bytes( $value );
    }

    








    public static function evaluate_memory_limit( $bytes ) {
        $label = __( 'PHP memory limit', 'easy-mcp-ai' );

        if ( (int) $bytes < 0 ) {
            return Diagnostic_Result::pass( 'g2', Diagnostic_Result::TIER_WARNING, $label, __( 'No limit set.', 'easy-mcp-ai' ), array( 'memory_limit' => -1 ) );
        }

        if ( (int) $bytes < self::MIN_MEMORY_BYTES ) {
            return Diagnostic_Result::warn(
                'g2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: configured memory limit, e.g. "64M". */
                    __( 'Set to %s. Listing the full tool set can exhaust this on sites with many plugin abilities.', 'easy-mcp-ai' ),
                    \size_format( (int) $bytes )
                ),
                __( 'Raise memory_limit to 128M or more, or set WP_MEMORY_LIMIT in wp-config.php.', 'easy-mcp-ai' ),
                array( 'memory_limit' => (int) $bytes )
            );
        }

        return Diagnostic_Result::pass( 'g2', Diagnostic_Result::TIER_WARNING, $label, \size_format( (int) $bytes ), array( 'memory_limit' => (int) $bytes ) );
    }

    



    public static function evaluate_input_limits( $post_max_bytes, $max_input_vars ) {
        $label   = __( 'PHP input limits', 'easy-mcp-ai' );
        $small   = array();
        $post_ok = (int) $post_max_bytes <= 0 || (int) $post_max_bytes >= self::MIN_POST_MAX_SIZE;
        $vars_ok = (int) $max_input_vars <= 0 || (int) $max_input_vars >= self::MIN_INPUT_VARS;

        if ( ! $post_ok ) {
            $small[] = 'post_max_size=' . \size_format( (int) $post_max_bytes );
        }
        if ( ! $vars_ok ) {
            $small[] = 'max_input_vars=' . (int) $max_input_vars;
        }

        $evidence = array(
            'post_max_size'  => (int) $post_max_bytes,
            'max_input_vars' => (int) $max_input_vars,
        );

        if ( ! empty( $small ) ) {
            return Diagnostic_Result::warn(
                'g3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated list of undersized PHP settings. */
                    __( 'Undersized: %s. Large tool arguments can be truncated before the tool sees them.', 'easy-mcp-ai' ),
                    implode( ', ', $small )
                ),
                __( 'Raise post_max_size to at least 8M and max_input_vars to at least 1000 in php.ini.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass( 'g3', Diagnostic_Result::TIER_WARNING, $label, '', $evidence );
    }

    



    public static function evaluate_cipher_support( $available ) {
        $label = __( 'AES-256-GCM encryption available', 'easy-mcp-ai' );

        if ( ! $available ) {
            return Diagnostic_Result::warn(
                'g4',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'This PHP build cannot perform AES-256-GCM. Stored External Data credentials (Google, DataForSEO, Semrush, SE Ranking) cannot be decrypted.', 'easy-mcp-ai' ),
                __( 'Upgrade PHP or enable the OpenSSL extension with AES-256-GCM support.', 'easy-mcp-ai' ),
                array( 'aes_256_gcm' => false )
            );
        }

        return Diagnostic_Result::pass( 'g4', Diagnostic_Result::TIER_WARNING, $label, '', array( 'aes_256_gcm' => true ) );
    }

    





    public static function evaluate_auth_keys( $key, $salt, array $undecryptable = array() ) {
        $label   = __( 'WordPress security keys usable for encryption', 'easy-mcp-ai' );
        $unusable = array();

        foreach ( array( 'SECURE_AUTH_KEY' => $key, 'SECURE_AUTH_SALT' => $salt ) as $name => $value ) {
            if ( ! is_string( $value ) || '' === trim( $value ) || false !== stripos( $value, self::KEY_PLACEHOLDER ) ) {
                $unusable[] = $name;
            }
        }

        if ( ! empty( $unusable ) ) {
            return Diagnostic_Result::warn(
                'g5',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated list of WordPress constant names. */
                    __( 'Missing or unedited: %s. External Data credentials are encrypted with keys derived from these.', 'easy-mcp-ai' ),
                    implode( ', ', $unusable )
                ),
                __( 'Generate fresh values at https://api.wordpress.org/secret-key/1.1/salt/ and set them in wp-config.php. Note that changing them makes already-saved External Data credentials unreadable, so re-enter those afterwards.', 'easy-mcp-ai' ),
                array( 'unusable_keys' => $unusable )
            );
        }

        
        
        
        
        if ( ! empty( $undecryptable ) ) {
            return self::credentials_unreadable( $label, $undecryptable );
        }

        return Diagnostic_Result::pass( 'g5', Diagnostic_Result::TIER_WARNING, $label, '', array( 'unusable_keys' => array() ) );
    }

    








    const CREDENTIAL_OPTIONS = array(
        array( 'label' => 'Google Analytics',  'option' => 'easy_mcp_ai_ga_service_account_json',  'class' => '\Easy_MCP_AI\GA\GA_Client' ),
        array( 'label' => 'Search Console',    'option' => 'easy_mcp_ai_gsc_service_account_json', 'class' => '\Easy_MCP_AI\GSC\GSC_Client' ),
        array( 'label' => 'DataForSEO login',  'option' => 'easy_mcp_ai_dfs_login',                'class' => '\Easy_MCP_AI\DFS\DataforSEO_Client' ),
        array( 'label' => 'DataForSEO password', 'option' => 'easy_mcp_ai_dfs_api_password',       'class' => '\Easy_MCP_AI\DFS\DataforSEO_Client' ),
        array( 'label' => 'Semrush',           'option' => 'easy_mcp_ai_semrush_api_key',          'class' => '\Easy_MCP_AI\Semrush\Semrush_Client' ),
        array( 'label' => 'SE Ranking',        'option' => 'easy_mcp_ai_seranking_api_key',        'class' => '\Easy_MCP_AI\SeRanking\SeRanking_Client' ),
    );

    




















    public static function undecryptable_credentials() {
        $failed = array();

        foreach ( self::CREDENTIAL_OPTIONS as $provider ) {
            $stored = \get_option( $provider['option'], '' );
            if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
                continue; 
            }

            if ( ! class_exists( $provider['class'] ) || ! method_exists( $provider['class'], 'decrypt' ) ) {
                continue; 
            }

            try {
                $plain = call_user_func( array( $provider['class'], 'decrypt' ), $stored );
            } catch ( \Throwable $e ) {
                $plain = false;
            }

            if ( false === $plain || '' === (string) $plain ) {
                $failed[] = $provider['label'];
            }
            unset( $plain );
        }

        return $failed;
    }

    
    private static function credentials_unreadable( $label, array $undecryptable ) {
        return Diagnostic_Result::warn(
            'g5',
            Diagnostic_Result::TIER_WARNING,
            $label,
            sprintf(
                /* translators: %s: comma-separated provider names. */
                __( 'Saved credentials for %s can no longer be read. This happens when the WordPress security keys in wp-config.php are changed after the credentials were saved — the keys they were encrypted with no longer exist, so the tools for those services fail on every call.', 'easy-mcp-ai' ),
                implode( ', ', $undecryptable )
            ),
            __( 'Open Easy MCP AI → Tools → External data and re-enter the credentials for the services listed above.', 'easy-mcp-ai' ),
            array( 'undecryptable_credentials' => $undecryptable )
        );
    }

    

    private static function cipher_available() {
        if ( ! function_exists( 'openssl_get_cipher_methods' ) ) {
            return false;
        }
        $methods = openssl_get_cipher_methods();
        return is_array( $methods ) && in_array( 'aes-256-gcm', array_map( 'strtolower', $methods ), true );
    }

    





    private static function parse_ini_bytes( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return 0;
        }
        if ( '-1' === $value ) {
            return -1;
        }

        $unit   = strtolower( substr( $value, -1 ) );
        $number = (int) $value;

        switch ( $unit ) {
            case 'g':
                return $number * 1024 * 1024 * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'k':
                return $number * 1024;
            default:
                return $number;
        }
    }
}
