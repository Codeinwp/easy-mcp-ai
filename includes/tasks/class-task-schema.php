<?php
namespace Easy_MCP_AI\Tasks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Task_Schema {
    









    const DB_VERSION  = '1.2.0';

    
    const REQUIRED_COLUMNS = array( 'signature', 'approval_id' );
    const OPTION_NAME = 'easy_mcp_ai_tasks_db_version';
    const TABLE       = 'easy_mcp_ai_tasks';

    public static function maybe_upgrade() {
        $installed = (string) \get_option( self::OPTION_NAME, '0' );
        if ( \version_compare( $installed, self::DB_VERSION, '>=' ) ) {
            return;
        }
        self::create_tables();
    }

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function create_tables() {
        global $wpdb;
        $table           = self::table_name();
        $charset_collate = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
        $sql = "CREATE TABLE {$table} (
            id               bigint(20)   unsigned NOT NULL AUTO_INCREMENT,
            task_id          varchar(36)  NOT NULL,
            auth_source      varchar(16)  NOT NULL DEFAULT 'legacy',
            token_id         bigint(20)   unsigned NOT NULL DEFAULT 0,
            oauth_client_id  varchar(191) DEFAULT NULL,
            wp_user_id       bigint(20)   unsigned NOT NULL DEFAULT 0,
            tool_name        varchar(255) NOT NULL,
            arguments        longtext     DEFAULT NULL,
            approval_id      varchar(36)  DEFAULT NULL,
            mode             varchar(16)  NOT NULL DEFAULT 'cursor',
            phase            varchar(16)  NOT NULL DEFAULT 'cursor',
            status           varchar(16)  NOT NULL DEFAULT 'working',
            status_message   text         DEFAULT NULL,
            progress_current bigint(20)   unsigned NOT NULL DEFAULT 0,
            progress_total   bigint(20)   unsigned NOT NULL DEFAULT 0,
            task_cursor      longtext     DEFAULT NULL,
            job_id           text         DEFAULT NULL,
            result           longtext     DEFAULT NULL,
            error            text         DEFAULT NULL,
            signature        varchar(64)  DEFAULT NULL,
            ticks            int(10)      unsigned NOT NULL DEFAULT 0,
            lock_token       varchar(32)  DEFAULT NULL,
            locked_until     datetime     DEFAULT NULL,
            created_at       datetime     NOT NULL,
            updated_at       datetime     NOT NULL,
            completed_at     datetime     DEFAULT NULL,
            expires_at       datetime     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY task_id (task_id),
            KEY status_updated (status, updated_at),
            KEY expires_at (expires_at),
            KEY wp_user_id (wp_user_id, created_at)
        ) {$charset_collate};";

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        \dbDelta( $sql );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table existence check.
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( $exists !== $table ) {
            return;
        }
        
        
        foreach ( self::REQUIRED_COLUMNS as $column ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; the name is prefix + a constant.
            if ( $column !== $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) ) ) {
                return;
            }
        }
        \update_option( self::OPTION_NAME, self::DB_VERSION );
    }
}
