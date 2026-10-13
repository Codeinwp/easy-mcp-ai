<?php






namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../class-config.php';





















final class Option_Guard {

    
    const PREFIX = 'easy_mcp_ai_';

    
    const SWITCHES = array(
        'easy_mcp_ai_enabled_tools',
        'easy_mcp_ai_enabled_abilities',
        'easy_mcp_ai_enabled_plugin_groups',
        'easy_mcp_ai_rest_disabled_namespaces',
    );

    
    const HOOKS = array( 'update_option', 'add_option', 'delete_option' );

    
    private static $depth = 0;

    
    private static $suspended = 0;

    
    public static function arm(): void {
        if ( 0 === self::$depth++ ) {
            foreach ( self::HOOKS as $hook ) {
                \add_action( $hook, array( self::class, 'refuse_protected' ), PHP_INT_MIN, 1 );
            }
        }
    }

    
    public static function release(): void {
        if ( self::$depth > 0 && 0 === --self::$depth ) {
            foreach ( self::HOOKS as $hook ) {
                \remove_action( $hook, array( self::class, 'refuse_protected' ), PHP_INT_MIN );
            }
        }
    }

    












    public static function while_suspended( callable $write ) {
        ++self::$suspended;
        try {
            return $write();
        } finally {
            --self::$suspended;
        }
    }

    
    public static function is_armed(): bool {
        return self::$depth > 0;
    }

    






    public static function refuse_protected( $option ): void {
        $option = (string) $option;
        if ( self::$suspended > 0 ) {
            return;
        }
        if ( self::is_protected( $option ) || self::is_database_alias( $option ) ) {
            throw new \RuntimeException( sprintf( 'This call tried to change %s, an Easy MCP AI setting; tool calls cannot change it, so the call was stopped at that write. An administrator can change it in the plugin\'s settings.', $option ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    













    private static function is_database_alias( string $option ): bool {
        if ( 1 !== preg_match( '/[^\x20-\x7E]/', $option ) ) {
            return false;
        }
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return false;
        }
        $table     = isset( $wpdb->options ) ? $wpdb->options : $wpdb->prefix . 'options';
        $collation = self::option_name_collation( $wpdb, $table );
        if ( '' !== $collation ) {
            $charset = strstr( $collation, '_', true );
            $names   = self::protected_names();
            $in      = implode( ', ', array_fill( 0, count( $names ), '%s' ) );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- the charset and collation are validated identifiers; every value is a placeholder.
            $match = $wpdb->get_var( $wpdb->prepare( "SELECT CONVERT( %s USING {$charset} ) COLLATE {$collation} IN ( {$in} )", array_merge( array( $option ), $names ) ) );
            if ( null !== $match ) {
                return '1' === (string) $match;
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core's own options table; the collation is the point.
        $row = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$table} WHERE option_name = %s LIMIT 1", $option ) );
        return is_string( $row ) && self::is_protected( $row );
    }

    








    private static function option_name_collation( object $wpdb, string $table ): string {
        $key       = 'option_guard_collation_' . $table;
        $cached    = \wp_cache_get( $key, 'easy_mcp_ai' );
        $collation = false !== $cached ? $cached : $wpdb->get_var( "SELECT COLLATION( option_name ) FROM {$table} LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- core's own options table; its collation is the point.
        if ( ! is_string( $collation ) || 1 !== preg_match( '/^[a-z0-9]+_[a-z0-9_]+$/i', $collation ) ) {
            return '';
        }
        if ( false === $cached ) {
            \wp_cache_set( $key, $collation, 'easy_mcp_ai', HOUR_IN_SECONDS );
        }
        return $collation;
    }

    






    public static function protected_names(): array {
        $names = self::SWITCHES;
        foreach ( array_keys( \Easy_MCP_AI\Config::settings() ) as $key ) {
            $names[] = self::PREFIX . $key;
        }
        if ( 1 === preg_match( '/\(([a-z|]+)\)/', \Easy_MCP_AI\Config::BUCKET_PATTERN, $buckets ) ) {
            foreach ( explode( '|', $buckets[1] ) as $bucket ) {
                $names[] = self::PREFIX . 'disabled_' . $bucket . '_tools';
                $names[] = self::PREFIX . self::PREFIX . 'disabled_' . $bucket . '_tools';
            }
        }
        return array_values( array_unique( $names ) );
    }

    





    public static function is_protected( string $option ): bool {
        
        
        
        $option = strtolower( trim( $option ) );
        if ( 0 !== strpos( $option, self::PREFIX ) ) {
            return false;
        }
        if ( in_array( $option, self::SWITCHES, true ) ) {
            return true;
        }
        $suffix = substr( $option, strlen( self::PREFIX ) );
        return isset( \Easy_MCP_AI\Config::settings()[ $suffix ] ) || 1 === preg_match( \Easy_MCP_AI\Config::BUCKET_PATTERN, $suffix );
    }
}
