<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Deactivate_Plugin extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_deactivate_plugin';
    }

    public function get_description() {
        return 'Deactivates an active plugin, exactly as the Deactivate link on Plugins → Installed Plugins does (deactivation hooks run; nothing is deleted). Required: `plugin` (e.g. "hello-dolly/hello.php" — the `plugin` field from wp_list_plugins). Optional: `network_wide` (boolean, default false; multisite only, deactivates a network-active plugin for every site and requires a super admin with manage_network_plugins). Returns { plugin, name, status ("inactive"), changed (false when it was already inactive), network_wide }. Refused when the plugin is not installed, when your WordPress user may not deactivate that plugin, when a network-active plugin is deactivated for a single site (a super admin must use network_wide: true), and always for Easy MCP AI itself — deactivating it would cut off this connection. Use wp_activate_plugin to reverse it.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'activate_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'plugin'       => array(
                    'type'        => 'string',
                    'description' => 'Plugin file relative to the plugins directory, e.g. "hello-dolly/hello.php" (the `plugin` field from wp_list_plugins).',
                ),
                'network_wide' => array(
                    'type'        => 'boolean',
                    'description' => 'Multisite only: deactivate a network-active plugin for every site. Requires a super admin with manage_network_plugins. Default false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'plugin' ),
        );
    }

    public function execute( array $arguments ) {
        $plugin       = $this->parse_plugin_argument( $arguments );
        $network_wide = $this->parse_network_wide( $arguments );

        $this->load_plugin_admin_functions();

        
        if ( ! current_user_can( 'deactivate_plugin', $plugin ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to deactivate this plugin.' );
        }

        
        
        if ( $this->is_own_plugin( $plugin ) ) {
            throw new \RuntimeException( 'Easy MCP AI cannot deactivate itself: this MCP connection runs through it. Deactivate it from wp-admin → Plugins instead.' );
        }

        if ( $network_wide ) {
            $this->assert_network_wide_allowed();
        } elseif ( is_plugin_active_for_network( $plugin ) ) {
            
            
            throw new \RuntimeException( 'This plugin is network-active. Only a super admin can deactivate it, with network_wide: true.' );
        }

        if ( ! $this->is_active_in_scope( $plugin, $network_wide ) ) {
            
            
            
            
            
            if ( ! isset( get_plugins()[ $plugin ] ) ) {
                throw new \RuntimeException(
                    sprintf( 'Plugin "%s" is not installed. Use wp_list_plugins to see installed plugins.', $plugin ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            return $this->result( $plugin, $network_wide, false );
        }

        
        
        deactivate_plugins( $plugin, false, $network_wide );

        
        if ( $network_wide ) {
            update_site_option( 'recently_activated', array( $plugin => time() ) + (array) get_site_option( 'recently_activated' ) );
        } else {
            update_option( 'recently_activated', array( $plugin => time() ) + (array) get_option( 'recently_activated' ), false );
        }

        return $this->result( $plugin, $network_wide, true );
    }

    





    private function result( string $plugin, bool $network_wide, bool $changed ): array {
        $installed = get_plugins();
        $data      = isset( $installed[ $plugin ] ) ? $installed[ $plugin ] : array();
        return array(
            'plugin'       => $plugin,
            'name'         => isset( $data['Name'] ) ? $data['Name'] : '',
            'status'       => 'inactive',
            'changed'      => $changed,
            'network_wide' => $network_wide,
        );
    }
}
