<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Plugin_Integrations_Page {

    







    public static function compute_state( array $submitted_groups, array $submitted_tools ): array {
        $all_groups = Plugin_Integration_Registry::get_groups();

        
        
        
        
        $enabled_groups = array();
        foreach ( $all_groups as $group ) {
            if ( ! Plugin_Integration_Registry::is_installed( $group ) ) {
                continue;
            }
            $group_checked = in_array( $group['slug'], $submitted_groups, true );
            $any_tool_checked = false;
            foreach ( $group['tools'] as $tool ) {
                if ( in_array( $tool['name'], $submitted_tools, true ) ) {
                    $any_tool_checked = true;
                    break;
                }
            }
            if ( $group_checked || $any_tool_checked ) {
                $enabled_groups[] = $group['slug'];
            }
        }

        
        
        $disabled_plugin_tools = array();
        foreach ( $all_groups as $group ) {
            if ( ! in_array( $group['slug'], $enabled_groups, true ) ) {
                
                foreach ( $group['tools'] as $tool ) {
                    $disabled_plugin_tools[] = $tool['name'];
                }
                continue;
            }
            foreach ( $group['tools'] as $tool ) {
                if ( ! in_array( $tool['name'], $submitted_tools, true ) ) {
                    $disabled_plugin_tools[] = $tool['name'];
                }
            }
        }

        return array(
            'enabled_groups'        => $enabled_groups,
            'disabled_plugin_tools' => $disabled_plugin_tools,
        );
    }

    






    public static function persist( array $enabled_groups, array $disabled_plugin_tools ) {
        \update_option( 'easy_mcp_ai_enabled_plugin_groups', $enabled_groups );
        
        
        
        
        
        \Easy_MCP_AI\Config::update( 'easy_mcp_ai_disabled_plugin_tools', $disabled_plugin_tools );

        
        
        
        $all_plugin_tool_names = Plugin_Integration_Registry::get_all_tool_names();
        $global_disabled       = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() );
        $non_plugin            = array_values( array_diff( $global_disabled, $all_plugin_tool_names ) );
        
        
        
        
        
        
        \Easy_MCP_AI\Config::update( 'easy_mcp_ai_disabled_tools',
            \Easy_MCP_AI\Admin\External_Data_Admin::merge_disabled_tool_buckets(
                array_merge( $non_plugin, $disabled_plugin_tools )
            )
        );
    }
}
