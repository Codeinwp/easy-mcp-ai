<?php










namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Setup_State {

    const OPTION = 'easy_mcp_ai_setup_complete';

    
    const REDIRECT_TRANSIENT = 'easy_mcp_ai_setup_redirect';

    







    public static function is_complete() {
        return in_array( \get_option( self::OPTION, false ), array( true, 1, '1', 'true' ), true );
    }

    




    public static function mark_complete() {
        \update_option( self::OPTION, 1 );
    }

    








    public static function mark_complete_if_in_use() {
        global $wpdb;
        if ( self::is_complete() || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return false;
        }
        $prefix  = $wpdb->prefix . 'easy_mcp_ai_';
        $queries = array(
            "SELECT 1 FROM `{$prefix}tokens` LIMIT 1",
            "SELECT 1 FROM `{$prefix}oauth_consents` LIMIT 1",
            
            "SELECT 1 FROM `{$prefix}audit_log` WHERE tool_name NOT LIKE '\\_%' AND result_status IN ('success', 'error') LIMIT 1",
        );
        $suppressed = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
        $in_use     = false;
        foreach ( $queries as $sql ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- plugin-owned tables, no user input.
            if ( null !== $wpdb->get_var( $sql ) ) {
                $in_use = true;
                break;
            }
        }
        if ( null !== $suppressed ) {
            $wpdb->suppress_errors( $suppressed );
        }
        if ( $in_use ) {
            self::mark_complete();
        }
        return $in_use;
    }

    






    public static function arm_activation_redirect() {
        \set_transient( self::REDIRECT_TRANSIENT, 1, 60 );
    }
}
