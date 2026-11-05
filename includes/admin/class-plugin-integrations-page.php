<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Plugin_Integrations_Page {

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
