<?php

































namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Observability {

    
    const FAILURE_WINDOW_HOURS = 24;

    
    const MAX_TOKENS_LISTED = 20;

    


    public static function run() {
        $audit_enabled = (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true );

        return array(
            self::evaluate_auth_failures( self::count_auth_failures(), $audit_enabled ),
            self::evaluate_last_used( self::token_last_used() ),
            self::evaluate_audit_log( $audit_enabled ),
            
            
            self::evaluate_table_growth(
                self::table_counts(),
                (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_retention', 30 ),
                (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_retention', 30 )
            ),
        );
    }

    





    public static function evaluate_auth_failures( $count, $log_enabled = true ) {
        $label = __( 'Recent sign-in failures', 'easy-mcp-ai' );

        if ( null === $count ) {
            return Diagnostic_Result::unknown( 'h1', Diagnostic_Result::TIER_INFO, $label, __( 'Could not read the activity log.', 'easy-mcp-ai' ) );
        }

        
        
        
        
        
        if ( ! $log_enabled ) {
            return Diagnostic_Result::unknown(
                'h1',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Activity logging is off, so refused sign-ins are not recorded and there is nothing to count. This is not a sign that any AI client succeeded or failed.', 'easy-mcp-ai' )
            );
        }

        if ( 0 === (int) $count ) {
            return Diagnostic_Result::pass(
                'h1',
                Diagnostic_Result::TIER_INFO,
                $label,
                sprintf(
                    /* translators: %d: number of hours in the reporting window. */
                    __( 'No AI client was refused a token in the last %d hours.', 'easy-mcp-ai' ),
                    self::FAILURE_WINDOW_HOURS
                ),
                array( 'auth_failures_24h' => 0 )
            );
        }

        return Diagnostic_Result::pass(
            'h1',
            Diagnostic_Result::TIER_INFO,
            $label,
            sprintf(
                /* translators: 1: number of rejected requests, 2: number of hours in the window. */
                __( '%1$d request(s) were refused in the last %2$d hours because the token was missing, expired or not recognised. This counts failed authentication only. Requests refused for insufficient permission are recorded separately, under Easy MCP AI → Activity → Audit log, and are not counted here; requests refused for exceeding the rate limit are not recorded at all.', 'easy-mcp-ai' ),
                (int) $count,
                self::FAILURE_WINDOW_HOURS
            ),
            array( 'auth_failures_24h' => (int) $count )
        );
    }

    




    public static function evaluate_last_used( $tokens ) {
        $label = __( 'When each API token last worked', 'easy-mcp-ai' );

        if ( ! is_array( $tokens ) ) {
            return Diagnostic_Result::unknown( 'h2', Diagnostic_Result::TIER_INFO, $label, __( 'Could not read the API tokens.', 'easy-mcp-ai' ) );
        }

        if ( empty( $tokens ) ) {
            return Diagnostic_Result::pass( 'h2', Diagnostic_Result::TIER_INFO, $label, __( 'No active API tokens.', 'easy-mcp-ai' ), array( 'tokens' => array() ) );
        }

        $parts = array();
        foreach ( array_slice( $tokens, 0, self::MAX_TOKENS_LISTED ) as $token ) {
            $name = isset( $token['name'] ) && '' !== $token['name'] ? $token['name'] : __( 'unnamed', 'easy-mcp-ai' );
            $days = isset( $token['days_ago'] ) ? $token['days_ago'] : null;

            if ( null === $days ) {
                /* translators: %s: token name. */
                $parts[] = sprintf( __( '%s: never used', 'easy-mcp-ai' ), $name );
            } elseif ( 0 === (int) $days ) {
                /* translators: %s: token name. */
                $parts[] = sprintf( __( '%s: today', 'easy-mcp-ai' ), $name );
            } else {
                $parts[] = sprintf(
                    /* translators: 1: token name, 2: number of days since last use. */
                    __( '%1$s: %2$d day(s) ago', 'easy-mcp-ai' ),
                    $name,
                    (int) $days
                );
            }
        }

        if ( count( $tokens ) > self::MAX_TOKENS_LISTED ) {
            $parts[] = sprintf(
                /* translators: %d: number of tokens not listed. */
                __( 'and %d more', 'easy-mcp-ai' ),
                count( $tokens ) - self::MAX_TOKENS_LISTED
            );
        }

        return Diagnostic_Result::pass(
            'h2',
            Diagnostic_Result::TIER_INFO,
            $label,
            implode( '; ', $parts ),
            array( 'tokens' => $tokens )
        );
    }

    
    public static function evaluate_audit_log( $enabled ) {
        $label = __( 'Activity logging', 'easy-mcp-ai' );

        if ( ! $enabled ) {
            return Diagnostic_Result::pass(
                'h3',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'Off. Nothing is recorded about AI requests, so the two checks above have nothing to report and there will be no trail to inspect if a problem appears.', 'easy-mcp-ai' ),
                array( 'audit_log_enabled' => false )
            );
        }

        return Diagnostic_Result::pass( 'h3', Diagnostic_Result::TIER_INFO, $label, __( 'On.', 'easy-mcp-ai' ), array( 'audit_log_enabled' => true ) );
    }

    











    public static function evaluate_table_growth( $counts, $audit_retention_days, $change_retention_days = null ) {
        $label = __( 'Stored history size', 'easy-mcp-ai' );

        if ( null === $counts || ! is_array( $counts ) ) {
            return Diagnostic_Result::unknown( 'h4', Diagnostic_Result::TIER_INFO, $label, __( 'Could not read the table sizes.', 'easy-mcp-ai' ) );
        }

        $parts = array();
        foreach ( $counts as $table => $rows ) {
            $parts[] = sprintf( '%s: %s', $table, \number_format_i18n( (int) $rows ) );
        }

        $sentences = array( implode( ', ', $parts ) . '.' );

        $sentences[] = sprintf(
            /* translators: %d: audit log retention in days. */
            __( 'Activity log rows older than %d days are removed automatically.', 'easy-mcp-ai' ),
            (int) $audit_retention_days
        );

        if ( null !== $change_retention_days ) {
            $sentences[] = ( 0 === (int) $change_retention_days )
                ? __( 'Change history rows are never removed — its retention is set to 0, which disables the cleanup.', 'easy-mcp-ai' )
                : sprintf(
                    /* translators: %d: change log retention in days. */
                    __( 'Change history rows older than %d days are removed automatically.', 'easy-mcp-ai' ),
                    (int) $change_retention_days
                );
        }

        $sentences[] = __( 'If these keep growing, the scheduled cleanup is not running.', 'easy-mcp-ai' );

        return Diagnostic_Result::pass(
            'h4',
            Diagnostic_Result::TIER_INFO,
            $label,
            implode( ' ', $sentences ),
            array(
                'row_counts'            => $counts,
                'audit_retention_days'  => (int) $audit_retention_days,
                'change_retention_days' => ( null === $change_retention_days ) ? null : (int) $change_retention_days,
            )
        );
    }

    

    private static function wpdb( $method = 'get_var' ) {
        global $wpdb;
        if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, $method ) ) {
            return null;
        }
        return $wpdb;
    }

    
    private static function count_auth_failures() {
        $wpdb = self::wpdb();
        if ( null === $wpdb ) {
            return null;
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_audit_log';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; created_at is indexed, and diagnostics must read live state.
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}`
                 WHERE result_status = %s
                   AND created_at >= DATE_SUB( UTC_TIMESTAMP(), INTERVAL %d HOUR )",
                'auth_failure',
                self::FAILURE_WINDOW_HOURS
            )
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return null === $count ? null : (int) $count;
    }

    
    private static function token_last_used() {
        $wpdb = self::wpdb( 'get_results' );
        if ( null === $wpdb ) {
            return null; 
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_tokens';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; diagnostics must read live state.
        $rows = $wpdb->get_results(
            "SELECT name, last_used_at FROM `{$table}` WHERE is_active = 1 ORDER BY last_used_at DESC LIMIT 50",
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ( ! is_array( $rows ) ) {
            return null;
        }

        $out = array();
        foreach ( $rows as $row ) {
            $days = null;
            if ( ! empty( $row['last_used_at'] ) ) {
                $ts = strtotime( $row['last_used_at'] . ' UTC' );
                if ( false !== $ts ) {
                    $days = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
                }
            }
            $out[] = array(
                'name'     => isset( $row['name'] ) ? (string) $row['name'] : '',
                'days_ago' => $days,
            );
        }

        return $out;
    }

    













    private static function table_counts() {
        $wpdb = self::wpdb();
        if ( null === $wpdb ) {
            return null;
        }

        $counts = array();
        foreach ( array( 'audit_log', 'change_log' ) as $suffix ) {
            $table = $wpdb->prefix . 'easy_mcp_ai_' . $suffix;

            if ( property_exists( $wpdb, 'last_error' ) ) {
                $wpdb->last_error = '';
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; diagnostics must read live state.
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if ( null === $count && property_exists( $wpdb, 'last_error' ) && '' !== (string) $wpdb->last_error ) {
                return null;
            }

            $counts[ $suffix ] = (int) $count;
        }

        return $counts;
    }
}
