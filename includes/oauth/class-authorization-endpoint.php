<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Authorization_Endpoint {

    const NAMESPACE_V1 = 'easy-mcp-ai/v1';

    const CODE_LIFETIME = 60;

    const ISS_SITE     = 'site';
    const ISS_RESOURCE = 'resource';
    const ISS_NONE     = 'none';

    const ISS_PARAM = 'easy_mcp_ai_iss';

    private string $iss_mode = self::ISS_SITE;

    public function __construct( $iss_mode = self::ISS_SITE ) {
        $this->iss_mode = self::valid_iss_mode( $iss_mode );
    }

    public static function valid_iss_mode( $mode ): string {
        return in_array( $mode, array( self::ISS_SITE, self::ISS_RESOURCE, self::ISS_NONE ), true ) ? $mode : self::ISS_SITE;
    }

    public static function issuer_for( $mode ): ?string {
        if ( self::ISS_NONE === $mode ) {
            return null;
        }
        if ( self::ISS_RESOURCE === $mode ) {
            return rest_url( self::NAMESPACE_V1 . '/mcp' );
        }
        return home_url();
    }

    public static function consent_nonce_action( string $client_id, string $mode ): string {
        $action = 'easy_mcp_ai_oauth_consent_' . $client_id;
        return self::ISS_SITE === $mode ? $action : $action . '|' . $mode;
    }

    public static function resolved_min_capability() {
        return \Easy_MCP_AI\Auth\Connect_Policy::floor_capability();
    }

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
            $current_url = $this->build_current_url( $request );
            $login_url   = wp_login_url( $current_url );

            $response = new \WP_REST_Response( null, 302 );
            $response->header( 'Location', $login_url );
            $this->add_security_headers( $response );
            return $response;
        }

        $params         = $this->extract_params( $request );
        $client_or_error = $this->validate_authorize_params( $params );

        if ( is_wp_error( $client_or_error ) ) {
            return $this->error_response( $client_or_error, $params );
        }

        $client = $client_or_error;

        if ( ! \Easy_MCP_AI\Auth\Connect_Policy::current_user_can_connect( \Easy_MCP_AI\Auth\Connect_Policy::CHANNEL_OAUTH ) ) {
            return $this->redirect_with_error(
                $params,
                'access_denied',
                __( 'Your account does not have sufficient permissions to authorize MCP access.', 'easy-mcp-ai' ),
                'insufficient_capability'
            );
        }

        $open_request    = empty( $params['scope'] );
        $requested_scope = $open_request ? '' : sanitize_text_field( $params['scope'] );

        $scope_list = array_values( array_filter( array_map( 'trim', explode( ' ', $requested_scope ) ) ) );
        $scope_list = Scope_Map::apply_legacy_scope_upgrades( $scope_list );

        $valid_scopes = Scope_Map::get_all_scopes();
        foreach ( $scope_list as $s ) {
            if ( 'mcp' !== $s && ! in_array( $s, $valid_scopes, true ) ) {
                return $this->error_response(
                    new \WP_Error( 'invalid_scope', __( 'Unknown scope requested.', 'easy-mcp-ai' ) ),
                    $params
                );
            }
        }

        $stored = array( 'client_id' => Client_Metadata::storage_id( $params['client_id'] ) ) + $params;

        $local_document = ! empty( $client->metadata_url ) && Client_Metadata::is_local_redirect( $params['redirect_uri'] );

        $user    = wp_get_current_user();
        $consent = $this->get_existing_consent( $user->ID, $stored['client_id'] );
        $focus   = array();
        $held    = array();

        if ( $consent ) {
            $consented_scopes = array_filter( array_map( 'trim', explode( ' ', $consent->scope ) ) );
            $full_consent     = in_array( 'mcp', $consented_scopes, true );
            $is_subset = $open_request
                ? array() !== $consented_scopes
                : ( empty( array_diff( $scope_list, $consented_scopes ) ) || $full_consent );
            $step_up       = Step_Up::pending( $user->ID, $stored['client_id'] );
            $consented_now = Scope_Map::expand_category_scopes( Scope_Map::apply_legacy_scope_upgrades( array_values( $consented_scopes ) ) );
            $step_up_new   = $full_consent ? array() : array_values( array_diff( $step_up, $consented_now ) );

            if ( $is_subset && array() === $step_up_new ) {
                if ( $open_request ) {
                    $grant_list = Scope_Map::apply_legacy_scope_upgrades( array_values( $consented_scopes ) );
                } else {
                    $grant_list = in_array( 'mcp', $scope_list, true ) ? $scope_list : array_values( array_unique( array_merge( $scope_list, $step_up ) ) );
                }
                $grant_scope = implode( ' ', $grant_list );

                if ( $local_document ) {
                    if ( $open_request ) {
                        $held            = Scope_Map::expand_category_scopes( $grant_list );
                        $requested_scope = implode( ' ', $held );
                    } else {
                        $requested_scope = $grant_scope;
                    }
                    $params['scope'] = $requested_scope;
                } else {
                    $code = $this->mint_authorization_code( $stored, $user->ID, $grant_scope );
                    if ( null === $code ) {
                        return $this->redirect_with_error( $params, 'server_error', __( 'Failed to issue authorization code.', 'easy-mcp-ai' ), 'code_issue_failed' );
                    }
                    Step_Up::clear( $user->ID, $stored['client_id'] );
                    return $this->redirect_with_code( $params['redirect_uri'], $code, $params['state'] );
                }
            }

            if ( array() !== $step_up_new ) {
                if ( ! $open_request ) {
                    $requested_scope = implode( ' ', array_values( array_unique( array_merge( $scope_list, $consented_now, $step_up ) ) ) );
                    $params['scope'] = $requested_scope;
                }
                $focus = array(
                    'new'  => $step_up_new,
                    'held' => $consented_now,
                );
            }
        }

        $params['scope_sig'] = self::sign_scope( $params['client_id'], $params['scope'] );
        $script_nonce        = wp_create_nonce( 'easy_mcp_ai_consent_script' );
        $consent_options = array();
        if ( self::ISS_SITE !== $this->iss_mode ) {
            $consent_options = array(
                'form_action'  => home_url( '?easy_mcp_ai_oauth=authorize&' . self::ISS_PARAM . '=' . $this->iss_mode ),
                'nonce_action' => self::consent_nonce_action( $params['client_id'], $this->iss_mode ),
            );
        }
        if ( ! empty( $client->metadata_url ) ) {
            $consent_options['nonce_action'] = self::consent_nonce_action( $params['client_id'], $this->iss_mode );
            $consent_options['client_label'] = Client_Metadata::host( $params['client_id'] );
            if ( $local_document ) {
                $consent_options['notice'] = sprintf(
                    /* translators: 1: application name, 2: where the sign-in goes, such as 127.0.0.1 */
                    __( '%1$s will hand this sign-in to a program on your computer (%2$s). Any program can use this application\'s public client ID, so approve only if you started this connection yourself just now.', 'easy-mcp-ai' ),
                    (string) $client->client_name,
                    (string) ( wp_parse_url( $params['redirect_uri'], PHP_URL_HOST ) ?: wp_parse_url( $params['redirect_uri'], PHP_URL_SCHEME ) )
                );
            }
        }
        if ( array() !== $focus ) {
            $consent_options['step_up'] = $focus;
        } elseif ( array() !== $held ) {
            $consent_options['held'] = $held;
        }
        $html = Consent_Screen::render( $client, $user, $requested_scope, $params, $script_nonce, $consent_options );

        $response = new \WP_REST_Response( $html, 200 );
        $response->header( 'Content-Type', 'text/html; charset=utf-8' );
        $this->add_security_headers( $response, $script_nonce );
        return $response;
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
            $this->log_authorize_failure( 'not_logged_in', $this->extract_params( $request ) );
            return new \WP_Error(
                'access_denied',
                __( 'You must be logged in to authorize this request.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        if ( ! \Easy_MCP_AI\Auth\Connect_Policy::current_user_can_connect( \Easy_MCP_AI\Auth\Connect_Policy::CHANNEL_OAUTH ) ) {
            $this->log_authorize_failure( 'insufficient_capability', $this->extract_params( $request ) );
            return new \WP_Error(
                'access_denied',
                __( 'Your account does not have sufficient permissions to authorize MCP access.', 'easy-mcp-ai' ),
                array( 'status' => 403 )
            );
        }

        $params = $this->extract_params( $request );

        $nonce = sanitize_text_field( $request->get_param( '_wpnonce' ) );
        if ( ! wp_verify_nonce( $nonce, self::consent_nonce_action( $params['client_id'], $this->iss_mode ) ) ) {
            $this->log_authorize_failure( 'nonce_failed', $params );
            return new \WP_Error(
                'invalid_request',
                __( 'Security check failed. Please try again.', 'easy-mcp-ai' ),
                array( 'status' => 403 )
            );
        }

        $scope_sig = sanitize_text_field( $request->get_param( 'scope_sig' ) );
        $expected  = self::sign_scope( $params['client_id'], $params['scope'] );
        if ( '' === $scope_sig || ! hash_equals( $expected, $scope_sig ) ) {
            $this->log_authorize_failure( 'scope_signature_failed', $params );
            return new \WP_Error(
                'invalid_request',
                __( 'Scope integrity check failed. Please restart authorization.', 'easy-mcp-ai' ),
                array( 'status' => 403 )
            );
        }

        $validate_result = $this->validate_authorize_params( $params );
        if ( is_wp_error( $validate_result ) ) {
            return $this->error_response( $validate_result, $params );
        }

        $user   = wp_get_current_user();
        $action = sanitize_text_field( $request->get_param( 'consent_action' ) );

        if ( 'approve' !== $action ) {
            return $this->handle_deny_action( $params );
        }

        return $this->handle_approve_action( $request, $params, $user );
    }

    private function redirect_with_error( array $params, string $error, string $error_description, string $log_reason = '' ) {
        $this->log_authorize_failure( '' !== $log_reason ? $log_reason : $error, $params );

        $args = array(
            'error'             => $error,
            'error_description' => Token_Endpoint::wire_safe_description( $error_description ),
            'iss'               => self::issuer_for( $this->iss_mode ),
        );
        if ( '' !== (string) $params['state'] ) {
            $args['state'] = $params['state'];
        }
        $redirect = self::build_query_url( $params['redirect_uri'], $args );

        $response = new \WP_REST_Response( null, 302 );
        $response->header( 'Location', $redirect );
        $this->add_security_headers( $response );
        return $response;
    }

    private function handle_deny_action( array $params ) {
        Step_Up::clear( get_current_user_id(), isset( $params['client_id'] ) ? Client_Metadata::storage_id( (string) $params['client_id'] ) : '' );
        return $this->redirect_with_error(
            $params,
            'access_denied',
            __( 'The user denied the authorization request.', 'easy-mcp-ai' ),
            'user_denied'
        );
    }

    private function handle_approved_without_scopes( array $params ) {
        return $this->redirect_with_error(
            $params,
            'access_denied',
            __( 'No permissions were selected, so nothing was granted. Choose at least one permission and approve again.', 'easy-mcp-ai' ),
            'approved_without_scopes'
        );
    }

    private function handle_approve_action( \WP_REST_Request $request, array $params, \WP_User $user ) {
        $scope_string = self::resolve_granted_scope( $params['scope'], self::submitted_scopes( $request->get_param( 'access_level' ), $request->get_param( 'scopes' ) ) );

        if ( '' === $scope_string ) {
            Step_Up::clear( $user->ID, Client_Metadata::storage_id( $params['client_id'] ) );
            return $this->handle_approved_without_scopes( $params );
        }

        $stored = array( 'client_id' => Client_Metadata::storage_id( $params['client_id'] ) ) + $params;
        self::store_consent( $user->ID, $stored['client_id'], $scope_string );
        $code = $this->mint_authorization_code( $stored, $user->ID, $scope_string );
        if ( null === $code ) {
            return $this->redirect_with_error( $params, 'server_error', __( 'Failed to issue authorization code.', 'easy-mcp-ai' ), 'code_issue_failed' );
        }
        Step_Up::clear( $user->ID, $stored['client_id'] );

        return $this->redirect_with_code( $params['redirect_uri'], $code, $params['state'] );
    }

    public static function submitted_scopes( $access_level, $submitted_scopes ) {
        switch ( is_string( $access_level ) ? $access_level : '' ) {
            case 'read':
                return Scope_Map::get_read_scopes();
            case 'full':
                return array( 'mcp' );
        }
        return $submitted_scopes;
    }

    public static function resolve_granted_scope( $requested_scope, $submitted_scopes ) {
        $all_scopes = array_values( array_diff( Scope_Map::get_all_scopes(), array( 'mcp' ) ) );
        $submitted  = array();

        if ( is_array( $submitted_scopes ) ) {
            foreach ( $submitted_scopes as $s ) {
                $submitted[] = sanitize_text_field( $s );
            }
        }

        if ( ! empty( $requested_scope ) ) {
            $requested_scope_list = array_filter( array_map( 'trim', explode( ' ', (string) $requested_scope ) ) );
        } else {
            $requested_scope_list = array( 'mcp' );
        }
        $client_requested_mcp = in_array( 'mcp', $requested_scope_list, true );

        if ( $client_requested_mcp && in_array( 'mcp', $submitted, true ) ) {
            return 'mcp';
        }

        $valid_submitted = array_values( array_intersect( $submitted, $all_scopes ) );

        if ( ! empty( $requested_scope_list ) && ! $client_requested_mcp ) {
            $valid_submitted = array_values( array_intersect( $valid_submitted, $requested_scope_list ) );
        }

        if ( ! empty( $valid_submitted ) && empty( array_diff( $all_scopes, $valid_submitted ) ) ) {
            return 'mcp';
        }

        return implode( ' ', $valid_submitted );
    }

    public static function sign_scope( string $client_id, string $scope ): string {
        return hash_hmac( 'sha256', $client_id . '|' . $scope, wp_salt( 'auth' ) );
    }

    private function log_authorize_failure( $reason, array $params = array(), $detail = '' ) {
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true ) ) {
            return;
        }

        $redirect_host = '';
        if ( ! empty( $params['redirect_uri'] ) ) {
            $parsed = wp_parse_url( $params['redirect_uri'] );
            $redirect_host = ( ! empty( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '' )
                           . ( ! empty( $parsed['host'] ) ? $parsed['host'] : '' );
        }

        $details = array(
            'stage'         => 'authorize',
            'reason'        => (string) $reason,
            'client_id'     => ! empty( $params['client_id'] ) ? (string) $params['client_id'] : '',
            'redirect_host' => $redirect_host,
            'wp_user_id'    => get_current_user_id(),
        );
        if ( '' !== (string) $detail ) {
            $details['detail'] = (string) $detail;
        }

        global $wpdb;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct insert required for audit logging; mirrors Server::log_auth_failure().
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array(
                'token_id'        => 0,
                'tool_name'       => '_oauth_authorize',
                'arguments'       => wp_json_encode( $details ),
                'result_status'   => 'auth_failure',
                'ip_address'      => class_exists( '\\Easy_MCP_AI\\Client_IP' ) ? \Easy_MCP_AI\Client_IP::get() : '',
                'auth_source'     => 'oauth',
                'wp_user_id'      => $details['wp_user_id'] > 0 ? (int) $details['wp_user_id'] : null,
                'oauth_client_id' => self::column_safe_client_id( Client_Metadata::storage_id( $details['client_id'] ) ),
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    const AUDIT_CLIENT_ID_MAX_LENGTH = 191;

    public static function column_safe_client_id( $client_id ) {
        $client_id = (string) $client_id;
        if ( '' === $client_id || strlen( $client_id ) > self::AUDIT_CLIENT_ID_MAX_LENGTH ) {
            return null;
        }
        return $client_id;
    }

    private function extract_params( \WP_REST_Request $request ) {
        $resource_raw = $request->get_param( 'resource' );
        $resource     = is_string( $resource_raw ) && '' !== $resource_raw
            ? esc_url_raw( $resource_raw )
            : rest_url( self::NAMESPACE_V1 . '/mcp' );

        return array(
            'response_type'         => sanitize_text_field( $request->get_param( 'response_type' ) ),
            'client_id'             => sanitize_text_field( $request->get_param( 'client_id' ) ),
            'redirect_uri'          => esc_url_raw( $request->get_param( 'redirect_uri' ), Client_Registry::redirect_uri_allowed_protocols() ),
            'code_challenge'        => sanitize_text_field( $request->get_param( 'code_challenge' ) ),
            'code_challenge_method' => sanitize_text_field( $request->get_param( 'code_challenge_method' ) ),
            'state'                 => is_scalar( $request->get_param( 'state' ) ) ? (string) $request->get_param( 'state' ) : '',
            'resource'              => $resource,
            'scope'                 => sanitize_text_field( $request->get_param( 'scope' ) ),
        );
    }

    private function validate_authorize_params( array $params ) {

        if ( empty( $params['client_id'] ) ) {
            return new \WP_Error(
                'invalid_request',
                __( 'Missing required parameter: client_id', 'easy-mcp-ai' ),
                array( 'status' => 400, 'no_redirect' => true )
            );
        }
        if ( empty( $params['redirect_uri'] ) ) {
            return new \WP_Error(
                'invalid_request',
                __( 'Missing required parameter: redirect_uri', 'easy-mcp-ai' ),
                array( 'status' => 400, 'no_redirect' => true )
            );
        }

        $client = null;
        if ( Client_Metadata::is_url( $params['client_id'] ) && Client_Metadata_Support::resolves() ) {
            $accept_new = Client_Metadata_Support::accepts_sign_ins();
            if ( $accept_new || Client_Metadata::is_stored( $params['client_id'] ) ) {
                if ( ! \Easy_MCP_AI\Auth\Connect_Policy::current_user_can_connect( \Easy_MCP_AI\Auth\Connect_Policy::CHANNEL_OAUTH ) ) {
                    return new \WP_Error(
                        'access_denied',
                        __( 'Your account does not have sufficient permissions to authorize MCP access.', 'easy-mcp-ai' ),
                        array( 'status' => 403, 'no_redirect' => true, 'log_reason' => 'insufficient_capability' )
                    );
                }
                $client = Client_Metadata::for_authorization( $params['client_id'], $params['redirect_uri'], $accept_new );
                if ( is_wp_error( $client ) ) {
                    return $client;
                }
            }
        } else {
            $client = ( new Client_Registry() )->find_client( $params['client_id'] );
        }
        if ( ! $client ) {
            return new \WP_Error(
                'invalid_client',
                __( 'Unknown or inactive client.', 'easy-mcp-ai' ),
                array( 'status' => 400, 'no_redirect' => true )
            );
        }

        $registered_uris = json_decode( $client->redirect_uris, true );
        if ( ! is_array( $registered_uris ) || ! Client_Registry::redirect_uri_is_registered( $params['redirect_uri'], $registered_uris ) ) {
            return new \WP_Error(
                'invalid_redirect_uri',
                __( 'The redirect_uri does not match any registered URI for this client.', 'easy-mcp-ai' ),
                array( 'status' => 400, 'no_redirect' => true )
            );
        }

        if ( 'code' !== $params['response_type'] ) {
            return new \WP_Error(
                'unsupported_response_type',
                __( 'Only response_type=code is supported.', 'easy-mcp-ai' )
            );
        }

        $required = array( 'code_challenge', 'code_challenge_method' );
        foreach ( $required as $field ) {
            if ( empty( $params[ $field ] ) ) {
                return new \WP_Error(
                    'invalid_request',
                    /* translators: %s: parameter name */
                    sprintf( __( 'Missing required parameter: %s', 'easy-mcp-ai' ), $field )
                );
            }
        }

        if ( 'S256' !== $params['code_challenge_method'] ) {
            return new \WP_Error(
                'invalid_request',
                __( 'Only code_challenge_method=S256 is supported.', 'easy-mcp-ai' )
            );
        }

        if ( ! preg_match( '/^[A-Za-z0-9\-._~]{43,128}$/', $params['code_challenge'] ) ) {
            return new \WP_Error(
                'invalid_request',
                __( 'Invalid code_challenge format.', 'easy-mcp-ai' )
            );
        }

        $expected_resource = rest_url( self::NAMESPACE_V1 . '/mcp' );
        if ( ! Token_Endpoint::resource_matches( $params['resource'], $expected_resource ) ) {
            return new \WP_Error(
                'invalid_target',
                __( 'Resource parameter does not match this server.', 'easy-mcp-ai' )
            );
        }

        return $client;
    }

    private function error_response( \WP_Error $error, array $params ) {
        $data = $error->get_error_data();

        $this->log_authorize_failure(
            is_array( $data ) && ! empty( $data['log_reason'] ) ? (string) $data['log_reason'] : $error->get_error_code(),
            $params,
            is_array( $data ) && ! empty( $data['log_detail'] ) ? (string) $data['log_detail'] : ''
        );

        if (
            empty( $params['redirect_uri'] ) ||
            ( is_array( $data ) && ! empty( $data['no_redirect'] ) ) ||
            'invalid_redirect_uri' === $error->get_error_code()
        ) {
            $html = $this->render_error_page( $error );

            $response = new \WP_REST_Response( $html, 400 );
            $response->header( 'Content-Type', 'text/html; charset=utf-8' );
            $this->add_security_headers( $response );
            return $response;
        }

        $error_args = array(
            'error'             => $error->get_error_code(),
            'error_description' => Token_Endpoint::wire_safe_description( $error->get_error_message() ),
            'iss'               => self::issuer_for( $this->iss_mode ),
        );
        if ( '' !== (string) $params['state'] ) {
            $error_args['state'] = $params['state'];
        }
        $redirect = self::build_query_url( $params['redirect_uri'], $error_args );

        $response = new \WP_REST_Response( null, 302 );
        $response->header( 'Location', $redirect );
        $this->add_security_headers( $response );
        return $response;
    }

    private function render_error_page( \WP_Error $error ) {
        $code    = esc_html( $error->get_error_code() );
        $message = esc_html( $error->get_error_message() );
        $title   = esc_html__( 'Authorization Error', 'easy-mcp-ai' );

        $site = esc_html( \Easy_MCP_AI\Console_Styles::site_label() );

        $html  = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="' . esc_attr( get_locale() ) . '">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '<meta charset="utf-8">' . "\n";
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        $html .= '<meta name="robots" content="noindex, nofollow">' . "\n";
        $html .= '<title>' . $title . ' &mdash; ' . $site . '</title>' . "\n";
        $html .= \Easy_MCP_AI\Console_Styles::inline() . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body class="emcp-page">' . "\n";
        $html .= '<div class="emcp-shell emcp-shell--narrow">' . "\n";
        $html .= '<div class="emcp-topbar">' . \Easy_MCP_AI\Console_Styles::logo();
        $html .= '<h1 class="emcp-topbar__title">' . $title . '</h1>';
        $html .= \Easy_MCP_AI\Console_Styles::site_name() . '</div>' . "\n";
        $html .= '<div class="emcp-body">' . "\n";
        $html .= '<div class="emcp-notice emcp-notice--error">' . "\n";
        $html .= '<p class="emcp-notice__title"><code class="emcp-mono">' . $code . '</code></p>' . "\n";
        $html .= '<p>' . $message . '</p>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '</div>' . "\n";
        $html .= '<p class="emcp-pagenote"><span class="emcp-dot"></span>' . esc_html__( 'Powered by Easy MCP AI', 'easy-mcp-ai' ) . '</p>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>' . "\n";

        return $html;
    }

    private function get_existing_consent( $user_id, $client_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name is prefixed by $wpdb->prefix (trusted); admin-side single-row lookup does not warrant object cache.
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE wp_user_id = %d AND client_id = %s LIMIT 1", $user_id, $client_id ) );
    }

    public static function store_consent( $user_id, $client_id, $scope ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $now   = current_time( 'mysql', true );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name is prefixed by $wpdb->prefix (trusted); upsert cannot use $wpdb->insert().
        $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (wp_user_id, client_id, scope, granted_at, updated_at) VALUES (%d, %s, %s, %s, %s) ON DUPLICATE KEY UPDATE scope = VALUES(scope), updated_at = VALUES(updated_at)", $user_id, $client_id, $scope, $now, $now ) );
        $id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE wp_user_id = %d AND client_id = %s LIMIT 1", $user_id, $client_id ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return (int) $id;
    }

    private function mint_authorization_code( array $params, $user_id, $scope ) {
        global $wpdb;

        $raw_code  = bin2hex( random_bytes( 32 ) );
        $code_hash = \Easy_MCP_AI\Auth\Token_Keys::hash_current( $raw_code );
        $table     = $wpdb->prefix . 'easy_mcp_ai_oauth_codes';
        $expires   = gmdate( 'Y-m-d H:i:s', time() + self::CODE_LIFETIME );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert() on plugin-owned table; writes don't need caching.
        $inserted = $wpdb->insert(
            $table,
            array(
                'code_hash'             => $code_hash,
                'client_id'             => $params['client_id'],
                'wp_user_id'            => $user_id,
                'redirect_uri'          => $params['redirect_uri'],
                'code_challenge'        => $params['code_challenge'],
                'code_challenge_method' => $params['code_challenge_method'],
                'resource'              => $params['resource'],
                'scope'                 => $scope,
                'expires_at'            => $expires,
            ),
            array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );

        if ( false === $inserted ) {
            return null;
        }

        return $raw_code;
    }

    private static function build_query_url( $base, array $args ) {
        $encoded = array();
        foreach ( $args as $key => $value ) {
            if ( false === $value || null === $value ) {
                continue;
            }
            $encoded[ $key ] = rawurlencode( (string) $value );
        }
        return add_query_arg( $encoded, $base );
    }

    private function redirect_with_code( $redirect_uri, $code, $state ) {
        $args = array(
            'code' => $code,
            'iss'  => self::issuer_for( $this->iss_mode ),
        );
        if ( '' !== (string) $state ) {
            $args['state'] = $state;
        }
        $redirect = self::build_query_url( $redirect_uri, $args );

        $response = new \WP_REST_Response( null, 302 );
        $response->header( 'Location', $redirect );
        $this->add_security_headers( $response );
        return $response;
    }

    private function build_current_url( \WP_REST_Request $request ) {
        $base   = rest_url( self::NAMESPACE_V1 . '/oauth/authorize' );
        $params = $request->get_query_params();
        if ( ! empty( $params ) ) {
            $allowed = array(
                'response_type',
                'client_id',
                'redirect_uri',
                'scope',
                'state',
                'code_challenge',
                'code_challenge_method',
                'resource',
            );
            $filtered = array();
            foreach ( $allowed as $key ) {
                if ( isset( $params[ $key ] ) ) {
                    $filtered[ $key ] = $params[ $key ];
                }
            }
            if ( self::ISS_SITE !== $this->iss_mode ) {
                $filtered[ self::ISS_PARAM ] = $this->iss_mode;
            }
            if ( ! empty( $filtered ) ) {
                $base = self::build_query_url( $base, $filtered );
            }
        }
        return $base;
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
        $csp = "default-src 'self'; style-src 'unsafe-inline' 'self'; frame-ancestors 'none'";
        if ( $script_nonce ) {
            $csp .= "; script-src 'nonce-" . $script_nonce . "'";
        }
        $response->header( 'Content-Security-Policy', $csp );
        $response->header( 'X-Content-Type-Options', 'nosniff' );

        if ( is_ssl() ) {
            $response->header( 'Strict-Transport-Security', 'max-age=31536000' );
        }
    }
}
