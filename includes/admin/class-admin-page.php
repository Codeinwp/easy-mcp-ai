<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Admin_Page {

    























    public static function oauth_transport_problem(): string {
        if ( \Easy_MCP_AI\Config::get( 'oauth_allow_http' ) ) {
            return '';
        }

        
        
        
        
        
        
        
        
        
        
        $home   = (string) \get_option( 'home' );
        $host   = (string) \wp_parse_url( $home, PHP_URL_HOST );
        $scheme = strtolower( (string) \wp_parse_url( $home, PHP_URL_SCHEME ) );

        
        if ( in_array( strtolower( $host ), array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true ) ) {
            return '';
        }

        if ( 'https' !== $scheme ) {
            return 'http';
        }

        
        return \is_ssl() ? '' : 'proxy';
    }

    























    public static function permalinks_are_plain(): bool {
        return '' === (string) \get_option( 'permalink_structure', '' );
    }

    










    public static function write_auth_rule() {
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-diagnostic-result.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-htaccess-auth-rule.php';

        if ( ! self::may_write_htaccess() ) {
            return 'auth_rule_refused';
        }

        $written = \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::write(
            \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::htaccess_path()
        );
        if ( ! $written ) {
            return 'auth_rule_failed';
        }

        
        
        if ( class_exists( '\Easy_MCP_AI\Diagnostics\Diagnostics' ) ) {
            \Easy_MCP_AI\Diagnostics\Diagnostics::run( true );
        }

        return 'auth_rule_written';
    }

    





    private static function may_write_htaccess(): bool {
        $is_multisite = function_exists( 'is_multisite' ) && \is_multisite();
        return \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::may_write(
            \current_user_can( 'manage_options' ),
            $is_multisite,
            $is_multisite && \is_super_admin(),
            $is_multisite && \current_user_can( 'manage_network_options' )
        );
    }

    


















    public static function write_plain_routes(): string {
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-diagnostic-result.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-htaccess-plain-routes.php';

        if ( ! self::may_write_htaccess() ) {
            return 'plain_routes_refused';
        }
        if ( ! self::permalinks_are_plain() || ( function_exists( 'is_multisite' ) && \is_multisite() && ! \is_main_site() ) ) {
            return 'plain_routes_not_applicable';
        }

        $path    = \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::htaccess_path();
        $before  = \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::front_page_status();
        $written = \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::write(
            $path,
            \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::home_root()
        );
        if ( ! $written ) {
            return 'plain_routes_failed';
        }
        if ( \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::breaks_site( $before, \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::front_page_status() ) ) {
            \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::remove( $path );
            return 'plain_routes_reverted';
        }

        
        
        if ( class_exists( '\Easy_MCP_AI\Diagnostics\Diagnostics' ) ) {
            \Easy_MCP_AI\Diagnostics\Diagnostics::run( true, \Easy_MCP_AI\Diagnostics\Diagnostics::CONNECTION_GROUPS );
        }

        return 'plain_routes_written';
    }

    










    public static function oauth_min_capability_choices() {
        return array(
            'publish_posts'     => __( 'Author and above (default)', 'easy-mcp-ai' ),
            'edit_others_posts' => __( 'Editor and above', 'easy-mcp-ai' ),
            'manage_options'    => __( 'Administrators only', 'easy-mcp-ai' ),
        );
    }

    








    public static function sanitize_oauth_min_capability( $value ) {
        return ( is_string( $value ) && array_key_exists( $value, self::oauth_min_capability_choices() ) )
            ? $value
            : 'publish_posts';
    }

    











    public static function external_data_min_capability_choices() {
        return array(
            'manage_options'    => __( 'Administrators only (default)', 'easy-mcp-ai' ),
            'edit_others_posts' => __( 'Editor and above', 'easy-mcp-ai' ),
            'publish_posts'     => __( 'Author and above', 'easy-mcp-ai' ),
        );
    }

    









    public static function sanitize_external_data_min_capability( $value ) {
        return ( is_string( $value ) && array_key_exists( $value, self::external_data_min_capability_choices() ) )
            ? $value
            : 'manage_options';
    }

    









    public static function token_expiry_presets() {
        return array( '7' => 7, '30' => 30, '60' => 60, '90' => 90 );
    }

    
    const TOKEN_EXPIRY_DEFAULT_PRESET = '30';

    
    const TOKEN_EXPIRY_CUSTOM = 'custom';

    
    const TOKEN_EXPIRY_NEVER = 'never';

    













    public static function token_expiry_preset_date( $days, $now = null ) {
        $now = null === $now ? time() : (int) $now;
        return gmdate( 'Y-m-d', $now + ( (int) $days * DAY_IN_SECONDS ) );
    }

    


























    public static function resolve_submitted_expiry( $preset, $custom, $stored = null, $now = null ) {
        $preset  = is_string( $preset ) ? $preset : '';
        $custom  = is_string( $custom ) ? trim( $custom ) : '';
        $presets = self::token_expiry_presets();
        $now     = null === $now ? time() : (int) $now;

        if ( isset( $presets[ $preset ] ) ) {
            return self::token_expiry_preset_date( $presets[ $preset ], $now );
        }
        if ( self::TOKEN_EXPIRY_NEVER === $preset ) {
            return null;
        }
        if ( self::TOKEN_EXPIRY_CUSTOM !== $preset ) {
            
            if ( '' === $custom ) {
                return null;
            }
        }
        if ( null !== $stored && '' !== $custom && $custom === (string) $stored ) {
            
            return $custom;
        }
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $custom, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            return new \WP_Error( 'invalid_expiry', __( 'Choose a valid expiration date.', 'easy-mcp-ai' ) );
        }
        if ( $custom < self::token_expiry_preset_date( 1, $now ) ) {
            return new \WP_Error( 'invalid_expiry', __( 'The expiration date must be in the future.', 'easy-mcp-ai' ) );
        }
        return $custom;
    }

    








    public static function batched_cleanup( $table, $retention ) {
        
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            return false;
        }
        global $wpdb;
        
        
        
        
        if ( (int) $retention < 1 ) {
            return false;
        }
        
        
        
        
        $table   = \esc_sql( $table );
        $iter    = 0;
        $max     = (int) \Easy_MCP_AI\Plugin::CLEANUP_MAX_ITERATIONS;
        $deleted = 0;
        do {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is plugin-controlled (esc_sql'd above); retention is %d-bound.
            $deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM `{$table}` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 500", $retention ) );
            $iter++;
        } while ( $deleted > 0 && $iter < $max );

        
        
        
        if ( $iter < $max || $deleted < 500 ) {
            return false;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- One-row existence check; same identifier guarantee.
        $remaining = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$table}` WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 1", $retention ) );
        return null !== $remaining;
    }
}
