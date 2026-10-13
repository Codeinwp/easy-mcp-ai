<?php
namespace Easy_MCP_AI\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the name is prefix + a constant, never input, and approval state must be read fresh.






class Wpdb_Approval_Store implements Approval_Store {

    const FORMATS = array(
        'approval_id' => '%s', 'auth_source' => '%s', 'token_id' => '%d', 'oauth_client_id' => '%s',
        'wp_user_id' => '%d', 'tool_name' => '%s', 'client_protocol' => '%s', 'client_capabilities' => '%s', 'arguments' => '%s', 'args_hash' => '%s',
        'preview' => '%s', 'preview_hash' => '%s', 'status' => '%s', 'card_secret_hash' => '%s',
        'decided_by' => '%d', 'decided_at' => '%s', 'decision_via' => '%s', 'audit_id' => '%d',
        'executed_audit_id' => '%d', 'result' => '%s', 'signature' => '%s', 'created_at' => '%s',
        'expires_at' => '%s', 'consumed_at' => '%s',
    );

    private function table() {
        return Approval_Schema::table_name();
    }

    private function formats( array $fields ) {
        $formats = array();
        foreach ( array_keys( $fields ) as $key ) {
            $formats[] = isset( self::FORMATS[ $key ] ) ? self::FORMATS[ $key ] : '%s';
        }
        return $formats;
    }

    public function create( array $row ) {
        global $wpdb;
        $row = array_intersect_key( $row, self::FORMATS );
        return false !== $wpdb->insert( $this->table(), $row, $this->formats( $row ) );
    }

    public function find( $approval_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table()}` WHERE approval_id = %s LIMIT 1", (string) $approval_id ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    public function find_latest( array $identity, $tool_name, $args_hash, $status ) {
        global $wpdb;
        $client = isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) ? $identity['oauth_client_id'] : '';
        
        
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$this->table()}` WHERE auth_source = %s AND token_id = %d AND COALESCE(oauth_client_id, '') = %s AND wp_user_id = %d AND tool_name = %s AND args_hash = %s AND status = %s ORDER BY id DESC LIMIT 1",
            (string) $identity['auth_source'], (int) $identity['token_id'], $client, (int) $identity['wp_user_id'], (string) $tool_name, (string) $args_hash, (string) $status
        ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    public function decide( $approval_id, $status, $decided_by, $decision_via, $now ) {
        global $wpdb;
        $affected = $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET status = %s, decided_by = %d, decided_at = %s, decision_via = %s WHERE approval_id = %s AND status = 'pending' AND expires_at > %s",
            (string) $status, (int) $decided_by, (string) $now, (string) $decision_via, (string) $approval_id, (string) $now
        ) );
        return 1 === (int) $affected;
    }

    public function claim( $approval_id, $now ) {
        global $wpdb;
        
        
        $affected = $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET status = 'consumed', consumed_at = %s, arguments = NULL WHERE approval_id = %s AND status = 'approved' AND expires_at > %s",
            (string) $now, (string) $approval_id, (string) $now
        ) );
        return 1 === (int) $affected;
    }

    public function update( $approval_id, array $fields ) {
        global $wpdb;
        $fields = array_intersect_key( $fields, self::FORMATS );
        if ( empty( $fields ) ) {
            return true;
        }
        return false !== $wpdb->update( $this->table(), $fields, array( 'approval_id' => (string) $approval_id ), $this->formats( $fields ), array( '%s' ) );
    }

    public function expire_pending( $now ) {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET status = 'expired', arguments = NULL WHERE status IN ('pending', 'approved') AND expires_at <= %s",
            (string) $now
        ) );
    }

    public function delete_old( $cutoff, $limit = 1000 ) {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$this->table()}` WHERE status NOT IN ('pending', 'approved') AND created_at < %s LIMIT %d",
            (string) $cutoff, (int) $limit
        ) );
    }
}
