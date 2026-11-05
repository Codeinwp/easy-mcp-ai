<?php
namespace Easy_MCP_AI\OAuth;

use Easy_MCP_AI\Auth\Connect_Policy;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Role_Limit {

    const SCHEMA_VERSION = '1.0.12';

    public static function offered( \WP_User $user ): array {
        if ( ! \Easy_MCP_AI\Cli_Router\Capability_Grants::user_holds( $user, 'manage_options' ) ) {
            return array();
        }
        $out = array();
        foreach ( Connect_Policy::site_roles() as $slug => $capabilities ) {
            foreach ( $capabilities as $cap => $grant ) {
                if ( $grant && ! \Easy_MCP_AI\Cli_Router\Capability_Grants::user_holds( $user, (string) $cap ) ) {
                    continue 2;
                }
            }
            $out[] = (string) $slug;
        }
        return $out;
    }

    public static function for_level( \WP_User $user, $access_level, $submitted ) {
        if ( 'role' !== $access_level ) {
            return null;
        }
        $roles = self::from_submission( $user, $submitted );
        if ( null === $roles ) {
            return new \WP_Error( 'role_limit_missing', __( 'Choose the role to match, then approve again.', 'easy-mcp-ai' ) );
        }
        return $roles;
    }

    public static function from_submission( \WP_User $user, $submitted ) {
        if ( null === $submitted || '' === $submitted || array() === $submitted ) {
            return null;
        }
        $offered = self::offered( $user );
        $roles   = array();
        foreach ( is_array( $submitted ) ? $submitted : array( $submitted ) as $slug ) {
            if ( ! is_string( $slug ) || ! in_array( $slug, $offered, true ) ) {
                return new \WP_Error( 'role_limit_refused', __( 'One of the roles chosen is not one this account can act as. Choose again.', 'easy-mcp-ai' ) );
            }
            $roles[] = $slug;
        }
        if ( ! self::column_ready() ) {
            return new \WP_Error( 'role_limit_unavailable', __( 'A role limit cannot be saved on this site yet. Approve without one and try again later.', 'easy-mcp-ai' ) );
        }
        return array_values( array_unique( $roles ) );
    }

    public static function decode( $stored ): ?array {
        if ( null === $stored || '' === $stored ) {
            return null;
        }
        $roles = json_decode( (string) $stored, true );
        if ( ! is_array( $roles ) || array_values( $roles ) !== $roles ) {
            return array();
        }
        foreach ( $roles as $slug ) {
            if ( ! is_string( $slug ) ) {
                return array();
            }
        }
        return $roles;
    }

    public static function for_grant( int $user_id, string $client_id ) {
        if ( ! self::column_ready() ) {
            return null;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name is prefixed by $wpdb->prefix (trusted); read on every request on purpose.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT role_limit FROM {$table} WHERE wp_user_id = %d AND client_id = %s LIMIT 1", $user_id, $client_id ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! is_object( $row ) ) {
            return '' !== (string) $wpdb->last_error ? array() : false;
        }
        return self::decode( isset( $row->role_limit ) ? $row->role_limit : null );
    }

    public static function column_ready(): bool {
        return version_compare( (string) \get_option( OAuth_Schema::VERSION_OPTION, '0' ), self::SCHEMA_VERSION, '>=' );
    }
}
