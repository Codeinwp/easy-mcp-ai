<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Step_Up {

    const TTL = 3600;

    const PREFIX = 'easy_mcp_ai_step_up_';

    public static function record( int $wp_user_id, string $client_id, array $scopes ): void {
        $scopes = self::clean( $scopes );
        if ( $wp_user_id <= 0 || '' === $client_id || array() === $scopes ) {
            return;
        }
        \set_transient( self::key( $wp_user_id, $client_id ), $scopes, self::TTL );
    }

    public static function pending( int $wp_user_id, string $client_id ): array {
        if ( $wp_user_id <= 0 || '' === $client_id ) {
            return array();
        }
        $stored = \get_transient( self::key( $wp_user_id, $client_id ) );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        $registered = array_diff( Scope_Map::get_all_scopes(), array( 'mcp' ) );
        return array_values( array_intersect( self::clean( $stored ), $registered ) );
    }

    public static function clear( int $wp_user_id, string $client_id ): void {
        if ( $wp_user_id <= 0 || '' === $client_id ) {
            return;
        }
        \delete_transient( self::key( $wp_user_id, $client_id ) );
    }

    private static function clean( array $scopes ): array {
        $out = array();
        foreach ( $scopes as $scope ) {
            if ( is_string( $scope ) && '' !== trim( $scope ) && 'mcp' !== trim( $scope ) ) {
                $out[] = trim( $scope );
            }
        }
        return array_values( array_unique( $out ) );
    }

    private static function key( int $wp_user_id, string $client_id ): string {
        return self::PREFIX . substr( hash( 'sha256', $wp_user_id . '|' . $client_id ), 0, 32 );
    }
}
