<?php
namespace Easy_MCP_AI\Feedback;

use Easy_MCP_AI\Approvals\Wpdb_Approval_Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the name is prefix + a constant, never input, and the queue must be read fresh.






class Wpdb_Feedback_Store extends Wpdb_Approval_Store implements Feedback_Report_Store {

    private function table_name(): string {
        return \Easy_MCP_AI\Approvals\Approval_Schema::table_name();
    }

    public function list_pending( string $now, int $limit ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$this->table_name()}` WHERE tool_name = %s AND status = 'pending' AND expires_at > %s ORDER BY id DESC LIMIT %d",
            Feedback_Queue::ROW_TOOL, (string) $now, (int) $limit
        ), ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    public function list_pending_for( array $identity, string $now, int $limit ): array {
        global $wpdb;
        $client = isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) ? $identity['oauth_client_id'] : '';
        $rows   = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM `{$this->table_name()}` WHERE tool_name = %s AND auth_source = %s AND token_id = %d AND COALESCE(oauth_client_id, '') = %s AND wp_user_id = %d AND status = 'pending' AND expires_at > %s ORDER BY id DESC LIMIT %d",
            Feedback_Queue::ROW_TOOL, (string) $identity['auth_source'], (int) $identity['token_id'], $client, (int) $identity['wp_user_id'], (string) $now, (int) $limit
        ), ARRAY_A );
        return is_array( $rows ) ? $rows : array();
    }

    public function delete_report( string $approval_id ): bool {
        global $wpdb;
        return 1 === (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$this->table_name()}` WHERE approval_id = %s AND tool_name = %s LIMIT 1",
            (string) $approval_id, Feedback_Queue::ROW_TOOL
        ) );
    }

    public function find_pending_by_hash( array $identity, string $args_hash, string $now ): ?array {
        global $wpdb;
        $client = isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) ? $identity['oauth_client_id'] : '';
        $row    = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$this->table_name()}` WHERE tool_name = %s AND auth_source = %s AND token_id = %d AND COALESCE(oauth_client_id, '') = %s AND wp_user_id = %d AND args_hash = %s AND status = 'pending' AND expires_at > %s ORDER BY id DESC LIMIT 1",
            Feedback_Queue::ROW_TOOL, (string) $identity['auth_source'], (int) $identity['token_id'], $client, (int) $identity['wp_user_id'], $args_hash, $now
        ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }
}
