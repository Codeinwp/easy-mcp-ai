<?php








namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_Groups {

    
    private $tool_registry;

    


    public function __construct( $tool_registry ) {
        $this->tool_registry = $tool_registry;
    }

    




    const CORE_CATEGORY_LABELS = array(
        'posts'     => 'Posts',
        'pages'     => 'Pages',
        'media'     => 'Media',
        'taxonomy'  => 'Taxonomy',
        'comments'  => 'Comments',
        'users'     => 'Users',
        'site'      => 'Site Settings',
        'menus'     => 'Menus',
        'plugins'   => 'Plugins',
        'themes'    => 'Themes',
        'revisions' => 'Revisions',
        'meta'      => 'Post Meta',
        'search'    => 'Search',
        'blocks'    => 'Blocks',
        'cpt'       => 'Custom Post Types',
        'templates' => 'Templates',
        'styles'    => 'Global Styles',
        'appearance' => 'Appearance',
        'widgets'   => 'Widgets & Sidebars',
        'history'   => 'Change History',
        'audit'     => 'Audit Log',
        'site_health' => 'Site Health, Cron & Error Log',
        'skills'    => 'Skills',
        
        'cli'       => 'WP-CLI commands',
        
        'rest'      => 'REST Routes (plugins and core)',
        
        'cache'     => 'Cache',
        
        'mail'      => 'Email',
        
        'builders'  => 'Page Builders',
        'general'   => 'General',
    );

    









    public function build() {
        $core_category_labels = self::CORE_CATEGORY_LABELS;

        
        $known_plugins = array(
            'woocommerce'     => array( 'label' => 'WooCommerce',                 'class' => 'WooCommerce',             'fn' => 'WC' ),
            'acf'             => array( 'label' => 'Advanced Custom Fields (ACF)','class' => 'ACF',                     'fn' => 'acf' ),
            'events-calendar' => array( 'label' => 'The Events Calendar',         'class' => 'Tribe__Events__Main',     'fn' => '' ),
            'redirection'     => array( 'label' => 'Redirection',                 'class' => 'Red_Item',                'fn' => '' ),
            'sitekit'         => array( 'label' => 'Site Kit by Google',          'class' => 'Google\\Site_Kit\\Plugin', 'fn' => '' ),
            'contact-form-7'  => array( 'label' => 'Contact Form 7',              'class' => 'WPCF7_ContactForm',       'fn' => '' ),
            'buddypress'      => array( 'label' => 'BuddyPress',                  'class' => 'BuddyPress',              'fn' => 'bp_is_active' ),
            'yoast-seo'       => array( 'label' => 'Yoast SEO',                   'class' => 'WPSEO_Options',           'fn' => '' ),
            'rank-math'       => array( 'label' => 'Rank Math SEO',               'class' => 'RankMath',                'fn' => '' ),
            'aioseo'          => array( 'label' => 'All in One SEO',              'class' => 'AIOSEO\Plugin\AIOSEO',    'fn' => 'aioseo' ),
            'seopress'          => array( 'label' => 'SEOPress',            'class' => '',                  'fn' => 'seopress_get_service' ),
            'slim-seo'          => array( 'label' => 'Slim SEO',            'class' => 'SlimSEO\Container', 'fn' => '' ),
            'the-seo-framework' => array( 'label' => 'The SEO Framework',   'class' => '',                  'fn' => 'tsf' ),
        );

        
        
        
        $known_external = array(
            'gsc' => array( 'label' => 'Google Search Console', 'option' => 'easy_mcp_ai_gsc_service_account_json' ),
            'ga'  => array( 'label' => 'Google Analytics',      'option' => 'easy_mcp_ai_ga_service_account_json' ),
            'dfs' => array( 'label' => 'DataforSEO',             'option' => 'easy_mcp_ai_dfs_login' ),
            'semrush' => array( 'label' => 'Semrush',             'option' => 'easy_mcp_ai_semrush_api_key' ),
            'seranking' => array( 'label' => 'SE Ranking',         'option' => 'easy_mcp_ai_seranking_api_key' ),
            
            'ahrefs' => array( 'label' => 'Ahrefs (DR)',        'option' => 'easy_mcp_ai_ahrefs_api_key' ),
        );

        $tools_by_category = $this->tool_registry->get_tools_by_category();

        
        $core           = array();
        $total          = 0;
        $ability_defs   = array();
        $disabled_tools   = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() );
        $allowed_patterns = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() );
        $off_tools = \Easy_MCP_AI\Tools\Tool_State::off_names( $this->tool_registry );

        
        
        
        
        $is_tool_active = function ( $tool ) use ( $off_tools, $allowed_patterns ) {
            $name = $tool['name'];
            if ( in_array( $name, $off_tools, true ) ) {
                return false;
            }
            return \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $allowed_patterns );
        };

        foreach ( $tools_by_category as $category => $tools ) {
            $active_tools = array_values( array_filter( $tools, $is_tool_active ) );
            $total       += count( $active_tools );
            if ( 'abilities' === $category ) {
                $ability_defs = $tools;
            } elseif ( isset( $core_category_labels[ $category ] ) ) {
                $core[ $core_category_labels[ $category ] ] = $active_tools;
            }
        }

        
        $plugins = array();
        foreach ( $known_plugins as $category => $info ) {
            $installed = ( ! empty( $info['class'] ) && \class_exists( $info['class'] ) )
                || ( ! empty( $info['fn'] ) && \function_exists( $info['fn'] ) );
            $tools     = isset( $tools_by_category[ $category ] )
                ? array_values( array_filter( $tools_by_category[ $category ], $is_tool_active ) )
                : array();

            if ( ! $installed ) {
                $status = 'not_installed';
            } elseif ( empty( $tools ) ) {
                $status = 'no_tools';
            } else {
                $status = 'active';
            }

            $plugins[ $info['label'] ] = array(
                'key'    => $category,
                'status' => $status,
                'tools'  => $tools,
            );
        }

        
        $external = array();
        foreach ( $known_external as $category => $info ) {
            
            $configured = \Easy_MCP_AI\Provider_Credentials::is_configured( $category );
            $tools      = isset( $tools_by_category[ $category ] )
                ? array_values( array_filter( $tools_by_category[ $category ], $is_tool_active ) )
                : array();

            if ( ! $configured ) {
                $status = 'not_configured';
            } elseif ( empty( $tools ) ) {
                $status = 'no_tools';
            } else {
                $status = 'active';
            }

            $external[ $info['label'] ] = array(
                'key'     => $category,
                'status'  => $status,
                'tools'   => $tools,
            );
        }

        
        $abilities = array();

        $ability_def_by_name = array();
        foreach ( $ability_defs as $def ) {
            $ability_def_by_name[ $def['name'] ] = $def;
        }

        
        $normalize_slug = function ( $s ) {
            return strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $s ) );
        };

        
        $resolve_ability = function ( $ability, $enabled_abilities ) use ( $ability_def_by_name ) {
            return self::resolve_ability_tool_def( $ability->get_name(), $enabled_abilities, $ability_def_by_name );
        };

        if ( function_exists( 'wp_get_abilities' ) ) {
            $wp_abilities      = \wp_get_abilities();
            $enabled_abilities = (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() );

            
            
            $by_prefix = array();
            foreach ( $wp_abilities as $ability ) {
                $name        = $ability->get_name();
                $parts       = explode( '/', $name, 2 );
                $raw_prefix  = count( $parts ) > 1 ? $parts[0] : 'core';
                $prefix_norm = $normalize_slug( $raw_prefix );
                if ( ! isset( $by_prefix[ $prefix_norm ] ) ) {
                    $by_prefix[ $prefix_norm ] = array( 'raw_prefix' => $raw_prefix, 'abilities' => array() );
                }
                $by_prefix[ $prefix_norm ]['abilities'][] = $ability;
            }

            
            if ( isset( $by_prefix['core'] ) ) {
                $tools        = array();
                $any_not_enabled = false;
                foreach ( $by_prefix['core']['abilities'] as $ability ) {
                    $r = $resolve_ability( $ability, $enabled_abilities );
                    if ( ! $r['is_enabled'] ) { $any_not_enabled = true; }
                    if ( $r['tool_def'] ) { $tools[] = $r['tool_def']; }
                }
                $abilities['Core'] = array(
                    'tools'           => $tools,
                    'is_known'        => false,
                    'has_abilities'   => true,
                    'any_not_enabled' => $any_not_enabled,
                );
                unset( $by_prefix['core'] );
            }

            
            $known_norms = array();
            foreach ( $known_plugins as $category => $info ) {
                $known_norms[ $normalize_slug( $info['label'] ) ] = true;
                $known_norms[ $normalize_slug( $category ) ]      = true;
            }

            
            $matched_prefixes = array();

            if ( function_exists( 'get_plugins' ) ) {
                $all_plugins  = \get_plugins();
                $active_paths = (array) \get_option( 'active_plugins', array() );
                if ( \is_multisite() ) {
                    $active_paths = array_merge( $active_paths, array_keys( (array) \get_site_option( 'active_sitewide_plugins', array() ) ) );
                }

                foreach ( $active_paths as $plugin_path ) {
                    if ( ! isset( $all_plugins[ $plugin_path ] ) ) { continue; }
                    $plugin_name = $all_plugins[ $plugin_path ]['Name'];
                    if ( false !== stripos( $plugin_name, 'Easy MCP' ) ) { continue; }

                    $folder = ( false !== strpos( $plugin_path, '/' ) )
                        ? explode( '/', $plugin_path )[0]
                        : pathinfo( $plugin_path, PATHINFO_FILENAME );
                    $folder_norm = $normalize_slug( $folder );

                    
                    $tools           = array();
                    $has_abilities   = false;
                    $any_not_enabled = false;
                    if ( isset( $by_prefix[ $folder_norm ] ) ) {
                        $has_abilities = true;
                        foreach ( $by_prefix[ $folder_norm ]['abilities'] as $ability ) {
                            $r = $resolve_ability( $ability, $enabled_abilities );
                            if ( ! $r['is_enabled'] ) { $any_not_enabled = true; }
                            if ( $r['tool_def'] ) { $tools[] = $r['tool_def']; }
                        }
                        $matched_prefixes[ $folder_norm ] = true;
                    }

                    $is_known = isset( $known_norms[ $folder_norm ] )
                        || isset( $known_norms[ $normalize_slug( $plugin_name ) ] );

                    $abilities[ $plugin_name ] = array(
                        'tools'           => $tools,
                        'is_known'        => $is_known,
                        'has_abilities'   => $has_abilities,
                        'any_not_enabled' => $any_not_enabled,
                    );
                }
            }

            
            foreach ( $by_prefix as $prefix_norm => $data ) {
                if ( isset( $matched_prefixes[ $prefix_norm ] ) ) { continue; }
                $label = ucwords( str_replace( array( '-', '_' ), ' ', $data['raw_prefix'] ) );
                if ( isset( $abilities[ $label ] ) ) { continue; }

                $tools           = array();
                $any_not_enabled = false;
                foreach ( $data['abilities'] as $ability ) {
                    $r = $resolve_ability( $ability, $enabled_abilities );
                    if ( ! $r['is_enabled'] ) { $any_not_enabled = true; }
                    if ( $r['tool_def'] ) { $tools[] = $r['tool_def']; }
                }
                $abilities[ $label ] = array(
                    'tools'           => $tools,
                    'is_known'        => false,
                    'has_abilities'   => true,
                    'any_not_enabled' => $any_not_enabled,
                );
            }

            
            $core_entry = isset( $abilities['Core'] ) ? $abilities['Core'] : null;
            unset( $abilities['Core'] );
            ksort( $abilities );
            if ( null !== $core_entry ) {
                $abilities = array_merge( array( 'Core' => $core_entry ), $abilities );
            }
        } else {
            
            $abilities['Core'] = array(
                'tools'           => $ability_defs,
                'is_known'        => false,
                'has_abilities'   => ! empty( $ability_defs ),
                'any_not_enabled' => false,
            );
        }

        
        
        
        
        $bucket_disables       = \Easy_MCP_AI\Admin\External_Data_Admin::merge_disabled_tool_buckets();
        $settings_only_disabled = array_diff( $disabled_tools, $bucket_disables );
        $has_global_overrides   = ! empty( $settings_only_disabled ) || ! empty( $allowed_patterns );

        
        
        $disabled_abilities_present = false;
        foreach ( $abilities as $g ) {
            if ( ! empty( $g['any_not_enabled'] ) ) {
                $disabled_abilities_present = true;
                break;
            }
        }

        
        
        $enabled_plugin_groups     = (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        $disabled_plugin_tools_raw = (array) \get_option( 'easy_mcp_ai_disabled_plugin_tools', array() );
        $disabled_plugin_tools_present = false;
        foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
            if ( ! Plugin_Integration_Registry::is_installed( $group ) ) {
                continue;
            }
            
            if ( ! in_array( $group['slug'], $enabled_plugin_groups, true ) ) {
                $disabled_plugin_tools_present = true;
                break;
            }
            
            $group_tool_names = array_column( $group['tools'], 'name' );
            if ( ! empty( array_intersect( $disabled_plugin_tools_raw, $group_tool_names ) ) ) {
                $disabled_plugin_tools_present = true;
                break;
            }
        }

        
        $ga_configured       = \Easy_MCP_AI\Provider_Credentials::is_configured( 'ga' );
        $gsc_configured      = \Easy_MCP_AI\Provider_Credentials::is_configured( 'gsc' );
        $dfs_configured      = \Easy_MCP_AI\Provider_Credentials::is_configured( 'dfs' );
        $semrush_configured  = \Easy_MCP_AI\Provider_Credentials::is_configured( 'semrush' );
        $seranking_configured = \Easy_MCP_AI\Provider_Credentials::is_configured( 'seranking' );
        $disabled_ga_present  = $ga_configured  && ! empty( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_ga_tools', array() ) );
        $disabled_gsc_present = $gsc_configured && ! empty( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_gsc_tools', array() ) );
        $disabled_dfs_present = $dfs_configured && ! empty( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_dfs_tools', array() ) );
        $disabled_semrush_present   = $semrush_configured && ! empty( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_semrush_tools', array() ) );
        $disabled_seranking_present = $seranking_configured && ! empty( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_seranking_tools', array() ) );

        
        $ga_missing       = ! $ga_configured;
        $gsc_missing      = ! $gsc_configured;
        $dfs_missing      = ! $dfs_configured;
        $semrush_missing  = ! $semrush_configured;
        $seranking_missing = ! $seranking_configured;
        
        $ahrefs_disabled  = ! \Easy_MCP_AI\Provider_Credentials::is_configured( 'ahrefs' )
            || ! (bool) \get_option( 'easy_mcp_ai_ahrefs_enabled', false );

        return array(
            'total'    => $total,
            'core'     => $core,
            'plugins'  => $plugins,
            'external' => $external,
            'abilities'=> $abilities,
            'hints'    => array(
                'has_global_overrides'         => $has_global_overrides,
                'has_allowed_patterns'         => ! empty( $allowed_patterns ),
                'disabled_abilities_present'   => $disabled_abilities_present,
                'disabled_plugin_tools_present'=> $disabled_plugin_tools_present,
                'disabled_ga_present'          => $disabled_ga_present,
                'disabled_gsc_present'         => $disabled_gsc_present,
                'disabled_dfs_present'         => $disabled_dfs_present,
                'disabled_semrush_present'     => $disabled_semrush_present,
                'disabled_seranking_present'   => $disabled_seranking_present,
                'ga_missing'                   => $ga_missing,
                'gsc_missing'                  => $gsc_missing,
                'dfs_missing'                  => $dfs_missing,
                'semrush_missing'              => $semrush_missing,
                'seranking_missing'            => $seranking_missing,
                'ahrefs_disabled'              => $ahrefs_disabled,
            ),
        );
    }


    















    public static function resolve_ability_tool_def( $ability_name, array $enabled_abilities, array $ability_def_by_name ) {
        $is_enabled = in_array( $ability_name, $enabled_abilities, true );
        $tool_name  = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::build_tool_name( $ability_name );
        $tool_def   = isset( $ability_def_by_name[ $tool_name ] ) ? $ability_def_by_name[ $tool_name ] : null;
        return array( 'is_enabled' => $is_enabled, 'tool_def' => $tool_def );
    }
}
