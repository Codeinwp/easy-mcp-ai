<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Client_Registry {

    




    const MAX_CLIENT_NAME_LENGTH = 120;

    
    
    const MAX_SOFTWARE_ID_LENGTH      = 255;
    const MAX_SOFTWARE_VERSION_LENGTH = 64;

    




    const DEFAULT_MAX_CLIENTS = 5000;

    




    const RATE_LIMIT_PER_HOUR = 10;

    





    const GLOBAL_RATE_LIMIT_PER_HOUR = 100;

    













    const AUTH_METHOD_NONE  = 'none';
    const AUTH_METHOD_POST  = 'client_secret_post';
    const AUTH_METHOD_BASIC = 'client_secret_basic';

    const SUPPORTED_AUTH_METHODS = array(
        self::AUTH_METHOD_NONE,
        self::AUTH_METHOD_POST,
        self::AUTH_METHOD_BASIC,
    );

    










    public static function is_dcr_enabled() {
        return (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_oauth_dcr_enabled', true );
    }

    








    public function handle_register( \WP_REST_Request $request ) {
        
        
        $transport_error = Token_Endpoint::enforce_transport_security();
        if ( null !== $transport_error ) {
            return $transport_error;
        }

        
        if ( ! self::is_dcr_enabled() ) {
            
            
            
            
            
            
            return self::dcr_error(
                'registration_not_supported',
                __( 'Dynamic Client Registration is disabled on this site. An administrator can enable it under Easy MCP AI > Connections > OAuth > Token lifetimes and client registration.', 'easy-mcp-ai' ),
                404
            );
        }

        
        $rate_result = $this->check_rate_limit();
        if ( $rate_result instanceof \WP_REST_Response ) {
            return $rate_result;
        }

        
        $content_type = $request->get_content_type();
        $ct_value     = is_array( $content_type ) && isset( $content_type['value'] ) ? strtolower( $content_type['value'] ) : '';
        if ( 'application/json' !== $ct_value ) {
            return self::dcr_error( 'invalid_client_metadata', __( 'Request Content-Type must be application/json.', 'easy-mcp-ai' ), 400 );
        }

        
        $body = $request->get_json_params();
        if ( empty( $body ) || ! is_array( $body ) ) {
            return self::dcr_error( 'invalid_client_metadata', __( 'Request body must be a JSON object.', 'easy-mcp-ai' ), 400 );
        }

        
        $validated = $this->validate_request_body( $body );
        if ( $validated instanceof \WP_REST_Response ) {
            return $validated;
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $this->cleanup_stale_duplicates( $validated['client_name'], wp_json_encode( $validated['redirect_uris'] ) );

        
        $cap_error = $this->check_client_cap();
        if ( $cap_error instanceof \WP_REST_Response ) {
            return $cap_error;
        }

        
        return $this->persist_client( $validated );
    }

    








    private function validate_request_body( array $body ) {
        $grant_types    = array( 'authorization_code' );
        $response_types = array( 'code' );

        $supported_grant_types    = array( 'authorization_code', 'refresh_token', Device_Authorization::GRANT_TYPE );
        $supported_response_types = array( 'code' );

        
        
        
        
        if ( isset( $body['grant_types'] ) && is_array( $body['grant_types'] ) && ! empty( $body['grant_types'] ) ) {
            $requested = array_values( array_unique( array_map( 'sanitize_text_field', $body['grant_types'] ) ) );
            foreach ( $requested as $gt ) {
                if ( ! in_array( $gt, $supported_grant_types, true ) ) {
                    return self::dcr_error(
                        'invalid_client_metadata',
                        /* translators: %s: unsupported grant type */
                        sprintf( __( 'Unsupported grant_type: %s', 'easy-mcp-ai' ), esc_html( $gt ) ),
                        400
                    );
                }
            }
            $grant_types = $requested;
        }

        
        
        
        
        
        
        
        $needs_redirect_uris = in_array( 'authorization_code', $grant_types, true );
        $has_redirect_uris   = isset( $body['redirect_uris'] ) && is_array( $body['redirect_uris'] ) && ! empty( $body['redirect_uris'] );

        
        if ( $needs_redirect_uris && ! $has_redirect_uris ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'redirect_uris is required and must be a non-empty array of strings.', 'easy-mcp-ai' ), 400 );
        }
        if ( ! $has_redirect_uris && isset( $body['redirect_uris'] ) && ! is_array( $body['redirect_uris'] ) ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'redirect_uris is required and must be a non-empty array of strings.', 'easy-mcp-ai' ), 400 );
        }

        
        if ( $has_redirect_uris && count( $body['redirect_uris'] ) > 10 ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Too many redirect_uris (maximum 10).', 'easy-mcp-ai' ), 400 );
        }

        $redirect_uris = array();
        foreach ( ( $has_redirect_uris ? $body['redirect_uris'] : array() ) as $uri ) {
            if ( ! is_string( $uri ) ) {
                return self::dcr_error( 'invalid_redirect_uri', __( 'Each redirect_uri must be a string.', 'easy-mcp-ai' ), 400 );
            }

            $validation_error = $this->validate_redirect_uri( $uri );
            if ( $validation_error instanceof \WP_REST_Response ) {
                return $validation_error;
            }

            
            
            
            
            
            $redirect_uris[] = sanitize_url( $uri, self::redirect_uri_allowed_protocols() );
        }

        
        $client_name = '';
        if ( isset( $body['client_name'] ) && is_string( $body['client_name'] ) ) {
            
            $client_name = str_replace( array( "\r", "\n" ), '', $body['client_name'] );
            $client_name = sanitize_text_field( $client_name );
            $client_name = mb_substr( $client_name, 0, self::MAX_CLIENT_NAME_LENGTH );
        }

        
        
        
        
        if ( ! $needs_redirect_uris ) {
            $response_types = array();
        }

        if ( isset( $body['response_types'] ) && is_array( $body['response_types'] ) && ! empty( $body['response_types'] ) ) {
            $requested = array_values( array_unique( array_map( 'sanitize_text_field', $body['response_types'] ) ) );
            foreach ( $requested as $rt ) {
                if ( ! in_array( $rt, $supported_response_types, true ) ) {
                    return self::dcr_error(
                        'invalid_client_metadata',
                        /* translators: %s: unsupported response type */
                        sprintf( __( 'Unsupported response_type: %s', 'easy-mcp-ai' ), esc_html( $rt ) ),
                        400
                    );
                }
            }
            $response_types = $requested;
        }

        
        
        
        
        $auth_method = self::AUTH_METHOD_NONE;
        if ( isset( $body['token_endpoint_auth_method'] ) ) {
            if ( ! is_string( $body['token_endpoint_auth_method'] ) || ! in_array( $body['token_endpoint_auth_method'], self::SUPPORTED_AUTH_METHODS, true ) ) {
                return self::dcr_error( 'invalid_client_metadata', __( 'Unsupported token_endpoint_auth_method. Supported: none, client_secret_post, client_secret_basic.', 'easy-mcp-ai' ), 400 );
            }
            $auth_method = $body['token_endpoint_auth_method'];
        }

        
        
        
        $software_id      = isset( $body['software_id'] ) && is_string( $body['software_id'] )
            ? mb_substr( sanitize_text_field( $body['software_id'] ), 0, self::MAX_SOFTWARE_ID_LENGTH )
            : null;
        $software_version = isset( $body['software_version'] ) && is_string( $body['software_version'] )
            ? mb_substr( sanitize_text_field( $body['software_version'] ), 0, self::MAX_SOFTWARE_VERSION_LENGTH )
            : null;

        return array(
            'redirect_uris'              => $redirect_uris,
            'client_name'                => $client_name,
            'grant_types'                => $grant_types,
            'response_types'             => $response_types,
            'software_id'                => $software_id,
            'software_version'           => $software_version,
            'token_endpoint_auth_method' => $auth_method,
        );
    }

    





    private function persist_client( array $fields ) {
        $redirect_uris    = $fields['redirect_uris'];
        $client_name      = $fields['client_name'];
        $grant_types      = $fields['grant_types'];
        $response_types   = $fields['response_types'];
        $software_id      = $fields['software_id'];
        $software_version = $fields['software_version'];
        $auth_method      = isset( $fields['token_endpoint_auth_method'] ) ? $fields['token_endpoint_auth_method'] : self::AUTH_METHOD_NONE;

        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';

        
        

        
        $client_id = bin2hex( random_bytes( 16 ) );

        
        
        
        
        
        
        
        
        $client_ip = class_exists( '\Easy_MCP_AI\Client_IP' )
            ? (string) \Easy_MCP_AI\Client_IP::get()
            : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );

        $row = array(
            'client_id'        => $client_id,
            'client_name'      => $client_name,
            'redirect_uris'    => wp_json_encode( $redirect_uris ),
            'grant_types'      => wp_json_encode( $grant_types ),
            'response_types'   => wp_json_encode( $response_types ),
            'scope'            => '',
            'software_id'      => $software_id,
            'software_version' => $software_version,
            'created_at'       => current_time( 'mysql', true ),
            'created_by_ip'    => $client_ip,
            'is_active'        => 1,
        );
        $formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

        
        
        
        
        
        
        
        $client_secret = null;
        if ( self::AUTH_METHOD_NONE !== $auth_method ) {
            $client_secret                     = self::generate_secret();
            $row['client_secret_hash']         = hash( 'sha256', $client_secret );
            $row['token_endpoint_auth_method'] = $auth_method;
            $formats[]                         = '%s';
            $formats[]                         = '%s';
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
        $inserted = $wpdb->insert( $table, $row, $formats );

        if ( false === $inserted ) {
            return self::dcr_error( 'server_error', __( 'Client registration failed. Please try again.', 'easy-mcp-ai' ), 500 );
        }

        $response_data = array(
            'client_id'                  => $client_id,
            'client_id_issued_at'        => time(),
            'client_name'                => $client_name,
            'redirect_uris'              => $redirect_uris,
            'grant_types'                => $grant_types,
            'response_types'             => $response_types,
            'token_endpoint_auth_method' => $auth_method,
        );
        if ( null !== $client_secret ) {
            
            
            
            $response_data['client_secret']            = $client_secret;
            $response_data['client_secret_expires_at'] = 0;
        }
        if ( ! empty( $software_id ) ) {
            $response_data['software_id'] = $software_id;
        }
        if ( ! empty( $software_version ) ) {
            $response_data['software_version'] = $software_version;
        }

        $response = new \WP_REST_Response( $response_data, 201 );
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        $response->header( 'Cache-Control', 'no-store' );

        return $response;
    }

    










    const ALLOWED_CLIENT_URI_SCHEMES = array( 'cursor', 'vscode' );

    














    public static function redirect_uri_allowed_protocols() {
        return array_values( array_unique( array_merge( wp_allowed_protocols(), self::ALLOWED_CLIENT_URI_SCHEMES ) ) );
    }

    





    private function validate_redirect_uri( $uri ) {
        
        
        if ( strlen( $uri ) > 2048 ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Redirect URI is too long (maximum 2048 bytes).', 'easy-mcp-ai' ), 400 );
        }

        
        if ( ! filter_var( $uri, FILTER_VALIDATE_URL ) ) {
            return self::dcr_error(
                'invalid_redirect_uri',
                /* translators: %s: the offending URI */
                sprintf( __( 'Invalid redirect URI: %s', 'easy-mcp-ai' ), esc_url( $uri ) ),
                400
            );
        }

        $parsed = wp_parse_url( $uri );

        
        $scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
        $forbidden_schemes = array( 'javascript', 'data', 'file' );
        if ( in_array( $scheme, $forbidden_schemes, true ) ) {
            return self::dcr_error(
                'invalid_redirect_uri',
                /* translators: %s: the offending scheme */
                sprintf( __( 'Forbidden scheme in redirect URI: %s', 'easy-mcp-ai' ), esc_html( $scheme ) ),
                400
            );
        }

        
        
        
        
        if ( false !== strpos( $uri, '#' ) ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Redirect URIs must not contain a fragment (#).', 'easy-mcp-ai' ), 400 );
        }

        
        if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Redirect URIs must not contain userinfo.', 'easy-mcp-ai' ), 400 );
        }

        
        if ( false !== strpos( $uri, '*' ) ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Redirect URIs must not contain wildcards.', 'easy-mcp-ai' ), 400 );
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $host = isset( $parsed['host'] ) ? strtolower( $parsed['host'] ) : '';
        if ( 'https' === $scheme ) {
            return true;
        }
        if ( 'http' === $scheme ) {
            $local_hosts = array( 'localhost', '127.0.0.1', '[::1]' );
            if ( ! in_array( $host, $local_hosts, true ) ) {
                return self::dcr_error( 'invalid_redirect_uri', __( 'Plain-HTTP redirect URIs are allowed only for loopback (localhost, 127.0.0.1, [::1]); use HTTPS otherwise.', 'easy-mcp-ai' ), 400 );
            }
            return true;
        }
        
        
        if ( ! in_array( $scheme, self::ALLOWED_CLIENT_URI_SCHEMES, true ) ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Redirect URIs must use HTTPS, loopback HTTP, or a supported native-app scheme (cursor://, vscode://).', 'easy-mcp-ai' ), 400 );
        }
        if ( '' === $host ) {
            return self::dcr_error( 'invalid_redirect_uri', __( 'Custom-scheme redirect URIs must include a host (for example cursor://host/path).', 'easy-mcp-ai' ), 400 );
        }

        return true;
    }

    







    public static function dcr_error( $error, $description, $status = 400 ) {
        $response = new \WP_REST_Response(
            array(
                'error'             => $error,
                'error_description' => $description,
            ),
            $status
        );
        $response->header( 'Cache-Control', 'no-store' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Content-Type', 'application/json' );
        return $response;
    }

    










    private function check_rate_limit() {
        
        
        
        
        
        $ip = \Easy_MCP_AI\Client_IP::get();

        $per_ip_key  = 'easy_mcp_ai_dcr_rl_' . md5( $ip );
        $global_key  = 'easy_mcp_ai_dcr_rl_global';
        $global_cap  = self::GLOBAL_RATE_LIMIT_PER_HOUR;

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $per_ip_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $new_count = \wp_cache_incr( $per_ip_key, 1, 'easy_mcp_ai' );

            \wp_cache_add( $global_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $new_global = \wp_cache_incr( $global_key, 1, 'easy_mcp_ai' );
        } else {
            
            $new_count  = Token_Endpoint::rl_transient_increment( $per_ip_key );
            $new_global = Token_Endpoint::rl_transient_increment( $global_key );
        }

        if ( $new_count > self::RATE_LIMIT_PER_HOUR || $new_global > $global_cap ) {
            return new \WP_REST_Response( null, 429 );
        }

        return null;
    }

    




    private function check_client_cap() {
        global $wpdb;

        $table     = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $max       = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_oauth_max_clients', self::DEFAULT_MAX_CLIENTS );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; live count must be fresh for cap check.
        $count     = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE is_active = %d",
                1
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( $count >= $max ) {
            return self::dcr_error( 'server_error', __( 'Maximum number of registered clients has been reached.', 'easy-mcp-ai' ), 503 );
        }

        return null;
    }

    














    private function cleanup_stale_duplicates( $client_name, $redirect_uris_json ) {
        global $wpdb;

        $clients_table  = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $tokens_table   = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $codes_table    = $wpdb->prefix . 'easy_mcp_ai_oauth_codes';
        $consents_table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $device_table   = $wpdb->prefix . 'easy_mcp_ai_oauth_device_codes';

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - 60 );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned tables prefixed by $wpdb->prefix.
        






























        




































        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$clients_table}
                 WHERE client_name = %s
                   AND redirect_uris = %s
                   AND created_at < %s
                   AND NOT EXISTS (SELECT 1 FROM {$tokens_table} t   WHERE t.client_id = {$clients_table}.client_id)
                   AND NOT EXISTS (SELECT 1 FROM {$consents_table} s WHERE s.client_id = {$clients_table}.client_id)
                   AND NOT EXISTS (SELECT 1 FROM {$codes_table} k    WHERE k.client_id = {$clients_table}.client_id)
                   AND NOT EXISTS (SELECT 1 FROM {$device_table} d   WHERE d.client_id = {$clients_table}.client_id)",
                $client_name,
                $redirect_uris_json,
                $cutoff
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    












    public function get_client( $client_id ) {
        global $wpdb;

        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; client lookup must be fresh.
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE client_id = %s AND is_active = %d LIMIT 1",
                $client_id,
                1
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    








    public static function generate_secret() {
        return bin2hex( random_bytes( 32 ) );
    }

    



















    public static function effective_auth_method( $client ) {
        if ( ! is_object( $client ) && ! is_array( $client ) ) {
            return '';
        }
        $client = (array) $client;
        $method = isset( $client['token_endpoint_auth_method'] ) ? (string) $client['token_endpoint_auth_method'] : '';
        if ( '' === $method ) {
            return self::AUTH_METHOD_NONE;
        }
        if ( ! in_array( $method, self::SUPPORTED_AUTH_METHODS, true ) ) {
            return '';
        }
        if ( self::AUTH_METHOD_NONE !== $method ) {
            $hash = isset( $client['client_secret_hash'] ) ? (string) $client['client_secret_hash'] : '';
            if ( 64 !== strlen( $hash ) ) {
                return '';
            }
        }
        return $method;
    }

    






    public static function is_confidential_method( $method ) {
        return self::AUTH_METHOD_POST === $method || self::AUTH_METHOD_BASIC === $method;
    }

    












    public function rotate_secret( $client_id ) {
        $client = $this->get_client( $client_id );
        if ( null === $client || ! self::is_confidential_method( self::effective_auth_method( $client ) ) ) {
            return null;
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $secret = self::generate_secret();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
        $updated = $wpdb->update(
            $table,
            array( 'client_secret_hash' => hash( 'sha256', $secret ) ),
            array( 'client_id' => $client_id, 'is_active' => 1 ),
            array( '%s' ),
            array( '%s', '%d' )
        );

        return ( 1 === (int) $updated ) ? $secret : null;
    }
}

