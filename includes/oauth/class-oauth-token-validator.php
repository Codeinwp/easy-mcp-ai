<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAuth_Token_Validator {

    const ERROR_INVALID_AUDIENCE = 'invalid_audience';

    private $token_manager;

    public function __construct( OAuth_Token_Manager $token_manager ) {
        $this->token_manager = $token_manager;
    }

    public function authenticate( \WP_REST_Request $request ) {
        $auth_header = $request->get_header( 'Authorization' );

        if ( empty( $auth_header ) ) {
            return new \WP_Error(
                'missing_token',
                __( 'Missing Authorization header.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        if ( 0 !== strpos( $auth_header, 'Bearer ' ) ) {
            return new \WP_Error(
                'invalid_token_format',
                __( 'Authorization header must use Bearer scheme.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        $raw_token = substr( $auth_header, 7 );

        if ( 0 !== strpos( $raw_token, OAuth_Token_Manager::TOKEN_PREFIX ) ) {
            return new \WP_Error(
                'invalid_token_prefix',
                __( 'Invalid token prefix.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        $token_data = $this->token_manager->validate_access_token( $raw_token );

        if ( false === $token_data ) {
            return new \WP_Error(
                'invalid_token',
                __( 'Token is invalid, expired, or revoked.', 'easy-mcp-ai' ),
                array( 'status' => 401 )
            );
        }

        $canonical_uri  = $this->get_canonical_resource_uri();
        $token_resource = isset( $token_data['resource'] ) && '' !== $token_data['resource']
            ? $token_data['resource']
            : $canonical_uri;

        if ( ! Token_Endpoint::resource_matches( $token_resource, $canonical_uri ) ) {
            return new \WP_Error(
                self::ERROR_INVALID_AUDIENCE,
                __( 'Token audience does not match this MCP server.', 'easy-mcp-ai' ),
                array(
                    'status'     => 403,
                    'token_id'   => (int) $token_data['id'],
                    'wp_user_id' => (int) $token_data['wp_user_id'],
                    'client_id'  => isset( $token_data['client_id'] ) ? (string) $token_data['client_id'] : null,
                )
            );
        }

        $scope         = isset( $token_data['scope'] ) ? $token_data['scope'] : '';
        $allowed_tools = Scope_Map::resolve_allowed_tools( $scope );

        return array(
            'token_id'      => (int) $token_data['id'],
            'wp_user_id'    => (int) $token_data['wp_user_id'],
            'client_id'     => isset( $token_data['client_id'] ) ? (string) $token_data['client_id'] : null,
            'allowed_tools' => $allowed_tools,
            'scope'         => (string) $scope,
        );
    }

    private function get_canonical_resource_uri(): string {
        return rest_url( 'easy-mcp-ai/v1/mcp' );
    }
}
