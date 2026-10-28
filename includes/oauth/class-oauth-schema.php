<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OAuth_Schema {

    const DB_VERSION = '1.0.11';
    const VERSION_OPTION = 'easy_mcp_ai_oauth_db_version';

    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix . 'easy_mcp_ai_oauth_';

        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $clients_table = $prefix . 'clients';
        $sql_clients   = "CREATE TABLE {$clients_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            client_id varchar(64) NOT NULL,
            client_name varchar(255) NOT NULL,
            redirect_uris longtext NOT NULL,
            grant_types varchar(255) NOT NULL,
            response_types varchar(255) NOT NULL,
            scope varchar(255) NOT NULL DEFAULT '',
            software_id varchar(255) DEFAULT NULL,
            software_version varchar(64) DEFAULT NULL,
            client_secret_hash char(64) DEFAULT NULL,
            token_endpoint_auth_method varchar(32) NOT NULL DEFAULT 'none',
            metadata_url varchar(1024) DEFAULT NULL,
            metadata_fetched_at datetime DEFAULT NULL,
            metadata_expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            created_by_ip varchar(45) NOT NULL DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            UNIQUE KEY client_id (client_id),
            KEY is_active_idx (is_active)
        ) {$charset_collate};";

        $codes_table = $prefix . 'codes';
        $sql_codes   = "CREATE TABLE {$codes_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code_hash char(64) NOT NULL,
            client_id varchar(64) NOT NULL,
            wp_user_id bigint(20) unsigned NOT NULL,
            redirect_uri text NOT NULL,
            code_challenge varchar(128) NOT NULL,
            code_challenge_method varchar(16) NOT NULL DEFAULT 'S256',
            resource text NOT NULL,
            scope text NOT NULL,
            expires_at datetime NOT NULL,
            used_at datetime DEFAULT NULL,
            minted_token_id bigint(20) unsigned DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code_hash (code_hash),
            KEY expires_at_idx (expires_at),
            KEY client_id_idx (client_id)
        ) {$charset_collate};";

        $tokens_table = $prefix . 'access_tokens';
        $sql_tokens   = "CREATE TABLE {$tokens_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            token_hash char(64) NOT NULL,
            refresh_hash char(64) DEFAULT NULL,
            refresh_parent_id bigint(20) unsigned DEFAULT NULL,
            client_id varchar(64) NOT NULL,
            wp_user_id bigint(20) unsigned NOT NULL,
            resource text NOT NULL,
            scope text NOT NULL,
            expires_at datetime NOT NULL,
            refresh_expires_at datetime DEFAULT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            last_used_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token_hash (token_hash),
            UNIQUE KEY refresh_hash (refresh_hash),
            KEY client_user_idx (client_id, wp_user_id),
            KEY refresh_parent_idx (refresh_parent_id),
            KEY active_expires_idx (is_active, expires_at),
            KEY expires_at_idx (expires_at)
        ) {$charset_collate};";

        $consents_table = $prefix . 'consents';
        $sql_consents   = "CREATE TABLE {$consents_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wp_user_id bigint(20) unsigned NOT NULL,
            client_id varchar(64) NOT NULL,
            scope text NOT NULL,
            granted_at datetime NOT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_client_idx (wp_user_id, client_id),
            KEY client_id_idx (client_id)
        ) {$charset_collate};";

        $device_table = $prefix . 'device_codes';
        $sql_device   = "CREATE TABLE {$device_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            device_code_hash char(64) NOT NULL,
            user_code_hash char(64) NOT NULL,
            client_id varchar(64) NOT NULL,
            wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            consent_id bigint(20) unsigned DEFAULT NULL,
            resource text NOT NULL,
            scope text NOT NULL,
            status varchar(16) NOT NULL DEFAULT 'pending',
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            last_polled_at datetime DEFAULT NULL,
            decided_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY device_code_hash (device_code_hash),
            UNIQUE KEY user_code_hash (user_code_hash),
            KEY expires_at_idx (expires_at),
            KEY client_id_idx (client_id)
        ) {$charset_collate};";

        dbDelta( $sql_clients );
        dbDelta( $sql_codes );
        dbDelta( $sql_tokens );
        dbDelta( $sql_consents );
        dbDelta( $sql_device );

        $expected = array(
            $clients_table,
            $codes_table,
            $tokens_table,
            $consents_table,
            $device_table,
        );
        foreach ( $expected as $table ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table existence check; name is $wpdb->prefix-derived.
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            if ( ! \Easy_MCP_AI\Schema_Guard::table_found( $exists, $table ) ) {
                return;
            }
        }

        foreach ( self::REQUIRED_INDEXES as $table_suffix => $index_names ) {
            foreach ( $index_names as $index_name ) {
                if ( ! self::index_exists( $prefix . $table_suffix, $index_name ) ) {
                    return;
                }
            }
        }

        foreach ( self::REQUIRED_COLUMNS as $table_suffix => $column_names ) {
            foreach ( $column_names as $column_name ) {
                if ( ! self::column_exists( $prefix . $table_suffix, $column_name ) ) {
                    return;
                }
            }
        }

        update_option( self::VERSION_OPTION, self::DB_VERSION );
    }

    const REQUIRED_INDEXES = array(
        'codes'         => array( 'client_id_idx' ),
        'consents'      => array( 'client_id_idx' ),
        'access_tokens' => array( 'expires_at_idx' ),
    );

    const REQUIRED_COLUMNS = array(
        'clients' => array(
            'client_secret_hash',
            'token_endpoint_auth_method',
            'metadata_url',
            'metadata_fetched_at',
            'metadata_expires_at',
        ),
    );

    public static function index_exists( $table, $index_name ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return true;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; name is $wpdb->prefix-derived, index name is a class constant. SHOW INDEX cannot take a placeholder for the table.
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM `{$table}` WHERE Key_name = %s", $index_name ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return ! empty( $found );
    }

    public static function column_exists( $table, $column_name ) {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return true;
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; name is $wpdb->prefix-derived, column name is a class constant. SHOW COLUMNS cannot take a placeholder for the table.
        $found = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column_name ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return ! empty( $found );
    }

    public static function maybe_upgrade() {
        $installed_version = get_option( self::VERSION_OPTION, '0' );

        if ( version_compare( $installed_version, self::DB_VERSION, '<' )
            && \Easy_MCP_AI\Schema_Guard::may_retry( self::VERSION_OPTION, self::DB_VERSION ) ) {
            self::create_tables();
            \Easy_MCP_AI\Schema_Guard::settle(
                self::VERSION_OPTION,
                self::DB_VERSION,
                version_compare( (string) get_option( self::VERSION_OPTION, '0' ), self::DB_VERSION, '>=' )
            );
        }
    }
}
