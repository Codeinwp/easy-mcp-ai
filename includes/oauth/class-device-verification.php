<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























class Device_Verification {

    








    const LOOKUP_LIMIT_PER_HOUR = 30;

    





    public function handle_get( \WP_REST_Request $request ) {
        $tls_error = $this->enforce_transport_security();
        if ( $tls_error ) {
            return $tls_error;
        }

        $rate_error = Token_Endpoint::enforce_rate_limit();
        if ( null !== $rate_error ) {
            return $rate_error;
        }

        if ( ! is_user_logged_in() ) {
            
            
            $response = new \WP_REST_Response( null, 302 );
            $response->header( 'Location', wp_login_url( Device_Authorization::verification_uri() ) );
            $this->add_security_headers( $response );
            return $response;
        }

        $cap_error = $this->enforce_min_capability( 'insufficient_capability' );
        if ( $cap_error ) {
            return $cap_error;
        }

        return $this->render_code_form();
    }

    





    public function handle_post( \WP_REST_Request $request ) {
        $tls_error = $this->enforce_transport_security();
        if ( $tls_error ) {
            return $tls_error;
        }

        $rate_error = Token_Endpoint::enforce_rate_limit();
        if ( null !== $rate_error ) {
            return $rate_error;
        }

        if ( ! is_user_logged_in() ) {
            $this->log_event( 'auth_failure', 'not_logged_in' );
            return new \WP_Error(
                'access_denied',
                __( 'You must be logged in to authorize this request.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        $cap_error = $this->enforce_min_capability( 'insufficient_capability' );
        if ( $cap_error ) {
            return $cap_error;
        }

        $action = sanitize_text_field( $request->get_param( 'device_action' ) );
        if ( 'decide' === $action ) {
            return $this->handle_decision( $request );
        }
        return $this->handle_lookup( $request );
    }

    
    
    

    





    private function handle_lookup( \WP_REST_Request $request ) {
        $nonce = sanitize_text_field( $request->get_param( '_wpnonce' ) );
        if ( ! wp_verify_nonce( $nonce, 'easy_mcp_ai_oauth_device_lookup' ) ) {
            $this->log_event( 'auth_failure', 'nonce_failed' );
            return new \WP_Error(
                'invalid_request',
                __( 'Security check failed. Please try again.', 'easy-mcp-ai' ),
                array( 'status' => 403 )
            );
        }

        
        if ( $this->lookup_rate_limited() ) {
            $this->log_event( 'auth_failure', 'lookup_rate_limited' );
            return $this->render_code_form( __( 'Too many attempts. Wait a while and try again.', 'easy-mcp-ai' ), 429 );
        }

        $normalized = Device_Authorization::normalize_user_code( $request->get_param( 'user_code' ) );
        $row        = self::live_pending_row( $normalized );
        if ( null === $row ) {
            $this->log_event( 'auth_failure', 'code_not_found' );
            return $this->render_code_form( __( 'That code is not valid or has expired. Check the code shown by your application and try again.', 'easy-mcp-ai' ) );
        }

        $client = ( new Client_Registry() )->get_client( (string) $row->client_id );
        if ( null === $client ) {
            
            
            $this->log_event( 'auth_failure', 'client_inactive', $row );
            return $this->render_code_form( __( 'The application that requested this code is no longer registered.', 'easy-mcp-ai' ) );
        }

        return $this->render_consent( $client, $row, $normalized );
    }

    











    private function handle_decision( \WP_REST_Request $request ) {
        $normalized = Device_Authorization::normalize_user_code( $request->get_param( 'user_code' ) );

        $nonce = sanitize_text_field( $request->get_param( '_wpnonce' ) );
        if ( '' === $normalized || ! wp_verify_nonce( $nonce, 'easy_mcp_ai_oauth_device_decide_' . $normalized ) ) {
            $this->log_event( 'auth_failure', 'nonce_failed' );
            return new \WP_Error(
                'invalid_request',
                __( 'Security check failed. Please start again from the code entry form.', 'easy-mcp-ai' ),
                array( 'status' => 403 )
            );
        }

        $row = self::live_pending_row( $normalized );
        if ( null === $row ) {
            $this->log_event( 'auth_failure', 'code_not_found' );
            return $this->render_code_form( __( 'That code is no longer pending. It may have expired, or a decision was already recorded.', 'easy-mcp-ai' ) );
        }

        $user   = wp_get_current_user();
        $action = sanitize_text_field( $request->get_param( 'consent_action' ) );

        if ( 'approve' !== $action ) {
            $recorded = Device_Authorization::record_decision( (int) $row->id, false, (int) $user->ID );
            $this->log_event( 'auth_failure', $recorded ? 'user_denied' : 'decision_lost_race', $row );
            return $this->render_result(
                false,
                __( 'Access denied', 'easy-mcp-ai' ),
                __( 'The application was not connected. You can close this page; the application will report that access was denied.', 'easy-mcp-ai' )
            );
        }

        $scope_string = Authorization_Endpoint::resolve_granted_scope( (string) $row->scope, Authorization_Endpoint::submitted_scopes( $request->get_param( 'access_level' ), $request->get_param( 'scopes' ) ) );

        
        
        
        
        if ( '' === $scope_string ) {
            $this->log_event( 'auth_failure', 'approved_without_scopes', $row );
            $client = ( new Client_Registry() )->get_client( (string) $row->client_id );
            if ( null === $client ) {
                return $this->render_code_form( __( 'The application that requested this code is no longer registered.', 'easy-mcp-ai' ) );
            }
            return $this->render_consent( $client, $row, $normalized, __( 'No permissions were selected, so nothing was granted. Choose at least one permission and approve again.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        
        
        
        
        $consent_id = Authorization_Endpoint::store_consent( (int) $user->ID, (string) $row->client_id, $scope_string );
        if ( $consent_id <= 0 ) {
            $this->log_event( 'auth_failure', 'consent_write_failed', $row );
            return $this->render_code_form( __( 'The approval could not be saved. Please try again.', 'easy-mcp-ai' ) );
        }

        $recorded = Device_Authorization::record_decision( (int) $row->id, true, (int) $user->ID, $scope_string, $consent_id );
        if ( ! $recorded ) {
            
            
            
            
            
            
            $this->log_event( 'auth_failure', 'decision_lost_race', $row );
            return $this->render_code_form( __( 'That code is no longer pending. It may have expired, or a decision was already recorded.', 'easy-mcp-ai' ) );
        }

        $this->log_event( 'success', 'approved', $row, $scope_string );

        return $this->render_result(
            true,
            __( 'Device connected', 'easy-mcp-ai' ),
            __( 'You can close this page and return to the application. It will finish connecting on its own within a few seconds.', 'easy-mcp-ai' )
        );
    }

    





    private static function live_pending_row( $normalized ) {
        if ( '' === $normalized ) {
            return null;
        }
        $row = Device_Authorization::find_by_user_code( $normalized );
        if ( null === $row || Device_Authorization::STATUS_PENDING !== $row->status || Device_Authorization::is_expired( $row ) ) {
            return null;
        }
        return $row;
    }

    
    
    

    






    private function render_code_form( $error = '', $status = 200 ) {
        
        
        
        
        $script_nonce = wp_create_nonce( 'easy_mcp_ai_consent_script' );
        $html         = self::render_view(
            array(
                'stage'        => 'enter',
                'error'        => (string) $error,
                'form_action'  => Device_Authorization::verification_uri(),
                'code_length'  => Device_Authorization::USER_CODE_LENGTH,
                'script_nonce' => $script_nonce,
            )
        );
        $response = new \WP_REST_Response( $html, $status );
        $response->header( 'Content-Type', 'text/html; charset=utf-8' );
        $this->add_security_headers( $response, $script_nonce );
        return $response;
    }

    








    private function render_consent( $client, $row, $normalized, $notice = '' ) {
        $user         = wp_get_current_user();
        $script_nonce = wp_create_nonce( 'easy_mcp_ai_consent_script' );

        $html = Consent_Screen::render(
            $client,
            $user,
            (string) $row->scope,
            array(),
            $script_nonce,
            array(
                'form_action'   => Device_Authorization::verification_uri(),
                'hidden_fields' => array(
                    'device_action' => 'decide',
                    'user_code'     => $normalized,
                ),
                'nonce_action'  => 'easy_mcp_ai_oauth_device_decide_' . $normalized,
                'context_label' => __( 'Device code:', 'easy-mcp-ai' ),
                'context_value' => Device_Authorization::format_user_code( $normalized ),
                'notice'        => $notice,
            )
        );

        $response = new \WP_REST_Response( $html, 200 );
        $response->header( 'Content-Type', 'text/html; charset=utf-8' );
        $this->add_security_headers( $response, $script_nonce );
        return $response;
    }

    







    private function render_result( $approved, $title, $message ) {
        $html = self::render_view(
            array(
                'stage'    => 'done',
                'approved' => (bool) $approved,
                'title'    => (string) $title,
                'message'  => (string) $message,
            )
        );
        $response = new \WP_REST_Response( $html, 200 );
        $response->header( 'Content-Type', 'text/html; charset=utf-8' );
        $this->add_security_headers( $response );
        return $response;
    }

    





    private static function render_view( array $vars ) {
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract( $vars );
        ob_start();
        include __DIR__ . '/views/device.php';
        return (string) ob_get_clean();
    }

    
    
    

    





    private function enforce_min_capability( $log_reason ) {
        $min_cap = Authorization_Endpoint::resolved_min_capability();
        if ( current_user_can( $min_cap ) ) {
            return null;
        }
        $this->log_event( 'auth_failure', $log_reason );
        return new \WP_Error(
            'access_denied',
            __( 'Your account does not have sufficient permissions to authorize MCP access.', 'easy-mcp-ai' ),
            array( 'status' => 403 )
        );
    }

    




    private function lookup_rate_limited() {
        $ip   = \Easy_MCP_AI\Client_IP::get();
        $keys = array(
            'easy_mcp_ai_device_lookup_rl_ip_' . md5( $ip ),
            'easy_mcp_ai_device_lookup_rl_user_' . (int) get_current_user_id(),
        );

        $limited = false;
        foreach ( $keys as $key ) {
            if ( \wp_using_ext_object_cache() ) {
                \wp_cache_add( $key, 0, 'easy_mcp_ai', HOUR_IN_SECONDS );
                $count = \wp_cache_incr( $key, 1, 'easy_mcp_ai' );
            } else {
                $count = Token_Endpoint::rl_transient_increment( $key );
            }
            if ( $count > self::LOOKUP_LIMIT_PER_HOUR ) {
                $limited = true;
            }
        }
        return $limited;
    }

    




    private function enforce_transport_security() {
        $result = Token_Endpoint::enforce_transport_security();
        if ( null !== $result ) {
            $this->add_security_headers( $result );
        }
        return $result;
    }

    








    private function add_security_headers( \WP_REST_Response $response, $script_nonce = null ) {
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        $response->header( 'Pragma', 'no-cache' );
        $response->header( 'Referrer-Policy', 'no-referrer' );
        $response->header( 'X-Frame-Options', 'DENY' );
        $csp = "default-src 'self'; style-src 'unsafe-inline' 'self'; frame-ancestors 'none'; form-action 'self'";
        if ( $script_nonce ) {
            $csp .= "; script-src 'nonce-" . $script_nonce . "'";
        }
        $response->header( 'Content-Security-Policy', $csp );
        $response->header( 'X-Content-Type-Options', 'nosniff' );
        if ( is_ssl() ) {
            $response->header( 'Strict-Transport-Security', 'max-age=31536000' );
        }
    }

    
    
    

    

















    private function log_event( $result_status, $reason, $row = null, $scope = '' ) {
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true ) ) {
            return;
        }

        $details = array(
            'stage'      => 'device_verify',
            'reason'     => (string) $reason,
            'client_id'  => is_object( $row ) && ! empty( $row->client_id ) ? (string) $row->client_id : '',
            'wp_user_id' => get_current_user_id(),
        );
        if ( '' !== $scope ) {
            $details['scope'] = (string) $scope;
        }

        global $wpdb;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct insert required for audit logging; mirrors Authorization_Endpoint::log_authorize_failure().
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array(
                'token_id'        => 0,
                'tool_name'       => '_oauth_device',
                'arguments'       => wp_json_encode( $details ),
                'result_status'   => (string) $result_status,
                'ip_address'      => class_exists( '\\Easy_MCP_AI\\Client_IP' ) ? \Easy_MCP_AI\Client_IP::get() : '',
                
                'auth_source'     => 'oauth',
                'wp_user_id'      => ! empty( $details['wp_user_id'] ) ? (int) $details['wp_user_id'] : null,
                'oauth_client_id' => ! empty( $details['client_id'] ) ? (string) $details['client_id'] : null,
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }
}
