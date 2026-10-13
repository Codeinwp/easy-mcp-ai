<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/auth/class-token-keys.php';































class Device_Authorization {

    




    const NAMESPACE_V1 = 'easy-mcp-ai/v1';

    





    const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:device_code';

    







    const CODE_LIFETIME = 600;

    





    const POLL_INTERVAL = 5;

    












    const USER_CODE_ALPHABET = 'BCDFGHJKLMNPQRSTVWXZ';

    




    const USER_CODE_LENGTH = 8;

    







    const USER_CODE_ATTEMPTS = 5;

    



    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED   = 'denied';
    const STATUS_CONSUMED = 'consumed';

    






    const RATE_LIMIT_PER_HOUR        = 10;
    const GLOBAL_RATE_LIMIT_PER_HOUR = 100;

    
















    public static function verification_uri() {
        return home_url( '?easy_mcp_ai_oauth=device' );
    }

    





    public function handle_device_request( \WP_REST_Request $request ) {
        
        
        $transport_error = Token_Endpoint::enforce_transport_security();
        if ( null !== $transport_error ) {
            return $transport_error;
        }

        $rate_error = self::enforce_rate_limit();
        if ( null !== $rate_error ) {
            return $rate_error;
        }

        
        
        
        $query_error = Token_Endpoint::reject_query_credentials( $request, array( 'client_secret' ) );
        if ( null !== $query_error ) {
            return $query_error;
        }

        
        
        
        
        
        
        $basic     = Token_Endpoint::read_basic_credentials( $request );
        $client_id = Token_Endpoint::resolve_client_id( $request, $basic );
        if ( $client_id instanceof \WP_REST_Response ) {
            return $client_id;
        }
        if ( '' === $client_id ) {
            return self::device_error( 'invalid_request', __( 'Missing required parameter: client_id', 'easy-mcp-ai' ) );
        }

        
        
        $client = ( new Client_Registry() )->get_client( $client_id );
        if ( null === $client ) {
            return self::device_error( 'invalid_client', __( 'Client not found or inactive.', 'easy-mcp-ai' ), 401 );
        }

        $auth_error = Token_Endpoint::authenticate_client( $request, $client, $basic );
        if ( null !== $auth_error ) {
            return $auth_error;
        }

        
        
        
        
        
        if ( ! self::client_supports_device_grant( $client ) ) {
            return self::device_error( 'unauthorized_client', __( 'This client is not registered for the device authorization grant. Register with grant_types including urn:ietf:params:oauth:grant-type:device_code.', 'easy-mcp-ai' ) );
        }

        
        
        
        $requested_scope = sanitize_text_field( $request->get_param( 'scope' ) );
        if ( '' === $requested_scope ) {
            $requested_scope = Scope_Map::get_default_scope();
        }
        $scope_list   = array_values( array_filter( array_map( 'trim', explode( ' ', $requested_scope ) ) ) );
        $scope_list   = Scope_Map::apply_legacy_scope_upgrades( $scope_list );
        $valid_scopes = Scope_Map::get_all_scopes();
        foreach ( $scope_list as $s ) {
            if ( 'mcp' !== $s && ! in_array( $s, $valid_scopes, true ) ) {
                return self::device_error( 'invalid_scope', __( 'Unknown scope requested.', 'easy-mcp-ai' ) );
            }
        }
        $scope = implode( ' ', $scope_list );

        
        
        
        $resource_raw = $request->get_param( 'resource' );
        $resource     = is_string( $resource_raw ) && '' !== $resource_raw
            ? esc_url_raw( $resource_raw )
            : rest_url( self::NAMESPACE_V1 . '/mcp' );
        if ( ! Token_Endpoint::resource_matches( $resource, rest_url( self::NAMESPACE_V1 . '/mcp' ) ) ) {
            return self::device_error( 'invalid_target', __( 'Resource parameter does not match this server.', 'easy-mcp-ai' ) );
        }

        $device_code = bin2hex( random_bytes( 32 ) );
        $user_code   = self::store_pending( $device_code, $client_id, $scope, $resource );
        if ( null === $user_code ) {
            return self::device_error( 'server_error', __( 'Failed to store the device authorization request.', 'easy-mcp-ai' ), 500 );
        }

        $response = new \WP_REST_Response(
            array(
                'device_code'      => $device_code,
                'user_code'        => self::format_user_code( $user_code ),
                'verification_uri' => self::verification_uri(),
                'expires_in'       => self::CODE_LIFETIME,
                'interval'         => self::POLL_INTERVAL,
            ),
            200
        );
        
        
        
        $response->header( 'Content-Type', 'application/json' );
        $response->header( 'Cache-Control', 'no-store' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        return $response;
    }

    





    public static function client_supports_device_grant( $client ) {
        if ( ! is_object( $client ) || empty( $client->grant_types ) ) {
            return false;
        }
        $grant_types = is_array( $client->grant_types ) ? $client->grant_types : json_decode( (string) $client->grant_types, true );
        return is_array( $grant_types ) && in_array( self::GRANT_TYPE, $grant_types, true );
    }

    
    
    

    





    public static function generate_user_code() {
        $alphabet = self::USER_CODE_ALPHABET;
        $max      = strlen( $alphabet ) - 1;
        $code     = '';
        for ( $i = 0; $i < self::USER_CODE_LENGTH; $i++ ) {
            $code .= $alphabet[ random_int( 0, $max ) ];
        }
        return $code;
    }

    





    public static function format_user_code( $normalized ) {
        $normalized = (string) $normalized;
        if ( self::USER_CODE_LENGTH !== strlen( $normalized ) ) {
            return $normalized;
        }
        return substr( $normalized, 0, 4 ) . '-' . substr( $normalized, 4 );
    }

    









    public static function normalize_user_code( $raw ) {
        if ( ! is_scalar( $raw ) ) {
            return '';
        }
        $upper = strtoupper( trim( (string) $raw ) );
        $out   = '';
        $len   = strlen( $upper );
        for ( $i = 0; $i < $len; $i++ ) {
            if ( false !== strpos( self::USER_CODE_ALPHABET, $upper[ $i ] ) ) {
                $out .= $upper[ $i ];
            }
        }
        return self::USER_CODE_LENGTH === strlen( $out ) ? $out : '';
    }

    





    public static function hash_user_code( $normalized ) {
        return hash( 'sha256', 'user_code|' . $normalized );
    }

    
    
    

    




    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'easy_mcp_ai_oauth_device_codes';
    }

    













    private static function store_pending( $device_code, $client_id, $scope, $resource ) {
        global $wpdb;

        $table   = self::table();
        $now     = time();
        $created = gmdate( 'Y-m-d H:i:s', $now );
        $expires = gmdate( 'Y-m-d H:i:s', $now + self::CODE_LIFETIME );

        for ( $attempt = 0; $attempt < self::USER_CODE_ATTEMPTS; $attempt++ ) {
            $user_code = self::generate_user_code();

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
            $inserted = $wpdb->insert(
                $table,
                array(
                    'device_code_hash' => \Easy_MCP_AI\Auth\Token_Keys::hash_current( $device_code ),
                    'user_code_hash'   => self::hash_user_code( $user_code ),
                    'client_id'        => $client_id,
                    'wp_user_id'       => 0,
                    'resource'         => $resource,
                    'scope'            => $scope,
                    'status'           => self::STATUS_PENDING,
                    'expires_at'       => $expires,
                    'created_at'       => $created,
                ),
                array( '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
            );

            if ( false !== $inserted ) {
                return $user_code;
            }

            
            if ( null === self::find_by_user_code( $user_code ) ) {
                return null;
            }
        }

        return null;
    }

    






    public static function find_by_user_code( $normalized ) {
        global $wpdb;
        if ( '' === (string) $normalized ) {
            return null;
        }
        $table = self::table();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the lookup must be fresh.
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE user_code_hash = %s LIMIT 1", self::hash_user_code( $normalized ) )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_object( $row ) ? $row : null;
    }

    





    public static function find_by_device_code( $device_code ) {
        global $wpdb;
        if ( '' === (string) $device_code ) {
            return null;
        }
        $table = self::table();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the lookup must be fresh.
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE device_code_hash = %s LIMIT 1", \Easy_MCP_AI\Auth\Token_Keys::hash_current( (string) $device_code ) )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_object( $row ) ? $row : null;
    }

    







    public static function is_expired( $row ) {
        return empty( $row->expires_at ) || (string) $row->expires_at < gmdate( 'Y-m-d H:i:s' );
    }

    

















    public static function record_decision( $row_id, $approved, $wp_user_id, $scope = '', $consent_id = 0 ) {
        global $wpdb;
        $table = self::table();
        $now   = gmdate( 'Y-m-d H:i:s' );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the conditional UPDATE is the atomicity guarantee.
        if ( $approved ) {
            $affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, wp_user_id = %d, consent_id = %d, scope = %s, decided_at = %s WHERE id = %d AND status = %s AND expires_at > %s",
                    self::STATUS_APPROVED,
                    (int) $wp_user_id,
                    (int) $consent_id,
                    (string) $scope,
                    $now,
                    (int) $row_id,
                    self::STATUS_PENDING,
                    $now
                )
            );
        } else {
            $affected = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, wp_user_id = %d, decided_at = %s WHERE id = %d AND status = %s AND expires_at > %s",
                    self::STATUS_DENIED,
                    (int) $wp_user_id,
                    $now,
                    (int) $row_id,
                    self::STATUS_PENDING,
                    $now
                )
            );
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return 1 === (int) $affected;
    }

    





    public static function touch_polled( $row_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
        $wpdb->update(
            self::table(),
            array( 'last_polled_at' => gmdate( 'Y-m-d H:i:s' ) ),
            array( 'id' => (int) $row_id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    








    public static function claim_approved( $row_id ) {
        global $wpdb;
        $table = self::table();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the conditional UPDATE is the atomicity guarantee.
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s WHERE id = %d AND status = %s",
                self::STATUS_CONSUMED,
                (int) $row_id,
                self::STATUS_APPROVED
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return 1 === (int) $affected;
    }

    
    
    

    







    public static function device_error( $error, $description, $status = 400 ) {
        $response = new \WP_REST_Response(
            array(
                'error'             => $error,
                'error_description' => Token_Endpoint::wire_safe_description( $description ),
            ),
            $status
        );
        $response->header( 'Cache-Control', 'no-store' );
        $response->header( 'Pragma', 'no-cache' );
        if ( 401 === (int) $status ) {
            $response->header( 'WWW-Authenticate', sprintf( 'Bearer realm="oauth", error="%s"', $error ) );
        }
        return $response;
    }

    










    public static function enforce_rate_limit() {
        $ip = \Easy_MCP_AI\Client_IP::get();

        $per_ip_key = 'easy_mcp_ai_device_rl_' . md5( $ip );
        $global_key = 'easy_mcp_ai_device_rl_global';

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $per_ip_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $ip_count = \wp_cache_incr( $per_ip_key, 1, 'easy_mcp_ai' );

            \wp_cache_add( $global_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $global_count = \wp_cache_incr( $global_key, 1, 'easy_mcp_ai' );
        } else {
            $ip_count     = Token_Endpoint::rl_transient_increment( $per_ip_key );
            $global_count = Token_Endpoint::rl_transient_increment( $global_key );
        }

        if ( $ip_count > self::RATE_LIMIT_PER_HOUR || $global_count > self::GLOBAL_RATE_LIMIT_PER_HOUR ) {
            return new \WP_REST_Response( null, 429 );
        }

        return null;
    }
}
