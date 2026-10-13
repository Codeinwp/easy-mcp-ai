<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















class Change_DB_Interceptor {

    const DEFAULT_ROWS_PER_CALL = 200;

    







    const MAX_SQL_LITERAL = 200;

    






    private static $table_denylist = array(
        'users', 'usermeta',
        'easy_mcp_ai_tokens', 'easy_mcp_ai_audit_log', 'easy_mcp_ai_change_log',
        'easy_mcp_ai_oauth_',
    );

    












    private static $core_tables = array(
        'posts'       => array( 'type' => 'post',         'id_cols' => array( 'ID' ) ),
        'options'     => array( 'type' => 'option',       'id_cols' => array( 'option_name' ) ),
        'postmeta'    => array( 'type' => 'meta',         'id_cols' => array( 'post_id', 'meta_key' ) ),
        'termmeta'    => array( 'type' => 'term_meta',    'id_cols' => array( 'term_id', 'meta_key' ) ),
        'commentmeta' => array( 'type' => 'comment_meta', 'id_cols' => array( 'comment_id', 'meta_key' ) ),
        'comments'    => array( 'type' => 'comment',      'id_cols' => array( 'comment_ID' ) ),
        'sitemeta'    => array( 'type' => 'site_option',  'id_cols' => array( 'meta_key' ) ),
    );

    
    private $repo;

    
    private $in_progress = false;

    


    private $pending = array();

    



    private $held = array();

    
    private $rows_written = 0;

    
    private $skipped = 0;

    









    private $skipped_memory = 0;

    

















    private $held_bytes = 0;

    





    private $tx_mark = null;

    











    private $tx_seen = false;

    public function __construct( Change_Log_Repository $repo ) {
        $this->repo = $repo;
    }

    public function arm() {
        $this->pending      = array();
        $this->held         = array();
        $this->rows_written = 0;
        $this->skipped        = 0;
        $this->skipped_memory = 0;
        $this->held_bytes     = 0;
        $this->tx_mark      = null;
        $this->tx_seen      = false;
        \add_filter( 'query', array( $this, 'on_query' ), PHP_INT_MAX );

        
        
        
        
        global $wpdb;
        if ( is_object( $wpdb ) && 'wpdb' !== \get_class( $wpdb ) && ! \defined( 'EASY_MCP_AI_DB_DROPIN_NOTICED' ) ) {
            \define( 'EASY_MCP_AI_DB_DROPIN_NOTICED', true );
            if ( \defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                \error_log( 'easy-mcp-ai: custom $wpdb drop-in (' . \get_class( $wpdb ) . ') detected — Layer-3 change capture requires it to apply the "query" filter.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- one-time operator diagnostic, same pattern as Server's tool-exception logging
            }
        }
    }

    public function disarm() {
        $this->guarded_flush();
        $this->emit_held_rows();
        $this->emit_summary_row();
        \remove_filter( 'query', array( $this, 'on_query' ), PHP_INT_MAX );
    }

    


    public function on_query( $sql ) {
        if ( $this->in_progress || ! Change_Context::is_active() ) {
            return $sql;
        }
        $this->in_progress = true;
        try {
            $trim = ltrim( (string) $sql );

            

















            if ( 0 === strncasecmp( $trim, 'ROLLBACK', 8 ) ) {
                
                
                
                
                $this->pending = array();
                if ( null !== $this->tx_mark ) {
                    if ( count( $this->held ) > $this->tx_mark ) {
                        array_splice( $this->held, $this->tx_mark );
                    }
                } elseif ( ! $this->tx_seen ) {
                    
                    $this->held = array();
                }
                
                
                
                $this->tx_mark = null;
                return $sql;
            }

            
            
            
            
            if ( 0 === strncasecmp( $trim, 'START TRANSACTION', 17 )
                || 0 === strncasecmp( $trim, 'BEGIN', 5 ) ) {
                $this->flush_pending();
                $this->tx_mark = count( $this->held );
                $this->tx_seen = true;
                return $sql;
            }

            
            if ( 0 === strncasecmp( $trim, 'COMMIT', 6 ) ) {
                $this->flush_pending();
                $this->tx_mark = null;
                return $sql;
            }

            
            
            
            if ( null !== $this->tx_mark
                && preg_match( '/^(CREATE|ALTER|DROP|TRUNCATE|RENAME)\s/i', $trim ) ) {
                $this->tx_mark = null;
            }

            
            
            $this->flush_pending();

            $c = self::classify( $sql );
            if ( null === $c['op'] || $this->table_denied( $c['table'] ) ) {
                return $sql;
            }

            if ( 'sql' === $c['tier'] ) {
                
                
                
                
                
                
                
                
                if ( $this->is_core_table( $c['table'] ) ) {
                    $this->pending[] = array( 'kind' => 'sql', 'sql' => (string) $sql, 'c' => $c );
                } else {
                    $this->capture_sql_row( $sql, $c );
                }
                return $sql;
            }

            
            
            $before = null;
            $pk     = null;
            if ( 'UPDATE' === $c['op'] || 'DELETE' === $c['op'] ) {
                $before = $this->select_row( $c['table'], $c['where'] );
                $pk     = $this->pk_from_where( $c['where'] );
            }
            $this->pending[] = array(
                'kind'     => 'row',
                'op'       => $c['op'],
                'table'    => $c['table'],
                'set_cols' => $c['set_cols'],
                'where'    => $c['where'],
                'pk'       => $pk,
                'before'   => $before,
                'sql'      => (string) $sql,
            );
        } finally {
            $this->in_progress = false;
        }
        return $sql;
    }

    

    




    public static function classify( $sql ) {
        $out  = array( 'op' => null, 'table' => null, 'tier' => null, 'set_cols' => array(), 'where' => array() );
        $trim = ltrim( (string) $sql );

        if ( ! preg_match( '/^(INSERT|REPLACE|UPDATE|DELETE)\b/i', $trim, $m ) ) {
            return $out;
        }
        $out['op']   = strtoupper( $m[1] );
        $out['tier'] = 'sql'; 

        if ( 'INSERT' === $out['op'] || 'REPLACE' === $out['op'] ) {
            if ( preg_match( '/^(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+`?([A-Za-z0-9_]+)`?\s*(\(|SET\b|VALUES?\b)/i', $trim, $t ) ) {
                $out['table'] = $t[1];
                
                
                
                
                if ( ! preg_match( '/\bSELECT\b/i', $trim )
                    && ! preg_match( '/ON\s+DUPLICATE\s+KEY/i', $trim )
                    && ! preg_match( '/\)\s*,\s*\(/', $trim ) ) {
                    $out['tier'] = 'row';
                }
            } else {
                self::best_effort_table( $trim, $out );
            }
            return $out;
        }

        $where_re = '(`?\w+`?\s*=\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|\d+))(\s+AND\s+`?\w+`?\s*=\s*(?:\'(?:[^\'\\\\]|\\\\.)*\'|\d+))*';

        if ( 'UPDATE' === $out['op'] ) {
            if ( preg_match( '/^UPDATE\s+`?([A-Za-z0-9_]+)`?\s+SET\s+(.*?)\s+WHERE\s+(.*)$/is', $trim, $t )
                && preg_match( '/^' . $where_re . '\s*;?\s*$/is', trim( $t[3] ) ) ) {
                $out['table']    = $t[1];
                $out['tier']     = 'row';
                $out['set_cols'] = self::parse_set_columns( $t[2] );
                $out['where']    = self::parse_where_pairs( $t[3] );
            } else {
                self::best_effort_table( $trim, $out );
            }
            return $out;
        }

        
        if ( preg_match( '/^DELETE\s+FROM\s+`?([A-Za-z0-9_]+)`?\s+WHERE\s+(.*)$/is', $trim, $t )
            && preg_match( '/^' . $where_re . '\s*;?\s*$/is', trim( $t[2] ) ) ) {
            $out['table'] = $t[1];
            $out['tier']  = 'row';
            $out['where'] = self::parse_where_pairs( $t[2] );
        } else {
            self::best_effort_table( $trim, $out );
        }
        return $out;
    }

    
    








    private static function best_effort_table( $trim, array &$out ) {
        if ( preg_match( '/(?:INTO|FROM|UPDATE)\s+((?:`[^`]+`|[A-Za-z0-9_]+)(?:\s*\.\s*(?:`[^`]+`|[A-Za-z0-9_]+))*)/i', $trim, $m ) ) {
            $out['table'] = self::unqualify( $m[1] );
        }
    }

    






    private static function unqualify( $identifier ) {
        $identifier = (string) $identifier;
        $parts      = preg_split( '/\s*\.\s*/', $identifier );
        $last       = is_array( $parts ) && $parts ? end( $parts ) : $identifier;
        return trim( (string) $last, " `\t\n\r" );
    }

    private static function parse_set_columns( $set_part ) {
        preg_match_all( '/(?:^|,)\s*`?(\w+)`?\s*=/', $set_part, $m );
        return array_values( array_unique( $m[1] ) );
    }

    private static function parse_where_pairs( $where_part ) {
        preg_match_all( '/`?(\w+)`?\s*=\s*(\'(?:[^\'\\\\]|\\\\.)*\'|\d+)/', $where_part, $m, PREG_SET_ORDER );
        $pairs = array();
        foreach ( $m as $p ) {
            $pairs[] = array( 'col' => $p[1], 'val' => $p[2] );
        }
        return $pairs;
    }

    

    private function table_denied( $table ) {
        if ( null === $table ) {
            return false; 
        }
        
        
        
        
        $stripped = strtolower( $this->strip_prefix( self::unqualify( $table ) ) );
        $deny     = (array) \apply_filters( 'easy_mcp_ai_change_log_db_table_denylist', self::$table_denylist );
        foreach ( $deny as $entry ) {
            $entry = strtolower( (string) $entry );
            if ( '' === $entry ) {
                continue;
            }
            if ( '_' === substr( $entry, -1 ) ) {
                if ( 0 === strpos( $stripped, $entry ) ) {
                    return true;
                }
            } elseif ( $stripped === $entry ) {
                return true;
            }
        }
        return false;
    }

    








    private function strip_prefix( $table ) {
        global $wpdb;
        $table    = (string) $table;
        $prefixes = array();
        if ( is_object( $wpdb ) ) {
            if ( isset( $wpdb->prefix ) ) {
                $prefixes[] = (string) $wpdb->prefix;
            }
            if ( isset( $wpdb->base_prefix ) ) {
                $prefixes[] = (string) $wpdb->base_prefix;
            }
        }
        if ( ! $prefixes ) {
            $prefixes = array( 'wp_' );
        }
        
        usort( $prefixes, function ( $a, $b ) {
            return strlen( $b ) - strlen( $a );
        } );
        foreach ( $prefixes as $prefix ) {
            if ( '' !== $prefix && 0 === stripos( $table, $prefix ) ) {
                return substr( $table, strlen( $prefix ) );
            }
        }
        return $table;
    }

    private function select_row( $table, array $where ) {
        global $wpdb;
        if ( ! $where || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return null;
        }
        
        
        
        if ( ! preg_match( '/^[A-Za-z0-9_]+$/', (string) $table ) ) {
            return null;
        }
        $conds  = array();
        $params = array();
        foreach ( $where as $p ) {
            if ( ! preg_match( '/^\w+$/', (string) $p['col'] ) ) {
                return null;
            }
            $val = (string) $p['val'];
            if ( 0 === strpos( $val, "'" ) ) {
                
                
                $conds[]  = '`' . $p['col'] . '` = %s';
                $params[] = stripslashes( substr( $val, 1, -1 ) );
            } else {
                $conds[]  = '`' . $p['col'] . '` = %d';
                $params[] = (int) $val;
            }
        }
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        $suppressible  = method_exists( $wpdb, 'suppress_errors' );
        $prev_suppress = $suppressible ? $wpdb->suppress_errors( true ) : false;

        try {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $conds is built above from regex-whitelisted `identifier` = %s|%d pairs ONLY (the sniff cannot see the placeholders inside the variable); $table/columns are ^[A-Za-z0-9_]+$-validated identifiers, which placeholders cannot carry; every VALUE is bound via prepare($params); a pre-image read of a row about to change is inherently uncacheable. Same pattern as class-change-log-repository.php.
            $row = $wpdb->get_row(
                $wpdb->prepare( "SELECT * FROM `{$table}` WHERE " . implode( ' AND ', $conds ) . ' LIMIT 1', $params ),
                'ARRAY_A'
            );
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        } finally {
            if ( $suppressible ) {
                $wpdb->suppress_errors( $prev_suppress );
            }
        }

        return is_array( $row ) ? $row : ( is_object( $row ) ? (array) $row : null );
    }

    





    private function pk_from_where( array $where ) {
        foreach ( $where as $p ) {
            $col = strtolower( (string) $p['col'] );
            if ( 'id' === $col || '_id' === substr( $col, -3 ) ) {
                return trim( $p['val'], "'" );
            }
        }
        return null;
    }

    private function guarded_flush() {
        if ( $this->in_progress ) {
            return;
        }
        $this->in_progress = true;
        try {
            $this->flush_pending();
        } finally {
            $this->in_progress = false;
        }
    }

    private function flush_pending() {
        if ( ! $this->pending ) {
            return;
        }
        $queue         = $this->pending;
        $this->pending = array();
        foreach ( $queue as $p ) {
            if ( 'sql' === $p['kind'] ) {
                
                
                
                
                
                
                
                
                
                
                
                global $wpdb;
                $op = strtoupper( (string) ( $p['c']['op'] ?? '' ) );
                if ( ! isset( $p['insert_id'] ) ) {
                    $p['insert_id'] = ( in_array( $op, array( 'INSERT', 'REPLACE' ), true ) && is_object( $wpdb ) && ! empty( $wpdb->insert_id ) )
                        ? (string) $wpdb->insert_id
                        : null;
                }
                if ( $this->may_hold( $p ) ) {
                    $this->held[] = $p;
                }
                continue;
            }
            $this->flush_row_entry( $p );
        }
    }

    private function flush_row_entry( array $p ) {
        global $wpdb;

        $pk = $p['pk'];
        if ( null === $pk && ( 'INSERT' === $p['op'] || 'REPLACE' === $p['op'] ) && is_object( $wpdb ) && ! empty( $wpdb->insert_id ) ) {
            $pk = (string) $wpdb->insert_id;
        }

        $after = null;
        if ( 'DELETE' !== $p['op'] ) {
            if ( $p['where'] ) {
                $after = $this->select_row( $p['table'], $p['where'] );
            } else {
                
                
                
                
                
                
                foreach ( $this->insert_identity_candidates( $p['table'], $p['sql'] ?? null, $pk ) as $candidate ) {
                    $after = $this->select_row( $p['table'], $candidate );
                    if ( null !== $after ) {
                        break;
                    }
                }
            }
        }

        $stripped = $this->strip_prefix( $p['table'] );
        $action   = 'UPDATE' === $p['op'] ? 'update' : ( 'DELETE' === $p['op'] ? 'delete' : 'create' );
        $changed  = 'UPDATE' === $p['op'] ? $this->actually_changed( $p['set_cols'], $p['before'], $after ) : null;

        
        
        
        
        
        
        
        
        
        

        
        
        
        
        if ( $this->is_core_table( $p['table'] ) ) {
            $entry = array(
                'kind'    => 'core_row',
                'op'      => $p['op'],
                'key'     => $this->core_key( $p['table'], $p['where'], $pk, $p['sql'] ?? null ),
                'partial' => array(
                    'action'         => $action,
                    'object_type'    => 'db_row',
                    'object_id'      => $stripped . ':' . ( null !== $pk ? $pk : '?' ),
                    'object_subtype' => $stripped,
                    'before_value'   => $this->redact_row( $p['before'] ),
                    'after_value'    => $this->redact_row( $after ),
                    'changed_fields' => $changed ?: null,
                    'capture_mode'   => 'db_row',
                ),
            );
            if ( $this->may_hold( $entry ) ) {
                $this->held[] = $entry;
            }
            return;
        }

        $this->write_db_row( array(
            'action'         => $action,
            'object_type'    => 'db_row',
            'object_id'      => $stripped . ':' . ( null !== $pk ? $pk : '?' ),
            'object_subtype' => $stripped,
            'before_value'   => $this->redact_row( $p['before'] ),
            'after_value'    => $this->redact_row( $after ),
            'changed_fields' => $changed ?: null,
            'capture_mode'   => 'db_row',
        ) );
    }

    




    private function emit_held_rows() {
        $held       = $this->held;
        $this->held = array();
        foreach ( $held as $h ) {
            if ( 'sql' === $h['kind'] ) {
                $key = $this->core_key( $h['c']['table'], array(), $h['insert_id'] ?? null, $h['sql'] );
                if ( $this->key_is_layer2_suppressed( $key ) ) {
                    continue;
                }
                if ( null === $key || ! Change_Context::object_was_recorded( $key[0], $key[1] ) ) {
                    $this->capture_sql_row( $h['sql'], $h['c'] );
                }
                continue;
            }
            $key = $h['key'];
            if ( $this->key_is_layer2_suppressed( $key ) ) {
                continue;
            }
            if ( null !== $key && Change_Context::object_was_recorded( $key[0], $key[1] ) ) {
                
                
                
                
                if ( 'post' !== $key[0] || 'UPDATE' !== $h['op'] ) {
                    continue; 
                }
                $changed = array_values( array_diff(
                    (array) ( $h['partial']['changed_fields'] ?? array() ),
                    Change_Context::recorded_fields( $key[0], $key[1] )
                ) );
                if ( ! $changed ) {
                    continue; 
                }
                $h['partial']['changed_fields'] = $changed;
            }
            $this->write_db_row( $h['partial'] );
        }
    }

    
















    



















    private function actually_changed( $set_cols, $before, $after ) {
        $set_cols = (array) $set_cols;
        if ( ! $set_cols || ! is_array( $before ) || ! is_array( $after ) ) {
            return $set_cols ?: null;
        }
        if ( ! class_exists( '\Easy_MCP_AI\History\Change_Recorder' )
            || ! method_exists( '\Easy_MCP_AI\History\Change_Recorder', 'values_match' ) ) {
            return $set_cols; 
        }
        $changed = array();
        foreach ( $set_cols as $col ) {
            $b = array_key_exists( $col, $before ) ? $before[ $col ] : null;
            $a = array_key_exists( $col, $after ) ? $after[ $col ] : null;
            if ( ! Change_Recorder::values_match( $b, $a ) ) {
                $changed[] = $col;
            }
        }
        return $changed;
    }

    private function key_is_layer2_suppressed( $key ) {
        if ( ! is_array( $key ) || count( $key ) < 2 ) {
            return false;
        }
        if ( 'option' !== $key[0] && 'site_option' !== $key[0] ) {
            return false;
        }
        if ( ! class_exists( '\Easy_MCP_AI\History\Change_Recorder' )
            || ! method_exists( '\Easy_MCP_AI\History\Change_Recorder', 'option_is_recordable' ) ) {
            return false; 
        }
        return ! Change_Recorder::option_is_recordable( (string) $key[1] );
    }

    private function is_core_table( $table ) {
        
        
        
        
        return null !== $table && isset( self::$core_tables[ strtolower( $this->strip_prefix( $table ) ) ] );
    }

    







    private function core_key( $table, array $where, $insert_id = null, $sql = null ) {
        if ( null === $table ) {
            return null;
        }
        $stripped = strtolower( $this->strip_prefix( $table ) );
        if ( ! isset( self::$core_tables[ $stripped ] ) ) {
            return null;
        }
        $desc   = self::$core_tables[ $stripped ];
        $values = array();
        foreach ( $desc['id_cols'] as $col ) {
            $val = $this->value_from_where( $where, $col );
            if ( null === $val && null !== $sql ) {
                $val = $this->value_from_sql( (string) $sql, $col );
            }
            if ( null === $val && 1 === count( $desc['id_cols'] ) && null !== $insert_id ) {
                $val = (string) $insert_id; 
            }
            if ( null === $val ) {
                return null;
            }
            $values[] = $val;
        }
        return array( $desc['type'], implode( ':', $values ) );
    }

    







    private function insert_identity_candidates( $table, $sql, $pk ) {
        $out      = array();
        $stripped = strtolower( $this->strip_prefix( (string) $table ) );

        if ( isset( self::$core_tables[ $stripped ] ) ) {
            $where = array();
            foreach ( self::$core_tables[ $stripped ]['id_cols'] as $col ) {
                $val = ( null !== $sql ) ? $this->value_from_sql( (string) $sql, $col ) : null;
                if ( null === $val && 1 === count( self::$core_tables[ $stripped ]['id_cols'] ) && null !== $pk ) {
                    $val = (string) $pk;
                }
                if ( null === $val ) {
                    $where = array();
                    break;
                }
                $where[] = array( 'col' => $col, 'val' => "'" . addslashes( $val ) . "'" );
            }
            if ( $where ) {
                $out[] = $where;
            }
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        if ( null !== $pk && '' !== (string) $pk && ctype_digit( (string) $pk )
            && ! isset( self::$core_tables[ $stripped ] ) ) {
            $out[] = array( array( 'col' => 'id', 'val' => (string) (int) $pk ) );
        }

        
        
        if ( null !== $sql
            && preg_match( '/\(([^)]*)\)\s*VALUES\s*\(/i', (string) $sql, $cm )
            && preg_match( '/VALUES\s*\((.*)$/is', (string) $sql, $vm )
            && preg_match_all( '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[+-]?\d+(?:\.\d+)?|NULL/i', $vm[1], $vals ) ) {
            $cols  = preg_split( '/\s*,\s*/', trim( $cm[1] ) );
            $where = array();
            foreach ( $cols as $i => $col ) {
                if ( count( $where ) >= 3 || ! isset( $vals[0][ $i ] ) ) {
                    break;
                }
                $col = trim( (string) $col, " `\t\r\n" );
                $val = $vals[0][ $i ];
                if ( ! preg_match( '/^\w+$/', $col ) || strlen( $val ) > 128 || 0 === strcasecmp( $val, 'NULL' ) ) {
                    continue;
                }
                $where[] = array( 'col' => $col, 'val' => $val );
            }
            if ( $where ) {
                $out[] = $where;
            }
        }
        return $out;
    }

    private function value_from_where( array $where, $col ) {
        foreach ( $where as $p ) {
            if ( 0 === strcasecmp( (string) $p['col'], (string) $col ) ) {
                return $this->unquote( (string) $p['val'] );
            }
        }
        return null;
    }

    




    private function value_from_sql( $sql, $col ) {
        if ( preg_match( '/^\s*(?:INSERT|REPLACE)\b/i', $sql )
            && preg_match( '/\(([^)]*)\)\s*VALUES\s*\(/i', $sql, $cm )
            && preg_match( '/VALUES\s*\((.*)$/is', $sql, $vm ) ) {
            $cols = array_map(
                function ( $c ) {
                    return trim( (string) $c, " `\t\r\n" );
                },
                preg_split( '/\s*,\s*/', trim( $cm[1] ) )
            );
            $idx = array_search( strtolower( (string) $col ), array_map( 'strtolower', $cols ), true );
            if ( false !== $idx
                && preg_match_all( '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|[+-]?\d+(?:\.\d+)?|NULL/i', $vm[1], $vals )
                && isset( $vals[0][ $idx ] ) ) {
                return $this->unquote( $vals[0][ $idx ] );
            }
        }
        
        
        
        if ( preg_match( '/(?<![A-Za-z0-9_])`?' . preg_quote( (string) $col, '/' ) . '`?\s*=\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|\d+)/i', $sql, $m ) ) {
            return $this->unquote( $m[1] );
        }
        return null;
    }

    private function unquote( $val ) {
        $val = trim( (string) $val );
        if ( '' !== $val && ( "'" === $val[0] || '"' === $val[0] ) ) {
            return stripslashes( substr( $val, 1, -1 ) );
        }
        return $val;
    }

    private function capture_sql_row( $sql, array $c ) {
        $stripped = null !== $c['table'] ? $this->strip_prefix( $c['table'] ) : 'unknown';
        $this->write_db_row( array(
            'action'         => 'INSERT' === $c['op'] || 'REPLACE' === $c['op'] ? 'create' : ( 'DELETE' === $c['op'] ? 'delete' : 'update' ),
            
            
            
            
            
            'object_type'    => 'db_sql',
            'object_id'      => $stripped . ':sql',
            'object_subtype' => $stripped,
            'before_value'   => null,
            'after_value'    => array( 'sql' => $this->redact_sql( (string) $sql ) ),
            'capture_mode'   => 'db_sql',
        ) );
    }

    private function write_db_row( array $partial ) {
        
        
        
        
        
        foreach ( array( 'before_value', 'after_value' ) as $f ) {
            if ( is_array( $partial[ $f ] ?? null ) ) {
                
                
                
                $redacted        = Change_Redactor::redact_for_table( $partial[ $f ], $partial['object_subtype'] ?? null );
                $partial[ $f ]   = $redacted['value'];
                if ( $redacted['truncated'] ) {
                    $partial['truncated'] = 1;
                }
            }
        }

        $cap = (int) \apply_filters( 'easy_mcp_ai_change_log_db_rows_per_call', \Easy_MCP_AI\Config::get( 'change_log_db_rows_per_call' ) );
        if ( $cap > 0 && $this->rows_written >= $cap ) {
            $this->skipped++;
            return;
        }
        $this->rows_written++;
        $this->insert_with_context( $partial );
    }

    















    private function may_hold( $entry ) {
        $budget = (int) \apply_filters( 'easy_mcp_ai_change_log_db_held_bytes', 8 * 1024 * 1024 );
        if ( $budget <= 0 ) {
            return true; 
        }
        if ( $this->held_bytes >= $budget ) {
            $this->skipped++;
            $this->skipped_memory++;
            return false;
        }
        
        
        
        
        $encoded = \wp_json_encode( $entry );
        $this->held_bytes += is_string( $encoded ) ? strlen( $encoded ) : 1024;
        return true;
    }

    private function emit_summary_row() {
        if ( $this->skipped < 1 ) {
            return;
        }
        $skipped              = $this->skipped;
        $by_memory            = $this->skipped_memory;
        $by_cap               = max( 0, $skipped - $by_memory );
        $this->skipped        = 0;
        $this->skipped_memory = 0;

        if ( $by_memory > 0 && $by_cap > 0 ) {
            $note = 'db-capture stopped: the memory budget and the row cap were both reached; further raw writes this call were not recorded individually';
        } elseif ( $by_memory > 0 ) {
            $note = 'db-capture memory budget reached; further raw writes this call were not recorded individually';
        } else {
            $note = 'db-capture row cap reached; further raw writes this call were not recorded individually';
        }

        $this->insert_with_context( array(
            'action'         => 'update',
            'object_type'    => 'db_row',
            'object_id'      => '_summary',
            'object_subtype' => '_summary',
            'after_value'    => array(
                'skipped'               => $skipped,
                'skipped_row_cap'       => $by_cap,
                'skipped_memory_budget' => $by_memory,
                'note'                  => $note,
            ),
            'truncated'      => 1,
            'capture_mode'   => 'db_row',
        ) );
    }

    private function insert_with_context( array $partial ) {
        
        Change_Context::increment_rows_written();
        $ctx = Change_Context::all();
        $this->repo->insert( array_merge( array(
            'audit_id'        => $ctx['audit_id']        ?? null,
            'auth_source'     => $ctx['auth_source']     ?? 'legacy',
            'token_id'        => $ctx['token_id']        ?? 0,
            'oauth_client_id' => $ctx['oauth_client_id'] ?? null,
            'wp_user_id'      => $ctx['wp_user_id']      ?? 0,
            'tool_name'       => $ctx['tool_name']       ?? '',
            'ip_address'      => $ctx['ip_address']      ?? null,
        ), $partial ) );
    }

    

    
    private function redact_row( $row ) {
        if ( ! is_array( $row ) ) {
            return $row;
        }

        
        
        
        
        
        
        
        $value_col = null;
        if ( isset( $row['option_name'] ) && array_key_exists( 'option_value', $row )
            && Change_Redactor::option_name_is_sensitive( (string) $row['option_name'] ) ) {
            $value_col = 'option_value';
        } elseif ( isset( $row['meta_key'] ) && array_key_exists( 'meta_value', $row )
            && Change_Redactor::key_is_sensitive( (string) $row['meta_key'] ) ) {
            $value_col = 'meta_value';
        }

        $out = array();
        foreach ( $row as $col => $val ) {
            $out[ $col ] = ( self::column_is_sensitive( (string) $col ) || $col === $value_col ) ? '[REDACTED]' : $val;
        }
        return $out;
    }

    



    public static function column_is_sensitive( $col ) {
        
        
        
        return Change_Redactor::key_is_sensitive( $col );
    }

    
    private function redact_sql( $sql ) {
        $sql = (string) $sql;

        
        
        $sql = preg_replace(
            '/(`?\w*(?:pass|secret|token|api_?key|_hash|private)\w*`?\s*=\s*)(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/i',
            '$1\'[REDACTED]\'',
            $sql
        );

        
        
        
        
        
        
        
        if ( preg_match( '/^\s*(?:INSERT|REPLACE)\b/i', $sql )
            && preg_match( '/\(([^)]*)\)\s*VALUES/i', $sql, $m ) ) {
            foreach ( preg_split( '/\s*,\s*/', trim( $m[1] ) ) as $col ) {
                if ( self::column_is_sensitive( trim( (string) $col, " `\t\r\n" ) ) ) {
                    $sql = preg_replace(
                        '/(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/',
                        '\'[REDACTED]\'',
                        $sql
                    );
                    break;
                }
            }
        }

        
        
        return (string) preg_replace_callback(
            '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"/',
            function ( $m ) {
                $len = strlen( $m[0] ) - 2; 
                return $len > self::MAX_SQL_LITERAL ? "'[TRUNCATED {$len} chars]'" : $m[0];
            },
            $sql
        );
    }
}
