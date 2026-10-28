<?php
namespace Easy_MCP_AI\MCP;

use Easy_MCP_AI\Auth\Token_Manager;
use Easy_MCP_AI\Auth\Token_Auth;
use Easy_MCP_AI\OAuth\OAuth_Token_Validator;
use Easy_MCP_AI\OAuth\OAuth_Token_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Transport {
    const NAMESPACE_V1     = 'easy-mcp-ai/v1';
    const ROUTE            = '/mcp';
    const ROUTE_WITH_KEY   = '/mcp/(?P<api_key>wpmcp_(?:[a-f0-9]{6}_)?[a-f0-9]{64})';
    const MAX_BATCH_SIZE   = 20;

    private $server;
    private $token_manager;
    private $oauth_scope = null;
    private $last_scope_challenge = null;
    private $in_batch = false;

    public function __construct( Server $server, Token_Manager $token_manager ) {
        $this->server        = $server;
        $this->token_manager = $token_manager;
    }

    public function header_probe_permitted( $request ) {
        if ( ! class_exists( '\Easy_MCP_AI\Diagnostics\Check_Header_Probe' ) ) {
            return false;
        }

        return \Easy_MCP_AI\Diagnostics\Check_Header_Probe::authorize_probe( (string) $request->get_param( 'probe' ) );
    }

    public function handle_header_probe( $request ) {
        $headers = function_exists( 'getallheaders' ) ? (array) getallheaders() : null;

        return \rest_ensure_response(
            \Easy_MCP_AI\Diagnostics\Check_Header_Probe::probe_response(
                $_SERVER, // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER -- Presence check only; no value is read or returned.
                $headers,
                (string) $request->get_param( 'probe' ),
                class_exists( '\Easy_MCP_AI\MCP\Rest_Auth_Override' ) ? Rest_Auth_Override::observed() : null
            )
        );
    }

    public function register_routes() {
        $handlers = array(
            array( 'methods' => 'POST',    'callback' => array( $this, 'handle_post' ),    'permission_callback' => '__return_true' ),
            array( 'methods' => 'GET',     'callback' => array( $this, 'handle_get' ),     'permission_callback' => '__return_true' ),
            array( 'methods' => 'DELETE',  'callback' => array( $this, 'handle_delete' ),  'permission_callback' => '__return_true' ),
            array( 'methods' => 'OPTIONS', 'callback' => array( $this, 'handle_options' ), 'permission_callback' => '__return_true' ),
        );
        \register_rest_route( self::NAMESPACE_V1, self::ROUTE, $handlers );

        \register_rest_route(
            self::NAMESPACE_V1,
            '/header-probe',
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'handle_header_probe' ),
                'permission_callback' => array( $this, 'header_probe_permitted' ),
            )
        );
        \register_rest_route( self::NAMESPACE_V1, self::ROUTE_WITH_KEY, $handlers );

        \add_filter( 'rest_allowed_cors_headers', array( $this, 'filter_cors_allowed_headers' ), 10, 2 );

        \add_filter( 'rest_pre_serve_request', array( $this, 'serve_listen_stream' ), 10, 4 );
    }

    public function filter_cors_allowed_headers( $headers, $request = null ) {
        if ( ! is_array( $headers ) ) {
            return $headers;
        }

        if ( $request instanceof \WP_REST_Request ) {
            $is_ours = ( 0 === strpos( (string) $request->get_route(), '/' . self::NAMESPACE_V1 . '/' ) );
        } else {
            $is_ours = self::request_uri_targets_our_namespace();
        }

        if ( ! $is_ours ) {
            return $headers;
        }

        foreach ( array( 'Mcp-Session-Id', 'Last-Event-ID', 'MCP-Protocol-Version', 'Mcp-Method', 'Mcp-Name' ) as $header ) {
            if ( ! in_array( strtolower( $header ), array_map( 'strtolower', $headers ), true ) ) {
                $headers[] = $header;
            }
        }
        return $headers;
    }

    private static function request_uri_targets_our_namespace() {
        if ( empty( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
            return false;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only substring match; nothing is stored or output.
        $uri    = (string) $_SERVER['REQUEST_URI'];
        $needle = '/' . self::NAMESPACE_V1 . '/';

        if ( false !== strpos( $uri, $needle ) ) {
            return true;
        }

        if ( false !== strpos( $uri, 'rest_route=' ) ) {
            return false !== strpos( rawurldecode( $uri ), $needle );
        }

        return false;
    }

    private function refuse_url_key( \WP_REST_Request $request ): ?\WP_REST_Response {
        $api_keys = self::url_key_values( $request );
        if ( ! $api_keys ) {
            return null;
        }
        if ( true !== \Easy_MCP_AI\Config::get( 'api_key_header_only' ) ) {
            return null;
        }
        $identity = array();
        foreach ( $api_keys as $api_key ) {
            $identity = $this->url_key_identity( $api_key );
            if ( $identity ) {
                break;
            }
        }
        if ( $identity && $this->refusal_log_budget_allows( 'easy_mcp_ai_urlkeyreflog_', 'legacy', $identity['token_id'] ) ) {
            $ip = class_exists( '\\Easy_MCP_AI\\Client_IP' )
                ? (string) \Easy_MCP_AI\Client_IP::get()
                : ( isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
            $this->server->log_auth_failure( $ip, self::ERROR_URL_KEY, $identity );
        }
        return $this->make_unauthorized_response( null, 401, 'invalid_request', self::URL_KEY_DESCRIPTION );
    }

    private static function url_key_values( \WP_REST_Request $request ): array {
        $first   = $request->get_param( 'api_key' );
        $values  = empty( $first ) ? array() : array( $first );
        $sources = array( $request->get_json_params(), $request->get_body_params(), $request->get_query_params(), $request->get_url_params() );
        foreach ( $sources as $source ) {
            if ( is_array( $source ) && ! empty( $source['api_key'] ) ) {
                $values[] = $source['api_key'];
            }
        }
        return $values;
    }

    private function url_key_identity( $api_key ): array {
        if ( ! is_string( $api_key ) || 0 !== strpos( $api_key, 'wpmcp_' ) ) {
            return array();
        }
        $row = $this->token_manager->validate_token( $api_key );
        if ( is_array( $row ) ) {
            return array(
                'auth_source' => 'legacy',
                'token_id'    => (int) $row['id'],
                'wp_user_id'  => (int) $row['wp_user_id'],
            );
        }
        $reason = $this->token_manager->get_last_validation_error();
        return $this->is_site_mismatch( $reason ) ? $this->site_mismatch_identity( $reason ) : array();
    }

    private function inject_url_token( \WP_REST_Request $request ) {
        $api_key = $request->get_param( 'api_key' );
        if ( $api_key && ! $request->get_header( 'authorization' ) ) {
            $request->set_header( 'Authorization', 'Bearer ' . $api_key );
        }
    }

    private function inject_basic_api_key( \WP_REST_Request $request ) {
        $header = $request->get_header( 'authorization' );
        if ( $header ) {
            if ( 0 !== stripos( $header, 'Basic ' ) ) {
                return;
            }
            $key = \Easy_MCP_AI\Auth_Header::api_key_from_basic( $header );
            if ( null !== $key ) {
                $request->set_header( 'Authorization', 'Bearer ' . $key );
            }
            return;
        }
        if ( isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['PHP_AUTH_PW'] ) ) {
            $key = \Easy_MCP_AI\Auth_Header::api_key_from_pair(
                isset( $_SERVER['PHP_AUTH_USER'] ) ? (string) $_SERVER['PHP_AUTH_USER'] : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
                isset( $_SERVER['PHP_AUTH_PW'] ) ? (string) $_SERVER['PHP_AUTH_PW'] : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
            );
            if ( null !== $key ) {
                $request->set_header( 'Authorization', 'Bearer ' . $key );
            }
        }
    }

    private static function mark_mcp_request(): void {
        if ( ! defined( 'EASY_MCP_AI_MCP_REQUEST' ) ) {
            define( 'EASY_MCP_AI_MCP_REQUEST', true );
        }
    }

    public function handle_post( \WP_REST_Request $request ) {
        self::mark_mcp_request();
        $this->server->set_request_client_caps( null );
        $this->oauth_scope          = null;
        $this->last_scope_challenge = null;
        $url_key_refusal = $this->refuse_url_key( $request );
        if ( $url_key_refusal ) {
            return $url_key_refusal;
        }
        $this->inject_url_token( $request );
        $this->inject_basic_api_key( $request );

        $origin_error = $this->validate_origin( $request, (bool) $request->get_header( 'authorization' ) );
        if ( $origin_error ) {
            return $origin_error;
        }

        if ( ! $request->get_header( 'authorization' ) ) {
            return $this->make_unauthorized_response( null );
        }

        $content_type = $request->get_content_type();
        if ( ! $content_type || 'application/json' !== $content_type['value'] ) {
            return new \WP_REST_Response( JSON_RPC::error_response( null, Error_Codes::INVALID_REQUEST, 'Content-Type must be application/json' ), 415 );
        }

        $accept = $request->get_header( 'accept' );
        if ( $accept && false === strpos( $accept, 'application/json' ) && false === strpos( $accept, '*/*' ) && false === strpos( $accept, 'text/event-stream' ) ) {
            return new \WP_REST_Response( null, 406 );
        }

        $parsed = JSON_RPC::parse_request( $request->get_body() );
        if ( \is_wp_error( $parsed ) ) {
            $error_code = is_int( $parsed->get_error_data() ) ? $parsed->get_error_data() : Error_Codes::PARSE_ERROR;
            $version = $request->get_header( 'mcp-protocol-version' );
            if ( $version && ! in_array( $version, Server::LEGACY_PROTOCOL_VERSIONS, true ) ) {
                json_decode( $request->get_body() );
                if ( Error_Codes::PARSE_ERROR === $error_code && JSON_ERROR_NONE === json_last_error() ) {
                    return $this->modern_error( null, Error_Codes::INVALID_REQUEST, 'Expected a JSON-RPC request object' );
                }
                return $this->modern_error( null, $error_code, $parsed->get_error_message() );
            }
            return new \WP_REST_Response( JSON_RPC::error_response( null, $error_code, $parsed->get_error_message() ), 400 );
        }

        $token_id = $wp_user_id = $allowed_tools = null;
        $oauth_client_id = null;
        $result   = null;
        $is_oauth = false;
        $auth_source_for_request = null;
        if ( $this->is_oauth_available() ) {
            $oauth_tm       = new OAuth_Token_Manager();
            $oauth_validator = new OAuth_Token_Validator( $oauth_tm );
            $result = $oauth_validator->authenticate( $request );
            if ( ! \is_wp_error( $result ) ) {
                $is_oauth      = true;
                $token_id      = $result['token_id'];
                $wp_user_id    = $result['wp_user_id'];
                $allowed_tools = isset( $result['allowed_tools'] ) ? $result['allowed_tools'] : null;
                $oauth_client_id = isset( $result['client_id'] ) ? $result['client_id'] : null;
                $this->oauth_scope = isset( $result['scope'] ) ? (string) $result['scope'] : null;
                $auth_source_for_request = 'oauth';
            }
        }
        $oauth_verdict = $result;
        if ( null === $token_id ) {
            $auth   = new Token_Auth( $this->token_manager );
            $result = $auth->authenticate( $request );
            if ( ! \is_wp_error( $result ) ) {
                $token_id   = $result['token_id'];
                $wp_user_id = $result['wp_user_id'];
                $auth_source_for_request = 'legacy';
            }
        }

        if ( null === $token_id && ! $request->get_header( 'authorization' ) ) {
            return $this->make_unauthorized_response( null );
        }

        if ( \is_wp_error( $result ) && $request->get_header( 'authorization' ) ) {
            $ip        = class_exists( '\\Easy_MCP_AI\\Client_IP' )
                ? (string) \Easy_MCP_AI\Client_IP::get()
                : trim( explode( ',', isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' )[0] );

            if ( $this->is_site_mismatch( $result ) ) {
                $this->server->log_auth_failure( $ip, Token_Manager::ERROR_SITE_MISMATCH, $this->site_mismatch_identity( $result ) );
                return $this->make_unauthorized_response( null, 401, 'invalid_token', self::SITE_MISMATCH_DESCRIPTION );
            }
            if ( $this->is_invalid_audience( $oauth_verdict ) ) {
                $this->server->log_auth_failure( $ip, OAuth_Token_Validator::ERROR_INVALID_AUDIENCE, $this->invalid_audience_identity( $oauth_verdict ) );
                return $this->make_unauthorized_response( null, 401, 'invalid_token' );
            }
            $cache_key = 'easy_mcp_ai_auth_fail_' . md5( $ip );

            if ( \wp_using_ext_object_cache() ) {
                \wp_cache_add( $cache_key, 0, 'easy_mcp_ai', 60 );
                $new_fails = \wp_cache_incr( $cache_key, 1, 'easy_mcp_ai' );
            } else {
                $new_fails = (int) \get_transient( $cache_key ) + 1;
                \set_transient( $cache_key, $new_fails, 60 );
            }

            if ( $new_fails > 20 ) {
                return new \WP_REST_Response( array( 'error' => 'Too many failed authentication attempts. Try again later.' ), 429 );
            }
            $this->server->log_auth_failure( $ip, $result->get_error_message() );
        }

        if ( null === $token_id ) {
            return $this->make_unauthorized_response( null, 401, 'invalid_token' );
        }

        $ip_refusal = $this->refuse_if_ip_forbidden( $token_id, $auth_source_for_request, $wp_user_id, $oauth_client_id );
        if ( $ip_refusal ) {
            return $ip_refusal;
        }

        $wire = json_decode( $request->get_body() );
        $header_version = $request->get_header( 'mcp-protocol-version' );
        $body_version = isset( $wire->params->_meta->{'io.modelcontextprotocol/protocolVersion'} )
            ? $wire->params->_meta->{'io.modelcontextprotocol/protocolVersion'} : null;
        $modern = ( $header_version && ! in_array( $header_version, Server::LEGACY_PROTOCOL_VERSIONS, true ) )
            || ( null !== $body_version && ! in_array( $body_version, Server::LEGACY_PROTOCOL_VERSIONS, true ) )
            || ( isset( $parsed['method'] ) && 'server/discover' === $parsed['method'] && ! $header_version );
        if ( $modern ) {
            return $this->process_modern_message( $wire, $request, $token_id, $wp_user_id, $allowed_tools, $auth_source_for_request, $oauth_client_id );
        }

        $is_initialize = isset( $parsed['method'] ) && 'initialize' === $parsed['method'];
        if ( ! $is_initialize ) {
            $header_error = $this->validate_protocol_version_header( $request );
            if ( $header_error ) {
                return $header_error;
            }
        }

        if ( isset( $parsed[0] ) && ( is_array( $parsed[0] ) || is_wp_error( $parsed[0] ) ) ) {
            $session_version = $this->get_session_protocol_version( $request );
            if ( ! $session_version ) {
                $session_version = $header_version ?: '2025-03-26';
            }
            if ( $session_version && version_compare( $session_version, '2025-06-18', '>=' ) ) {
                return new \WP_REST_Response(
                    JSON_RPC::error_response( null, Error_Codes::INVALID_REQUEST, 'JSON-RPC batching was removed in MCP protocol 2025-06-18' ),
                    400
                );
            }
            return $this->handle_batch( $parsed, $token_id, $wp_user_id, $request, $allowed_tools, $auth_source_for_request, $oauth_client_id );
        }
        return $this->process_single_message( $parsed, $token_id, $wp_user_id, $request, null, $allowed_tools, $auth_source_for_request, $oauth_client_id );
    }

    private function call_handle_message_with_identity( $message, $token_id, $allowed_tools, $auth_source, $wp_user_id, $oauth_client_id, $omit_allowed_tools = false ) {
        $this->server->set_request_identity( $auth_source, $wp_user_id, $oauth_client_id, 'oauth' === $auth_source ? $this->oauth_scope : null );
        try {
            return $omit_allowed_tools
                ? $this->server->handle_message( $message, $token_id )
                : $this->server->handle_message( $message, $token_id, $allowed_tools );
        } finally {
            $this->last_scope_challenge = $this->server->take_scope_challenge();
            $this->server->clear_request_identity();
        }
    }

    private function scope_challenge_response( $body, $wp_user_id = 0, $client_id = null ) {
        $scope                      = $this->last_scope_challenge;
        $this->last_scope_challenge = null;
        if ( $this->in_batch || null === $scope || '' === $scope ) {
            return null;
        }
        $granted = preg_split( '/\s+/', trim( (string) $this->oauth_scope ), -1, PREG_SPLIT_NO_EMPTY );
        $added   = array_diff( preg_split( '/\s+/', trim( $scope ), -1, PREG_SPLIT_NO_EMPTY ), $granted );
        \Easy_MCP_AI\OAuth\Step_Up::record( (int) $wp_user_id, (string) $client_id, $added );
        $response = new \WP_REST_Response( $body, 403 );
        $response->header( 'Cache-Control', 'no-store, private' );
        $response->header(
            'WWW-Authenticate',
            'Bearer error="insufficient_scope", scope="' . $scope . '", resource_metadata="' . self::resource_metadata_url() . '", error_description="This tool needs an additional scope; ask the user to authorize it"'
        );
        $this->add_cors_headers( $response );
        return $response;
    }

    private function modern_error( $id, $code, $message, $status = 400, $data = null ) {
        $body = JSON_RPC::error_response( $id, $code, $message, $data );
        if ( null === $id ) {
            unset( $body['id'] );
        }
        $response = new \WP_REST_Response( $body, $status );
        $response->header( 'Cache-Control', 'no-store, private' );
        $this->add_cors_headers( $response );
        return $response;
    }

    private function process_modern_message( $wire, $request, $token_id, $wp_user_id, $allowed_tools, $auth_source, $client_id ) {
        $id = isset( $wire->id ) && ( is_string( $wire->id ) || is_int( $wire->id ) || ( is_float( $wire->id ) && is_finite( $wire->id ) ) ) ? $wire->id : null;
        if ( ! ( $wire instanceof \stdClass ) || null === $id || ! isset( $wire->method ) || ! is_string( $wire->method ) ) {
            return $this->modern_error( null, Error_Codes::INVALID_REQUEST, 'Expected a single JSON-RPC request with a string or number id' );
        }
        $params = isset( $wire->params ) ? $wire->params : null;
        $meta = $params instanceof \stdClass && isset( $params->_meta ) ? $params->_meta : null;
        $version = $meta instanceof \stdClass && isset( $meta->{'io.modelcontextprotocol/protocolVersion'} )
            ? $meta->{'io.modelcontextprotocol/protocolVersion'} : null;
        $header_version = $request->get_header( 'mcp-protocol-version' );
        if ( $header_version && ! in_array( $header_version, Server::SUPPORTED_PROTOCOL_VERSIONS, true ) ) {
            return $this->modern_error( $id, Error_Codes::UNSUPPORTED_PROTOCOL_VERSION, 'Unsupported protocol version', 400, array(
                'supported' => Server::SUPPORTED_PROTOCOL_VERSIONS, 'requested' => $header_version,
            ) );
        }
        if ( ! $header_version ) {
            return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'MCP-Protocol-Version header is required' );
        }
        if ( ! is_string( $version ) || '' === $version ) {
            return $this->modern_error( $id, Error_Codes::INVALID_PARAMS, 'Request metadata must include io.modelcontextprotocol/protocolVersion' );
        }
        if ( $version !== $header_version ) {
            return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'MCP-Protocol-Version must match request metadata' );
        }
        if ( Server::PROTOCOL_VERSION !== $version ) {
            return $this->modern_error( $id, Error_Codes::UNSUPPORTED_PROTOCOL_VERSION, 'Unsupported protocol version', 400, array(
                'supported' => Server::SUPPORTED_PROTOCOL_VERSIONS, 'requested' => $version,
            ) );
        }
        $method = $request->get_header( 'mcp-method' );
        if ( ! $method || ! preg_match( '/^[\x21-\x7e]+$/D', $method ) || $method !== $wire->method ) {
            return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'Mcp-Method must match the request method' );
        }
        $name_fields = array( 'tools/call' => 'name', 'prompts/get' => 'name', 'resources/read' => 'uri' );
        if ( isset( $name_fields[ $method ] ) ) {
            $field = $name_fields[ $method ];
            $name = $request->get_header( 'mcp-name' );
            if ( ! is_string( $name ) || '' === $name || ! preg_match( '/^[\x20-\x7e\x09]+$/D', $name ) || trim( $name ) !== $name ) {
                return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'Missing or malformed Mcp-Name' );
            }
            if ( 0 === strpos( $name, '=?base64?' ) && '?=' === substr( $name, -2 ) ) {
                $encoded = substr( $name, 9, -2 );
                $name = base64_decode( $encoded, true );
                if ( false === $name || base64_encode( $name ) !== $encoded || ! preg_match( '//u', $name ) ) {
                    return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'Malformed Base64 Mcp-Name' );
                }
            }
            if ( ! isset( $params->$field ) || ! is_string( $params->$field ) || $name !== $params->$field ) {
                return $this->modern_error( $id, Error_Codes::HEADER_MISMATCH, 'Mcp-Name must match the request name or URI' );
            }
        }
        $capabilities = isset( $meta->{'io.modelcontextprotocol/clientCapabilities'} ) ? $meta->{'io.modelcontextprotocol/clientCapabilities'} : null;
        if ( ! ( $capabilities instanceof \stdClass ) ) {
            return $this->modern_error( $id, Error_Codes::INVALID_PARAMS, 'clientCapabilities must be a JSON object' );
        }
        if ( property_exists( $meta, 'io.modelcontextprotocol/clientInfo' ) ) {
            $info = $meta->{'io.modelcontextprotocol/clientInfo'};
            if ( ! ( $info instanceof \stdClass ) || ! isset( $info->name, $info->version ) || ! is_string( $info->name ) || ! is_string( $info->version ) ) {
                return $this->modern_error( $id, Error_Codes::INVALID_PARAMS, 'clientInfo must include string name and version fields' );
            }
        }
        if ( 'tools/call' === $method && property_exists( $params, 'arguments' ) && ! ( $params->arguments instanceof \stdClass ) ) {
            return $this->modern_error( $id, Error_Codes::INVALID_PARAMS, 'Tool arguments must be a JSON object' );
        }
        if ( ! $this->set_current_user( $wp_user_id ) ) {
            return $this->make_unauthorized_response( null, 401, 'invalid_token' );
        }
        $this->token_manager->update_last_used( $token_id );
        $message = json_decode( $request->get_body(), true );
        $this->server->set_request_identity( $auth_source, $wp_user_id, $client_id, 'oauth' === $auth_source ? $this->oauth_scope : null );
        try {
            $body = $this->server->handle_modern_message( $message, $token_id, $allowed_tools );
        } finally {
            $this->last_scope_challenge = $this->server->take_scope_challenge();
            $this->server->clear_request_identity();
        }
        if ( 'subscriptions/listen' === $method && isset( $body['result'] ) ) {
            return $this->listen_stream_response( $id, $body );
        }
        $challenged = $this->scope_challenge_response( $body, $wp_user_id, $client_id );
        if ( $challenged ) {
            return $challenged;
        }
        $status = isset( $body['error']['code'] ) && Error_Codes::METHOD_NOT_FOUND === $body['error']['code'] ? 404 : 200;
        $response = new \WP_REST_Response( $body, $status );
        $response->header( 'Cache-Control', 'no-store, private' );
        $this->add_cors_headers( $response );
        return $response;
    }

    private $listen_streams = array();

    private function listen_stream_response( $id, $body ) {
        $response = new \WP_REST_Response( $body, 200 );
        $response->header( 'Content-Type', 'text/event-stream; charset=utf-8' );
        $response->header( 'Cache-Control', 'no-store, private' );
        $response->header( 'X-Accel-Buffering', 'no' );
        $this->add_cors_headers( $response );
        $this->listen_streams[ spl_object_id( $response ) ] = array(
            'response' => $response,
            'body'     => self::render_listen_stream( $id, $body ),
        );
        return $response;
    }

    public static function render_listen_stream( $id, $body ) {
        $events = '';
        foreach ( array( Server::subscriptions_acknowledged( $id ), $body ) as $message ) {
            $events .= "event: message\ndata: " . \wp_json_encode( $message ) . "\n\n";
        }
        return $events;
    }

    public function serve_listen_stream( $served, $result, $request = null, $server = null ) {
        if ( $served || ! is_object( $result ) || ! isset( $this->listen_streams[ spl_object_id( $result ) ] ) ) {
            return $served;
        }
        $stream = $this->listen_streams[ spl_object_id( $result ) ];
        if ( $stream['response'] !== $result ) {
            return $served;
        }
        unset( $this->listen_streams[ spl_object_id( $result ) ] );
        echo $stream['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SSE frames of wp_json_encode() output.
        return true;
    }

    private function process_single_message( $message, $token_id, $wp_user_id, $request, $batch_revalidated = null, $allowed_tools = null, $auth_source = null, $oauth_client_id = null ) {
        $method = isset( $message['method'] ) ? $message['method'] : '';

        if ( empty( $method ) ) {
            $id = isset( $message['id'] ) ? $message['id'] : null;
            return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::INVALID_REQUEST, 'Missing method' ), 200 );
        }

        if ( 'initialize' === $method ) {
            if ( null === $token_id ) {
                $id = isset( $message['id'] ) ? $message['id'] : null;
                return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Valid Bearer token required' ), 200 );
            }
            if ( ! $this->set_current_user( $wp_user_id ) ) {
                $id = isset( $message['id'] ) ? $message['id'] : null;
                return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Token user no longer exists' ), 200 );
            }
            $response_data = $this->call_handle_message_with_identity( $message, $token_id, $allowed_tools, $auth_source, $wp_user_id, $oauth_client_id );
            $response = new \WP_REST_Response( $response_data, 200 );
            if ( ! isset( $response_data['error'] ) ) {
                $negotiated = $this->server->get_last_negotiated_version() ?? Server::LEGACY_PROTOCOL_VERSION;
                $declared   = isset( $message['params']['capabilities'] ) && is_array( $message['params']['capabilities'] ) ? $message['params']['capabilities'] : array();
                $session_id = $this->server->get_session_manager()->create( $token_id, $wp_user_id, $negotiated, $auth_source, $declared );
                $response->header( 'Mcp-Session-Id', $session_id );
            }
            $this->add_cors_headers( $response );
            return $response;
        }

        if ( 'notifications/initialized' === $method || JSON_RPC::is_notification( $message ) ) {
            if ( null === $token_id ) {
                return $this->make_unauthorized_response( null );
            }
            if ( ! $this->set_current_user( $wp_user_id ) ) {
                return new \WP_REST_Response( null, 401 );
            }

            $this->call_handle_message_with_identity( $message, $token_id, $allowed_tools, $auth_source, $wp_user_id, $oauth_client_id );
            $response = new \WP_REST_Response( null, 202 );
            $this->add_cors_headers( $response );
            return $response;
        }

        if ( 'ping' === $method ) {
            $session_id = $request->get_header( 'mcp-session-id' );
            $authenticated = null !== $token_id;

            if ( ! $authenticated && $session_id && self::is_valid_session_id_format( $session_id ) ) {
                $revalidated = $this->revalidate_session( $session_id );
                $authenticated = false !== $revalidated;
            }

            if ( ! $authenticated ) {
                $id = isset( $message['id'] ) ? $message['id'] : null;
                return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' ), 200 );
            }

            $response_data = $this->call_handle_message_with_identity( $message, $token_id, $allowed_tools, $auth_source, $wp_user_id, $oauth_client_id, true );
            $response = new \WP_REST_Response( $response_data, 200 );
            $this->add_cors_headers( $response );
            return $response;
        }

        $session_id  = $request->get_header( 'mcp-session-id' );
        $revalidated = $batch_revalidated;

        if ( null === $revalidated && $session_id ) {
            if ( ! self::is_valid_session_id_format( $session_id ) ) {
                return new \WP_REST_Response( null, 400 );
            }
            $revalidated = $this->revalidate_session( $session_id );
            if ( false === $revalidated ) {
                if ( null === $token_id ) {
                    return new \WP_REST_Response( null, 404 );
                }
                $revalidated = null;
            }
        }

        if ( null === $token_id ) {
            $id = isset( $message['id'] ) ? $message['id'] : null;
            return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Valid Bearer token required' ), 200 );
        }

        if ( $revalidated ) {
            $session_source = isset( $revalidated['auth_source'] ) ? $revalidated['auth_source'] : 'legacy';
            $token_source   = null === $auth_source ? 'legacy' : $auth_source;
            if ( (int) $revalidated['token_id'] !== (int) $token_id
                || $session_source !== $token_source ) {
                $id = isset( $message['id'] ) ? $message['id'] : null;
                return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Bearer token does not match session owner' ), 200 );
            }
        }

        if ( ! $this->set_current_user( $wp_user_id ) ) {
            $id = isset( $message['id'] ) ? $message['id'] : null;
            return new \WP_REST_Response( JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Token user no longer exists' ), 200 );
        }

        $this->token_manager->update_last_used( $token_id );
        $response_data = $this->call_handle_message_with_identity( $message, $token_id, $allowed_tools, $auth_source, $wp_user_id, $oauth_client_id );
        $challenged    = $this->scope_challenge_response( $response_data, $wp_user_id, $oauth_client_id );
        if ( $challenged ) {
            return $challenged;
        }
        $response = new \WP_REST_Response( $response_data, 200 );
        $this->add_cors_headers( $response );
        return $response;
    }

    private function handle_batch( $messages, $token_id, $wp_user_id, $request, $allowed_tools = null, $auth_source = null, $oauth_client_id = null ) {
        if ( count( $messages ) > self::MAX_BATCH_SIZE ) {
            return new \WP_REST_Response(
                array( JSON_RPC::error_response( null, Error_Codes::INVALID_REQUEST, 'Batch size exceeds maximum of ' . self::MAX_BATCH_SIZE ) ),
                200
            );
        }

        foreach ( $messages as $message ) {
            if ( ! is_wp_error( $message ) && isset( $message['method'] ) && 'initialize' === $message['method'] ) {
                $id = isset( $message['id'] ) ? $message['id'] : null;
                return new \WP_REST_Response(
                    array( JSON_RPC::error_response( $id, Error_Codes::INVALID_REQUEST, '"initialize" must not be included in a batch request' ) ),
                    200
                );
            }
        }

        $session_id  = $request->get_header( 'mcp-session-id' );
        $revalidated = null;
        if ( $session_id ) {
            if ( ! self::is_valid_session_id_format( $session_id ) ) {
                return new \WP_REST_Response( null, 400 );
            }
            $revalidated = $this->revalidate_session( $session_id );
            if ( false === $revalidated ) {
                if ( null === $token_id ) {
                    return new \WP_REST_Response( null, 404 );
                }
                $revalidated = null;
            }
        }

        $responses      = array();
        $this->in_batch = true;
        try {
            foreach ( $messages as $message ) {
                if ( \is_wp_error( $message ) ) {
                    $responses[] = JSON_RPC::error_response( null, Error_Codes::INVALID_REQUEST, $message->get_error_message() );
                    continue;
                }
                $result = $this->process_single_message( $message, $token_id, $wp_user_id, $request, $revalidated, $allowed_tools, $auth_source, $oauth_client_id );
                if ( $result instanceof \WP_REST_Response && null !== $result->get_data() ) {
                    $responses[] = $result->get_data();
                }
            }
        } finally {
            $this->in_batch = false;
        }
        $response = empty( $responses ) ? new \WP_REST_Response( null, 202 ) : new \WP_REST_Response( $responses, 200 );
        $this->add_cors_headers( $response );
        return $response;
    }

    public function handle_get( \WP_REST_Request $request ) {
        self::mark_mcp_request();
        $url_key_refusal = $this->refuse_url_key( $request );
        if ( $url_key_refusal ) {
            return $url_key_refusal;
        }
        $this->inject_url_token( $request );

        if ( ! $request->get_header( 'authorization' ) ) {
            return $this->make_unauthorized_response( null );
        }

        $origin_error = $this->validate_origin( $request, (bool) $request->get_header( 'authorization' ) );
        if ( $origin_error ) {
            return $origin_error;
        }

        $response = new \WP_REST_Response( array( 'error' => 'SSE streaming not supported. Use POST for MCP communication.' ), 405 );
        $response->header( 'Allow', 'POST, DELETE, OPTIONS' );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        $response->header( 'Pragma', 'no-cache' );
        return $response;
    }

    public function handle_delete( \WP_REST_Request $request ) {
        self::mark_mcp_request();
        $url_key_refusal = $this->refuse_url_key( $request );
        if ( $url_key_refusal ) {
            return $url_key_refusal;
        }
        $this->inject_url_token( $request );
        $this->inject_basic_api_key( $request );

        $origin_error = $this->validate_origin( $request, (bool) $request->get_header( 'authorization' ) );
        if ( $origin_error ) {
            return $origin_error;
        }

        $token_id          = null;
        $auth_source       = null;
        $delete_wp_user_id = 0;
        $delete_client_id  = null;
        if ( $this->is_oauth_available() ) {
            $oauth_tm        = new OAuth_Token_Manager();
            $oauth_validator = new OAuth_Token_Validator( $oauth_tm );
            $oauth_result    = $oauth_validator->authenticate( $request );
            if ( ! \is_wp_error( $oauth_result ) ) {
                $token_id          = $oauth_result['token_id'];
                $auth_source       = 'oauth';
                $delete_wp_user_id = isset( $oauth_result['wp_user_id'] ) ? (int) $oauth_result['wp_user_id'] : 0;
                $delete_client_id  = isset( $oauth_result['client_id'] ) ? $oauth_result['client_id'] : null;
            }
        }
        if ( null === $token_id ) {
            $auth        = new Token_Auth( $this->token_manager );
            $legacy_result = $auth->authenticate( $request );
            if ( ! \is_wp_error( $legacy_result ) ) {
                $token_id          = $legacy_result['token_id'];
                $auth_source       = 'legacy';
                $delete_wp_user_id = isset( $legacy_result['wp_user_id'] ) ? (int) $legacy_result['wp_user_id'] : 0;
            } elseif ( $this->is_site_mismatch( $legacy_result ) ) {
                $ip = class_exists( '\\Easy_MCP_AI\\Client_IP' ) ? (string) \Easy_MCP_AI\Client_IP::get() : '';
                $this->server->log_auth_failure( $ip, Token_Manager::ERROR_SITE_MISMATCH, $this->site_mismatch_identity( $legacy_result ) );
                return $this->make_unauthorized_response( null, 401, 'invalid_token', self::SITE_MISMATCH_DESCRIPTION );
            }
        }
        if ( null === $token_id ) {
            return $this->make_unauthorized_response( array( 'error' => 'Authentication required' ) );
        }
        $ip_refusal = $this->refuse_if_ip_forbidden( $token_id, $auth_source, $delete_wp_user_id, $delete_client_id );
        if ( $ip_refusal ) {
            return $ip_refusal;
        }

        if ( Server::PROTOCOL_VERSION === $request->get_header( 'mcp-protocol-version' ) ) {
            $response = new \WP_REST_Response( null, 405 );
            $response->header( 'Allow', 'POST, OPTIONS' );
            $this->add_cors_headers( $response );
            return $response;
        }

        $session_id = $request->get_header( 'mcp-session-id' );
        if ( $session_id && self::is_valid_session_id_format( $session_id ) ) {
            $session_data = $this->server->get_session_manager()->validate( $session_id );
            $session_source = isset( $session_data['auth_source'] ) ? $session_data['auth_source'] : 'legacy';
            $token_source   = null === $auth_source ? 'legacy' : $auth_source;
            if ( $session_data
                && (int) $session_data['token_id'] === (int) $token_id
                && $session_source === $token_source
            ) {
                $this->server->get_session_manager()->destroy( $session_id );
            }
        }

        $response = new \WP_REST_Response( null, 204 );
        $this->add_cors_headers( $response );
        return $response;
    }

    public function handle_options( \WP_REST_Request $request ) {
        self::mark_mcp_request();
        $response = new \WP_REST_Response( null, 204 );
        $this->add_cors_headers( $response );
        return $response;
    }

    private static function normalize_origin( $origin ) {
        if ( ! is_string( $origin ) || '' === trim( $origin ) ) {
            return '';
        }
        $parts = \wp_parse_url( trim( $origin ) );
        if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return '';
        }
        $normalized = strtolower( $parts['scheme'] ) . '://' . strtolower( $parts['host'] );
        if ( ! empty( $parts['port'] ) ) {
            $normalized .= ':' . (int) $parts['port'];
        }
        return $normalized;
    }

    private static function allowed_origins() {
        $defaults = array();
        foreach ( array( \get_site_url(), \home_url() ) as $url ) {
            $normalized = self::normalize_origin( $url );
            if ( '' !== $normalized && ! in_array( $normalized, $defaults, true ) ) {
                $defaults[] = $normalized;
            }
        }

        $filtered = \apply_filters( 'easy_mcp_ai_allowed_origins', $defaults );

        if ( ! is_array( $filtered ) ) {
            return $defaults;
        }

        $out = array();
        foreach ( $filtered as $candidate ) {
            $normalized = self::normalize_origin( $candidate );
            if ( '' !== $normalized && ! in_array( $normalized, $out, true ) ) {
                $out[] = $normalized;
            }
        }

        return empty( $out ) ? $defaults : $out;
    }

    private function validate_origin( \WP_REST_Request $request, $credential_presented = false ) {
        $origin = $request->get_header( 'origin' );
        if ( empty( $origin ) ) {
            return null;
        }

        $allowed = self::allowed_origins();
        if ( in_array( self::normalize_origin( $origin ), $allowed, true ) ) {
            return null;
        }

        if ( $credential_presented ) {
            return null;
        }

        return new \WP_REST_Response(
            array(
                'error'            => 'origin_not_allowed',
                'message'          => 'The Origin header on this request is not permitted for this site. This is the plugin refusing the request, not the web server. Browser-based clients must be served from an allowed origin; non-browser clients should send no Origin header at all. Site owners can extend the list with the easy_mcp_ai_allowed_origins filter.',
                'received_origin'  => \sanitize_text_field( $origin ),
                'allowed_origins'  => $allowed,
            ),
            403
        );
    }

    private function validate_protocol_version_header( \WP_REST_Request $request ) {
        $header_version = $request->get_header( 'mcp-protocol-version' );
        if ( empty( $header_version ) ) {
            return null;
        }
        if ( ! in_array( $header_version, Server::SUPPORTED_PROTOCOL_VERSIONS, true ) ) {
            return new \WP_REST_Response(
                array(
                    'error'     => 'unsupported_protocol_version',
                    'supported' => Server::SUPPORTED_PROTOCOL_VERSIONS,
                ),
                400
            );
        }
        $session_version = $this->get_session_protocol_version( $request );
        if ( $session_version && $header_version !== $session_version ) {
            return new \WP_REST_Response(
                array(
                    'error'   => 'protocol_version_mismatch',
                    'message' => 'MCP-Protocol-Version header does not match the negotiated session version.',
                ),
                400
            );
        }
        return null;
    }

    private function get_session_protocol_version( \WP_REST_Request $request ) {
        $session_id = $request->get_header( 'mcp-session-id' );
        if ( ! $session_id || ! self::is_valid_session_id_format( $session_id ) ) {
            return null;
        }
        $session_data = $this->server->get_session_manager()->validate( $session_id );
        if ( ! $session_data ) {
            return null;
        }
        return isset( $session_data['protocol_version'] ) ? $session_data['protocol_version'] : '2025-03-26';
    }

    private function add_cors_headers( \WP_REST_Response $response ) {
        $response->header( 'Access-Control-Expose-Headers', 'Content-Type, Mcp-Session-Id' );
        $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
        $response->header( 'Pragma', 'no-cache' );
    }

    private function revalidate_session( $session_id ) {
        $session_data = $this->server->get_session_manager()->validate( $session_id );
        if ( ! $session_data ) {
            return false;
        }
        $this->server->set_request_client_caps(
            isset( $session_data['client_capabilities'] ) && is_array( $session_data['client_capabilities'] ) ? $session_data['client_capabilities'] : array(),
            isset( $session_data['protocol_version'] ) ? (string) $session_data['protocol_version'] : null
        );

        $auth_source = isset( $session_data['auth_source'] ) ? $session_data['auth_source'] : 'legacy';
        $token_id    = (int) $session_data['token_id'];

        if ( 'oauth' === $auth_source ) {
            if ( ! $this->is_oauth_available() ) {
                $this->server->get_session_manager()->destroy( $session_id );
                return false;
            }

            $throttle_key = 'easy_mcp_ai_oat_srv_' . $token_id;
            $cached = \get_transient( $throttle_key );
            if ( is_array( $cached ) ) {
                $this->server->get_session_manager()->touch( $session_id );
                return $cached;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; token revalidation must be fresh.
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT id, wp_user_id, is_active, expires_at FROM {$table} WHERE id = %d LIMIT 1", $token_id ),
                ARRAY_A
            );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! $row || empty( $row['is_active'] ) ) {
                $this->server->get_session_manager()->destroy( $session_id );
                return false;
            }
            if ( ! empty( $row['expires_at'] ) && strtotime( $row['expires_at'] . ' UTC' ) < time() ) {
                $this->server->get_session_manager()->destroy( $session_id );
                return false;
            }
            $result = array(
                'token_id'    => (int) $row['id'],
                'wp_user_id'  => (int) $row['wp_user_id'],
                'auth_source' => 'oauth',
            );
            \set_transient( $throttle_key, $result, 60 );
            $this->server->get_session_manager()->touch( $session_id );
            return $result;
        }

        $token = $this->token_manager->get_token_by_id( $token_id );
        if ( ! $token || empty( $token['is_active'] ) ) {
            $this->server->get_session_manager()->destroy( $session_id );
            return false;
        }
        if ( ! empty( $token['expires_at'] ) && strtotime( $token['expires_at'] . ' UTC' ) < time() ) {
            $this->server->get_session_manager()->destroy( $session_id );
            return false;
        }
        $this->server->get_session_manager()->touch( $session_id );
        return array(
            'token_id'    => (int) $token['id'],
            'wp_user_id'  => (int) $token['wp_user_id'],
            'auth_source' => 'legacy',
        );
    }

    public static function is_valid_session_id_format( $session_id ) {
        return is_string( $session_id ) && 1 === preg_match( '/^[0-9a-f]{64}$/', $session_id );
    }

    private function set_current_user( $user_id ) {
        $user_id = (int) $user_id;
        if ( $user_id > 0 && \get_userdata( $user_id ) ) {
            \wp_set_current_user( $user_id );
            return true;
        }
        return false;
    }

    private function is_oauth_available() {
        if ( ! \apply_filters( 'easy_mcp_ai_oauth_enabled', \Easy_MCP_AI\Config::get( 'oauth_enabled' ) ) ) {
            return false;
        }
        $file = EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-oauth-token-validator.php';
        if ( ! file_exists( $file ) ) {
            return false;
        }
        return true;
    }

    private function refuse_if_ip_forbidden( $token_id, $auth_source, $wp_user_id, $client_id ) {
        $auth = new Token_Auth( $this->token_manager );
        if ( $auth->check_ip_allowed() ) {
            return null;
        }
        $ip = class_exists( '\\Easy_MCP_AI\\Client_IP' )
            ? (string) \Easy_MCP_AI\Client_IP::get()
            : ( isset( $_SERVER['REMOTE_ADDR'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
        if ( $this->refusal_log_budget_allows( 'easy_mcp_ai_ipreflog_', $auth_source, $token_id ) ) {
            $this->server->log_auth_failure( $ip, 'ip_forbidden', array(
                'token_id'        => (int) $token_id,
                'auth_source'     => $auth_source,
                'wp_user_id'      => (int) $wp_user_id,
                'oauth_client_id' => $client_id,
            ) );
        }
        $response = new \WP_REST_Response( array(
            'error'             => 'ip_forbidden',
            'error_description' => 'This credential is valid but requests from your IP address are not permitted by the site\'s IP whitelist.',
        ), 403 );
        $this->add_cors_headers( $response );
        return $response;
    }

    private function refusal_log_budget_allows( string $family, ?string $auth_source, int $token_id ): bool {
        $limit = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_rate_limit_per_minute', 60 );
        if ( $limit < 1 ) {
            $limit = 60;
        }
        $key = $family . ( 'oauth' === $auth_source ? 'oauth' : 'legacy' ) . '_' . (int) $token_id;

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $key, 0, 'easy_mcp_ai', 60 );
            return (int) \wp_cache_incr( $key, 1, 'easy_mcp_ai' ) <= $limit;
        }
        $written = (int) \get_transient( $key );
        if ( $written >= $limit ) {
            return false;
        }
        \set_transient( $key, $written + 1, 60 );
        return true;
    }

    const SITE_MISMATCH_DESCRIPTION = 'The API key is bound to another site';

    const URL_KEY_DESCRIPTION = 'This site accepts API keys only in the Authorization header, never in the URL';

    const ERROR_URL_KEY = 'api_key_in_url';

    private function is_site_mismatch( $result ) {
        return \is_wp_error( $result ) && Token_Manager::ERROR_SITE_MISMATCH === $result->get_error_code();
    }

    private function is_invalid_audience( $result ) {
        return \is_wp_error( $result ) && OAuth_Token_Validator::ERROR_INVALID_AUDIENCE === $result->get_error_code();
    }

    private function invalid_audience_identity( \WP_Error $result ) {
        $data = (array) $result->get_error_data();
        return array(
            'auth_source'     => 'oauth',
            'token_id'        => isset( $data['token_id'] ) ? (int) $data['token_id'] : 0,
            'wp_user_id'      => isset( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : 0,
            'oauth_client_id' => isset( $data['client_id'] ) ? (string) $data['client_id'] : null,
        );
    }

    private function site_mismatch_identity( \WP_Error $result ) {
        $data = (array) $result->get_error_data();
        return array(
            'auth_source' => 'legacy',
            'token_id'    => isset( $data['token_id'] ) ? (int) $data['token_id'] : 0,
            'wp_user_id'  => isset( $data['wp_user_id'] ) ? (int) $data['wp_user_id'] : 0,
        );
    }

    public static function resource_metadata_url(): string {
        if ( \Easy_MCP_AI\OAuth\Root_Fallback::is_armed() ) {
            if ( \Easy_MCP_AI\OAuth\Root_Fallback::mcp_path_discovery_reachable() ) {
                return \rest_url( self::NAMESPACE_V1 . '/mcp/.well-known/oauth-protected-resource' );
            }
            return \rest_url( self::NAMESPACE_V1 . '/discovery/oauth-protected-resource' );
        }
        return \home_url( '/.well-known/oauth-protected-resource' );
    }

    private function make_unauthorized_response( $data, $http_status = 401, $error_code = null, $error_description = 'The access token is invalid or expired' ) {
        $response = new \WP_REST_Response( $data, $http_status );

        $params = array();
        if ( $error_code ) {
            $params[] = 'error="' . $error_code . '"';
            $params[] = 'error_description="' . $error_description . '"';
        }
        if ( $this->is_oauth_available() ) {
            $params[] = 'resource_metadata="' . self::resource_metadata_url() . '"';
        }
        $challenge = 'Bearer' . ( $params ? ' ' . implode( ', ', $params ) : '' );
        $response->header( 'WWW-Authenticate', $challenge );

        $this->add_cors_headers( $response );
        return $response;
    }
}
