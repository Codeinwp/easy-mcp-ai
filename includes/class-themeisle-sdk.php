<?php






namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































final class Themeisle_SDK {

    
    const PRODUCT_SLUG = 'easy-mcp-ai';

    
    const PRODUCT_KEY = 'easy_mcp_ai';

    
    const ABOUT_US_MENU_SLUG = 'ti-about-easy_mcp_ai';

    
    const MENU_SLUG = 'easy-mcp-ai';

    
    const CONSENT_OPTION = 'easy_mcp_ai_logger_flag';

    
    const USAGE_WINDOW_DAYS = 30;

    
    const USAGE_MAX_TOOLS = 50;

    




    private static $basefile = '';

    





    public static function register( $basefile ) {
        self::$basefile = (string) $basefile;

        
        
        if ( false === \get_option( self::CONSENT_OPTION ) ) {
            \add_option( self::CONSENT_OPTION, 'no' );
        }

        \add_filter( 'themeisle_sdk_products', array( __CLASS__, 'register_product' ) );

        foreach ( self::policy() as $hook => $callback ) {
            \add_filter( $hook, $callback );
        }

        \add_filter( self::PRODUCT_KEY . '_about_us_metadata', array( __CLASS__, 'about_us_metadata' ) );
        \add_filter( self::PRODUCT_KEY . '_logger_data', array( __CLASS__, 'logger_data' ) );
        \add_action( 'admin_menu', array( __CLASS__, 'move_about_us_submenu_last' ), 999 );
    }

    








    public static function move_about_us_submenu_last() {
        global $submenu;

        if ( ! is_array( $submenu ) || empty( $submenu[ self::MENU_SLUG ] ) || ! is_array( $submenu[ self::MENU_SLUG ] ) ) {
            return;
        }

        $items = $submenu[ self::MENU_SLUG ];
        $about = array();
        foreach ( $items as $index => $item ) {
            if ( isset( $item[2] ) && self::ABOUT_US_MENU_SLUG === $item[2] ) {
                $about[] = $item;
                unset( $items[ $index ] );
            }
        }

        if ( empty( $about ) ) {
            return;
        }

        $submenu[ self::MENU_SLUG ] = array_merge( array_values( $items ), $about );
    }

    


















    public static function about_us_metadata( $data = array() ) {
        if ( self::is_white_labelled() ) {
            return array();
        }

        return array(
            'location'         => self::MENU_SLUG,
            'logo'             => \plugin_dir_url( self::$basefile ) . 'assets/images/icon-256x256.png',
            'has_upgrade_menu' => false,
        );
    }

    
    private static function is_white_labelled() {
        return Config::is_white_labelled();
    }

    

























    public static function logger_data( $data = array() ) {
        $data = is_array( $data ) ? $data : array();

        if ( 'yes' !== \get_option( self::CONSENT_OPTION, 'no' ) ) {
            return $data;
        }

        $clients = self::client_counts();
        if ( ! empty( $clients ) ) {
            $data['clients'] = $clients;
        }

        $tools = self::tool_usage_counts();
        if ( ! empty( $tools ) ) {
            $data['tools'] = $tools;
        }

        return $data;
    }

    








    private static function client_counts() {
        global $wpdb;

        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
            return array();
        }

        $now   = \gmdate( 'Y-m-d H:i:s' );
        $out   = array();
        $specs = array(
            'api_keys'      => array(
                'easy_mcp_ai_tokens',
                'WHERE is_active = 1 AND ( expires_at IS NULL OR expires_at > %s )',
                array( $now ),
            ),
            'oauth_clients' => array(
                'easy_mcp_ai_oauth_clients',
                'WHERE is_active = 1',
                array(),
            ),
            'oauth_grants'  => array(
                'easy_mcp_ai_oauth_access_tokens',
                'WHERE is_active = 1 AND expires_at > %s',
                array( $now ),
            ),
        );

        foreach ( $specs as $key => $spec ) {
            list( $suffix, $where, $args ) = $spec;

            $table = $wpdb->prefix . $suffix;
            if ( ! self::table_exists( $table ) ) {
                continue;
            }

            $sql = "SELECT COUNT(*) FROM `{$table}` {$where}";
            if ( ! empty( $args ) ) {
                if ( ! method_exists( $wpdb, 'prepare' ) ) {
                    continue;
                }
                $sql = $wpdb->prepare( $sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Plugin-owned table name; every value is bound.
            }

            self::clear_error( $wpdb );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table, aggregate count, runs at most once per day.
            $count = $wpdb->get_var( $sql );
            if ( null === $count || self::has_error( $wpdb ) ) {
                continue;
            }

            $out[ $key ] = (int) $count;
        }

        return $out;
    }

    










    private static function tool_usage_counts() {
        global $wpdb;

        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_results' ) || ! method_exists( $wpdb, 'prepare' ) ) {
            return array();
        }

        $table = $wpdb->prefix . 'easy_mcp_ai_audit_log';
        if ( ! self::table_exists( $table ) ) {
            return array();
        }

        $since = \gmdate( 'Y-m-d H:i:s', time() - ( self::USAGE_WINDOW_DAYS * DAY_IN_SECONDS ) );
        $sql   = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Plugin-owned table name; the cutoff and the cap are bound.
            "SELECT tool_name, COUNT(*) AS calls FROM `{$table}` WHERE created_at >= %s AND tool_name NOT LIKE %s GROUP BY tool_name ORDER BY calls DESC LIMIT %d",
            array( $since, $wpdb->esc_like( '_' ) . '%', self::USAGE_MAX_TOOLS )
        );

        self::clear_error( $wpdb );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table, aggregate count, runs at most once per day.
        $rows = $wpdb->get_results( $sql, ARRAY_A );
        if ( ! is_array( $rows ) || self::has_error( $wpdb ) ) {
            return array();
        }

        $out = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['tool_name'] ) ) {
                continue;
            }
            $name = (string) $row['tool_name'];
            
            
            
            if ( 1 !== preg_match( '/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/', $name ) ) {
                continue;
            }
            $out[ $name ] = isset( $row['calls'] ) ? (int) $row['calls'] : 0;
        }

        return $out;
    }

    






    private static function table_exists( $table ) {
        global $wpdb;

        if ( ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'esc_like' ) ) {
            return false;
        }

        self::clear_error( $wpdb );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- existence check on a plugin-owned table.
        $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );

        return ! self::has_error( $wpdb ) && $found === $table;
    }

    





    private static function clear_error( $wpdb ) {
        if ( property_exists( $wpdb, 'last_error' ) ) {
            $wpdb->last_error = '';
        }
    }

    





    private static function has_error( $wpdb ) {
        return isset( $wpdb->last_error ) && '' !== $wpdb->last_error;
    }

    





    public static function register_product( $products ) {
        $products   = is_array( $products ) ? $products : array();
        $products[] = self::$basefile;

        return $products;
    }

    




    public static function policy() {
        return array(
            
            
            
            
            
            'themeisle_sdk_ran_promos'                    => '__return_true',

            
            
            
            'themeisle_sdk_hide_notifications'            => '__return_true',

            
            
            
            'themeisle_sdk_blackfriday_data'              => '__return_empty_array',

            
            
            
            self::PRODUCT_SLUG . '_sdk_should_review'     => '__return_false',
            self::PRODUCT_SLUG . '_sdk_enable_translate'  => '__return_false',
            
            
            
            
            
            

            
            self::PRODUCT_SLUG . '_load_dashboard_widget' => '__return_false',
        );
    }
}
