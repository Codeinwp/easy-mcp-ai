<?php
namespace Easy_MCP_AI\OAuth;

use Easy_MCP_AI\Tools\Dynamic_Tool_Registrar;
use Easy_MCP_AI\Privileged\Privileged_Groups;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scope_Map {

    





    const SCOPE_MAP = array(
        
        
        'mcp:posts:read'         => array( 'wp_get_post', 'wp_list_posts', 'wp_get_page', 'wp_list_pages', 'wp_list_revisions', 'wp_get_revision', 'wp_get_post_meta', 'wp_get_post_types', 'wp_get_post_statuses', 'wp_count_posts', 'wp_get_post_full', 'wp_get_post_blocks', 'wp_create_preview_link' ),
        'mcp:posts:write'        => array( 'wp_create_post', 'wp_update_post', 'wp_delete_post', 'wp_create_page', 'wp_update_page', 'wp_delete_page', 'wp_delete_revision', 'wp_restore_revision', 'wp_update_post_meta', 'wp_delete_post_meta', 'wp_get_post_statuses', 'wp_add_post_terms', 'wp_replace_in_post', 'wp_update_post_blocks' ),
        'mcp:cpt:read'           => array( 'wp_list_cpt_items', 'wp_get_cpt_item' ),
        'mcp:cpt:write'          => array( 'wp_create_cpt_item', 'wp_update_cpt_item', 'wp_delete_cpt_item' ),
        'mcp:media:read'         => array( 'wp_get_media', 'wp_list_media', 'wp_count_media' ),
        'mcp:media:write'        => array( 'wp_upload_media', 'wp_update_media', 'wp_delete_media', 'wp_upload_media_from_url', 'wp_edit_media_image', 'wp_regenerate_media_sizes' ),
        'mcp:taxonomies:read'    => array( 'wp_get_category', 'wp_list_categories', 'wp_get_tag', 'wp_list_tags', 'wp_get_taxonomies', 'wp_count_terms', 'wp_get_term', 'wp_list_terms' ),
        'mcp:taxonomies:write'   => array( 'wp_create_category', 'wp_update_category', 'wp_delete_category', 'wp_create_tag', 'wp_update_tag', 'wp_delete_tag', 'wp_create_term', 'wp_update_term', 'wp_delete_term' ),
        'mcp:term_meta:read'     => array( 'wp_get_term_meta' ),
        'mcp:term_meta:write'    => array( 'wp_update_term_meta', 'wp_delete_term_meta' ),
        'mcp:user_meta:read'     => array( 'wp_get_user_meta' ),
        'mcp:user_meta:write'    => array( 'wp_update_user_meta', 'wp_delete_user_meta' ),
        'mcp:comments:read'      => array( 'wp_get_comment', 'wp_list_comments' ),
        'mcp:comments:write'     => array(
            'wp_create_comment', 'wp_update_comment', 'wp_delete_comment',
            'wp_approve_comment', 'wp_unapprove_comment', 'wp_spam_comment', 'wp_unspam_comment',
            'wp_trash_comment', 'wp_untrash_comment', 'wp_reply_to_comment', 'wp_bulk_moderate_comments',
        ),
        'mcp:users:read'         => array( 'wp_get_user', 'wp_list_users' ),
        'mcp:user_create:write'  => array( 'wp_create_user' ),
        'mcp:user_update:write'  => array( 'wp_update_user' ),
        'mcp:user_delete:write'  => array( 'wp_delete_user' ),
        'mcp:menus:read'         => array( 'wp_get_menu', 'wp_list_menus', 'wp_list_menu_items' ),
        'mcp:menus:write'        => array( 'wp_create_menu', 'wp_update_menu', 'wp_delete_menu', 'wp_create_menu_item', 'wp_update_menu_item', 'wp_delete_menu_item', 'wp_reorder_menu_items' ),
        'mcp:blocks:read'        => array( 'wp_get_block', 'wp_list_blocks', 'wp_list_patterns', 'wp_get_template', 'wp_list_templates', 'wp_get_global_styles', 'wp_get_active_theme', 'wp_list_themes', 'wp_list_block_types' ),
        
        
        
        
        'mcp:blocks:write'       => array( 'wp_create_block', 'wp_update_block', 'wp_delete_block' ),
        
        
        
        
        
        
        'mcp:appearance:read'    => array( 'wp_get_theme_mods', 'wp_get_custom_css' ),
        'mcp:appearance:write'   => array( 'wp_update_template', 'wp_update_global_styles', 'wp_update_theme_mod', 'wp_delete_theme_mod', 'wp_update_custom_css' ),
        
        
        'mcp:widgets:read'       => array( 'wp_list_sidebars', 'wp_list_widgets', 'wp_get_widget', 'wp_list_widget_types' ),
        'mcp:widgets:write'      => array( 'wp_create_widget', 'wp_update_widget', 'wp_delete_widget' ),
        'mcp:plugins:read'       => array( 'wp_list_plugins', 'wp_list_plugin_updates' ),
        
        
        
        'mcp:plugins:write'      => array( 'wp_activate_plugin', 'wp_deactivate_plugin', 'wp_update_plugin' ),
        
        
        
        
        
        
        
        'mcp:plugin_files:write' => array( 'wp_install_plugin', 'wp_delete_plugin', 'wp_uninstall_plugin' ),
        
        
        'mcp:themes:read'        => array( 'wp_list_themes', 'wp_get_active_theme' ),
        'mcp:themes:write'       => array( 'wp_switch_theme', 'wp_update_theme' ),
        'mcp:theme_files:write'  => array( 'wp_install_theme', 'wp_delete_theme' ),
        'mcp:settings:read'      => array( 'wp_get_site_settings' ),
        'mcp:settings:write'     => array( 'wp_update_site_settings' ),
        'mcp:woocommerce:read'   => array( 'wp_wc_get_product', 'wp_wc_list_products', 'wp_wc_get_order', 'wp_wc_list_orders', 'wp_wc_get_customer', 'wp_wc_list_customers', 'wp_wc_list_coupons', 'wp_wc_get_coupon', 'wp_wc_list_order_notes', 'wp_wc_list_order_refunds', 'wp_wc_list_payment_gateways', 'wp_wc_list_shipping_methods', 'wp_wc_list_shipping_zones', 'wp_wc_list_tax_rates', 'wp_wc_list_webhooks', 'wp_wc_list_product_categories', 'wp_wc_list_product_variations', 'wp_wc_get_product_variation', 'wp_wc_list_product_attributes', 'wp_wc_report_sales', 'wp_wc_report_orders', 'wp_wc_report_products', 'wp_wc_report_customers', 'wp_wc_report_top_sellers', 'wp_wc_list_logs', 'wp_wc_get_log' ),
        'mcp:woocommerce:write'  => array( 'wp_wc_create_product', 'wp_wc_update_product', 'wp_wc_delete_product', 'wp_wc_create_order', 'wp_wc_update_order', 'wp_wc_create_order_note', 'wp_wc_create_customer', 'wp_wc_update_customer', 'wp_wc_delete_customer', 'wp_wc_create_coupon', 'wp_wc_update_coupon', 'wp_wc_delete_coupon', 'wp_wc_create_product_variation', 'wp_wc_update_product_variation', 'wp_wc_delete_product_variation', 'wp_wc_batch_update_products', 'wp_wc_batch_update_orders', 'wp_wc_batch_update_variations', 'wp_wc_create_product_attribute', 'wp_wc_set_product_attributes' ),
        'mcp:wc_webhooks:write'  => array( 'wp_wc_create_webhook', 'wp_wc_update_webhook', 'wp_wc_delete_webhook' ),
        'mcp:acf:read'           => array( 'wp_acf_get_fields', 'wp_acf_get_term_fields', 'wp_acf_get_user_fields', 'wp_acf_list_field_groups' ),
        'mcp:acf:write'          => array( 'wp_acf_update_fields', 'wp_acf_update_user_fields' ),
        
        
        
        
        'mcp:acf_posts:write'    => array( 'wp_acf_update_fields' ),
        'mcp:buddypress:read'    => array( 'wp_bp_list_activity', 'wp_bp_list_groups', 'wp_bp_get_group', 'wp_bp_list_group_members', 'wp_bp_list_members', 'wp_bp_get_member', 'wp_bp_list_message_threads', 'wp_bp_get_message_thread' ),
        'mcp:buddypress:write'   => array( 'wp_bp_create_activity', 'wp_bp_delete_activity' ),
        'mcp:events:read'        => array( 'wp_tec_list_events', 'wp_tec_get_event', 'wp_tec_list_venues', 'wp_tec_get_venue', 'wp_tec_list_organizers' ),
        'mcp:events:write'       => array( 'wp_tec_create_event', 'wp_tec_update_event', 'wp_tec_delete_event', 'wp_tec_create_venue', 'wp_tec_create_organizer' ),
        'mcp:redirection:read'   => array( 'wp_redirection_list_redirects', 'wp_redirection_get_redirect', 'wp_redirection_list_groups', 'wp_redirection_list_404s' ),
        'mcp:redirection:write'  => array( 'wp_redirection_create_redirect', 'wp_redirection_update_redirect', 'wp_redirection_delete_redirects' ),
        'mcp:yoast:read'         => array( 'wp_yoast_get_post_seo', 'wp_yoast_get_head', 'wp_yoast_get_term_seo' ),
        'mcp:yoast:write'        => array( 'wp_yoast_update_post_seo', 'wp_yoast_update_term_seo' ),
        'mcp:aioseo:read'        => array( 'wp_aioseo_get_post_seo', 'wp_aioseo_get_breadcrumb' ),
        'mcp:aioseo:write'       => array( 'wp_aioseo_update_post_seo' ),
        'mcp:rankmath:read'      => array( 'wp_rm_get_post_seo', 'wp_rm_get_head' ),
        'mcp:rankmath:write'     => array( 'wp_rm_update_post_seo' ),
        'mcp:seopress:read'      => array( 'wp_seopress_get_post_seo', 'wp_seopress_get_content_analysis', 'wp_seopress_get_term_seo' ),
        'mcp:seopress:write'     => array( 'wp_seopress_update_post_title_desc', 'wp_seopress_update_post_robots', 'wp_seopress_update_post_social', 'wp_seopress_update_post_keywords' ),
        'mcp:slimseo:read'       => array( 'wp_slimseo_get_post_seo' ),
        'mcp:slimseo:write'      => array( 'wp_slimseo_update_post_seo' ),
        'mcp:tsf:read'           => array( 'wp_tsf_get_post_seo' ),
        'mcp:tsf:write'          => array( 'wp_tsf_update_post_seo' ),
        'mcp:gsc:read'           => array( 'wp_gsc_list_sites', 'wp_gsc_get_site', 'wp_gsc_query_performance', 'wp_gsc_list_sitemaps', 'wp_gsc_get_sitemap', 'wp_gsc_inspect_url' ),
        'mcp:ga:read'            => array( 'wp_ga_list_account_summaries', 'wp_ga_get_property', 'wp_ga_list_data_streams', 'wp_ga_list_custom_dimensions', 'wp_ga_list_custom_metrics', 'wp_ga_list_conversion_events', 'wp_ga_get_metadata', 'wp_ga_run_report', 'wp_ga_run_pivot_report', 'wp_ga_run_realtime_report', 'wp_ga_check_compatibility' ),
        'mcp:dfs:read'           => array(
            'wp_dfs_serp_google_organic_live',
            'wp_dfs_keywords_search_volume_live',
            'wp_dfs_labs_keywords_for_site_live',
            'wp_dfs_labs_ranked_keywords_live',
            'wp_dfs_backlinks_summary_live',
            'wp_dfs_backlinks_referring_domains_live',
            'wp_dfs_on_page_instant_pages',
            'wp_dfs_account_balance',
        ),
        
        
        
        
        
        'mcp:rest:read'          => array( 'wp_rest_routes', 'wp_rest_read' ),
        'mcp:rest:write'         => array( 'wp_rest_write' ),
        'mcp:rest_delete:write'  => array( 'wp_rest_delete' ),
        'mcp:history:read'       => array( 'wp_history_list', 'wp_history_get', 'wp_history_diff' ),
        
        
        'mcp:history:write'      => array( 'wp_history_apply' ),
        'mcp:audit:read'         => array( 'wp_audit_list' ),
        
        
        
        
        'mcp:site_health:read'   => array( 'wp_get_site_health', 'wp_list_cron_events', 'wp_get_error_log', 'wp_get_easy_mcp_diagnostics' ),
        'mcp:site_health:write'  => array( 'wp_run_cron_event' ),
        
        'mcp:cache:write'        => array( 'wp_clear_cache' ),
        
        
        
        
        
        'mcp:core_update:write'  => array( 'wp_update_core' ),
        
        
        
        
        
        'mcp:cli:write'          => array( 'wp_cli_run' ),
        'mcp:semrush:read'       => array(
            'wp_semrush_domain_overview',
            'wp_semrush_domain_organic_keywords',
            'wp_semrush_competitors_organic',
            'wp_semrush_keyword_overview',
            'wp_semrush_related_keywords',
            'wp_semrush_keyword_difficulty',
            'wp_semrush_phrase_questions',
            'wp_semrush_backlinks_overview',
            'wp_semrush_backlinks',
            'wp_semrush_referring_domains',
            'wp_semrush_anchors',
            'wp_semrush_url_organic_keywords',
            'wp_semrush_api_units_balance',
        ),
        'mcp:ahrefs:read'        => array(
            'wp_ahrefs_domain_rating_free',
        ),
        'mcp:seranking:read'     => array(
            'wp_seranking_account_balance',
            'wp_seranking_domain_overview',
            'wp_seranking_domain_overview_worldwide',
            'wp_seranking_domain_keywords',
            'wp_seranking_domain_competitors',
            'wp_seranking_domain_keyword_comparison',
            'wp_seranking_domain_pages',
            'wp_seranking_keyword_research',
            'wp_seranking_keywords_overview',
            'wp_seranking_backlinks_summary',
            'wp_seranking_backlinks_list',
            'wp_seranking_backlinks_domain_authority',
            'wp_seranking_ai_overview',
            'wp_seranking_ai_discover_brand',
            'wp_seranking_ai_prompts',
        ),
    );

    


    const CROSS_CATEGORY_READ_TOOLS = array( 'wp_search', 'wp_search_posts' );

    





    const CROSS_CATEGORY_WRITE_TOOLS = array( 'wp_approve_operation', 'wp_deny_operation' );

    





    const LEGACY_SCOPE_UPGRADES = array(
        'mcp:users:write' => array( 'mcp:user_create:write', 'mcp:user_update:write', 'mcp:user_delete:write' ),
    );

    


    const CORE_CATEGORIES = array( 'posts', 'cpt', 'media', 'taxonomies', 'term_meta', 'comments', 'users', 'user_meta', 'menus', 'blocks' );

    







    const PLUGIN_CATEGORIES = array(
        'woocommerce' => 'WooCommerce',
        'wc_webhooks' => 'WooCommerce',
        'acf'         => 'Advanced Custom Fields',
        'acf_posts'   => 'Advanced Custom Fields',
        'buddypress'  => 'BuddyPress',
        'events'      => 'The Events Calendar',
        'redirection' => 'Redirection',
        'yoast'       => 'Yoast SEO',
        'aioseo'      => 'All in One SEO',
        'rankmath'    => 'Rank Math SEO',
        'gsc'         => 'Google Search Console',
        'ga'          => 'Google Analytics',
        'dfs'         => 'DataforSEO',
        'semrush'     => 'Semrush',
        'ahrefs'      => 'Ahrefs (DR)',
        'seranking'   => 'SE Ranking',
        'seopress'    => 'SEOPress',
        'slimseo'     => 'Slim SEO',
        'tsf'         => 'The SEO Framework',
    );

    





    const PLUGIN_DETECTION_CLASSES = array(
        'woocommerce' => array( 'WooCommerce' ),
        'wc_webhooks' => array( 'WooCommerce' ),
        'acf'         => array( 'ACF' ),
        'acf_posts'   => array( 'ACF' ),
        'buddypress'  => array( 'BuddyPress' ),
        'events'      => array( 'Tribe__Events__Main' ),
        'redirection' => array( 'Red_Item' ),
        'yoast'       => array( 'WPSEO_Options' ),
        'aioseo'      => array( 'AIOSEO\\Plugin\\AIOSEO' ),
        'rankmath'    => array( 'RankMath' ),
        'gsc'         => array(),
        'ga'          => array(),
        'dfs'         => array(),
        'semrush'     => array(),
        'ahrefs'      => array(),
        'seranking'   => array(),
        'seopress'    => array(),
        'slimseo'     => array( 'SlimSEO\\Container' ),
        'tsf'         => array(),
    );

    







    public static function is_plugin_category_active( string $slug ): bool {
        if ( 'gsc' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_gsc_service_account_json', '' ) );
        }
        if ( 'ga' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_ga_service_account_json', '' ) );
        }
        if ( 'dfs' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_dfs_login', '' ) )
                && ! empty( \get_option( 'easy_mcp_ai_dfs_api_password', '' ) );
        }
        if ( 'semrush' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_semrush_api_key', '' ) );
        }
        
        
        
        
        if ( 'ahrefs' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_ahrefs_api_key', '' ) )
                && (bool) \get_option( 'easy_mcp_ai_ahrefs_enabled', false );
        }
        if ( 'seranking' === $slug ) {
            return ! empty( \get_option( 'easy_mcp_ai_seranking_api_key', '' ) );
        }
        
        if ( 'seopress' === $slug ) {
            return \function_exists( 'seopress_get_service' );
        }
        if ( 'tsf' === $slug ) {
            return \function_exists( 'tsf' );
        }
        if ( ! isset( self::PLUGIN_DETECTION_CLASSES[ $slug ] ) ) {
            return false;
        }
        foreach ( self::PLUGIN_DETECTION_CLASSES[ $slug ] as $class ) {
            if ( class_exists( $class ) ) {
                return true;
            }
        }
        return false;
    }

    




    public static function get_map(): array {
        return self::SCOPE_MAP;
    }

    








    public static function apply_legacy_scope_upgrades( array $scopes ): array {
        $result = array();
        foreach ( $scopes as $scope ) {
            if ( isset( self::LEGACY_SCOPE_UPGRADES[ $scope ] ) ) {
                foreach ( self::LEGACY_SCOPE_UPGRADES[ $scope ] as $replacement ) {
                    if ( ! in_array( $replacement, $result, true ) ) {
                        $result[] = $replacement;
                    }
                }
            } elseif ( ! in_array( $scope, $result, true ) ) {
                $result[] = $scope;
            }
        }
        return $result;
    }

    




    public static function get_all_scopes(): array {
        $scopes     = array( 'mcp' );
        $categories = array();

        foreach ( array_keys( self::SCOPE_MAP ) as $leaf ) {
            $scopes[] = $leaf;
            
            $parts    = explode( ':', $leaf );
            $category = $parts[0] . ':' . $parts[1];
            $categories[ $category ] = true;
        }

        foreach ( array_keys( $categories ) as $cat ) {
            $scopes[] = $cat;
        }

        $scopes = array_merge( $scopes, self::get_dynamic_ability_scopes() );

        
        
        
        
        
        
        self::load_privileged();
        $scopes = array_merge( $scopes, Privileged_Groups::enabled_scopes() );

        return array_values( array_unique( $scopes ) );
    }

    




    private static function load_privileged() {
        if ( ! class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' ) ) {
            require_once dirname( __DIR__ ) . '/privileged/class-privileged-groups.php';
        }
    }


    










    public static function get_public_scopes(): array {
        $scopes     = array( 'mcp' );
        $categories = array();

        foreach ( array_keys( self::SCOPE_MAP ) as $leaf ) {
            $scopes[] = $leaf;
            $parts    = explode( ':', $leaf );
            $category = $parts[0] . ':' . $parts[1];
            $categories[ $category ] = true;
        }

        foreach ( array_keys( $categories ) as $cat ) {
            $scopes[] = $cat;
        }

        if ( ! empty( self::get_cached_abilities() ) ) {
            $scopes[] = 'mcp:abilities';
        }

        
        
        
        

        return array_values( array_unique( $scopes ) );
    }

    









    private static function get_cached_abilities(): array {
        return function_exists( 'wp_get_abilities' ) ? (array) \wp_get_abilities() : array();
    }

    








    public static function get_dynamic_ability_scopes(): array {
        $abilities = self::get_cached_abilities();
        if ( empty( $abilities ) ) {
            return array();
        }

        if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Dynamic_Tool_Registrar' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/tools/class-dynamic-tool-registrar.php';
        }

        $prefixes = array();
        foreach ( $abilities as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' )
                ? $ability->get_name()
                : (string) ( is_string( $ability ) ? $ability : '' );
            if ( '' === $name ) {
                continue;
            }
            $parts  = explode( '/', $name, 2 );
            $prefix = count( $parts ) > 1
                ? Dynamic_Tool_Registrar::normalize_identifier( $parts[0] )
                : 'core';
            if ( '' === $prefix ) {
                continue;
            }
            $prefixes[ $prefix ] = true;
        }

        $scopes = array( 'mcp:abilities' );
        foreach ( array_keys( $prefixes ) as $prefix ) {
            $scopes[] = "mcp:abilities:{$prefix}";
        }
        return $scopes;
    }

    


















    private static function resolve_prefix_ability_tools( string $normalized_prefix ): array {
        $tools = array();
        foreach ( self::get_cached_abilities() as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' )
                ? $ability->get_name()
                : (string) ( is_string( $ability ) ? $ability : '' );
            if ( '' === $name ) {
                continue;
            }
            $parts = explode( '/', $name, 2 );
            $ability_normalized_prefix = ( count( $parts ) > 1 )
                ? Dynamic_Tool_Registrar::normalize_identifier( $parts[0] )
                : 'core';
            if ( $ability_normalized_prefix === $normalized_prefix ) {
                $tools[] = Dynamic_Tool_Registrar::build_tool_name( $name );
            }
        }
        return $tools;
    }

    








    public static function resolve_allowed_tools( string $scope ): array {
        $scope_parts    = array_filter( array_map( 'trim', explode( ' ', $scope ) ) );
        $scope_parts    = self::apply_legacy_scope_upgrades( array_values( $scope_parts ) );
        $tools           = array();
        $has_read_scope  = false;
        $has_write_scope = false;
        
        
        
        
        self::load_privileged();
        $privileged_tools = Privileged_Groups::tools_for_scopes( $scope_parts );

        foreach ( $scope_parts as $part ) {
            if ( Privileged_Groups::is_privileged_scope( $part ) ) {
                continue;
            }
            if ( 'mcp' === $part ) {
                return array_values( array_unique( array_merge( array( '*' ), $privileged_tools ) ) );
            }

            
            if ( 'mcp:abilities' === $part ) {
                $tools[] = 'wp_ability_*';
                continue;
            }

            
            
            
            if ( 0 === strpos( $part, 'mcp:abilities:' ) ) {
                
                
                
                
                
                $prefix = Dynamic_Tool_Registrar::normalize_identifier( substr( $part, strlen( 'mcp:abilities:' ) ) );
                if ( '' !== $prefix ) {
                    $tools = array_merge( $tools, self::resolve_prefix_ability_tools( $prefix ) );
                }
                continue;
            }

            
            if ( isset( self::SCOPE_MAP[ $part ] ) ) {
                $tools = array_merge( $tools, self::SCOPE_MAP[ $part ] );
                if ( substr( $part, -5 ) === ':read' ) {
                    $has_read_scope = true;
                }
                if ( substr( $part, -6 ) === ':write' ) {
                    $has_write_scope = true;
                }
                continue;
            }

            
            foreach ( self::SCOPE_MAP as $leaf_scope => $leaf_tools ) {
                if ( 0 === strpos( $leaf_scope, $part . ':' ) ) {
                    $tools = array_merge( $tools, $leaf_tools );
                    if ( substr( $leaf_scope, -5 ) === ':read' ) {
                        $has_read_scope = true;
                    }
                    if ( substr( $leaf_scope, -6 ) === ':write' ) {
                        $has_write_scope = true;
                    }
                }
            }
            
        }

        if ( $has_write_scope ) {
            $tools = array_merge( $tools, self::CROSS_CATEGORY_WRITE_TOOLS );
        }
        if ( $has_read_scope ) {
            $tools = array_merge( $tools, self::CROSS_CATEGORY_READ_TOOLS );
        }
        $tools = array_merge( $tools, $privileged_tools );

        return array_values( array_unique( $tools ) );
    }

    





    public static function get_read_scopes(): array {
        $scopes = array();
        foreach ( array_keys( self::SCOPE_MAP ) as $scope ) {
            if ( ':read' === substr( $scope, -5 ) ) {
                $scopes[] = $scope;
            }
        }
        return $scopes;
    }

    







    public static function get_default_scope(): string {
        $scopes = array();

        
        foreach ( self::CORE_CATEGORIES as $cat ) {
            if ( 'users' === $cat ) {
                $scopes[] = 'mcp:users:read';
                continue;
            }
            $scopes[] = "mcp:{$cat}:read";
            $scopes[] = "mcp:{$cat}:write";
        }

        
        
        $scopes[] = 'mcp:rest:read';

        
        
        $scopes[] = 'mcp:cache:write';

        
        foreach ( array_keys( self::PLUGIN_CATEGORIES ) as $cat ) {
            if ( ! isset( self::SCOPE_MAP[ "mcp:{$cat}:read" ] ) ) {
                continue;
            }
            $scopes[] = "mcp:{$cat}:read";
        }

        
        
        
        $scopes[] = 'mcp:acf_posts:write';

        return implode( ' ', $scopes );
    }

    






    public static function scope_permits_tool( string $scope, string $tool_name ): bool {
        $allowed = self::resolve_allowed_tools( $scope );

        
        
        
        if ( Privileged_Groups::is_privileged_tool( $tool_name ) ) {
            return in_array( '*', $allowed, true ) || in_array( $tool_name, $allowed, true );
        }

        if ( in_array( '*', $allowed, true ) ) {
            return true;
        }

        if ( in_array( $tool_name, $allowed, true ) ) {
            return true;
        }

        
        foreach ( $allowed as $entry ) {
            if ( is_string( $entry ) && false !== strpos( $entry, '*' ) && fnmatch( $entry, $tool_name ) ) {
                return true;
            }
        }

        return false;
    }

    





    private const DEFAULT_OFF_CATEGORIES = array( 'settings', 'plugins', 'themes', 'appearance', 'widgets', 'wc_webhooks', 'user_create', 'user_update', 'user_delete', 'history', 'audit', 'site_health', 'core_update', 'plugin_files', 'theme_files', 'cli', 'rest_delete' );

    





    public static function get_categories(): array {
        
        
        
        
        
        
        
        
        
        
        if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Dynamic_Tool_Registrar' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/tools/class-dynamic-tool-registrar.php';
        }

        $labels     = self::category_labels();
        $categories = array();
        $seen       = array();
        foreach ( array_keys( self::SCOPE_MAP ) as $leaf ) {
            $parts = explode( ':', $leaf );
            $slug  = $parts[1];

            if ( isset( $seen[ $slug ] ) ) {
                continue;
            }
            $seen[ $slug ] = true;
            $categories[]  = self::scope_category( $slug, $labels );
        }

        return array_merge( $categories, self::ability_categories() );
    }

    





    private static function category_labels(): array {
        return array(
            'posts'       => __( 'Posts & Pages', 'easy-mcp-ai' ),
            'cpt'         => __( 'Custom Post Types', 'easy-mcp-ai' ),
            'media'       => __( 'Media', 'easy-mcp-ai' ),
            'taxonomies'  => __( 'Taxonomies', 'easy-mcp-ai' ),
            'comments'    => __( 'Comments', 'easy-mcp-ai' ),
            'users'       => __( 'Users', 'easy-mcp-ai' ),
            'menus'       => __( 'Menus', 'easy-mcp-ai' ),
            'blocks'      => __( 'Blocks & Themes', 'easy-mcp-ai' ),
            'plugins'     => __( 'Plugins', 'easy-mcp-ai' ),
            'themes'      => __( 'Themes', 'easy-mcp-ai' ),
            'settings'    => __( 'Settings', 'easy-mcp-ai' ),
            'woocommerce' => 'WooCommerce',
            'acf'         => __( 'Advanced Custom Fields', 'easy-mcp-ai' ),
            'acf_posts'   => __( 'Advanced Custom Fields — Post & Page Fields', 'easy-mcp-ai' ),
            'buddypress'  => 'BuddyPress',
            'events'      => __( 'The Events Calendar', 'easy-mcp-ai' ),
            'redirection' => 'Redirection',
            'yoast'       => __( 'Yoast SEO', 'easy-mcp-ai' ),
            'aioseo'      => __( 'All in One SEO', 'easy-mcp-ai' ),
            'rankmath'    => __( 'Rank Math SEO', 'easy-mcp-ai' ),
            'gsc'         => __( 'Google Search Console', 'easy-mcp-ai' ),
            'ga'          => __( 'Google Analytics', 'easy-mcp-ai' ),
            'dfs'         => __( 'DataforSEO', 'easy-mcp-ai' ),
            'semrush'     => __( 'Semrush', 'easy-mcp-ai' ),
            'ahrefs'      => __( 'Ahrefs (DR)', 'easy-mcp-ai' ),
            'seranking'   => __( 'SE Ranking', 'easy-mcp-ai' ),
            'seopress'    => __( 'SEOPress', 'easy-mcp-ai' ),
            'slimseo'     => __( 'Slim SEO', 'easy-mcp-ai' ),
            'tsf'         => __( 'The SEO Framework', 'easy-mcp-ai' ),
            'term_meta'   => __( 'Term Meta', 'easy-mcp-ai' ),
            'user_meta'   => __( 'User Meta', 'easy-mcp-ai' ),
            'appearance'  => __( 'Site Appearance (templates, global styles, theme mods & CSS)', 'easy-mcp-ai' ),
            'widgets'     => __( 'Widgets & Sidebars', 'easy-mcp-ai' ),
            'wc_webhooks' => 'WooCommerce Webhooks',
            'user_create' => __( 'Users — Create', 'easy-mcp-ai' ),
            'user_update' => __( 'Users — Update', 'easy-mcp-ai' ),
            'user_delete' => __( 'Users — Delete', 'easy-mcp-ai' ),
            'history'     => __( 'Change History (audit log of MCP edits)', 'easy-mcp-ai' ),
            'audit'       => __( 'Audit Log (every MCP call, all users — admin only)', 'easy-mcp-ai' ),
            'site_health' => __( 'Site Health, Cron & Error Log', 'easy-mcp-ai' ),
            'core_update' => __( 'WordPress core update (replaces WordPress itself with a newer release from wordpress.org)', 'easy-mcp-ai' ),
            'plugin_files' => __( 'Plugin installs and deletions (adds plugins from wordpress.org, or removes plugin code)', 'easy-mcp-ai' ),
            'theme_files' => __( 'Theme installs and deletions (adds themes from wordpress.org, or removes theme code)', 'easy-mcp-ai' ),
            'cli'         => __( 'WP-CLI commands (commands no other tool performs, such as options, roles and sites, need only this permission, limited by your WordPress role)', 'easy-mcp-ai' ),
            'rest'        => __( 'REST routes of plugins and core (the route checks your permissions)', 'easy-mcp-ai' ),
            'rest_delete' => __( 'REST routes — Delete', 'easy-mcp-ai' ),
            'cache'       => __( 'Clear caches (page, asset and CDN caches, through each cache plugin)', 'easy-mcp-ai' ),
        );
    }

    






    private static function scope_category( string $slug, array $labels ): array {
        $plugin_required = isset( self::PLUGIN_CATEGORIES[ $slug ] ) ? self::PLUGIN_CATEGORIES[ $slug ] : null;

        list( $default_read, $default_write ) = self::category_defaults( $slug );

        $read_scope  = isset( self::SCOPE_MAP[ "mcp:{$slug}:read" ] ) ? "mcp:{$slug}:read" : '';
        $write_scope = isset( self::SCOPE_MAP[ "mcp:{$slug}:write" ] ) ? "mcp:{$slug}:write" : '';

        return array(
            'slug'            => $slug,
            'label'           => isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucfirst( $slug ),
            'read_scope'      => $read_scope,
            'write_scope'     => $write_scope,
            'write_only'      => '' === $read_scope && '' !== $write_scope,
            'default_read'    => $default_read,
            'default_write'   => $default_write,
            'plugin_required' => $plugin_required,
            'is_ability'      => false,
        );
    }

    





    private static function category_defaults( string $slug ): array {
        if ( in_array( $slug, self::DEFAULT_OFF_CATEGORIES, true ) ) {
            return array( false, false );
        }
        if ( 'users' === $slug ) {
            return array( true, false );
        }
        if ( 'cache' === $slug || 'acf_posts' === $slug ) {
            
            return array( false, true );
        }
        if ( in_array( $slug, self::CORE_CATEGORIES, true ) ) {
            return array( true, true );
        }
        
        return array( true, false );
    }

    




    private static function ability_categories(): array {
        $abilities = self::get_cached_abilities();
        if ( empty( $abilities ) ) {
            return array();
        }
        $categories = array();
        foreach ( self::ability_prefixes( (array) $abilities ) as $norm_prefix => $display_label ) {
            $categories[] = array(
                'slug'            => "abilities_{$norm_prefix}",
                /* translators: %s: ability prefix (e.g. "Core", "Wpforms") */
                'label'           => sprintf( __( 'Abilities — %s', 'easy-mcp-ai' ), $display_label ),
                'read_scope'      => "mcp:abilities:{$norm_prefix}",
                'write_scope'     => '',     
                'default_read'    => false,  
                'default_write'   => false,
                'plugin_required' => null,   
                'is_ability'      => true,
            );
        }
        return $categories;
    }

    







    private static function ability_prefixes( array $abilities ): array {
        $seen_prefixes = array();
        foreach ( $abilities as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' )
                ? $ability->get_name()
                : (string) ( is_string( $ability ) ? $ability : '' );
            if ( '' === $name ) {
                continue;
            }
            $parts = explode( '/', $name, 2 );
            if ( count( $parts ) > 1 ) {
                $norm_prefix   = Dynamic_Tool_Registrar::normalize_identifier( $parts[0] );
                $display_label = ucfirst( $parts[0] );
            } else {
                $norm_prefix   = 'core';
                $display_label = __( 'Core', 'easy-mcp-ai' );
            }
            if ( '' === $norm_prefix || isset( $seen_prefixes[ $norm_prefix ] ) ) {
                continue;
            }
            $seen_prefixes[ $norm_prefix ] = $display_label;
        }
        return $seen_prefixes;
    }
}
