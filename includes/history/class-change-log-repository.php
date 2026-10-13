<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




class Change_Log_Repository {

    












    public static function list_columns() {
        return 'id, audit_id, tool_name, action, object_type, object_id, object_subtype, '
            . 'changed_fields, revision_id, wp_user_id, oauth_client_id, auth_source, '
            . 'created_at, truncated, ip_address, capture_mode';
    }

    public function table() {
        global $wpdb;
        return $wpdb->prefix . 'easy_mcp_ai_change_log';
    }

    public function insert( array $row ) {
        global $wpdb;
        $defaults = array(
            'audit_id'        => null,
            'auth_source'     => 'legacy',
            'token_id'        => 0,
            'oauth_client_id' => null,
            'wp_user_id'      => 0,
            'tool_name'       => '',
            'action'          => 'update',
            'object_type'     => '',
            'object_id'       => '',
            'object_subtype'  => null,
            'before_value'    => null,
            'after_value'     => null,
            'changed_fields'  => null,
            'revision_id'     => null,
            'truncated'       => 0,
            
            
            
            'capture_mode'    => null,
            'ip_address'      => null,
            'created_at'      => \current_time( 'mysql', true ),
        );
        $row = array_merge( $defaults, $row );

        
        
        
        if ( isset( $row['object_id'] ) && is_string( $row['object_id'] ) ) {
            $row['object_id'] = mb_substr( $row['object_id'], 0, 191 );
        }

        foreach ( array( 'before_value', 'after_value', 'changed_fields' ) as $field ) {
            if ( is_array( $row[ $field ] ) || is_object( $row[ $field ] ) ) {
                $row[ $field ] = \wp_json_encode( $row[ $field ] );
            }
        }

        
        
        
        
        
        $formats = array(
            'audit_id'        => '%d',
            'auth_source'     => '%s',
            'token_id'        => '%d',
            'oauth_client_id' => '%s',
            'wp_user_id'      => '%d',
            'tool_name'       => '%s',
            'action'          => '%s',
            'object_type'     => '%s',
            'object_id'       => '%s',
            'object_subtype'  => '%s',
            'before_value'    => '%s',
            'after_value'     => '%s',
            'changed_fields'  => '%s',
            'revision_id'     => '%d',
            'truncated'       => '%d',
            'capture_mode'    => '%s',
            'ip_address'      => '%s',
            'created_at'      => '%s',
        );
        $format_values = array();
        foreach ( $row as $col => $_ ) {
            $format_values[] = isset( $formats[ $col ] ) ? $formats[ $col ] : '%s';
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table direct insert for change log.
        $wpdb->insert( $this->table(), $row, $format_values );
        return (int) $wpdb->insert_id;
    }

    






    public function update_revision_id( $row_id, $revision_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table back-patch.
        return $wpdb->update(
            $this->table(),
            array( 'revision_id' => (int) $revision_id ),
            array( 'id' => (int) $row_id ),
            array( '%d' ),
            array( '%d' )
        );
    }

    public function find( $id ) {
        global $wpdb;
        $table = $this->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; row read with prepared placeholder.
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A );
    }

    












    public function query( array $filters, $limit = 50, $offset = 0, $columns = null ) {
        global $wpdb;
        $table  = $this->table();
        $cols   = ( is_string( $columns ) && '' !== $columns ) ? $columns : '*';
        $w      = self::build_where( $filters );
        $params = array_merge( $w['params'], array( (int) $limit, (int) $offset ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; $cols/$table/$where are server-built from constant column lists, all user values bound via prepared placeholders. UnfinishedPrepare fires because the query text is returned by deferred_join_sql() rather than written inline, so the sniff cannot see the placeholders it contains.
        return $wpdb->get_results(
            $wpdb->prepare(
                self::deferred_join_sql( $table, $cols, $w['sql'] ),
                ...$params
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    






    public function count( array $filters ) {
        global $wpdb;
        $table = $this->table();
        $w     = self::build_where( $filters );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the WHERE is server-built from a constant column list with every user value bound.
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$w['sql']}";
        return (int) $wpdb->get_var( empty( $w['params'] ) ? $sql : $wpdb->prepare( $sql, ...$w['params'] ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    






    public static function build_where( array $filters ) {
        $where  = array( '1=1' );
        $params = array();
        foreach ( array( 'object_type', 'object_id', 'tool_name', 'wp_user_id', 'oauth_client_id', 'auth_source', 'action' ) as $f ) {
            if ( isset( $filters[ $f ] ) && '' !== $filters[ $f ] ) {
                $where[]  = "{$f} = %s";
                $params[] = $filters[ $f ];
            }
        }
        if ( isset( $filters['audit_id'] ) && '' !== $filters['audit_id'] ) {
            $where[]  = 'audit_id = %d';
            $params[] = (int) $filters['audit_id'];
        }
        if ( ! empty( $filters['drafts'] ) ) {
            global $wpdb;
            
            $where[] = "action = 'create' AND object_type = 'post' AND object_id IN ( SELECT CAST( ID AS CHAR ) FROM {$wpdb->posts} WHERE post_status = 'draft' )";
        }
        if ( ! empty( $filters['since'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['since'];
        }
        if ( ! empty( $filters['until'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['until'];
        }
        return array( 'sql' => implode( ' AND ', $where ), 'params' => $params );
    }

    






























    public static function deferred_join_sql( $table, $cols, $where ) {
        return "SELECT " . self::qualify_columns( $cols ) . " FROM {$table} c"
            . " JOIN (SELECT id FROM {$table} WHERE {$where}"
            . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d) k ON k.id = c.id'
            . ' ORDER BY c.created_at DESC, c.id DESC';
    }

    








    private static function qualify_columns( $cols ) {
        $cols = trim( (string) $cols );
        if ( '' === $cols || '*' === $cols ) {
            return 'c.*';
        }
        $out = array();
        foreach ( explode( ',', $cols ) as $col ) {
            $col = trim( $col );
            if ( '' === $col ) {
                continue;
            }
            $out[] = ( '*' === $col ) ? 'c.*' : 'c.' . $col;
        }
        return $out ? implode( ', ', $out ) : 'c.*';
    }

    public function delete_older_than( $datetime_gmt, $batch = 500 ) {
        global $wpdb;
        $table = $this->table();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; batched delete with prepared placeholders.
        return (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s LIMIT %d", $datetime_gmt, (int) $batch )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    



    public function delete_db_rows_older_than( $datetime_gmt, $batch = 500 ) {
        global $wpdb;
        $table = $this->table();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; batched delete with prepared placeholders.
        return (int) $wpdb->query(
            $wpdb->prepare( "DELETE FROM {$table} WHERE capture_mode IN ('db_row', 'db_sql') AND created_at < %s LIMIT %d", $datetime_gmt, (int) $batch )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }
}
