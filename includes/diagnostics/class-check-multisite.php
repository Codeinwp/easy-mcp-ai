<?php






















namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Multisite {

    
    const MAX_SITES_EXAMINED = 25;

    
    const REASON_UNREADABLE    = 'unreadable';
    const REASON_NOT_PERMITTED = 'not_permitted';

    















    public static function may_read_network() {
        if ( ! function_exists( 'is_multisite' ) || ! \is_multisite() ) {
            return true; 
        }

        return function_exists( 'is_super_admin' ) && \is_super_admin();
    }

    


    public static function run() {
        $is_multisite = function_exists( 'is_multisite' ) && \is_multisite();

        if ( ! $is_multisite ) {
            return array(
                self::evaluate_per_site_tables( false, array(), 0, 0 ),
                self::evaluate_discovery_origin( false, '', '' ),
            );
        }

        
        
        $i2 = self::evaluate_discovery_origin( true, self::rtrim_slash( \home_url() ), self::rtrim_slash( \network_home_url() ) );

        if ( ! self::may_read_network() ) {
            return array(
                self::evaluate_per_site_tables( true, null, 0, 0, self::REASON_NOT_PERMITTED ),
                $i2,
            );
        }

        $probe = self::probe_sites();

        return array(
            self::evaluate_per_site_tables( true, $probe['missing'], $probe['examined'], $probe['total'] ),
            $i2,
        );
    }

    







    public static function evaluate_per_site_tables( $is_multisite, $missing, $examined, $total, $reason = self::REASON_UNREADABLE ) {
        $label = __( 'Every site on the network is set up', 'easy-mcp-ai' );

        if ( $is_multisite && ! is_array( $missing ) ) {
            
            
            
            
            $permitted = ( self::REASON_NOT_PERMITTED === $reason );

            $detail = $permitted
                ? __( 'Only a network administrator can check the other sites on this network. Everything else on this page applies to this site alone.', 'easy-mcp-ai' )
                : __( 'Could not examine the sites on this network.', 'easy-mcp-ai' );

            
            
            
            
            
            
            $evidence = $permitted ? array( 'not_permitted' => true ) : array();

            return Diagnostic_Result::unknown( 'i1', Diagnostic_Result::TIER_WARNING, $label, $detail, $evidence );
        }
        $missing = (array) $missing;

        if ( ! $is_multisite ) {
            return Diagnostic_Result::pass( 'i1', Diagnostic_Result::TIER_WARNING, $label, __( 'Not a multisite network.', 'easy-mcp-ai' ), array( 'multisite' => false ) );
        }

        
        
        
        
        
        
        $examined = (int) $examined;
        $total    = (int) $total;

        if ( $examined < $total ) {
            $scope = sprintf(
                /* translators: 1: number of sites examined, 2: total sites on the network. */
                __( ' Checked the first %1$d of %2$d sites.', 'easy-mcp-ai' ),
                $examined,
                $total
            );
        } elseif ( $examined > $total ) {
            $scope = sprintf(
                /* translators: 1: number of sites examined, 2: the network's own site count. */
                __( ' Checked %1$d sites; the network reports %2$d, so this may not be all of them.', 'easy-mcp-ai' ),
                $examined,
                $total
            );
        } else {
            $scope = '';
        }

        $evidence = array(
            'missing'  => $missing,
            'examined' => (int) $examined,
            'total'    => (int) $total,
        );

        if ( ! empty( $missing ) ) {
            return Diagnostic_Result::warn(
                'i1',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: comma-separated site names. */
                    __( 'These sites are missing their plugin tables, so AI clients cannot use them: %s.', 'easy-mcp-ai' ),
                    implode( ', ', $missing )
                ) . $scope,
                __( 'Visit each listed site\'s dashboard once, or deactivate and reactivate the plugin across the network, to create the missing tables.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass(
            'i1',
            Diagnostic_Result::TIER_WARNING,
            $label,
            trim( $scope ),
            $evidence
        );
    }

    




    public static function evaluate_discovery_origin( $is_multisite, $site_home, $network_home ) {
        $label = __( 'AI clients can find this site\'s settings', 'easy-mcp-ai' );

        if ( ! $is_multisite ) {
            return Diagnostic_Result::pass( 'i2', Diagnostic_Result::TIER_WARNING, $label, '', array( 'multisite' => false ) );
        }

        $evidence = array( 'site_home' => (string) $site_home, 'network_home' => (string) $network_home );

        if ( '' !== (string) $site_home && self::sits_under_a_sub_path( $site_home, $network_home ) ) {
            return Diagnostic_Result::warn(
                'i2',
                Diagnostic_Result::TIER_WARNING,
                $label,
                sprintf(
                    /* translators: %s: this site's home URL. */
                    __( 'This site sits under a sub-path of the network (%s), so its discovery addresses live there rather than at the network root. An AI client given only the domain will not find them.', 'easy-mcp-ai' ),
                    (string) $site_home
                ),
                __( 'Use the full connection URL shown on this dashboard when connecting an AI client — not just the domain name.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::pass( 'i2', Diagnostic_Result::TIER_WARNING, $label, '', $evidence );
    }

    
















    private static function sits_under_a_sub_path( $site_home, $network_home ) {
        $site_host    = strtolower( (string) \wp_parse_url( (string) $site_home, PHP_URL_HOST ) );
        $network_host = strtolower( (string) \wp_parse_url( (string) $network_home, PHP_URL_HOST ) );

        
        if ( '' === $site_host || '' === $network_host || $site_host !== $network_host ) {
            return false;
        }

        $site_path    = self::rtrim_slash( (string) \wp_parse_url( (string) $site_home, PHP_URL_PATH ) );
        $network_path = self::rtrim_slash( (string) \wp_parse_url( (string) $network_home, PHP_URL_PATH ) );

        return $site_path !== $network_path;
    }

    

    private static function rtrim_slash( $url ) {
        return rtrim( (string) $url, '/' );
    }

    


    private static function probe_sites() {
        global $wpdb;

        
        
        $empty = array( 'missing' => null, 'examined' => 0, 'total' => 0 );

        if ( ! function_exists( 'get_sites' ) || ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'get_blog_prefix' ) ) {
            return $empty;
        }

        
        
        
        
        
        $total = (int) \get_sites( array( 'count' => true ) );
        if ( $total <= 0 && function_exists( 'get_blog_count' ) ) {
            $total = (int) \get_blog_count();
        }

        $sites = \get_sites( array( 'number' => self::MAX_SITES_EXAMINED, 'fields' => 'ids' ) );
        if ( ! is_array( $sites ) ) {
            return $empty;
        }

        $missing  = array();
        $examined = 0;

        foreach ( $sites as $site_id ) {
            $examined++;
            $prefix = $wpdb->get_blog_prefix( (int) $site_id );
            $table  = $prefix . 'easy_mcp_ai_tokens';

            if ( property_exists( $wpdb, 'last_error' ) ) {
                $wpdb->last_error = '';
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; SHOW TABLES cannot take a placeholder for the name.
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

            
            
            
            
            
            if ( null === $found && property_exists( $wpdb, 'last_error' ) && '' !== (string) $wpdb->last_error ) {
                return $empty;
            }

            if ( empty( $found ) ) {
                $details   = function_exists( 'get_blog_details' ) ? \get_blog_details( (int) $site_id ) : null;
                $missing[] = ( $details && ! empty( $details->domain ) )
                    ? $details->domain . ( isset( $details->path ) ? rtrim( $details->path, '/' ) : '' )
                    : ( 'site ' . (int) $site_id );
            }
        }

        return array(
            'missing'  => $missing,
            'examined' => $examined,
            
            
            'total'    => $total,
        );
    }
}
