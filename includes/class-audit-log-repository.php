<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















class Audit_Log_Repository {

    
    const PER_PAGE = 50;

    
    const SOURCES = array( 'legacy', 'oauth' );

    
    const SOURCE_UNKNOWN = 'unknown';

    






    public static function list_columns() {
        return 'id, token_id, tool_name, arguments, result_status, ip_address, '
            . 'auth_source, wp_user_id, oauth_client_id, duration_ms, created_at';
    }

    
    public static function filter_keys() {
        return array( 'search', 'tool_name', 'wp_user_id', 'oauth_client_id', 'auth_source', 'result_status', 'token_id', 'since', 'until', 'before_id' );
    }

    














    public function max_id() {
        global $wpdb;
        $table = $this->table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table, no user input.
        return (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$table}`" );
    }

    public function table() {
        global $wpdb;
        return $wpdb->prefix . 'easy_mcp_ai_audit_log';
    }

    















    public static function build_where( array $filters ) {
        global $wpdb;
        $where  = array( '1=1' );
        $params = array();

        if ( isset( $filters['search'] ) && '' !== trim( (string) $filters['search'] ) ) {
            $needle = trim( (string) $filters['search'] );
            $like   = '%' . ( is_object( $wpdb ) && method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( $needle ) : addcslashes( $needle, '_%\\' ) ) . '%';
            $where[]  = '(tool_name LIKE %s OR arguments LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }
        foreach ( array( 'tool_name', 'oauth_client_id', 'result_status' ) as $col ) {
            if ( isset( $filters[ $col ] ) && '' !== (string) $filters[ $col ] ) {
                $where[]  = "{$col} = %s";
                $params[] = (string) $filters[ $col ];
            }
        }
        foreach ( array( 'wp_user_id', 'token_id' ) as $col ) {
            if ( isset( $filters[ $col ] ) && '' !== (string) $filters[ $col ] && (int) $filters[ $col ] > 0 ) {
                $where[]  = "{$col} = %d";
                $params[] = (int) $filters[ $col ];
            }
        }
        if ( isset( $filters['auth_source'] ) && '' !== (string) $filters['auth_source'] ) {
            $source = (string) $filters['auth_source'];
            if ( self::SOURCE_UNKNOWN === $source ) {
                $where[] = 'auth_source IS NULL';
            } elseif ( in_array( $source, self::SOURCES, true ) ) {
                $where[]  = 'auth_source = %s';
                $params[] = $source;
            }
        }
        if ( ! empty( $filters['since'] ) ) {
            $where[]  = 'created_at >= %s';
            $params[] = (string) $filters['since'];
        }
        if ( ! empty( $filters['until'] ) ) {
            $where[]  = 'created_at <= %s';
            $params[] = (string) $filters['until'];
        }
        
        
        if ( isset( $filters['before_id'] ) && (int) $filters['before_id'] > 0 ) {
            $where[]  = 'id <= %d';
            $params[] = (int) $filters['before_id'];
        }
        return array( 'sql' => implode( ' AND ', $where ), 'params' => $params );
    }

    



    public function count( array $filters ) {
        global $wpdb;
        $table = $this->table();
        $w     = self::build_where( $filters );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the WHERE is server-built from a constant column list with every user value bound via placeholders.
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$w['sql']}";
        return (int) $wpdb->get_var( empty( $w['params'] ) ? $sql : $wpdb->prepare( $sql, ...$w['params'] ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    










    public function query( array $filters, $limit = self::PER_PAGE, $offset = 0 ) {
        global $wpdb;
        $table  = $this->table();
        $w      = self::build_where( $filters );
        $cols   = self::qualify_columns( self::list_columns() );
        $params = array_merge( $w['params'], array( (int) $limit, (int) $offset ) );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; column list and WHERE are server-built, every user value bound via placeholders.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT {$cols} FROM `{$table}` l"
                . " JOIN (SELECT id FROM `{$table}` WHERE {$w['sql']} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d) k ON k.id = l.id"
                . ' ORDER BY l.created_at DESC, l.id DESC',
                ...$params
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_array( $rows ) ? $rows : array();
    }

    






    public function find( $id ) {
        global $wpdb;
        $table = $this->table();
        $cols  = self::qualify_columns( self::list_columns() );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; constant column list; the id is bound.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT {$cols} FROM `{$table}` l WHERE l.id = %d", (int) $id ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    







    public function distinct( $column ) {
        global $wpdb;
        if ( ! in_array( $column, array( 'tool_name', 'result_status', 'wp_user_id' ), true ) ) {
            return array();
        }
        $key    = 'easy_mcp_ai_audit_distinct_' . $column;
        $cached = \get_transient( $key );
        if ( is_array( $cached ) ) {
            return $cached;
        }
        $table = $this->table();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- whitelisted column on a plugin-owned table; cached above.
        $values = $wpdb->get_col( "SELECT DISTINCT {$column} FROM `{$table}` WHERE {$column} IS NOT NULL ORDER BY {$column} ASC" );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $values = is_array( $values ) ? array_values( array_filter( array_map( 'strval', $values ), 'strlen' ) ) : array();
        \set_transient( $key, $values, 300 ); 
        return $values;
    }

    










    public function attach_labels( array $rows ) {
        global $wpdb;
        $user_ids   = array();
        $token_ids  = array();
        $client_ids = array();
        foreach ( $rows as $r ) {
            if ( ! empty( $r['wp_user_id'] ) ) {
                $user_ids[ (int) $r['wp_user_id'] ] = true;
            }
            $source = isset( $r['auth_source'] ) ? $r['auth_source'] : null;
            if ( 'legacy' === $source && ! empty( $r['token_id'] ) ) {
                $token_ids[ (int) $r['token_id'] ] = true;
            }
            if ( 'oauth' === $source && ! empty( $r['oauth_client_id'] ) ) {
                $client_ids[ (string) $r['oauth_client_id'] ] = true;
            }
        }

        $users = array();
        if ( $user_ids && function_exists( 'get_users' ) ) {
            foreach ( (array) \get_users( array( 'include' => array_keys( $user_ids ), 'fields' => array( 'ID', 'user_login' ) ) ) as $u ) {
                if ( is_object( $u ) && isset( $u->ID ) ) {
                    $users[ (int) $u->ID ] = (string) $u->user_login;
                }
            }
        }

        $tokens = array();
        if ( $token_ids && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
            $ids          = array_keys( $token_ids );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
            $table        = $wpdb->prefix . 'easy_mcp_ai_tokens';
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated per id, plugin-owned table.
            foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, created_by, wp_user_id FROM `{$table}` WHERE id IN ({$placeholders})", ...$ids ), ARRAY_A ) as $t ) {
                $tokens[ (int) $t['id'] ] = (string) $t['name'];
                if ( ! empty( $t['created_by'] ) && (int) $t['created_by'] === (int) $t['wp_user_id'] ) {
                    $tokens[ (int) $t['id'] ] .= ' (' . __( 'self-service', 'easy-mcp-ai' ) . ')';
                }
            }
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        $clients = array();
        if ( $client_ids && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
            $ids          = array_keys( $client_ids );
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
            $table        = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- placeholders generated per id, plugin-owned table.
            foreach ( (array) $wpdb->get_results( $wpdb->prepare( "SELECT client_id, client_name FROM `{$table}` WHERE client_id IN ({$placeholders})", ...$ids ), ARRAY_A ) as $c ) {
                $clients[ (string) $c['client_id'] ] = (string) $c['client_name'];
            }
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        foreach ( $rows as $i => $r ) {
            $uid    = ! empty( $r['wp_user_id'] ) ? (int) $r['wp_user_id'] : 0;
            $source = isset( $r['auth_source'] ) ? $r['auth_source'] : null;
            $rows[ $i ]['user_login']  = ( $uid && isset( $users[ $uid ] ) ) ? $users[ $uid ] : null;
            $rows[ $i ]['token_name']  = ( 'legacy' === $source && isset( $tokens[ (int) $r['token_id'] ] ) ) ? $tokens[ (int) $r['token_id'] ] : null;
            $rows[ $i ]['client_name'] = ( 'oauth' === $source && ! empty( $r['oauth_client_id'] ) && isset( $clients[ (string) $r['oauth_client_id'] ] ) ) ? $clients[ (string) $r['oauth_client_id'] ] : null;
        }
        return $rows;
    }

    
















    public static function credential_label( array $row ) {
        $source   = isset( $row['auth_source'] ) && in_array( $row['auth_source'], self::SOURCES, true ) ? $row['auth_source'] : self::SOURCE_UNKNOWN;
        $token_id = isset( $row['token_id'] ) ? (int) $row['token_id'] : 0;

        if ( 'legacy' === $source ) {
            if ( 0 === $token_id ) {
                return array( 'source' => $source, 'label' => '—' );
            }
            $label = ! empty( $row['token_name'] )
                ? (string) $row['token_name']
                /* translators: %d: API key row id */
                : sprintf( __( 'API key #%d', 'easy-mcp-ai' ), $token_id );
            return array( 'source' => $source, 'label' => $label );
        }
        if ( 'oauth' === $source ) {
            if ( ! empty( $row['client_name'] ) ) {
                return array( 'source' => $source, 'label' => (string) $row['client_name'] );
            }
            if ( ! empty( $row['oauth_client_id'] ) ) {
                return array( 'source' => $source, 'label' => (string) $row['oauth_client_id'] );
            }
            if ( 0 === $token_id ) {
                return array( 'source' => $source, 'label' => '—' );
            }
            /* translators: %d: OAuth access-token row id */
            return array( 'source' => $source, 'label' => sprintf( __( 'OAuth token #%d', 'easy-mcp-ai' ), $token_id ) );
        }
        if ( 0 === $token_id ) {
            return array( 'source' => self::SOURCE_UNKNOWN, 'label' => '—' );
        }
        /* translators: %d: credential row id whose table is not recorded */
        return array( 'source' => self::SOURCE_UNKNOWN, 'label' => sprintf( __( '#%d (unknown source)', 'easy-mcp-ai' ), $token_id ) );
    }

    



    private static function qualify_columns( $cols ) {
        $out = array();
        foreach ( explode( ',', (string) $cols ) as $col ) {
            $col = trim( $col );
            if ( '' !== $col ) {
                $out[] = 'l.' . $col;
            }
        }
        return $out ? implode( ', ', $out ) : 'l.*';
    }
}
