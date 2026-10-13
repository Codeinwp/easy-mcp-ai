<?php
namespace Easy_MCP_AI\Admin;

use Easy_MCP_AI\GSC\GSC_Client;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class External_Data_Admin {

    














    public static function disabled_tool_bucket_options() {
        return array(
            'easy_mcp_ai_disabled_plugin_tools',
            'easy_mcp_ai_disabled_gsc_tools',
            'easy_mcp_ai_disabled_ga_tools',
            'easy_mcp_ai_disabled_dfs_tools',
            'easy_mcp_ai_disabled_semrush_tools',
            'easy_mcp_ai_disabled_seranking_tools',
            'easy_mcp_ai_disabled_ahrefs_tools',
        );
    }

    





    public static function merge_disabled_tool_buckets( array $base = array() ) {
        $merged = $base;
        foreach ( self::disabled_tool_bucket_options() as $option ) {
            $merged = array_merge( $merged, (array) \get_option( $option, array() ) );
        }
        return array_values( array_unique( $merged ) );
    }

    const OPTION_GSC_SITES_CACHE = 'easy_mcp_ai_gsc_sites_cache';

    const OPTION_GA_PROPS_CACHE  = 'easy_mcp_ai_ga_properties_cache';

    




    public static function write_gsc_sites_cache( array $data ): void {
        $sites = array();
        foreach ( $data['siteEntry'] ?? array() as $entry ) {
            $url = $entry['siteUrl'] ?? '';
            if ( '' !== $url ) {
                $sites[] = $url;
            }
        }
        \update_option( self::OPTION_GSC_SITES_CACHE, $sites, false );
        if ( ! empty( $sites ) && '' === \get_option( GSC_Client::OPTION_SITE_URL, '' ) ) {
            \update_option( GSC_Client::OPTION_SITE_URL, $sites[0] );
        }
    }

    




    public static function write_ga_properties_cache( array $data ): void {
        $properties = array();
        foreach ( $data['accountSummaries'] ?? array() as $account ) {
            $account_name = $account['displayName'] ?? '';
            foreach ( $account['propertySummaries'] ?? array() as $prop ) {
                $resource = $prop['property'] ?? '';
                $id       = ltrim( str_replace( 'properties/', '', $resource ), '/' );
                if ( '' === $id ) {
                    continue;
                }
                $prop_name    = $prop['displayName'] ?? $resource;
                $properties[] = array(
                    'id'    => $id,
                    'label' => $account_name . ' – ' . $prop_name,
                );
            }
        }
        \update_option( self::OPTION_GA_PROPS_CACHE, $properties, false );
        if ( ! empty( $properties ) && '' === \get_option( GA_Client::OPTION_PROPERTY_ID, '' ) ) {
            \update_option( GA_Client::OPTION_PROPERTY_ID, $properties[0]['id'] );
        }
    }

    









    public static function purge_transients_by_prefix( string $prefix ): void {
        
        
        if ( ! \current_user_can( 'manage_options' ) ) {
            return;
        }
        global $wpdb;
        $like_value   = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
        $like_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_value, $like_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    }

    










    public static function compute_disabled_tools( array $all_names, array $checked ): array {
        $disabled = array();
        foreach ( $all_names as $name ) {
            if ( ! in_array( $name, $checked, true ) ) {
                $disabled[] = $name;
            }
        }
        return $disabled;
    }
}
