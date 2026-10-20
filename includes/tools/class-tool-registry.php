<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_Registry {

    private $tools = array();

    






    private $lazy_classes = array();

    







    private $lazy_loader = null;
    private $lazy_loader_ran = false;

    


    public function set_lazy_loader( callable $loader ) {
        $this->lazy_loader = $loader;
    }

    




    private function ensure_loaded() {
        if ( $this->lazy_loader_ran || null === $this->lazy_loader ) {
            return;
        }
        if ( ! empty( $this->tools ) || ! empty( $this->lazy_classes ) ) {
            return;
        }
        $this->lazy_loader_ran = true;
        ( $this->lazy_loader )();
    }

    



















    public function register( Base_Tool $tool ) {
        $name = $tool->get_name();
        if ( isset( $this->tools[ $name ] ) && $this->tools[ $name ] !== $tool ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( sprintf(
                'Easy MCP AI: tool name collision on "%s" — a tool is already registered under this name; keeping the first and refusing to overwrite it.',
                $name
            ) );
            return;
        }
        if ( $tool instanceof Privileged_Tool && ! self::admit_privileged( $tool ) ) {
            return;
        }
        $this->tools[ $name ] = $tool;
    }

    




    private static function admit_privileged( Privileged_Tool $tool ) {
        return \Easy_MCP_AI\Privileged\Privileged_Groups::admit_tool( $tool->get_name(), $tool->get_privileged_group() );
    }

    public function get_tool( $name ) {
        $this->ensure_loaded();
        if ( isset( $this->tools[ $name ] ) ) {
            return $this->tools[ $name ];
        }
        
        
        
        foreach ( $this->lazy_classes as $key => $class ) {
            $instance  = new $class();
            $tool_name = $instance->get_name();
            $this->tools[ $tool_name ] = $instance;
            unset( $this->lazy_classes[ $key ] );
            if ( $tool_name === $name ) {
                return $instance;
            }
        }
        return null;
    }

    public function get_all_definitions() {
        $this->materialize_all();
        $definitions = array();
        foreach ( $this->tools as $tool ) {
            $definitions[] = $tool->get_definition();
        }
        return $definitions;
    }

    public function get_all_tool_names() {
        $this->materialize_all();
        return array_keys( $this->tools );
    }

    public function get_tools_by_category() {
        $this->materialize_all();
        $categorized = array();
        foreach ( $this->tools as $tool ) {
            $category = $tool->get_category();
            if ( ! isset( $categorized[ $category ] ) ) {
                $categorized[ $category ] = array();
            }
            $categorized[ $category ][] = $tool->get_definition();
        }
        return $categorized;
    }

    private function materialize_all() {
        $this->ensure_loaded();
        foreach ( $this->lazy_classes as $key => $class ) {
            $instance = new $class();
            $this->tools[ $instance->get_name() ] = $instance;
            unset( $this->lazy_classes[ $key ] );
        }
    }

    

















    public function auto_discover( bool $quiet = false, ?array $dirs = null ): void {
        $tool_classes = array(
            'Easy_MCP_AI\\Tools\\Posts\\List_Posts',
            'Easy_MCP_AI\\Tools\\Posts\\Get_Post',
            'Easy_MCP_AI\\Tools\\Posts\\Create_Post',
            'Easy_MCP_AI\\Tools\\Posts\\Update_Post',
            'Easy_MCP_AI\\Tools\\Posts\\Delete_Post',
            'Easy_MCP_AI\\Tools\\Posts\\Search_Posts',
            'Easy_MCP_AI\\Tools\\Pages\\List_Pages',
            'Easy_MCP_AI\\Tools\\Pages\\Get_Page',
            'Easy_MCP_AI\\Tools\\Pages\\Create_Page',
            'Easy_MCP_AI\\Tools\\Pages\\Update_Page',
            'Easy_MCP_AI\\Tools\\Pages\\Delete_Page',
            'Easy_MCP_AI\\Tools\\Media\\List_Media',
            'Easy_MCP_AI\\Tools\\Media\\Get_Media',
            'Easy_MCP_AI\\Tools\\Media\\Upload_Media',
            'Easy_MCP_AI\\Tools\\Media\\Update_Media',
            'Easy_MCP_AI\\Tools\\Media\\Delete_Media',
            'Easy_MCP_AI\\Tools\\Media\\Edit_Media_Image',
            'Easy_MCP_AI\\Tools\\Media\\Regenerate_Media_Sizes',
            'Easy_MCP_AI\\Tools\\Taxonomy\\List_Categories',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Get_Category',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Create_Category',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Update_Category',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Delete_Category',
            'Easy_MCP_AI\\Tools\\Taxonomy\\List_Tags',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Get_Tag',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Create_Tag',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Update_Tag',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Delete_Tag',
            'Easy_MCP_AI\\Tools\\Comments\\List_Comments',
            'Easy_MCP_AI\\Tools\\Comments\\Get_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Create_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Update_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Delete_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Approve_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Unapprove_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Spam_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Unspam_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Trash_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Untrash_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Reply_To_Comment',
            'Easy_MCP_AI\\Tools\\Comments\\Bulk_Moderate_Comments',
            'Easy_MCP_AI\\Tools\\Users\\List_Users',
            'Easy_MCP_AI\\Tools\\Users\\Get_User',
            'Easy_MCP_AI\\Tools\\Users\\Create_User',
            'Easy_MCP_AI\\Tools\\Users\\Update_User',
            'Easy_MCP_AI\\Tools\\Users\\Delete_User',
            'Easy_MCP_AI\\Tools\\Site\\Get_Site_Settings',
            'Easy_MCP_AI\\Tools\\Site\\Update_Site_Settings',
            'Easy_MCP_AI\\Tools\\Site\\Get_Post_Types',
            'Easy_MCP_AI\\Tools\\Site\\Get_Taxonomies',
            'Easy_MCP_AI\\Tools\\Menus\\List_Menus',
            'Easy_MCP_AI\\Tools\\Menus\\Get_Menu',
            'Easy_MCP_AI\\Tools\\Menus\\List_Menu_Items',
            'Easy_MCP_AI\\Tools\\Menus\\Create_Menu_Item',
            'Easy_MCP_AI\\Tools\\Plugins\\List_Plugins',
            'Easy_MCP_AI\\Tools\\Plugins\\Activate_Plugin',
            'Easy_MCP_AI\\Tools\\Plugins\\Deactivate_Plugin',
            'Easy_MCP_AI\\Tools\\Plugins\\List_Plugin_Updates',
            'Easy_MCP_AI\\Tools\\Plugins\\Update_Plugin',
            
            'Easy_MCP_AI\\Tools\\Plugins\\Install_Plugin',
            'Easy_MCP_AI\\Tools\\Plugins\\Delete_Plugin',
            'Easy_MCP_AI\\Tools\\Plugins\\Uninstall_Plugin',
            'Easy_MCP_AI\\Tools\\Themes\\List_Themes',
            'Easy_MCP_AI\\Tools\\Themes\\Get_Active_Theme',
            'Easy_MCP_AI\\Tools\\Themes\\Switch_Theme',
            'Easy_MCP_AI\\Tools\\Themes\\Update_Theme',
            'Easy_MCP_AI\\Tools\\Themes\\Install_Theme',
            'Easy_MCP_AI\\Tools\\Themes\\Delete_Theme',
            
            'Easy_MCP_AI\\Tools\\Revisions\\List_Revisions',
            'Easy_MCP_AI\\Tools\\Revisions\\Get_Revision',
            'Easy_MCP_AI\\Tools\\Revisions\\Delete_Revision',
            
            'Easy_MCP_AI\\Tools\\Meta\\Get_Post_Meta',
            'Easy_MCP_AI\\Tools\\Meta\\Update_Post_Meta',
            
            'Easy_MCP_AI\\Tools\\Search\\Search',
            
            'Easy_MCP_AI\\Tools\\Blocks\\List_Blocks',
            'Easy_MCP_AI\\Tools\\Blocks\\Get_Block',
            'Easy_MCP_AI\\Tools\\Blocks\\Create_Block',
            'Easy_MCP_AI\\Tools\\Blocks\\Update_Block',
            'Easy_MCP_AI\\Tools\\Blocks\\Delete_Block',
            'Easy_MCP_AI\\Tools\\Blocks\\List_Patterns',
            'Easy_MCP_AI\\Tools\\Blocks\\Get_Post_Blocks',
            'Easy_MCP_AI\\Tools\\Blocks\\Update_Post_Blocks',
            'Easy_MCP_AI\\Tools\\Blocks\\List_Block_Types',
            
            'Easy_MCP_AI\\Tools\\Site\\Get_Post_Statuses',
            
            'Easy_MCP_AI\\Tools\\CPT\\List_CPT_Items',
            'Easy_MCP_AI\\Tools\\CPT\\Get_CPT_Item',
            'Easy_MCP_AI\\Tools\\CPT\\Create_CPT_Item',
            'Easy_MCP_AI\\Tools\\CPT\\Update_CPT_Item',
            'Easy_MCP_AI\\Tools\\CPT\\Delete_CPT_Item',
            
            'Easy_MCP_AI\\Tools\\Menus\\Create_Menu',
            'Easy_MCP_AI\\Tools\\Menus\\Update_Menu',
            'Easy_MCP_AI\\Tools\\Menus\\Delete_Menu',
            'Easy_MCP_AI\\Tools\\Menus\\Update_Menu_Item',
            'Easy_MCP_AI\\Tools\\Menus\\Delete_Menu_Item',
            'Easy_MCP_AI\\Tools\\Menus\\Reorder_Menu_Items',
            
            'Easy_MCP_AI\\Tools\\Templates\\List_Templates',
            'Easy_MCP_AI\\Tools\\Templates\\Get_Template',
            'Easy_MCP_AI\\Tools\\Templates\\Update_Template',
            
            'Easy_MCP_AI\\Tools\\Styles\\Get_Global_Styles',
            'Easy_MCP_AI\\Tools\\Styles\\Update_Global_Styles',
            
            'Easy_MCP_AI\\Tools\\Appearance\\Get_Theme_Mods',
            'Easy_MCP_AI\\Tools\\Appearance\\Update_Theme_Mod',
            'Easy_MCP_AI\\Tools\\Appearance\\Delete_Theme_Mod',
            'Easy_MCP_AI\\Tools\\Appearance\\Get_Custom_Css',
            'Easy_MCP_AI\\Tools\\Appearance\\Update_Custom_Css',
            
            'Easy_MCP_AI\\Tools\\Widgets\\List_Sidebars',
            'Easy_MCP_AI\\Tools\\Widgets\\List_Widgets',
            'Easy_MCP_AI\\Tools\\Widgets\\Get_Widget',
            'Easy_MCP_AI\\Tools\\Widgets\\List_Widget_Types',
            'Easy_MCP_AI\\Tools\\Widgets\\Create_Widget',
            'Easy_MCP_AI\\Tools\\Widgets\\Update_Widget',
            'Easy_MCP_AI\\Tools\\Widgets\\Delete_Widget',
            
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Products',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Product',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Product',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Product',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Delete_Product',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Product_Variations',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Product_Variation',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Product_Variation',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Product_Variation',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Delete_Product_Variation',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Product_Categories',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Orders',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Order',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Order',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Order',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Order_Notes',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Order_Note',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Order_Refunds',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Customers',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Customer',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Customer',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Customer',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Delete_Customer',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Coupons',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Coupon',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Coupon',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Coupon',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Delete_Coupon',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Report_Sales',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Report_Top_Sellers',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Report_Orders',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Report_Products',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Report_Customers',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Webhooks',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Webhook',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Update_Webhook',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Delete_Webhook',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Shipping_Zones',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Shipping_Methods',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Tax_Rates',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Payment_Gateways',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Logs',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Get_Log',
            
            'Easy_MCP_AI\\Tools\\ACF\\Get_Fields',
            'Easy_MCP_AI\\Tools\\ACF\\Update_Fields',
            'Easy_MCP_AI\\Tools\\ACF\\Get_User_Fields',
            'Easy_MCP_AI\\Tools\\ACF\\Update_User_Fields',
            'Easy_MCP_AI\\Tools\\ACF\\Get_Term_Fields',
            'Easy_MCP_AI\\Tools\\ACF\\List_Field_Groups',
            
            'Easy_MCP_AI\\Tools\\Events_Calendar\\List_Events',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Get_Event',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Create_Event',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Update_Event',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Delete_Event',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\List_Venues',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Get_Venue',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\List_Organizers',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Create_Venue',
            'Easy_MCP_AI\\Tools\\Events_Calendar\\Create_Organizer',
            
            'Easy_MCP_AI\\Tools\\Redirection\\List_Redirects',
            'Easy_MCP_AI\\Tools\\Redirection\\Get_Redirect',
            'Easy_MCP_AI\\Tools\\Redirection\\Create_Redirect',
            'Easy_MCP_AI\\Tools\\Redirection\\Update_Redirect',
            'Easy_MCP_AI\\Tools\\Redirection\\Delete_Redirects',
            'Easy_MCP_AI\\Tools\\Redirection\\List_Groups',
            'Easy_MCP_AI\\Tools\\Redirection\\List_404s',
            
            'Easy_MCP_AI\\Tools\\BuddyPress\\List_Members',
            'Easy_MCP_AI\\Tools\\BuddyPress\\Get_Member',
            'Easy_MCP_AI\\Tools\\BuddyPress\\List_Activity',
            'Easy_MCP_AI\\Tools\\BuddyPress\\Create_Activity',
            'Easy_MCP_AI\\Tools\\BuddyPress\\Delete_Activity',
            'Easy_MCP_AI\\Tools\\BuddyPress\\List_Groups',
            'Easy_MCP_AI\\Tools\\BuddyPress\\Get_Group',
            'Easy_MCP_AI\\Tools\\BuddyPress\\List_Group_Members',
            'Easy_MCP_AI\\Tools\\BuddyPress\\List_Message_Threads',
            'Easy_MCP_AI\\Tools\\BuddyPress\\Get_Message_Thread',
            
            'Easy_MCP_AI\\Tools\\SEO\\Yoast_Get_Head',
            'Easy_MCP_AI\\Tools\\SEO\\Yoast_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Yoast_Update_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Yoast_Get_Term_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Yoast_Update_Term_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Rankmath_Get_Head',
            'Easy_MCP_AI\\Tools\\SEO\\Rankmath_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Rankmath_Update_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Aioseo_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Aioseo_Update_Post_Seo',
            
            'Easy_MCP_AI\\Tools\\SEO\\Aioseo_Get_Breadcrumb',
            
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Update_Title_Desc',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Update_Robots',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Update_Social',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Update_Keywords',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Get_Content_Analysis',
            'Easy_MCP_AI\\Tools\\SEO\\Seopress_Get_Term_Seo',
            
            'Easy_MCP_AI\\Tools\\SEO\\Slimseo_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Slimseo_Update_Post_Seo',
            
            'Easy_MCP_AI\\Tools\\SEO\\Tsf_Get_Post_Seo',
            'Easy_MCP_AI\\Tools\\SEO\\Tsf_Update_Post_Seo',
            'Easy_MCP_AI\\Tools\\GSC\\List_Sites',
            'Easy_MCP_AI\\Tools\\GSC\\Get_Site',
            'Easy_MCP_AI\\Tools\\GSC\\Query_Performance',
            'Easy_MCP_AI\\Tools\\GSC\\List_Sitemaps',
            'Easy_MCP_AI\\Tools\\GSC\\Get_Sitemap',
            'Easy_MCP_AI\\Tools\\GSC\\Inspect_Url',

            'Easy_MCP_AI\\Tools\\GA\\List_Account_Summaries',
            'Easy_MCP_AI\\Tools\\GA\\Get_Property',
            'Easy_MCP_AI\\Tools\\GA\\List_Data_Streams',
            'Easy_MCP_AI\\Tools\\GA\\List_Custom_Dimensions',
            'Easy_MCP_AI\\Tools\\GA\\List_Custom_Metrics',
            'Easy_MCP_AI\\Tools\\GA\\List_Conversion_Events',
            'Easy_MCP_AI\\Tools\\GA\\Get_Metadata',
            'Easy_MCP_AI\\Tools\\GA\\Run_Report',
            'Easy_MCP_AI\\Tools\\GA\\Run_Pivot_Report',
            'Easy_MCP_AI\\Tools\\GA\\Run_Realtime_Report',
            'Easy_MCP_AI\\Tools\\GA\\Check_Compatibility',
            'Easy_MCP_AI\\Tools\\GA\\Create_Key_Event',
            'Easy_MCP_AI\\Tools\\GA\\Delete_Key_Event',

            
            'Easy_MCP_AI\\Tools\\DFS\\Serp_Google_Organic_Live',
            'Easy_MCP_AI\\Tools\\DFS\\Keywords_Search_Volume_Live',
            'Easy_MCP_AI\\Tools\\DFS\\Backlinks_Referring_Domains_Live',
            'Easy_MCP_AI\\Tools\\DFS\\On_Page_Instant_Pages',
            'Easy_MCP_AI\\Tools\\DFS\\Account_Balance',
            'Easy_MCP_AI\\Tools\\DFS\\Labs_Keywords_For_Site_Live',
            'Easy_MCP_AI\\Tools\\DFS\\Labs_Ranked_Keywords_Live',
            'Easy_MCP_AI\\Tools\\DFS\\Backlinks_Summary_Live',

            
            'Easy_MCP_AI\\Tools\\Posts\\Count_Posts',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Count_Terms',
            'Easy_MCP_AI\\Tools\\Media\\Count_Media',
            
            'Easy_MCP_AI\\Tools\\Revisions\\Restore_Revision',
            
            'Easy_MCP_AI\\Tools\\Meta\\Delete_Post_Meta',
            
            'Easy_MCP_AI\\Tools\\Taxonomy\\Get_Term_Meta',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Update_Term_Meta',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Delete_Term_Meta',
            
            'Easy_MCP_AI\\Tools\\Posts\\Add_Post_Terms',
            
            'Easy_MCP_AI\\Tools\\Users\\Get_User_Meta',
            'Easy_MCP_AI\\Tools\\Users\\Update_User_Meta',
            'Easy_MCP_AI\\Tools\\Users\\Delete_User_Meta',
            
            'Easy_MCP_AI\\Tools\\WooCommerce\\Batch_Update_Products',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Batch_Update_Orders',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Batch_Update_Variations',
            'Easy_MCP_AI\\Tools\\WooCommerce\\List_Product_Attributes',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Create_Product_Attribute',
            'Easy_MCP_AI\\Tools\\WooCommerce\\Set_Product_Attributes',

            
            
            'Easy_MCP_AI\\Tools\\Posts\\Get_Post_Full',
            'Easy_MCP_AI\\Tools\\Media\\Upload_Media_From_Url',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Get_Term',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Create_Term',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Update_Term',
            'Easy_MCP_AI\\Tools\\Taxonomy\\Delete_Term',
            'Easy_MCP_AI\\Tools\\Posts\\Replace_In_Post',
            
            'Easy_MCP_AI\\Tools\\Posts\\Create_Preview_Link',
            
            'Easy_MCP_AI\\Tools\\Cache\\Clear_Cache',

            
            'Easy_MCP_AI\\Tools\\Taxonomy\\List_Terms',

            
            'Easy_MCP_AI\\Tools\\History\\History_List',
            'Easy_MCP_AI\\Tools\\History\\History_Get',
            'Easy_MCP_AI\\Tools\\History\\History_Diff',
            
            'Easy_MCP_AI\\Tools\\History\\History_Apply',

            
            'Easy_MCP_AI\\Tools\\Audit\\Audit_List',
            
            'Easy_MCP_AI\\Tools\\Approvals\\Approve_Operation',
            'Easy_MCP_AI\\Tools\\Approvals\\Deny_Operation',
            
            
            'Easy_MCP_AI\\Tools\\Feedback\\Send_Feedback',
            
            'Easy_MCP_AI\\Tools\\Skills\\Get_Skill',
            
            'Easy_MCP_AI\\Tools\\Site\\List_Disabled_Tools',

            
            'Easy_MCP_AI\\Tools\\Cli\\Cli_Run',

            
            'Easy_MCP_AI\\Tools\\Site_Health\\Get_Site_Health',
            'Easy_MCP_AI\\Tools\\Site_Health\\List_Cron_Events',
            'Easy_MCP_AI\\Tools\\Site_Health\\Run_Cron_Event',
            'Easy_MCP_AI\\Tools\\Site_Health\\Get_Error_Log',
            'Easy_MCP_AI\\Tools\\Site_Health\\Get_Easy_Mcp_Diagnostics',
            
            'Easy_MCP_AI\\Tools\\Site_Health\\Update_Core',

            
            'Easy_MCP_AI\\Tools\\Rest\\Rest_Routes',
            'Easy_MCP_AI\\Tools\\Rest\\Rest_Read',
            'Easy_MCP_AI\\Tools\\Rest\\Rest_Write',
            'Easy_MCP_AI\\Tools\\Rest\\Rest_Delete',

            
            'Easy_MCP_AI\\Tools\\Semrush\\Domain_Overview',
            'Easy_MCP_AI\\Tools\\Semrush\\Domain_Organic_Keywords',
            'Easy_MCP_AI\\Tools\\Semrush\\Competitors_Organic',
            'Easy_MCP_AI\\Tools\\Semrush\\Keyword_Overview',
            'Easy_MCP_AI\\Tools\\Semrush\\Related_Keywords',
            'Easy_MCP_AI\\Tools\\Semrush\\Keyword_Difficulty',
            'Easy_MCP_AI\\Tools\\Semrush\\Phrase_Questions',
            'Easy_MCP_AI\\Tools\\Semrush\\Backlinks_Overview',
            'Easy_MCP_AI\\Tools\\Semrush\\Backlinks',
            'Easy_MCP_AI\\Tools\\Semrush\\Referring_Domains',
            'Easy_MCP_AI\\Tools\\Semrush\\Anchors',
            'Easy_MCP_AI\\Tools\\Semrush\\Url_Organic_Keywords',
            'Easy_MCP_AI\\Tools\\Semrush\\Api_Units_Balance',

            
            'Easy_MCP_AI\\Tools\\Ahrefs\\Domain_Rating_Free',

            
            'Easy_MCP_AI\\Tools\\SeRanking\\Account_Balance',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Overview',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Overview_Worldwide',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Keywords',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Competitors',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Keyword_Comparison',
            'Easy_MCP_AI\\Tools\\SeRanking\\Domain_Pages',
            'Easy_MCP_AI\\Tools\\SeRanking\\Keyword_Research',
            'Easy_MCP_AI\\Tools\\SeRanking\\Keywords_Overview',
            'Easy_MCP_AI\\Tools\\SeRanking\\Backlinks_Summary',
            'Easy_MCP_AI\\Tools\\SeRanking\\Backlinks_List',
            'Easy_MCP_AI\\Tools\\SeRanking\\Backlinks_Domain_Authority',
            'Easy_MCP_AI\\Tools\\SeRanking\\Ai_Overview',
            'Easy_MCP_AI\\Tools\\SeRanking\\Ai_Discover_Brand',
            'Easy_MCP_AI\\Tools\\SeRanking\\Ai_Prompts',

        );

        $dirs = null === $dirs ? Tool_Directories::enabled() : $dirs;
        foreach ( $tool_classes as $class ) {
            if ( self::is_withheld( $class ) ) {
                continue;
            }
            $dir = Tool_Directories::of( $class );
            if ( null === $dir ) {
                if ( ! $quiet && ! self::is_plugin_class( $class ) ) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                    error_log( 'Easy MCP AI: Tool class not found, skipping: ' . $class );
                }
                continue;
            }
            if ( ! in_array( $dir, $dirs, true ) ) {
                continue;
            }
            $this->lazy_classes[] = $class;
        }

        $this->register_privileged_tools();
    }

    






    private const PLUGIN_NAMESPACES = array(
        'Easy_MCP_AI\\Tools\\WooCommerce\\',
        'Easy_MCP_AI\\Tools\\ACF\\',
        'Easy_MCP_AI\\Tools\\Events_Calendar\\',
        'Easy_MCP_AI\\Tools\\Redirection\\',
        'Easy_MCP_AI\\Tools\\BuddyPress\\',
        'Easy_MCP_AI\\Tools\\SEO\\',
        'Easy_MCP_AI\\Tools\\GSC\\',
        'Easy_MCP_AI\\Tools\\GA\\',
        'Easy_MCP_AI\\Tools\\DFS\\',
        'Easy_MCP_AI\\Tools\\Semrush\\',
        'Easy_MCP_AI\\Tools\\Ahrefs\\',
        'Easy_MCP_AI\\Tools\\SeRanking\\',
    );

    







    private static function is_withheld( string $class ): bool {
        return 'Easy_MCP_AI\\Tools\\Feedback\\Send_Feedback' === $class && class_exists( '\\Easy_MCP_AI\\Config' ) && \Easy_MCP_AI\Config::is_white_labelled();
    }

    




    private static function is_plugin_class( string $class ): bool {
        foreach ( self::PLUGIN_NAMESPACES as $ns ) {
            if ( strncmp( $class, $ns, strlen( $ns ) ) === 0 ) {
                return true;
            }
        }
        return false;
    }

    





    private function register_privileged_tools(): void {
        if ( ! class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' ) ) {
            return;
        }
        foreach ( \Easy_MCP_AI\Privileged\Privileged_Groups::TOOL_CLASSES as $class ) {
            if ( class_exists( $class ) ) {
                $this->register( new $class() );
            }
        }
    }
}
