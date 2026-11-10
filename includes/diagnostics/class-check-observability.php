<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Observability {

    const FAILURE_WINDOW_HOURS = 24;

    const MAX_TOKENS_LISTED = 20;

    const MAX_OBSERVATIONS_LISTED = 5;

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
            self::evaluate_call_observations(
                self::call_observations(),
                (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true ),
                (bool) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_capture_db', false )
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
                __( '%1$d sign-in refusal(s) were recorded in the last %2$d hours: a token that was missing, expired or not recognised; a real credential refused by a site rule (issued for another site address, sent from an address outside the IP whitelist, or an API key sent outside the Authorization header on a header-only site); or a failed step of an OAuth authorization or device sign-in, including too many device-code attempts. This counts sign-in refusals only. Tool calls refused after sign-in for insufficient permission are recorded separately, under Easy MCP AI → Activity → Audit log, and are not counted here; MCP requests refused for exceeding the rate limit are not recorded at all.', 'easy-mcp-ai' ),
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

    public static function evaluate_call_observations( $inspected, $history_on = true, $capture_db = false ) {
        $label = __( 'Tool call observations', 'easy-mcp-ai' );

        if ( ! is_array( $inspected ) || ! isset( $inspected['state'], $inspected['log']['counts'], $inspected['log']['recent'] )
            || ! is_array( $inspected['log']['counts'] ) || ! is_array( $inspected['log']['recent'] ) ) {
            return Diagnostic_Result::unknown( 'h5', Diagnostic_Result::TIER_INFO, $label, __( 'Could not read the record of tool calls.', 'easy-mcp-ai' ) );
        }
        if ( 'malformed' === $inspected['state'] ) {
            return Diagnostic_Result::unknown(
                'h5',
                Diagnostic_Result::TIER_INFO,
                $label,
                __( 'The record of tool calls holds a value this plugin did not write, so it is read as empty. The next observation starts it again.', 'easy-mcp-ai' ),
                array( 'state' => 'malformed' )
            );
        }

        $log   = $inspected['log'];
        $off   = isset( $log['counts']['off_schema'] ) ? (int) $log['counts']['off_schema'] : 0;
        $wrote = isset( $log['counts']['wrote_then_refused'] ) ? (int) $log['counts']['wrote_then_refused'] : 0;
        $since = isset( $log['since'] ) ? (int) $log['since'] : 0;

        $recent = array();
        $named  = array();
        foreach ( array_reverse( array_slice( $log['recent'], -self::MAX_OBSERVATIONS_LISTED ) ) as $entry ) {
            if ( ! is_array( $entry ) || ! isset( $entry['kind'], $entry['tool'] ) ) {
                continue;
            }
            $tool = (string) $entry['tool'];
            if ( 'off_schema' === $entry['kind'] ) {
                $names    = array_map( 'strval', array_column( isset( $entry['arguments'] ) && is_array( $entry['arguments'] ) ? $entry['arguments'] : array(), 'name' ) );
                $recent[] = array(
                    'kind'      => 'off_schema',
                    'tool'      => $tool,
                    'time'      => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
                    'arguments' => $names,
                );
                /* translators: 1: MCP tool name, 2: comma-separated argument names. */
                $named[] = sprintf( __( '%1$s sent %2$s', 'easy-mcp-ai' ), $tool, implode( ', ', $names ) );
            } else {
                $rows     = isset( $entry['rows'] ) ? (int) $entry['rows'] : 0;
                $recent[] = array(
                    'kind' => 'wrote_then_refused',
                    'tool' => $tool,
                    'time' => isset( $entry['time'] ) ? (int) $entry['time'] : 0,
                    'rows' => $rows,
                );
                /* translators: 1: MCP tool name, 2: number of change-history rows. */
                $named[] = sprintf( __( '%1$s ended in an error after %2$d change-history row(s)', 'easy-mcp-ai' ), $tool, $rows );
            }
        }

        $sentences = array();
        if ( 0 === $off && 0 === $wrote ) {
            $sentences[] = __( 'Nothing recorded yet. Each tool call\'s arguments are compared with the types and values its tool declares, and a call that ends in an error after it changed the site is noted. Calls are answered exactly as before: this is a record, and refuses nothing.', 'easy-mcp-ai' );
        } else {
            $sentences[] = sprintf(
                /* translators: 1: date the record starts, 2: number of calls with an off-schema argument, 3: number of refused calls that changed the site. */
                __( 'Since %1$s: %2$d call(s) sent an argument outside the types or values its tool declares, and %3$d call(s) ended in an error after they had changed the site.', 'easy-mcp-ai' ),
                $since > 0 ? \wp_date( \get_option( 'date_format', 'Y-m-d' ), $since ) : __( 'the record began', 'easy-mcp-ai' ),
                $off,
                $wrote
            );
            if ( array() !== $named ) {
                /* translators: %s: the most recent calls, separated by semicolons. */
                $sentences[] = sprintf( __( 'Latest: %s.', 'easy-mcp-ai' ), implode( '; ', $named ) );
            }
            $sentences[] = __( 'These calls were answered as before; nothing is refused because of this record.', 'easy-mcp-ai' );
            if ( $wrote > 0 ) {
                $sentences[] = __( 'What a refused call changed is listed under Easy MCP AI → Activity → Change history, with its audit row.', 'easy-mcp-ai' );
            }
        }
        if ( ! $history_on ) {
            $sentences[] = __( 'Change history is off, so a call that changed the site and then failed cannot be noticed.', 'easy-mcp-ai' );
        } elseif ( ! $capture_db ) {
            $sentences[] = __( 'Changes to other plugins\' own database tables are noticed only while database capture is on.', 'easy-mcp-ai' );
        }

        return Diagnostic_Result::pass(
            'h5',
            Diagnostic_Result::TIER_INFO,
            $label,
            implode( ' ', $sentences ),
            array(
                'state'          => (string) $inspected['state'],
                'since'          => $since > 0 ? $since : null,
                'counts'         => array(
                    'off_schema'         => $off,
                    'wrote_then_refused' => $wrote,
                ),
                'recent'         => $recent,
                'change_history' => (bool) $history_on,
                'capture_db'     => (bool) $capture_db,
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

    private static function call_observations() {
        try {
            return \Easy_MCP_AI\MCP\Call_Observations::inspect();
        } catch ( \Throwable $e ) {
            return null;
        }
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
