<?php
namespace Easy_MCP_AI\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










class Approval_Schema {
    








    const DB_VERSION  = '1.1.0';

    
    const REQUIRED_COLUMNS = array( 'client_protocol', 'client_capabilities' );
    const OPTION_NAME = 'easy_mcp_ai_approvals_db_version';
    const TABLE       = 'easy_mcp_ai_approvals';

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
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
            return;
        }
        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();
        
        
        
        $sql = "CREATE TABLE {$table} (
            id                bigint(20)   unsigned NOT NULL AUTO_INCREMENT,
            approval_id       varchar(36)  NOT NULL,
            auth_source       varchar(16)  NOT NULL DEFAULT 'legacy',
            token_id          bigint(20)   unsigned NOT NULL DEFAULT 0,
            oauth_client_id   varchar(191) DEFAULT NULL,
            wp_user_id        bigint(20)   unsigned NOT NULL DEFAULT 0,
            tool_name         varchar(255) NOT NULL,
            client_protocol   varchar(16)  DEFAULT NULL,
            client_capabilities text       DEFAULT NULL,
            arguments         longtext     DEFAULT NULL,
            args_hash         varchar(64)  NOT NULL,
            preview           text         DEFAULT NULL,
            preview_hash      varchar(64)  DEFAULT NULL,
            status            varchar(16)  NOT NULL DEFAULT 'pending',
            card_secret_hash  varchar(64)  DEFAULT NULL,
            decided_by        bigint(20)   unsigned DEFAULT NULL,
            decided_at        datetime     DEFAULT NULL,
            decision_via      varchar(16)  DEFAULT NULL,
            audit_id          bigint(20)   unsigned DEFAULT NULL,
            executed_audit_id bigint(20)   unsigned DEFAULT NULL,
            result            longtext     DEFAULT NULL,
            signature         varchar(64)  DEFAULT NULL,
            created_at        datetime     NOT NULL,
            expires_at        datetime     NOT NULL,
            consumed_at       datetime     DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY approval_id (approval_id),
            KEY status_expires (status, expires_at),
            KEY lookup (auth_source, token_id, args_hash),
            KEY wp_user_id (wp_user_id, created_at)
        ) {$charset_collate};";

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        \dbDelta( $sql );

        if ( ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return;
        }
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
