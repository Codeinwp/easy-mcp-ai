<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/auth/class-token-keys.php';










class Token_Endpoint {

    




    const NAMESPACE_V1 = 'easy-mcp-ai/v1';

    












    const BODY_ONLY_PARAMS = array( 'code', 'code_verifier', 'refresh_token', 'client_secret' );

    





    public function handle( \WP_REST_Request $request ) {

        
        
        $transport_error = self::enforce_transport_security();
        if ( null !== $transport_error ) {
            return $transport_error;
        }

        
        $rate_error = self::enforce_rate_limit();
        if ( null !== $rate_error ) {
            return $rate_error;
        }

        
        
        
        $query_error = self::reject_query_credentials( $request, self::BODY_ONLY_PARAMS );
        if ( null !== $query_error ) {
            return $query_error;
        }

        
        
        
        

        $grant_type = sanitize_text_field( $request->get_param( 'grant_type' ) );

        switch ( $grant_type ) {
            case 'authorization_code':
                return $this->handle_authorization_code( $request );

            case 'refresh_token':
                return $this->handle_refresh_token( $request );

            case Device_Authorization::GRANT_TYPE:
                return $this->handle_device_code( $request );

            default:
                return new \WP_REST_Response(
                    array(
                        'error'             => 'unsupported_grant_type',
                        'error_description' => __( 'Only authorization_code, refresh_token and urn:ietf:params:oauth:grant-type:device_code grant types are supported.', 'easy-mcp-ai' ),
                    ),
                    400
                );
        }
    }

    






















    private function handle_device_code( \WP_REST_Request $request ) {
        
        $device_code = is_string( $request->get_param( 'device_code' ) ) ? $request->get_param( 'device_code' ) : '';
        $resource    = is_string( $request->get_param( 'resource' ) ) ? esc_url_raw( $request->get_param( 'resource' ) ) : '';

        
        
        
        
        $basic     = self::read_basic_credentials( $request );
        $client_id = self::resolve_client_id( $request, $basic );
        if ( $client_id instanceof \WP_REST_Response ) {
            return $client_id;
        }

        if ( '' === $device_code || '' === $client_id ) {
            return $this->token_error( 'invalid_request', __( 'Missing required parameter.', 'easy-mcp-ai' ) );
        }

        
        
        $client = ( new Client_Registry() )->get_client( $client_id );
        if ( null === $client ) {
            return $this->token_error( 'invalid_client', __( 'Client not found or inactive.', 'easy-mcp-ai' ), 401 );
        }

        
        
        
        
        
        
        $auth_error = self::authenticate_client( $request, $client, $basic );
        if ( null !== $auth_error ) {
            return $auth_error;
        }

        $row = Device_Authorization::find_by_device_code( $device_code );

        
        
        
        if ( null === $row || ! hash_equals( (string) $row->client_id, $client_id ) ) {
            return $this->token_error( 'invalid_grant', __( 'The device code is invalid.', 'easy-mcp-ai' ) );
        }

        
        
        
        if ( Device_Authorization::STATUS_CONSUMED === $row->status ) {
            return $this->token_error( 'invalid_grant', __( 'The device code has already been redeemed.', 'easy-mcp-ai' ) );
        }

        if ( Device_Authorization::is_expired( $row ) ) {
            return $this->token_error( 'expired_token', __( 'The device code has expired. Start the login again.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        
        
        if ( ! empty( $row->last_polled_at ) ) {
            $previous = strtotime( $row->last_polled_at . ' UTC' );
            if ( false !== $previous && ( time() - $previous ) < ( Device_Authorization::POLL_INTERVAL - 1 ) ) {
                return $this->token_error( 'slow_down', __( 'Polling too frequently.', 'easy-mcp-ai' ) );
            }
        }
        Device_Authorization::touch_polled( (int) $row->id );

        if ( Device_Authorization::STATUS_PENDING === $row->status ) {
            return $this->token_error( 'authorization_pending', __( 'The authorization request is still pending.', 'easy-mcp-ai' ) );
        }

        if ( Device_Authorization::STATUS_DENIED === $row->status ) {
            return $this->token_error( 'access_denied', __( 'The user denied the authorization request.', 'easy-mcp-ai' ) );
        }

        
        
        if ( '' === $resource ) {
            $resource = rest_url( self::NAMESPACE_V1 . '/mcp' );
        }
        if ( ! empty( $row->resource ) && ! self::resource_matches( $resource, $row->resource ) ) {
            return $this->token_error( 'invalid_target', __( 'Resource parameter does not match the authorized resource.', 'easy-mcp-ai' ) );
        }

        
        
        
        if ( Device_Authorization::STATUS_APPROVED !== $row->status || ! Device_Authorization::claim_approved( (int) $row->id ) ) {
            return $this->token_error( 'invalid_grant', __( 'The device code has already been redeemed.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $consent = self::live_consent( (int) $row->wp_user_id, (string) $row->client_id );
        if ( ! self::consent_backs_row( $consent, $row ) ) {
            return $this->token_error( 'invalid_grant', __( 'The grant behind this device code has been revoked.', 'easy-mcp-ai' ) );
        }
        $effective_scope = self::cap_scope( (string) $row->scope, (string) $consent->scope );
        if ( '' === $effective_scope ) {
            return $this->token_error( 'invalid_grant', __( 'The grant behind this device code no longer covers any permission.', 'easy-mcp-ai' ) );
        }

        $token_manager = new OAuth_Token_Manager();
        $tokens        = $token_manager->mint_access_token(
            (string) $row->client_id,
            (int) $row->wp_user_id,
            (string) $row->resource,
            $effective_scope
        );

        if ( null === $tokens ) {
            return $this->token_error( 'server_error', __( 'Failed to issue access token.', 'easy-mcp-ai' ), 500 );
        }

        
        
        
        
        
        
        
        
        
        
        
        $post_consent = self::live_consent( (int) $row->wp_user_id, (string) $row->client_id );
        $token_id     = isset( $tokens['token_id'] ) ? (int) $tokens['token_id'] : 0;
        if ( ! self::consent_backs_row( $post_consent, $row ) ) {
            $token_manager->revoke_chain( $token_id );
            return $this->token_error( 'invalid_grant', __( 'The grant behind this device code has been revoked.', 'easy-mcp-ai' ) );
        }
        $settled_scope = self::cap_scope( (string) $row->scope, (string) $post_consent->scope );
        if ( '' === $settled_scope ) {
            $token_manager->revoke_chain( $token_id );
            return $this->token_error( 'invalid_grant', __( 'The grant behind this device code no longer covers any permission.', 'easy-mcp-ai' ) );
        }
        if ( $token_id > 0 ) {
            global $wpdb;
            $tokens_table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the conditional UPDATE and the read-back are the point.
            if ( $settled_scope !== $effective_scope ) {
                
                
                
                
                
                
                
                
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$tokens_table} SET scope = %s WHERE id = %d AND scope = %s",
                        $settled_scope,
                        $token_id,
                        $effective_scope
                    )
                );
            }
            
            
            
            
            $final = $wpdb->get_row(
                $wpdb->prepare( "SELECT scope, is_active FROM {$tokens_table} WHERE id = %d LIMIT 1", $token_id )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! is_object( $final ) || empty( $final->is_active ) || '' === trim( (string) $final->scope ) ) {
                $token_manager->revoke_chain( $token_id );
                return $this->token_error( 'invalid_grant', __( 'The grant behind this device code no longer covers any permission.', 'easy-mcp-ai' ) );
            }
            
            
            
            
            
            
            
            $final_scope = (string) $final->scope;
            if ( self::cap_scope( $final_scope, (string) $post_consent->scope ) !== $final_scope ) {
                $token_manager->revoke_chain( $token_id );
                return $this->token_error( 'invalid_grant', __( 'The grant behind this device code could not be narrowed to its current permissions.', 'easy-mcp-ai' ) );
            }
            $tokens['scope'] = $final_scope;
        }

        $response = new \WP_REST_Response(
            array(
                'access_token'  => $tokens['access_token'],
                'token_type'    => 'Bearer',
                'expires_in'    => $tokens['expires_in'],
                'refresh_token' => $tokens['refresh_token'],
                'scope'         => $tokens['scope'],
            ),
            200
        );
        $this->add_token_headers( $response );
        return $response;
    }

    








    private static function consent_backs_row( $consent, $row ): bool {
        return null !== $consent
            && ! empty( $row->consent_id )
            && (int) $consent->id === (int) $row->consent_id
            && '' !== trim( (string) $consent->scope );
    }

    





    private function handle_authorization_code( \WP_REST_Request $request ) {
        global $wpdb;

        
        
        
        
        
        
        
        
        
        
        $code          = self::body_param( $request, 'code' );
        $code_verifier = self::body_param( $request, 'code_verifier' );
        $basic         = self::read_basic_credentials( $request );
        $client_id     = self::resolve_client_id( $request, $basic );
        if ( $client_id instanceof \WP_REST_Response ) {
            return $client_id;
        }
        $redirect_uri  = is_string( $request->get_param( 'redirect_uri' ) ) ? esc_url_raw( $request->get_param( 'redirect_uri' ), Client_Registry::redirect_uri_allowed_protocols() ) : '';
        
        
        
        $resource      = is_string( $request->get_param( 'resource' ) ) ? esc_url_raw( $request->get_param( 'resource' ) ) : '';

        
        if ( '' === $code || '' === $code_verifier || '' === $client_id || '' === $redirect_uri ) {
            return $this->token_error( 'invalid_request', __( 'Missing required parameter.', 'easy-mcp-ai' ) );
        }

        
        if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $code_verifier ) ) {
            return $this->token_error( 'invalid_request', __( 'Invalid code_verifier format.', 'easy-mcp-ai' ) );
        }

        
        $code_hash   = \Easy_MCP_AI\Auth\Token_Keys::hash_current( $code );
        $codes_table = $wpdb->prefix . 'easy_mcp_ai_oauth_codes';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; auth code lookup must be fresh.
        $code_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$codes_table} WHERE code_hash = %s LIMIT 1",
                $code_hash
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $code_row ) {
            return $this->token_error( 'invalid_grant', __( 'Authorization code not found.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        
        
        
        
        
        $is_reuse = ( null !== $code_row->used_at );

        
        
        
        $now_utc = gmdate( 'Y-m-d H:i:s' );
        if ( ! $is_reuse && $code_row->expires_at < $now_utc ) {
            return $this->token_error( 'invalid_grant', __( 'Authorization code has expired.', 'easy-mcp-ai' ) );
        }

        
        if ( $code_row->client_id !== $client_id ) {
            return $this->token_error( 'invalid_grant', __( 'Client ID mismatch.', 'easy-mcp-ai' ) );
        }

        
        
        $registry = new Client_Registry();
        $client   = $registry->get_client( $client_id );
        if ( null === $client ) {
            return $this->token_error( 'invalid_client', __( 'Client not found or inactive.', 'easy-mcp-ai' ), 401 );
        }

        
        
        
        
        
        $auth_error = self::authenticate_client( $request, $client, $basic );
        if ( null !== $auth_error ) {
            return $auth_error;
        }

        if ( $code_row->redirect_uri !== $redirect_uri ) {
            return $this->token_error( 'invalid_grant', __( 'Redirect URI mismatch.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        
        if ( '' === $resource ) {
            $resource = rest_url( self::NAMESPACE_V1 . '/mcp' );
        }
        if ( ! empty( $code_row->resource ) && ! self::resource_matches( $resource, $code_row->resource ) ) {
            return $this->token_error( 'invalid_target', __( 'Resource parameter does not match the authorized resource.', 'easy-mcp-ai' ) );
        }

        
        $computed_challenge = self::base64url_encode( hash( 'sha256', $code_verifier, true ) );
        if ( ! hash_equals( $code_row->code_challenge, $computed_challenge ) ) {
            return $this->token_error( 'invalid_grant', __( 'PKCE verification failed.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        if ( $is_reuse ) {
            $this->revoke_tokens_for_code( $code_row );
            return $this->token_error( 'invalid_grant', __( 'Authorization code has already been used. Any tokens issued from it have been revoked.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; atomic redemption must hit DB.
        $affected = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$codes_table} SET used_at = %s WHERE code_hash = %s AND used_at IS NULL AND expires_at > %s",
                $now_utc,
                $code_hash,
                $now_utc
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( 1 !== (int) $affected ) {
            
            
            $this->revoke_tokens_for_code( $code_row );
            return $this->token_error( 'invalid_grant', __( 'Authorization code already redeemed or expired. Tokens revoked.', 'easy-mcp-ai' ) );
        }

        
        $token_manager = new OAuth_Token_Manager();
        $tokens = $token_manager->mint_access_token(
            $code_row->client_id,
            (int) $code_row->wp_user_id,
            $code_row->resource,
            $code_row->scope
        );

        if ( null === $tokens ) {
            return $this->token_error( 'server_error', __( 'Failed to issue access token.', 'easy-mcp-ai' ), 500 );
        }

        
        
        $minted_token_id = isset( $tokens['token_id'] ) ? (int) $tokens['token_id'] : 0;
        if ( $minted_token_id > 0 ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
            $wpdb->update(
                $codes_table,
                array( 'minted_token_id' => $minted_token_id ),
                array( 'code_hash' => $code_hash ),
                array( '%d' ),
                array( '%s' )
            );
        }

        $response = new \WP_REST_Response(
            array(
                'access_token'  => $tokens['access_token'],
                'token_type'    => 'Bearer',
                'expires_in'    => $tokens['expires_in'],
                'refresh_token' => $tokens['refresh_token'],
                'scope'         => $tokens['scope'],
            ),
            200
        );
        $this->add_token_headers( $response );
        return $response;
    }

    





    private function handle_refresh_token( \WP_REST_Request $request ) {

        
        
        $refresh_token = self::body_param( $request, 'refresh_token' );
        $basic         = self::read_basic_credentials( $request );
        $client_id     = self::resolve_client_id( $request, $basic );
        if ( $client_id instanceof \WP_REST_Response ) {
            return $client_id;
        }
        $resource      = is_string( $request->get_param( 'resource' ) ) ? $request->get_param( 'resource' ) : '';

        if ( '' === $refresh_token || '' === $client_id ) {
            return $this->token_error( 'invalid_request', __( 'Missing required parameter.', 'easy-mcp-ai' ) );
        }

        
        
        $client = ( new Client_Registry() )->get_client( $client_id );
        if ( ! $client ) {
            return $this->token_error( 'invalid_client', __( 'Client not found or inactive.', 'easy-mcp-ai' ), 401 );
        }

        
        
        
        
        $auth_error = self::authenticate_client( $request, $client, $basic );
        if ( null !== $auth_error ) {
            return $auth_error;
        }

        if ( empty( $resource ) ) {
            $resource = rest_url( self::NAMESPACE_V1 . '/mcp' );
        }

        
        
        
        
        
        
        $expected_resource = rest_url( self::NAMESPACE_V1 . '/mcp' );
        if ( ! self::resource_matches( $resource, $expected_resource ) ) {
            return $this->token_error( 'invalid_grant', __( 'Resource mismatch.', 'easy-mcp-ai' ) );
        }

        $token_manager = new OAuth_Token_Manager();
        $result = $token_manager->refresh( $refresh_token, $client_id, $resource );

        if ( false === $result ) {
            return $this->token_error( 'invalid_grant', __( 'Refresh token is invalid, expired, or revoked.', 'easy-mcp-ai' ) );
        }

        $response = new \WP_REST_Response(
            array(
                'access_token'  => $result['access_token'],
                'token_type'    => 'Bearer',
                'expires_in'    => $result['expires_in'],
                'refresh_token' => $result['refresh_token'],
                'scope'         => $result['scope'],
            ),
            200
        );
        $this->add_token_headers( $response );
        return $response;
    }

    







    public static function live_consent( int $wp_user_id, string $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the read must be fresh, it is the revocation check.
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT id, scope FROM {$table} WHERE wp_user_id = %d AND client_id = %s LIMIT 1", $wp_user_id, $client_id )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_object( $row ) && isset( $row->id, $row->scope ) ? $row : null;
    }

    










    public static function cap_scope( string $granted, string $consent ): string {
        $g = array_values( array_filter( array_map( 'trim', explode( ' ', $granted ) ) ) );
        $c = array_values( array_filter( array_map( 'trim', explode( ' ', $consent ) ) ) );
        if ( in_array( 'mcp', $c, true ) ) {
            return implode( ' ', $g );
        }
        if ( in_array( 'mcp', $g, true ) ) {
            return implode( ' ', $c );
        }
        return implode( ' ', array_values( array_intersect( $g, $c ) ) );
    }

    



























    private function revoke_tokens_for_code( $code_row ) {
        global $wpdb;
        $tokens_table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $codes_table  = $wpdb->prefix . 'easy_mcp_ai_oauth_codes';

        if ( empty( $code_row->minted_token_id ) && ! empty( $code_row->code_hash ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix.
            $fresh_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT minted_token_id FROM {$codes_table} WHERE code_hash = %s LIMIT 1",
                    $code_row->code_hash
                )
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! empty( $fresh_id ) ) {
                $code_row->minted_token_id = (int) $fresh_id;
            }
        }

        if ( ! empty( $code_row->minted_token_id ) ) {
            $token_manager = new OAuth_Token_Manager();
            $token_manager->revoke_chain( (int) $code_row->minted_token_id );
        }
    }

    





    private function add_token_headers( \WP_REST_Response $response ) {
        $response->header( 'Content-Type', 'application/json' );
        $response->header( 'Cache-Control', 'no-store' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
    }

    











    




















    public static function wire_safe_description( $description ): string {
        if ( ! is_scalar( $description ) ) {
            return '';
        }
        
        
        $text = function_exists( 'remove_accents' ) ? remove_accents( (string) $description ) : (string) $description;
        $text = preg_replace( '/[^\x20\x21\x23-\x5B\x5D-\x7E]+/', ' ', $text );
        return trim( (string) $text );
    }

    private function token_error( $error, $description, $status = 400 ) {
        return self::build_token_error( $error, $description, $status, 'Bearer' );
    }

    















    public static function build_token_error( $error, $description, $status = 400, $challenge_scheme = 'Bearer' ) {
        $response = new \WP_REST_Response(
            array(
                'error'             => $error,
                'error_description' => self::wire_safe_description( $description ),
            ),
            $status
        );
        
        
        
        if ( 401 === (int) $status ) {
            $response->header(
                'WWW-Authenticate',
                sprintf( '%s realm="oauth", error="%s"', 'Basic' === $challenge_scheme ? 'Basic' : 'Bearer', $error )
            );
        }
        return $response;
    }

    

    









    public static function body_param( \WP_REST_Request $request, $key ) {
        $json = $request->get_json_params();
        if ( is_array( $json ) && isset( $json[ $key ] ) && is_string( $json[ $key ] ) ) {
            return $json[ $key ];
        }
        $body = $request->get_body_params();
        if ( is_array( $body ) && isset( $body[ $key ] ) && is_string( $body[ $key ] ) ) {
            return $body[ $key ];
        }
        return '';
    }

    











    public static function reject_query_credentials( \WP_REST_Request $request, array $keys ) {
        $query = $request->get_query_params();
        if ( ! is_array( $query ) ) {
            return null;
        }
        foreach ( $keys as $key ) {
            if ( array_key_exists( $key, $query ) ) {
                return self::build_token_error(
                    'invalid_request',
                    /* translators: %s: parameter name */
                    sprintf( __( 'The %s parameter must be sent in the request body, not in the query string.', 'easy-mcp-ai' ), $key ),
                    400
                );
            }
        }
        return null;
    }

    

    




























    public static function read_basic_credentials( \WP_REST_Request $request ) {
        $raw = $request->get_header( 'authorization' );
        if ( is_string( $raw ) && 0 === stripos( $raw, 'Basic ' ) ) {
            return self::decode_basic( substr( $raw, 6 ) );
        }

        if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) && is_string( $_SERVER['PHP_AUTH_USER'] ) && is_string( $_SERVER['PHP_AUTH_PW'] ) ) {
            
            
            
            return array(
                'client_id' => urldecode( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Credential compared by SHA-256 hash; sanitizing would alter it.
                'secret'    => urldecode( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Credential compared by SHA-256 hash; sanitizing would alter it.
            );
        }

        if ( class_exists( '\Easy_MCP_AI\Auth_Header' ) ) {
            $headers = \Easy_MCP_AI\Auth_Header::request_headers();
            if ( null !== $headers ) {
                $found = \Easy_MCP_AI\Auth_Header::find_authorization( $headers, 'Basic' );
                if ( null !== $found ) {
                    return self::decode_basic( substr( $found, 6 ) );
                }
            }
        }

        return null;
    }

    





    private static function decode_basic( $payload ) {
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding an HTTP Basic credential (RFC 7617), not obfuscation.
        $decoded = base64_decode( trim( (string) $payload ), true );
        if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
            return array( 'client_id' => '', 'secret' => '' );
        }
        list( $user, $pass ) = explode( ':', $decoded, 2 );
        return array(
            'client_id' => urldecode( $user ),
            'secret'    => urldecode( $pass ),
        );
    }

    












    public static function resolve_client_id( \WP_REST_Request $request, $basic ) {
        $body_client_id  = sanitize_text_field( (string) $request->get_param( 'client_id' ) );
        $basic_client_id = ( is_array( $basic ) && isset( $basic['client_id'] ) ) ? sanitize_text_field( $basic['client_id'] ) : '';

        if ( '' !== $body_client_id && '' !== $basic_client_id && ! hash_equals( $body_client_id, $basic_client_id ) ) {
            return self::build_token_error( 'invalid_client', __( 'client_id in the request body does not match the Authorization header.', 'easy-mcp-ai' ), 401, 'Basic' );
        }

        return '' !== $body_client_id ? $body_client_id : $basic_client_id;
    }

    





































    public static function authenticate_client( \WP_REST_Request $request, $client, $basic ) {
        $method       = Client_Registry::effective_auth_method( $client );
        $body_secret  = self::body_param( $request, 'client_secret' );
        $basic_secret = ( is_array( $basic ) && isset( $basic['secret'] ) ) ? (string) $basic['secret'] : '';
        $used_basic   = is_array( $basic );
        $scheme       = ( $used_basic || Client_Registry::AUTH_METHOD_BASIC === $method ) ? 'Basic' : 'Bearer';

        if ( '' === $method ) {
            return self::build_token_error( 'invalid_client', __( 'Client registration is unusable; an administrator must revoke and re-register it.', 'easy-mcp-ai' ), 401, $scheme );
        }

        if ( Client_Registry::AUTH_METHOD_NONE === $method ) {
            if ( '' !== $body_secret || $used_basic ) {
                return self::build_token_error( 'invalid_client', __( 'This client is registered as a public client (token_endpoint_auth_method=none); do not send a client secret.', 'easy-mcp-ai' ), 401, $scheme );
            }
            return null;
        }

        if ( '' !== $body_secret && $used_basic ) {
            return self::build_token_error( 'invalid_request', __( 'Use exactly one client authentication method per request: client_secret in the body OR an Authorization: Basic header.', 'easy-mcp-ai' ), 400 );
        }

        $secret = '' !== $body_secret ? $body_secret : $basic_secret;
        if ( '' === $secret ) {
            return self::build_token_error( 'invalid_client', __( 'Client authentication required: this client was registered with a client secret.', 'easy-mcp-ai' ), 401, $scheme );
        }

        $client_arr = (array) $client;
        if ( ! hash_equals( (string) $client_arr['client_secret_hash'], hash( 'sha256', $secret ) ) ) {
            return self::build_token_error( 'invalid_client', __( 'Client authentication failed.', 'easy-mcp-ai' ), 401, $scheme );
        }

        return null;
    }

    











    public static function resource_matches( string $candidate, string $expected ): bool {
        $a = self::canonicalize_resource_uri( $candidate );
        $b = self::canonicalize_resource_uri( $expected );
        return '' !== $a && $a === $b;
    }

    









    public static function canonicalize_resource_uri( string $uri ): string {
        $uri = trim( $uri );
        if ( '' === $uri ) {
            return '';
        }

        $parts = wp_parse_url( $uri );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) || empty( $parts['scheme'] ) ) {
            return '';
        }

        $scheme = strtolower( $parts['scheme'] );
        $host   = strtolower( $parts['host'] );

        $port = '';
        if ( isset( $parts['port'] ) ) {
            $default = ( 'https' === $scheme ) ? 443 : ( ( 'http' === $scheme ) ? 80 : 0 );
            if ( (int) $parts['port'] !== $default ) {
                $port = ':' . (int) $parts['port'];
            }
        }

        $path  = isset( $parts['path'] ) ? $parts['path'] : '';
        $query = isset( $parts['query'] ) ? $parts['query'] : '';

        
        if ( '' !== $query ) {
            parse_str( $query, $q );
            if ( isset( $q['rest_route'] ) && is_string( $q['rest_route'] ) && '' !== $q['rest_route'] ) {
                $path  = '/wp-json' . $q['rest_route'];
                $query = '';
            }
        }

        
        if ( strlen( $path ) > 1 && '/' === substr( $path, -1 ) ) {
            $path = rtrim( $path, '/' );
        }

        $canonical = $scheme . '://' . $host . $port . $path;
        if ( '' !== $query ) {
            $canonical .= '?' . $query;
        }

        return $canonical;
    }

    





    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    







    public static function enforce_transport_security() {
        if ( is_ssl() ) {
            return null;
        }

        if ( self::is_local_dev_request() ) {
            return null;
        }

        return new \WP_REST_Response(
            array(
                'error'             => 'insecure_transport',
                'error_description' => self::insecure_transport_description(),
            ),
            403
        );
    }

    


















    private static function insecure_transport_description(): string {
        $proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] )
            ? strtolower( trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) )
            : '';

        if ( 'https' === $proto ) {
            return __( 'HTTPS is required, and this request arrived over HTTPS at your proxy or CDN — but WordPress does not know that, so is_ssl() returns false. TLS is terminating in front of PHP and the forwarded protocol is not being honoured. Fix it in wp-config.php, above the "stop editing" line: if ( isset( $_SERVER["HTTP_X_FORWARDED_PROTO"] ) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https" ) { $_SERVER["HTTPS"] = "on"; } Only add this if a proxy you trust sets that header. This also corrects WordPress core and every other plugin on the site, not just this one.', 'easy-mcp-ai' );
        }

        return __( 'HTTPS is required. This request reached PHP over plain HTTP. If your site is served over HTTPS through a proxy or CDN, WordPress is not being told — set $_SERVER["HTTPS"] from the forwarded protocol in wp-config.php. For local development over plain HTTP, define EASY_MCP_AI_OAUTH_ALLOW_HTTP as true in wp-config.php instead. Never set that on a production site.', 'easy-mcp-ai' );
    }

    












    private static function is_local_dev_request(): bool {
        if ( \Easy_MCP_AI\Config::get( 'oauth_allow_http' ) ) {
            return true;
        }

        if ( ! isset( $_SERVER['REMOTE_ADDR'] ) ) {
            return false;
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        foreach ( array( 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_PROTO', 'HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP' ) as $proxy_header ) {
            if ( ! empty( $_SERVER[ $proxy_header ] ) ) {
                return false;
            }
        }

        $remote = trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), '[]' );
        return in_array( $remote, array( '127.0.0.1', '::1' ), true );
    }

    








    public static function enforce_rate_limit() {
        
        
        
        $ip = \Easy_MCP_AI\Client_IP::get();

        $per_ip_key   = 'easy_mcp_ai_token_rl_' . md5( $ip );
        $global_key   = 'easy_mcp_ai_token_rl_global';
        $per_ip_limit = 120;
        $global_limit = 1200;

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $per_ip_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $ip_count = \wp_cache_incr( $per_ip_key, 1, 'easy_mcp_ai' );

            \wp_cache_add( $global_key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
            $global_count = \wp_cache_incr( $global_key, 1, 'easy_mcp_ai' );
        } else {
            
            
            
            
            
            
            
            
            
            
            $ip_count     = self::rl_transient_increment( $per_ip_key );
            $global_count = self::rl_transient_increment( $global_key );
        }

        if ( $ip_count > $per_ip_limit || $global_count > $global_limit ) {
            return new \WP_REST_Response( null, 429 );
        }

        return null;
    }

    









    public static function rl_transient_increment( string $key ): int {
        $now    = time();
        $packed = get_transient( $key );
        if ( ! is_string( $packed ) || false === strpos( $packed, ':' ) ) {
            $count    = 1;
            $deadline = $now + HOUR_IN_SECONDS;
        } else {
            list( $c, $d ) = explode( ':', $packed, 2 );
            $deadline      = (int) $d;
            if ( $deadline <= $now ) {
                $count    = 1;
                $deadline = $now + HOUR_IN_SECONDS;
            } else {
                $count = (int) $c + 1;
            }
        }
        $ttl = max( 1, $deadline - $now );
        set_transient( $key, $count . ':' . $deadline, $ttl );
        return $count;
    }
}
