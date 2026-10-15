<?php






if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;


wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_audit_log' );
wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_oauth' );
wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_new_token_meta' );
wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_change_log' );
wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_tasks' );
wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_approvals' );
wp_clear_scheduled_hook( 'easy_mcp_ai_skills_sync' ); 
wp_unschedule_hook( 'easy_mcp_ai_task_tick' ); 
wp_unschedule_hook( 'easy_mcp_ai_search_replace_tick' ); 
if ( function_exists( 'as_unschedule_all_actions' ) && class_exists( 'ActionScheduler', false ) && ActionScheduler::is_initialized() ) {
    as_unschedule_all_actions( 'easy_mcp_ai_task_tick' ); 
}




require_once __DIR__ . '/includes/diagnostics/class-htaccess-auth-rule.php';
Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::remove( Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::htaccess_path() );

require_once __DIR__ . '/includes/diagnostics/class-htaccess-plain-routes.php';
Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::remove( Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::htaccess_path() );


$options = array( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
    'easy_mcp_ai_db_version',
    'easy_mcp_ai_token_schema_version',
    'easy_mcp_ai_self_service_keys',
    'easy_mcp_ai_self_service_max_keys',
    'easy_mcp_ai_self_service_max_expiry_days',
    'easy_mcp_ai_paused',                  
    'easy_mcp_ai_api_key_header_only',     
    'easy_mcp_ai_setup_complete',          
    'easy_mcp_ai_rate_limit_per_minute',
    'easy_mcp_ai_session_ttl_minutes',  
    'easy_mcp_ai_cors_origins',         
    'easy_mcp_ai_audit_log_retention',
    'easy_mcp_ai_enabled_categories',
    'easy_mcp_ai_ip_whitelist',
    'easy_mcp_ai_disabled_tools',
    'easy_mcp_ai_enabled_tools',            
    'easy_mcp_ai_rest_disabled_namespaces', 
    'easy_mcp_ai_force_draft_on_create',
    'easy_mcp_ai_max_title_length',
    'easy_mcp_ai_audit_log_enabled',
    'easy_mcp_ai_allowed_tool_patterns',
    'easy_mcp_ai_enabled_abilities',
    'easy_mcp_ai_abilities_seed_pending',   
    'easy_mcp_ai_enabled_hooks',
    'easy_mcp_ai_allowed_plugins',          
    'easy_mcp_ai_admin_language',
    'easy_mcp_ai_enabled_plugin_groups',    
    'easy_mcp_ai_learnpress_enable_pending', 
    'easy_mcp_ai_disabled_plugin_tools',   
    'easy_mcp_ai_oauth_db_version',        
    'easy_mcp_ai_oauth_access_token_ttl',  
    'easy_mcp_ai_oauth_refresh_token_ttl', 
    'easy_mcp_ai_oauth_dcr_enabled',       
    'easy_mcp_ai_oauth_max_clients',       
    'easy_mcp_ai_oauth_client_retention',  
    'easy_mcp_ai_oauth_site_host',         
    'easy_mcp_ai_gsc_service_account_json', 
    'easy_mcp_ai_gsc_default_site_url',     
    'easy_mcp_ai_disabled_gsc_tools',       
    'easy_mcp_ai_ga_service_account_json',  
    'easy_mcp_ai_ga_default_property_id',   
    'easy_mcp_ai_disabled_ga_tools',        
    'easy_mcp_ai_gsc_sites_cache',          
    'easy_mcp_ai_ga_properties_cache',      
    'easy_mcp_ai_dfs_login',                
    'easy_mcp_ai_dfs_api_password',         
    'easy_mcp_ai_disabled_dfs_tools',       
    'easy_mcp_ai_semrush_api_key',          
    'easy_mcp_ai_disabled_semrush_tools',   
    'easy_mcp_ai_seranking_api_key',        
    'easy_mcp_ai_disabled_seranking_tools', 
    'easy_mcp_ai_disabled_ahrefs_tools',    
    'easy_mcp_ai_ahrefs_enabled',           
    'easy_mcp_ai_ahrefs_api_key',           
    'easy_mcp_ai_external_data_balances',   
    'easy_mcp_ai_cache_site_purge_at',      
    'easy_mcp_ai_last_tool_call',           
    'easy_mcp_ai_change_log_db_version',    
    'easy_mcp_ai_tasks_db_version',         
    'easy_mcp_ai_tasks_tick_budget_seconds', 
    'easy_mcp_ai_tasks_retention_days',     
    'easy_mcp_ai_tasks_background',         
    'easy_mcp_ai_tasks_max_per_credential', 
    'easy_mcp_ai_tasks_max_concurrent_ticks', 
    'easy_mcp_ai_approvals_db_version',     
    'easy_mcp_ai_skills_release',           
    'easy_mcp_ai_skills_state',             
    'easy_mcp_ai_skills_sync',              
    'easy_mcp_ai_approval_required',        
    'easy_mcp_ai_approval_always',          
    'easy_mcp_ai_approval_never',           
    'easy_mcp_ai_change_log_retention',     
    'easy_mcp_ai_change_log_enabled',       
    'easy_mcp_ai_change_log_option_mode',   
    'easy_mcp_ai_change_log_capture_meta',  
    'easy_mcp_ai_change_log_capture_db',    
    'easy_mcp_ai_change_log_db_retention',  
    'easy_mcp_ai_change_log_external_intent', 
    'easy_mcp_ai_oauth_min_capability',     
    'easy_mcp_ai_connect_roles',            
    'easy_mcp_ai_external_data_min_capability', 
    'easy_mcp_ai_diagnostics_last_run',      
    'easy_mcp_ai_oauth_root_discovery',      
    'easy_mcp_ai_oauth_root_fallback',       
    'easy_mcp_ai_header_probe_secret',       
    'easy_mcp_ai_rest_auth_observed',        
    
    
    
    
    'easy_mcp_ai_install',                   
    'easy-mcp-ai_version',                   
    'easy_mcp_ai_logger_flag',               
    'easy_mcp_ai_review_flag',               
    'easy_mcp_ai_translate_flag',            
    'easy_mcp_ai_welcome_upsell_flag',       
    'easy_mcp_ai_crash_data',                
    'easy_mcp_ai_ran_migrations',            
    'easy_mcp_ai_migrated_version',          
);

if ( is_multisite() ) {
    
    delete_site_option( 'easy_mcp_ai_skills_release' );
    delete_site_option( 'easy_mcp_ai_skills_state' );
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_easy_mcp_ai_new_token_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", '_easy_mcp_ai_token_form_draft' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $easy_mcp_ai_site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
    foreach ( $easy_mcp_ai_site_ids as $easy_mcp_ai_site_id ) {
        switch_to_blog( $easy_mcp_ai_site_id );
        
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_audit_log' );
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_oauth' );
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_new_token_meta' );
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_change_log' );
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_tasks' );
        wp_clear_scheduled_hook( 'easy_mcp_ai_cleanup_approvals' );
        wp_unschedule_hook( 'easy_mcp_ai_task_tick' );
        wp_unschedule_hook( 'easy_mcp_ai_search_replace_tick' ); 
        wp_clear_scheduled_hook( 'easy_mcp_ai_skills_sync' );
        
        $easy_mcp_ai_admin_role = get_role( 'administrator' );
        if ( $easy_mcp_ai_admin_role ) {
            $easy_mcp_ai_admin_role->remove_cap( 'easy_mcp_ai_view_all_history' );
        }
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_tokens" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_clients" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_codes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_access_tokens" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_consents" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_device_codes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_change_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_tasks" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_approvals" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
        
        
        delete_transient( 'easy_mcp_ai_change_log_object_types' );
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_audit_distinct_%', '_transient_timeout_easy_mcp_ai_audit_distinct_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.

        foreach ( $options as $easy_mcp_ai_option ) {
            delete_option( $easy_mcp_ai_option );
        }
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_session_%', '_transient_timeout_easy_mcp_ai_session_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_rate_%', '_transient_timeout_easy_mcp_ai_rate_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_priv_rate_%', '_transient_timeout_easy_mcp_ai_priv_rate_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_reflog_%', '_transient_timeout_easy_mcp_ai_reflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_ipreflog_%', '_transient_timeout_easy_mcp_ai_ipreflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_urlkeyreflog_%', '_transient_timeout_easy_mcp_ai_urlkeyreflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_new_token_%', '_transient_timeout_easy_mcp_ai_new_token_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_dcr_rl_%', '_transient_timeout_easy_mcp_ai_dcr_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_device_rl_%', '_transient_timeout_easy_mcp_ai_device_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_device_lookup_rl_%', '_transient_timeout_easy_mcp_ai_device_lookup_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_revoke_rl_%', '_transient_timeout_easy_mcp_ai_revoke_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_token_rl_%', '_transient_timeout_easy_mcp_ai_token_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_oat_lu_%', '_transient_timeout_easy_mcp_ai_oat_lu_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_oat_srv_%', '_transient_timeout_easy_mcp_ai_oat_srv_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_auth_fail_%', '_transient_timeout_easy_mcp_ai_auth_fail_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'easy_mcp_ai_sr_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for option cleanup on uninstall.
        
        
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_ga_' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_ga_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_gsc_' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_gsc_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        
        
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_dfs_balance' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_dfs_balance' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        restore_current_blog();
    }
} else {
    
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_tokens" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_audit_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_clients" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_codes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_access_tokens" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_consents" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_oauth_device_codes" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_change_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_tasks" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_mcp_ai_approvals" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Intentional schema drop on uninstall.

    

    delete_transient( 'easy_mcp_ai_change_log_object_types' );
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_audit_distinct_%', '_transient_timeout_easy_mcp_ai_audit_distinct_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.


    foreach ( $options as $easy_mcp_ai_option ) {
        delete_option( $easy_mcp_ai_option );
    }

    
    $easy_mcp_ai_admin_role = get_role( 'administrator' );
    if ( $easy_mcp_ai_admin_role ) {
        $easy_mcp_ai_admin_role->remove_cap( 'easy_mcp_ai_view_all_history' );
    }

    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_session_%', '_transient_timeout_easy_mcp_ai_session_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_rate_%', '_transient_timeout_easy_mcp_ai_rate_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_priv_rate_%', '_transient_timeout_easy_mcp_ai_priv_rate_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_reflog_%', '_transient_timeout_easy_mcp_ai_reflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_ipreflog_%', '_transient_timeout_easy_mcp_ai_ipreflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_urlkeyreflog_%', '_transient_timeout_easy_mcp_ai_urlkeyreflog_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_new_token_%', '_transient_timeout_easy_mcp_ai_new_token_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_dcr_rl_%', '_transient_timeout_easy_mcp_ai_dcr_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_device_rl_%', '_transient_timeout_easy_mcp_ai_device_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_device_lookup_rl_%', '_transient_timeout_easy_mcp_ai_device_lookup_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_revoke_rl_%', '_transient_timeout_easy_mcp_ai_revoke_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_token_rl_%', '_transient_timeout_easy_mcp_ai_token_rl_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_oat_lu_%', '_transient_timeout_easy_mcp_ai_oat_lu_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_oat_srv_%', '_transient_timeout_easy_mcp_ai_oat_srv_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", '_transient_easy_mcp_ai_auth_fail_%', '_transient_timeout_easy_mcp_ai_auth_fail_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for transient cleanup on uninstall.
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'easy_mcp_ai_sr_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct DB call for option cleanup on uninstall.
    
    
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_ga_' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_ga_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_gsc_' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_gsc_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
    
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_easy_mcp_ai_dfs_balance' ) . '%', $wpdb->esc_like( '_transient_timeout_easy_mcp_ai_dfs_balance' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_easy_mcp_ai_new_token_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    
    
    $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s", '_easy_mcp_ai_token_form_draft' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}
