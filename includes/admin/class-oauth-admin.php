<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






class OAuth_Admin {

    








    public static function save_settings( $access_ttl, $refresh_ttl, $dcr_enabled ) {
        $access_ttl  = max( 60, (int) $access_ttl );
        $refresh_ttl = max( 60, (int) $refresh_ttl );

        \Easy_MCP_AI\Config::update( 'easy_mcp_ai_oauth_access_token_ttl', $access_ttl );
        \Easy_MCP_AI\Config::update( 'easy_mcp_ai_oauth_refresh_token_ttl', $refresh_ttl );
        \Easy_MCP_AI\Config::update( 'easy_mcp_ai_oauth_dcr_enabled', $dcr_enabled ? 1 : 0 );
    }

    






    public static function revoke_client( $client_id ) {
        
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            return false;
        }
        global $wpdb;

        $clients_table  = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $tokens_table   = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        $codes_table    = $wpdb->prefix . 'easy_mcp_ai_oauth_codes';
        $consents_table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';

        
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $tokens_table, array( 'client_id' => $client_id ), array( '%s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $codes_table, array( 'client_id' => $client_id ), array( '%s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $consents_table, array( 'client_id' => $client_id ), array( '%s' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete( $clients_table, array( 'client_id' => $client_id ), array( '%s' ) );

        return (int) $deleted > 0;
    }

    







    public static function revoke_grant( $consent_id ) {
        
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            return false;
        }
        global $wpdb;

        $consents_table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $tokens_table   = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name prefixed by $wpdb->prefix.
        $consent = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT wp_user_id, client_id FROM {$consents_table} WHERE id = %d",
                $consent_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $consent ) {
            return false;
        }

        
        
        
        
        
        
        
        
        
        
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $consents_table,
            array(
                'scope'      => '',
                'updated_at' => \current_time( 'mysql', true ),
            ),
            array( 'id' => $consent_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $tokens_table,
            array( 'is_active' => 0 ),
            array(
                'client_id'  => $consent->client_id,
                'wp_user_id' => $consent->wp_user_id,
            ),
            array( '%d' ),
            array( '%s', '%d' )
        );

        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $consents_table,
            array( 'id' => $consent_id ),
            array( '%d' )
        );

        return true;
    }

    








    public static function save_scope( $consent_id, array $scopes ) {
        
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            return false;
        }
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-scope-map.php';

        $valid_scopes = \Easy_MCP_AI\OAuth\Scope_Map::get_all_scopes();

        
        $clean_scopes = array_values( array_intersect( $scopes, $valid_scopes ) );
        $new_scope    = implode( ' ', $clean_scopes );

        global $wpdb;

        $consents_table = $wpdb->prefix . 'easy_mcp_ai_oauth_consents';
        $tokens_table   = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';

        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom plugin table; name prefixed by $wpdb->prefix.
        $consent = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT wp_user_id, client_id FROM {$consents_table} WHERE id = %d",
                $consent_id
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! $consent ) {
            return false;
        }

        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $consents_table,
            array(
                'scope'      => $new_scope,
                'updated_at' => \current_time( 'mysql', true ),
            ),
            array( 'id' => $consent_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        
        
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $token_update_result = $wpdb->update(
            $tokens_table,
            array( 'scope' => $new_scope ),
            array(
                'client_id'  => $consent->client_id,
                'wp_user_id' => $consent->wp_user_id,
                'is_active'  => 1,
            ),
            array( '%s' ),
            array( '%s', '%d', '%d' )
        );

        if ( false === $token_update_result && ! empty( $wpdb->last_error ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            
            
            
            error_log( 'Easy MCP AI: token scope update failed after consent update — ' . $wpdb->last_error ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        }

        return true;
    }
}
