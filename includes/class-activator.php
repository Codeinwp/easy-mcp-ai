<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate( $network_wide = false, $flush_rewrite = true ) {
        if ( \is_multisite() && $network_wide ) {
            
            $sites = \get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
            foreach ( $sites as $blog_id ) {
                \switch_to_blog( $blog_id );
                try {
                    self::create_tables();
                    self::create_oauth_tables();
                    self::create_change_log_tables();
                    self::create_tasks_tables();
                    self::create_approvals_tables();
                    self::set_default_options();
                    self::mark_setup_complete();
                } finally {
                    \restore_current_blog();
                }
            }
        } else {
            self::create_tables();
            self::create_oauth_tables();
            self::create_change_log_tables();
            self::create_tasks_tables();
            self::create_approvals_tables();
            self::set_default_options();
            self::mark_setup_complete();
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_audit_log' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_audit_log' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_oauth' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_oauth' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_new_token_meta' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_new_token_meta' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_change_log' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_change_log' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_tasks' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_tasks' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_approvals' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_approvals' );
        }
        
        
        
        if ( $flush_rewrite ) {
            \flush_rewrite_rules();
            
            
            if ( ! $network_wide ) {
                Admin\Setup_State::arm_activation_redirect();
            }
        }
    }

    



    public static function maybe_upgrade() {
        
        
        
        
        
        $is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
        $is_cli  = defined( 'WP_CLI' ) && WP_CLI;
        if ( ! \is_admin() && ! $is_rest && ! $is_cli && ! \wp_doing_cron() ) {
            return;
        }
        self::maybe_upgrade_core_tables();
        
        
        self::maybe_upgrade_oauth_tables();
        self::maybe_upgrade_change_log_tables();
        self::maybe_upgrade_tasks_tables();
        self::maybe_upgrade_approvals_tables();
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_audit_log' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_audit_log' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_oauth' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_oauth' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_new_token_meta' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_new_token_meta' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_change_log' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_change_log' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_tasks' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_tasks' );
        }
        if ( ! \wp_next_scheduled( 'easy_mcp_ai_cleanup_approvals' ) ) {
            \wp_schedule_event( time(), 'daily', 'easy_mcp_ai_cleanup_approvals' );
        }
    }

    












    const AUDIT_REQUIRED_COLUMNS = array( 'auth_source', 'wp_user_id', 'oauth_client_id', 'duration_ms', 'approval_id', 'approved_by', 'approved_via', 'approved_at', 'error_message' );

    









    const AUDIT_REQUIRED_INDEXES = array( 'user_time', 'source_time' );

    











    const TOKENS_REQUIRED_COLUMNS = array( 'created_by', 'site_host_hash', 'unattended' );

    













    public static function maybe_upgrade_core_tables() {
        if ( \get_option( 'easy_mcp_ai_db_version' ) !== EASY_MCP_AI_VERSION ) {
            
            self::mark_setup_complete();
            self::create_tables();
        }
    }

    





    private static function mark_setup_complete() {
        Admin\Setup_State::mark_complete();
    }

    




    private static function column_exists( $table, $column ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return true;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off existence check on a plugin-owned table.
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
        return ! empty( $found );
    }

    



    private static function index_exists( $table, $index_name ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return true;
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off existence check on a plugin-owned table; SHOW INDEX cannot take a placeholder for the table.
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name ) );
        return ! empty( $found );
    }

    private static function create_tables() {
        global $wpdb;
        
        
        
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_charset_collate' ) ) {
            return;
        }
        $charset_collate = $wpdb->get_charset_collate();
        $tokens_table = $wpdb->prefix . 'easy_mcp_ai_tokens';
        $audit_table  = $wpdb->prefix . 'easy_mcp_ai_audit_log';

        
        
        
        
        $sql = "CREATE TABLE {$tokens_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            token_hash varchar(64) NOT NULL,
            token_prefix varchar(14) NOT NULL,
            allowed_tools longtext NOT NULL,
            wp_user_id bigint(20) unsigned NOT NULL,
            created_by bigint(20) unsigned DEFAULT NULL,
            site_host_hash varchar(64) DEFAULT NULL,
            last_used_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            unattended tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY wp_user_id (wp_user_id),
            KEY is_active (is_active)
        ) {$charset_collate};

        CREATE TABLE {$audit_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_id bigint(20) unsigned NOT NULL,
            tool_name varchar(255) NOT NULL,
            arguments longtext DEFAULT NULL,
            result_status varchar(20) NOT NULL DEFAULT 'success',
            ip_address varchar(45) DEFAULT NULL,
            auth_source varchar(16) DEFAULT NULL,
            wp_user_id bigint(20) unsigned DEFAULT NULL,
            oauth_client_id varchar(191) DEFAULT NULL,
            duration_ms int unsigned DEFAULT NULL,
            approval_id varchar(36) DEFAULT NULL,
            approved_by bigint(20) unsigned DEFAULT NULL,
            approved_via varchar(16) DEFAULT NULL,
            approved_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY token_id (token_id),
            KEY tool_name (tool_name(150)),
            KEY created_at (created_at),
            KEY user_time (wp_user_id, created_at),
            KEY source_time (auth_source, created_at)
        ) {$charset_collate};";

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        \dbDelta( $sql );

        
        
        
        
        
        
        
        
        if ( ! method_exists( $wpdb, 'esc_like' ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return; 
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off existence check on plugin-owned tables.
        $tokens_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $tokens_table ) ) ) === $tokens_table;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off existence check on plugin-owned tables.
        $audit_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $audit_table ) ) ) === $audit_table;
        if ( ! $tokens_exists || ! $audit_exists ) {
            return;
        }
        
        
        
        
        
        
        
        
        
        foreach ( self::TOKENS_REQUIRED_COLUMNS as $column ) {
            if ( ! self::column_exists( $tokens_table, $column ) ) { return; }
        }
        
        
        
        
        if ( ! self::backfill_token_site_host( $tokens_table ) ) {
            return;
        }
        
        
        
        
        
        foreach ( self::AUDIT_REQUIRED_COLUMNS as $column ) {
            if ( ! self::column_exists( $audit_table, $column ) ) {
                return;
            }
        }
        
        
        foreach ( self::AUDIT_REQUIRED_INDEXES as $index_name ) {
            if ( ! self::index_exists( $audit_table, $index_name ) ) {
                return;
            }
        }
        \update_option( 'easy_mcp_ai_db_version', EASY_MCP_AI_VERSION );
    }

    













    private static function backfill_token_site_host( $tokens_table ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return true; 
        }
        $host_hash = \Easy_MCP_AI\Site_Host::current_hash();
        if ( '' === $host_hash ) {
            return false; 
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- one-off backfill on a plugin-owned table; the table name is plugin-controlled.
        $result = $wpdb->query( $wpdb->prepare( "UPDATE `{$tokens_table}` SET site_host_hash = %s WHERE site_host_hash IS NULL", $host_hash ) );
        return false !== $result;
    }

    



    private static function create_oauth_tables() {
        
        
        
        
        if ( class_exists( OAuth\OAuth_Site_Move::class ) ) {
            OAuth\OAuth_Site_Move::ensure_recorded();
        }
        if ( class_exists( \Easy_MCP_AI\OAuth\OAuth_Schema::class ) ) {
            \Easy_MCP_AI\OAuth\OAuth_Schema::create_tables();
        }
    }

    



    private static function maybe_upgrade_oauth_tables() {
        if ( class_exists( \Easy_MCP_AI\OAuth\OAuth_Schema::class ) ) {
            \Easy_MCP_AI\OAuth\OAuth_Schema::maybe_upgrade();
        }
    }

    



    private static function create_change_log_tables() {
        if ( ! class_exists( \Easy_MCP_AI\History\Change_Log_Schema::class ) ) {
            return;
        }

        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        \Easy_MCP_AI\History\Change_Log_Schema::maybe_upgrade();
        \Easy_MCP_AI\History\Change_Log_Schema::create_tables();

        
        
        \add_option( 'easy_mcp_ai_change_log_retention', 30 );
        \add_option( 'easy_mcp_ai_change_log_enabled', true );

        self::ensure_view_all_history_cap();
    }

    









    private static function ensure_view_all_history_cap() {
        $admin_role = \get_role( 'administrator' );
        if ( $admin_role && ! $admin_role->has_cap( 'easy_mcp_ai_view_all_history' ) ) {
            $admin_role->add_cap( 'easy_mcp_ai_view_all_history' );
        }
    }

    




    private static function create_tasks_tables() {
        if ( ! class_exists( \Easy_MCP_AI\Tasks\Task_Schema::class ) ) {
            return;
        }
        \Easy_MCP_AI\Tasks\Task_Schema::create_tables();
    }

    private static function maybe_upgrade_tasks_tables() {
        if ( ! class_exists( \Easy_MCP_AI\Tasks\Task_Schema::class ) ) {
            return;
        }
        \Easy_MCP_AI\Tasks\Task_Schema::maybe_upgrade();
    }

    private static function create_approvals_tables() {
        if ( ! class_exists( \Easy_MCP_AI\Approvals\Approval_Schema::class ) ) {
            return;
        }
        \Easy_MCP_AI\Approvals\Approval_Schema::create_tables();
    }

    private static function maybe_upgrade_approvals_tables() {
        if ( ! class_exists( \Easy_MCP_AI\Approvals\Approval_Schema::class ) ) {
            return;
        }
        \Easy_MCP_AI\Approvals\Approval_Schema::maybe_upgrade();
    }

    private static function maybe_upgrade_change_log_tables() {
        if ( ! class_exists( \Easy_MCP_AI\History\Change_Log_Schema::class ) ) {
            return;
        }
        \Easy_MCP_AI\History\Change_Log_Schema::maybe_upgrade();
        self::ensure_view_all_history_cap();
    }

    private static function set_default_options() {
        $defaults = array(
            'rate_limit_per_minute'  => 60,
            'audit_log_retention'    => 30,
            'ip_whitelist'           => '',
            'enabled_categories'     => array( 'posts', 'pages', 'media', 'taxonomy', 'comments', 'users', 'site', 'menus', 'plugins', 'themes' ),
            'allowed_tool_patterns'  => array(),
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            'disabled_tools'         => array(
                'wp_update_site_settings',
                'wp_create_user',
                'wp_update_user',
                'wp_delete_user',
                'wp_update_user_meta',
                'wp_update_template',
                'wp_update_global_styles',
                'wp_delete_user_meta',
                
                'wp_run_cron_event',
                
                
                'wp_activate_plugin',
                'wp_deactivate_plugin',
                'wp_update_plugin',
                'wp_switch_theme',
                'wp_update_theme',
                
                
                'wp_update_theme_mod',
                'wp_delete_theme_mod',
                'wp_update_custom_css',
            ),
            'enabled_abilities'      => array(),
            'enabled_hooks'          => array(),
        );
        
        
        
        $fresh_abilities = ( false === \get_option( 'easy_mcp_ai_enabled_abilities' ) );
        foreach ( $defaults as $key => $value ) {
            if ( false === \get_option( 'easy_mcp_ai_' . $key ) ) {
                \add_option( 'easy_mcp_ai_' . $key, $value );
            }
        }
        if ( $fresh_abilities ) {
            Ability_Seed::mark_pending();
        }
    }
}
