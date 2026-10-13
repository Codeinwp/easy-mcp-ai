<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Activate_Plugin extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_activate_plugin';
    }

    public function get_description() {
        return 'Activates an installed plugin, exactly as the Activate link on Plugins → Installed Plugins does. Required: `plugin` (the plugin file relative to the plugins directory, e.g. "hello-dolly/hello.php" — the `plugin` field from wp_list_plugins). Optional: `network_wide` (boolean, default false; multisite only, activates for every site in the network and requires a super admin with manage_network_plugins). Returns { plugin, name, version, status ("active" | "network-active"), changed (false when it was already active), network_wide, warning? }. Refused when your WordPress user may not activate that plugin, when a "Network: true" plugin is activated for a single site, or when the plugin is already network-active. WordPress checks the plugin\'s Requires WP / Requires PHP / Requires Plugins headers and its error message is returned as-is. Limit: activation loads the plugin\'s code in this request, so a plugin that throws a fatal error on load fails the whole call with no structured error (WordPress writes the plugin to the active list only after its file loads). Use wp_deactivate_plugin to reverse it.';
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
                    'description' => 'Multisite only: activate for every site in the network. Requires a super admin with manage_network_plugins. Default false.',
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

        
        
        
        
        if ( ! current_user_can( 'activate_plugin', $plugin ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to activate this plugin.' );
        }

        if ( $network_wide ) {
            $this->assert_network_wide_allowed();
        }

        $valid = validate_plugin( $plugin );
        if ( is_wp_error( $valid ) ) {
            throw new \RuntimeException( $valid->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( ! $network_wide ) {
            $this->refuse_site_level_conflicts( $plugin );
        }

        if ( $this->is_active_in_scope( $plugin, $network_wide ) ) {
            return $this->result( $plugin, $network_wide, false );
        }

        $warning = $this->activate( $plugin, $network_wide );
        $this->forget_recently_activated( $plugin, $network_wide );

        $result = $this->result( $plugin, $network_wide, true );
        if ( null !== $warning ) {
            $result['warning'] = $warning;
        }
        return $result;
    }

    






    private function refuse_site_level_conflicts( string $plugin ) {
        if ( ! is_multisite() ) {
            return;
        }
        
        
        
        
        if ( is_network_only_plugin( $plugin ) ) {
            throw new \RuntimeException( 'This plugin can only be activated network-wide. A super admin must activate it with network_wide: true.' );
        }
        
        
        
        if ( is_plugin_active_for_network( $plugin ) ) {
            throw new \RuntimeException( 'This plugin is already network-active; it cannot also be activated for a single site.' );
        }
    }

    







    private function activate( string $plugin, bool $network_wide ) {
        
        
        
        $activated = activate_plugin( $plugin, '', $network_wide );
        if ( ! is_wp_error( $activated ) ) {
            return null;
        }
        
        
        
        
        if ( 'unexpected_output' === $activated->get_error_code() && $this->is_active_in_scope( $plugin, $network_wide ) ) {
            return $activated->get_error_message();
        }
        throw new \RuntimeException( $activated->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private function forget_recently_activated( string $plugin, bool $network_wide ) {
        if ( $network_wide ) {
            $recent = (array) get_site_option( 'recently_activated' );
            unset( $recent[ $plugin ] );
            update_site_option( 'recently_activated', $recent );
            return;
        }
        $recent = (array) get_option( 'recently_activated' );
        unset( $recent[ $plugin ] );
        update_option( 'recently_activated', $recent, false );
    }

    





    private function result( string $plugin, bool $network_wide, bool $changed ): array {
        $installed = get_plugins();
        $data      = isset( $installed[ $plugin ] ) ? $installed[ $plugin ] : array();
        return array(
            'plugin'       => $plugin,
            'name'         => isset( $data['Name'] ) ? $data['Name'] : '',
            'version'      => isset( $data['Version'] ) ? $data['Version'] : '',
            'status'       => $network_wide ? 'network-active' : 'active',
            'changed'      => $changed,
            'network_wide' => $network_wide,
        );
    }
}
