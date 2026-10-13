<?php






























namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Root_Fallback {

    
    const STATE_OPTION = 'easy_mcp_ai_oauth_root_discovery';

    
    const FILTER = 'easy_mcp_ai_oauth_root_fallback';

    
    const GRANT_TYPES = array(
        'authorization_code',
        'refresh_token',
        'urn:ietf:params:oauth:grant-type:device_code',
    );

    
    const ENDPOINT_METHODS = array(
        '/authorize' => 'GET',
        '/register'  => 'POST',
        '/token'     => 'POST',
    );

    





    public static function mode(): string {
        $mode = \Easy_MCP_AI\Config::get( 'oauth_root_fallback' );
        $mode = \apply_filters( self::FILTER, $mode );
        return in_array( $mode, array( 'auto', 'on', 'off' ), true ) ? $mode : 'auto';
    }

    




    public static function is_armed(): bool {
        $mode = self::mode();
        if ( 'on' === $mode ) {
            return true;
        }
        if ( 'off' === $mode ) {
            return false;
        }
        return true === self::root_discovery_blocked();
    }

    




    public static function root_discovery_blocked(): ?bool {
        $state = \get_option( self::STATE_OPTION, null );
        if ( ! is_array( $state ) || ! array_key_exists( 'blocked', $state ) || ! is_bool( $state['blocked'] ) ) {
            return null;
        }
        return $state['blocked'];
    }

    








    public static function mcp_path_discovery_reachable(): bool {
        $state = \get_option( self::STATE_OPTION, null );
        return is_array( $state )
            && isset( $state['blocked'], $state['mcp_path'] )
            && true === $state['blocked']
            && true === $state['mcp_path'];
    }

    








    public static function record_root_discovery( $blocked, $mcp_path = null ): void {
        $state = array(
            'blocked' => (bool) $blocked,
            'at'      => time(),
        );
        if ( is_bool( $mcp_path ) ) {
            $state['mcp_path'] = $mcp_path;
        }
        \update_option( self::STATE_OPTION, $state, false );
    }

    










    public static function endpoint_for( $method, $path, array $query, array $body, $content_type, $home_path = '' ): ?string {
        $path = \Easy_MCP_AI\Plugin::strip_home_path( (string) $path, (string) $home_path );
        if ( ! isset( self::ENDPOINT_METHODS[ $path ] ) || strtoupper( (string) $method ) !== self::ENDPOINT_METHODS[ $path ] ) {
            return null;
        }

        $endpoint = substr( $path, 1 );
        switch ( $endpoint ) {
            case 'authorize':
                $ok = self::is_authorize_request( $query );
                break;
            case 'register':
                $ok = self::is_registration( $body, (string) $content_type );
                break;
            default:
                $ok = isset( $body['grant_type'] ) && in_array( $body['grant_type'], self::GRANT_TYPES, true );
        }
        return $ok ? $endpoint : null;
    }

    





    private static function is_authorize_request( array $query ): bool {
        return isset( $query['response_type'] )
            && 'code' === $query['response_type']
            && self::is_non_empty_string( $query, 'client_id' )
            && self::is_non_empty_string( $query, 'code_challenge' );
    }

    






    private static function is_registration( array $body, string $content_type ): bool {
        return 0 === stripos( trim( $content_type ), 'application/json' )
            && isset( $body['redirect_uris'] )
            && is_array( $body['redirect_uris'] )
            && ! empty( $body['redirect_uris'] );
    }

    






    private static function is_non_empty_string( array $params, string $key ): bool {
        return isset( $params[ $key ] ) && is_string( $params[ $key ] ) && '' !== $params[ $key ];
    }

    







    public static function client_id_from( array $params, $basic_user = '', $auth_header = '' ): string {
        if ( isset( $params['client_id'] ) && is_string( $params['client_id'] ) && '' !== $params['client_id'] ) {
            return $params['client_id'];
        }
        if ( is_string( $basic_user ) && '' !== $basic_user ) {
            return rawurldecode( $basic_user );
        }
        if ( is_string( $auth_header ) && 0 === stripos( $auth_header, 'Basic ' ) ) {
            $decoded = base64_decode( substr( $auth_header, 6 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- RFC 6749 §2.3.1 client credentials.
            if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
                return rawurldecode( explode( ':', $decoded, 2 )[0] );
            }
        }
        return '';
    }

    










    public static function defers_to_other_route( $matched_query, array $core_vars ): bool {
        if ( '' === (string) $matched_query ) {
            return false;
        }
        parse_str( (string) $matched_query, $vars );
        foreach ( array_keys( $vars ) as $var ) {
            if ( ! in_array( $var, $core_vars, true ) ) {
                return true;
            }
        }
        return false;
    }

    









    public static function dispatch( \WP $wp ): void {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) \wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never output.
        $path = (string) \wp_parse_url( $uri, PHP_URL_PATH );
        if ( ! self::may_answer( $wp, $path ) ) {
            return;
        }

        $request   = self::read_request();
        $home_path = \Easy_MCP_AI\Plugin::home_path();
        $endpoint  = self::endpoint_for( $request['method'], $path, $request['query'], $request['body'], $request['content_type'], $home_path );
        if ( null === $endpoint ) {
            return;
        }
        
        if ( 'register' !== $endpoint && ! self::is_our_client( self::request_client_id( $request ) ) ) {
            return;
        }

        if ( 'authorize' === $endpoint ) {
            
            
            \Easy_MCP_AI\Plugin::instance()->serve_oauth_authorize( 'none' );
            return;
        }
        $wp->query_vars['rest_route'] = '/easy-mcp-ai/v1/oauth/' . $endpoint;
    }

    







    private static function may_answer( \WP $wp, string $path ): bool {
        if ( ! preg_match( '#/(authorize|register|token)/?$#', $path ) ) {
            return false;
        }
        if ( ! \apply_filters( 'easy_mcp_ai_oauth_enabled', \Easy_MCP_AI\Config::get( 'oauth_enabled' ) ) ) {
            return false;
        }
        if ( ! self::is_armed() ) {
            return false;
        }
        $matched_query = isset( $wp->matched_query ) ? $wp->matched_query : '';
        return ! self::defers_to_other_route( $matched_query, ( new \WP() )->public_query_vars );
    }

    





    private static function read_request(): array {
        $method       = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
        $content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) : '';
        // phpcs:disable WordPress.Security.NonceVerification -- OAuth requests carry no WordPress nonce; the handlers they reach authenticate them.
        $query = isset( $_GET ) ? (array) \wp_unslash( $_GET ) : array();
        $body  = isset( $_POST ) ? (array) \wp_unslash( $_POST ) : array();
        // phpcs:enable WordPress.Security.NonceVerification
        if ( 'POST' === $method && 0 === stripos( $content_type, 'application/json' ) ) {
            $json = json_decode( (string) file_get_contents( 'php://input' ), true );
            $body = is_array( $json ) ? $json : array();
        }
        return array(
            'method'       => $method,
            'content_type' => $content_type,
            'query'        => $query,
            'body'         => $body,
        );
    }

    





    private static function request_client_id( array $request ): string {
        return self::client_id_from(
            array_merge( $request['query'], $request['body'] ),
            isset( $_SERVER['PHP_AUTH_USER'] ) ? (string) \wp_unslash( $_SERVER['PHP_AUTH_USER'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- looked up with a prepared statement only.
            isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? (string) \wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) : '' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- decoded, looked up with a prepared statement only.
        );
    }

    





    private static function is_our_client( string $client_id ): bool {
        if ( '' === $client_id || strlen( $client_id ) > 191 ) {
            return false;
        }
        require_once __DIR__ . '/class-client-registry.php';
        return null !== ( new Client_Registry() )->get_client( $client_id );
    }
}
