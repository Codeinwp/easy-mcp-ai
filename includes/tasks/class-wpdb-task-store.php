<?php
namespace Easy_MCP_AI\Tasks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the name is prefix + a constant, never input, and task state must be read fresh.

class Wpdb_Task_Store implements Task_Store {

    const FORMATS = array(
        'task_id' => '%s', 'auth_source' => '%s', 'token_id' => '%d', 'oauth_client_id' => '%s',
        'wp_user_id' => '%d', 'tool_name' => '%s', 'arguments' => '%s', 'approval_id' => '%s', 'mode' => '%s', 'phase' => '%s',
        'status' => '%s', 'status_message' => '%s', 'progress_current' => '%d', 'progress_total' => '%d',
        'task_cursor' => '%s', 'job_id' => '%s', 'result' => '%s', 'error' => '%s', 'signature' => '%s', 'ticks' => '%d',
        'lock_token' => '%s', 'locked_until' => '%s', 'created_at' => '%s', 'updated_at' => '%s',
        'completed_at' => '%s', 'expires_at' => '%s',
    );

    private function table() {
        return Task_Schema::table_name();
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

    public function find( $task_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$this->table()}` WHERE task_id = %s LIMIT 1", (string) $task_id ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    public function update( $task_id, array $fields ) {
        global $wpdb;
        $fields = array_intersect_key( $fields, self::FORMATS );
        if ( empty( $fields ) ) {
            return true;
        }
        return false !== $wpdb->update( $this->table(), $fields, array( 'task_id' => (string) $task_id ), $this->formats( $fields ), array( '%s' ) );
    }

    public function update_working( $task_id, array $fields, $lock_token = null ) {
        global $wpdb;
        $fields = array_intersect_key( $fields, self::FORMATS );
        if ( empty( $fields ) ) {
            return false;
        }
        $set    = array();
        $values = array();
        foreach ( $fields as $column => $value ) {
            if ( null === $value ) {
                $set[] = "`{$column}` = NULL";
                continue;
            }
            $set[]    = "`{$column}` = " . self::FORMATS[ $column ];
            $values[] = $value;
        }
        $where    = "task_id = %s AND status = 'working'";
        $values[] = (string) $task_id;
        if ( null !== $lock_token ) {
            $where   .= ' AND lock_token = %s';
            $values[] = (string) $lock_token;
        }
        $sql = "UPDATE `{$this->table()}` SET " . implode( ', ', $set ) . " WHERE {$where}";
        return 1 === (int) $wpdb->query( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Column names come from the FORMATS constant, values are placeholders.
    }

    public function claim( $task_id, $lock_token, $now, $locked_until ) {
        global $wpdb;
        $affected = $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET lock_token = %s, locked_until = %s
             WHERE task_id = %s AND status = 'working' AND ( locked_until IS NULL OR locked_until < %s )",
            (string) $lock_token, (string) $locked_until, (string) $task_id, (string) $now
        ) );
        return 1 === (int) $affected;
    }

    public function release( $task_id, $lock_token ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET lock_token = NULL, locked_until = NULL WHERE task_id = %s AND lock_token = %s",
            (string) $task_id, (string) $lock_token
        ) );
    }

    public function count_working( $auth_source, $wp_user_id, $token_id, $oauth_client_id ) {
        global $wpdb;
        if ( 'oauth' === $auth_source ) {
            
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$this->table()}` WHERE status = 'working' AND auth_source = 'oauth' AND wp_user_id = %d AND oauth_client_id = %s",
                (int) $wp_user_id, (string) $oauth_client_id
            ) );
        }
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->table()}` WHERE status = 'working' AND auth_source = 'legacy' AND wp_user_id = %d AND token_id = %d",
            (int) $wp_user_id, (int) $token_id
        ) );
    }

    public function count_running( $now ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->table()}` WHERE status = 'working' AND locked_until IS NOT NULL AND locked_until >= %s",
            (string) $now
        ) );
    }

    public function fail_stale( $cutoff, $now, $expires_at, $message ) {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "UPDATE `{$this->table()}` SET status = 'failed', status_message = %s, error = %s, arguments = NULL,
                    task_cursor = NULL, lock_token = NULL, locked_until = NULL, updated_at = %s, completed_at = %s, expires_at = %s
             WHERE status IN ( 'working', 'input_required' ) AND created_at < %s",
            (string) $message, \wp_json_encode( array( 'code' => -32603, 'message' => (string) $message ) ),
            (string) $now, (string) $now, (string) $expires_at, (string) $cutoff
        ) );
    }

    public function delete_expired( $now, $limit = 1000 ) {
        global $wpdb;
        return (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM `{$this->table()}` WHERE expires_at < %s AND status NOT IN ( 'working', 'input_required' ) LIMIT %d",
            (string) $now, max( 1, (int) $limit )
        ) );
    }
}
// phpcs:enable
