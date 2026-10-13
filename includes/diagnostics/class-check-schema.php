<?php























namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Schema {

    
    const TABLES = array(
        'easy_mcp_ai_tokens',
        'easy_mcp_ai_audit_log',
        'easy_mcp_ai_change_log',
        'easy_mcp_ai_oauth_clients',
        'easy_mcp_ai_oauth_codes',
        'easy_mcp_ai_oauth_access_tokens',
        'easy_mcp_ai_oauth_consents',
        'easy_mcp_ai_oauth_device_codes',
    );

    
    const CHANGE_LOG_INDEX = 'object_lookup';

    
    const REASON_NO_DATABASE        = 'no_database';
    const REASON_OAUTH_UNAVAILABLE  = 'oauth_unavailable';

    


    public static function run() {
        return array(
            self::evaluate_tables( self::missing_tables() ),
            self::evaluate_indexes( self::missing_indexes(), self::index_abstention_reason() ),
            self::evaluate_change_log_index(
                self::change_log_index_present(),
                self::db_description(),
                self::REASON_NO_DATABASE
            ),
            self::evaluate_schema_versions( self::schema_versions() ),
            self::evaluate_privileges( self::read_grants() ),
        );
    }

    









    private static function abstention_reason( $reason ) {
        if ( self::REASON_OAUTH_UNAVAILABLE === $reason ) {
            return __( 'The OAuth layer is switched off on this site, so its tables and indexes were not examined. This is not a database problem.', 'easy-mcp-ai' );
        }

        
        
        
        
        
        
        
        
        
        
        return __( 'The indexes could not be read. If a table is missing, the table check above says so; otherwise the database did not answer this query.', 'easy-mcp-ai' );
    }

    
    private static function index_abstention_reason() {
        if ( ! class_exists( '\Easy_MCP_AI\OAuth\OAuth_Schema' ) ) {
            return self::REASON_OAUTH_UNAVAILABLE;
        }
        return self::REASON_NO_DATABASE;
    }

    














    public static function index_probe( $table, $index_name ) {
        global $wpdb;

        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return null;
        }

        
        if ( property_exists( $wpdb, 'last_error' ) ) {
            $wpdb->last_error = '';
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; name is $wpdb->prefix-derived, index name is a class constant. SHOW INDEX cannot take a placeholder for the table.
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        
        
        
        
        
        
        
        
        
        if ( null === $found && property_exists( $wpdb, 'last_error' ) && '' !== (string) $wpdb->last_error ) {
            return null;
        }

        return ! empty( $found );
    }

    





    public static function evaluate_tables( $missing ) {
        $label = __( 'Plugin database tables present', 'easy-mcp-ai' );

        if ( null === $missing ) {
            return Diagnostic_Result::unknown( 'c1', Diagnostic_Result::TIER_BLOCKER, $label, __( 'Could not query the database.', 'easy-mcp-ai' ) );
        }

        if ( ! empty( $missing ) ) {
            return Diagnostic_Result::fail(
                'c1',
                Diagnostic_Result::TIER_BLOCKER,
                $label,
                sprintf(
                    /* translators: %s: comma-separated database table names. */
                    __( 'Missing: %s. Features backed by these tables cannot work, and depending on which is absent, authentication itself may fail.', 'easy-mcp-ai' ),
                    implode( ', ', $missing )
                ),
                __( 'Deactivate and reactivate the plugin to recreate them. If they are still missing afterwards, the database user is probably not allowed to create tables — see the database privileges check.', 'easy-mcp-ai' ),
                array( 'missing_tables' => $missing )
            );
        }

        return Diagnostic_Result::pass( 'c1', Diagnostic_Result::TIER_BLOCKER, $label, '', array( 'missing_tables' => array() ) );
    }

    





    public static function evaluate_indexes( $missing, $reason = self::REASON_NO_DATABASE ) {
        $label = __( 'Required database indexes present', 'easy-mcp-ai' );

        if ( null === $missing ) {
            return Diagnostic_Result::unknown( 'c2', Diagnostic_Result::TIER_WARNING, $label, self::abstention_reason( $reason ) );
        }

        if ( ! empty( $missing ) ) {
            return Diagnostic_Result::warn(
                'c2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated index names. */
                    __( 'Missing: %s. The index was requested during setup but the database did not create it, so the affected queries scan whole tables and get slower as the site grows.', 'easy-mcp-ai' ),
                    implode( ', ', $missing )
                ),
                __( 'Deactivate and reactivate the plugin to retry the index creation.', 'easy-mcp-ai' ),
                array( 'missing_indexes' => $missing )
            );
        }

        return Diagnostic_Result::pass( 'c2', Diagnostic_Result::TIER_WARNING, $label, '', array( 'missing_indexes' => array() ) );
    }

    







    public static function evaluate_change_log_index( $present, $db_describe, $reason = self::REASON_NO_DATABASE ) {
        $label = __( 'Change history lookup index', 'easy-mcp-ai' );

        if ( null === $present ) {
            return Diagnostic_Result::unknown( 'c3', Diagnostic_Result::TIER_WARNING, $label, self::abstention_reason( $reason ) );
        }

        if ( ! $present ) {
            return Diagnostic_Result::warn(
                'c3',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: database engine and version, e.g. "MariaDB 11.4". */
                    __( 'The change history table has no lookup index on this database (%s). Some engines reject the index for exceeding their maximum key length; history still records correctly, but looking up an object\'s history scans the whole table.', 'easy-mcp-ai' ),
                    (string) $db_describe
                ),
                __( 'Deactivate and reactivate the plugin to retry. If it does not return, report the database version above — the index length may need adjusting for your engine.', 'easy-mcp-ai' ),
                array( 'change_log_index' => false, 'database' => (string) $db_describe )
            );
        }

        return Diagnostic_Result::pass( 'c3', Diagnostic_Result::TIER_WARNING, $label, '', array( 'change_log_index' => true ) );
    }

    




    public static function evaluate_schema_versions( $versions ) {
        $label  = __( 'Database migrations complete', 'easy-mcp-ai' );

        
        
        if ( ! is_array( $versions ) || empty( $versions ) ) {
            return Diagnostic_Result::unknown( 'c4', Diagnostic_Result::TIER_WARNING, $label, __( 'Could not read the recorded database versions.', 'easy-mcp-ai' ) );
        }

        $behind = array();

        foreach ( $versions as $option => $pair ) {
            $stored = isset( $pair['stored'] ) ? (string) $pair['stored'] : '';
            $code   = isset( $pair['code'] ) ? (string) $pair['code'] : '';
            if ( '' === $code ) {
                continue;
            }
            
            
            
            if ( version_compare( '' === $stored ? '0' : $stored, $code, '<' ) ) {
                $behind[] = sprintf( '%s (%s < %s)', $option, '' === $stored ? '—' : $stored, $code );
            }
        }

        if ( ! empty( $behind ) ) {
            return Diagnostic_Result::warn(
                'c4',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated "option (stored < code)" entries. */
                    __( 'A database upgrade started but did not finish: %s. The plugin retries on each admin page load, so a version that never advances means the change is being refused.', 'easy-mcp-ai' ),
                    implode( ', ', $behind )
                ),
                __( 'Deactivate and reactivate the plugin. If the version still does not advance, check the database privileges below.', 'easy-mcp-ai' ),
                array( 'behind' => $behind )
            );
        }

        return Diagnostic_Result::pass( 'c4', Diagnostic_Result::TIER_WARNING, $label, '', array( 'behind' => array() ) );
    }

    





    public static function evaluate_privileges( $grants ) {
        $label = __( 'Database user can change the schema', 'easy-mcp-ai' );

        if ( null === $grants ) {
            return Diagnostic_Result::unknown(
                'c5',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Could not read the database privileges. Many managed hosts do not permit this; it is not a fault.', 'easy-mcp-ai' )
            );
        }

        
        
        
        
        
        $upper = strtoupper( self::privilege_lists( (string) $grants ) );
        if ( false !== strpos( $upper, 'ALL PRIVILEGES' ) ) {
            return Diagnostic_Result::pass( 'c5', Diagnostic_Result::TIER_INFO, $label, __( 'Full privileges.', 'easy-mcp-ai' ), array( 'schema_privileges' => true ) );
        }

        
        
        $granted = array_map( 'trim', explode( ',', $upper ) );
        $missing = array();
        foreach ( array( 'CREATE', 'ALTER', 'INDEX' ) as $needed ) {
            if ( ! in_array( $needed, $granted, true ) ) {
                $missing[] = $needed;
            }
        }

        if ( ! empty( $missing ) ) {
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            return Diagnostic_Result::pass(
                'c5',
                Diagnostic_Result::TIER_INFO,
                __( 'Database schema privileges', 'easy-mcp-ai' ),
                sprintf(
                    /* translators: %s: comma-separated SQL privilege names. */
                    __( 'The database user appears to lack: %s. If tables or indexes above are reported missing, this is why reactivating does not repair them.', 'easy-mcp-ai' ),
                    implode( ', ', $missing )
                ),
                array( 'schema_privileges' => false, 'missing_privileges' => $missing )
            );
        }

        return Diagnostic_Result::pass( 'c5', Diagnostic_Result::TIER_INFO, $label, '', array( 'schema_privileges' => true ) );
    }

    

    private static function wpdb( $method = 'get_var' ) {
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, $method ) ) {
            return null;
        }
        return $wpdb;
    }

    










    private static function missing_tables() {
        $wpdb = self::wpdb();
        if ( null === $wpdb ) {
            return null;
        }

        $missing = array();
        foreach ( self::TABLES as $suffix ) {
            $table = $wpdb->prefix . $suffix;

            if ( property_exists( $wpdb, 'last_error' ) ) {
                $wpdb->last_error = '';
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; SHOW TABLES cannot take a placeholder for the name.
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ( null === $found && property_exists( $wpdb, 'last_error' ) && '' !== (string) $wpdb->last_error ) {
                return null;
            }

            if ( empty( $found ) ) {
                $missing[] = $suffix;
            }
        }

        return $missing;
    }

    













    private static function missing_indexes() {
        if ( null === self::wpdb() || ! class_exists( '\Easy_MCP_AI\OAuth\OAuth_Schema' ) ) {
            return null;
        }

        global $wpdb;
        $missing = array();
        foreach ( \Easy_MCP_AI\OAuth\OAuth_Schema::REQUIRED_INDEXES as $suffix => $indexes ) {
            foreach ( $indexes as $index ) {
                $table   = $wpdb->prefix . 'easy_mcp_ai_oauth_' . $suffix;
                $present = self::index_probe( $table, $index );

                if ( null === $present ) {
                    return null; 
                }
                if ( ! $present ) {
                    $missing[] = 'oauth_' . $suffix . '.' . $index;
                }
            }
        }

        return $missing;
    }

    
    private static function change_log_index_present() {
        $wpdb = self::wpdb();
        if ( null === $wpdb ) {
            return null;
        }
        
        
        return self::index_probe( $wpdb->prefix . 'easy_mcp_ai_change_log', self::CHANGE_LOG_INDEX );
    }

    private static function db_description() {
        $wpdb = self::wpdb( 'db_version' );
        if ( null === $wpdb ) {
            return __( 'unknown database', 'easy-mcp-ai' );
        }
        $server = method_exists( $wpdb, 'db_server_info' ) ? (string) $wpdb->db_server_info() : '';
        $engine = ( '' !== $server && false !== stripos( $server, 'mariadb' ) ) ? 'MariaDB' : 'MySQL';
        return trim( $engine . ' ' . (string) $wpdb->db_version() );
    }

    







    private static function schema_versions() {
        if ( null === self::wpdb() ) {
            return null;
        }

        $versions = array();

        if ( class_exists( '\Easy_MCP_AI\OAuth\OAuth_Schema' ) ) {
            $versions['easy_mcp_ai_oauth_db_version'] = array(
                'stored' => (string) \get_option( 'easy_mcp_ai_oauth_db_version', '' ),
                'code'   => (string) \Easy_MCP_AI\OAuth\OAuth_Schema::DB_VERSION,
            );
        }
        if ( class_exists( '\Easy_MCP_AI\History\Change_Log_Schema' ) ) {
            $versions['easy_mcp_ai_change_log_db_version'] = array(
                'stored' => (string) \get_option( 'easy_mcp_ai_change_log_db_version', '' ),
                'code'   => (string) \Easy_MCP_AI\History\Change_Log_Schema::DB_VERSION,
            );
        }

        return $versions;
    }

    






    private static function privilege_lists( $grants ) {
        $lists = array();

        foreach ( preg_split( '/\r\n|\r|\n/', (string) $grants ) as $line ) {
            if ( preg_match( '/^\s*GRANT\s+(.*?)\s+ON\s/i', $line, $m ) ) {
                $lists[] = $m[1];
            }
        }

        return implode( ',', $lists );
    }

    
    private static function read_grants() {
        $wpdb = self::wpdb( 'get_col' );
        if ( null === $wpdb ) {
            return null;
        }

        $had_errors = isset( $wpdb->suppress_errors ) ? $wpdb->suppress_errors : null;
        if ( method_exists( $wpdb, 'suppress_errors' ) ) {
            $wpdb->suppress_errors( true );
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Privilege introspection; no table involved and nothing to cache.
        $rows = $wpdb->get_col( 'SHOW GRANTS FOR CURRENT_USER()' );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ( method_exists( $wpdb, 'suppress_errors' ) && null !== $had_errors ) {
            $wpdb->suppress_errors( $had_errors );
        }

        if ( empty( $rows ) || ! is_array( $rows ) ) {
            return null;
        }

        return implode( "\n", $rows );
    }
}
