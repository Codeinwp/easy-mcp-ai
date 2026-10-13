<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Deactivator {
    public static function deactivate() {
        global $wpdb;
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_session_%', '_transient_timeout_easy_mcp_ai_session_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on deactivation.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_rate_%', '_transient_timeout_easy_mcp_ai_rate_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on deactivation.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_new_token_%', '_transient_timeout_easy_mcp_ai_new_token_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on deactivation.
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_audit_log' );
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_oauth' );
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_new_token_meta' );
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_change_log' );
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_tasks' );
        \wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_approvals' );
        
        
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-config.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/tasks/class-task-scheduler.php';
        \Easy_MCP_AI\Tasks\Task_Scheduler::clear_all();
        \flush_rewrite_rules();
    }
}
