<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Update_Plugin extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_update_plugin';
    }

    public function get_description() {
        return 'Updates one installed plugin to its available new version, exactly as the "Update now" link on the Plugins screen does (download, replace files, the plugin keeps its active state). Required: `plugin` (e.g. "akismet/akismet.php" — the `plugin` field from wp_list_plugin_updates). Returns { plugin, name, old_version, new_version, active (whether it is active after the update), network_active, messages (the upgrader log, last 20 lines, with every URL cut to its host because package URLs can carry licence keys) }. Check wp_list_plugin_updates first: refused when no update is available. Also refused when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress would need FTP/SSH credentials to write the files, for Easy MCP AI itself, and on multisite unless you are a super admin with manage_network_plugins. The update runs within this request and can take a minute; while an active plugin is being replaced WordPress puts the site in maintenance mode.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'update_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'plugin' => array(
                    'type'        => 'string',
                    'description' => 'Plugin file relative to the plugins directory, e.g. "akismet/akismet.php" (the `plugin` field from wp_list_plugin_updates).',
                ),
            ),
            'required'   => array( 'plugin' ),
        );
    }

    public function execute( array $arguments ) {
        
        
        if ( isset( $arguments['plugin'] ) && is_string( $arguments['plugin'] ) ) {
            $arguments['plugin'] = sanitize_text_field( $arguments['plugin'] );
        }
        $plugin = $this->parse_plugin_argument( $arguments );

        $this->assert_may_update( $plugin );

        $this->load_plugin_admin_functions();
        list( $name, $old_version ) = $this->installed_header( $plugin );

        $this->load_upgrader();
        $this->assert_filesystem_available( WP_PLUGIN_DIR );
        $this->raise_time_limit();

        
        wp_update_plugins();
        $this->assert_update_available( $plugin, $name, $old_version );

        
        
        
        
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result   = $upgrader->bulk_upgrade( array( $plugin ) );

        $item = $this->interpret_upgrade_result( $result, $skin, $upgrader, $plugin, 'Plugin update failed.' );

        return array(
            'plugin'         => $plugin,
            'name'           => $name,
            'old_version'    => $old_version,
            'new_version'    => $this->version_after_upgrade( $item ),
            'active'         => is_plugin_active( $plugin ),
            'network_active' => is_plugin_active_for_network( $plugin ),
            'messages'       => $this->upgrade_messages( $skin ),
        );
    }

    






    private function assert_may_update( string $plugin ) {
        $this->assert_file_mods_allowed( 'plugins' );

        
        if ( ! current_user_can( 'update_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to update plugins for this site.' );
        }
        $this->assert_network_update_allowed( 'manage_network_plugins' );

        if ( $this->is_own_plugin( $plugin ) ) {
            throw new \RuntimeException( 'Easy MCP AI cannot update itself over MCP: replacing its files mid-request would cut off this connection. Update it from wp-admin → Plugins or Dashboard → Updates.' );
        }
    }

    






    private function installed_header( string $plugin ): array {
        $installed = get_plugins();
        if ( ! isset( $installed[ $plugin ] ) ) {
            throw new \RuntimeException(
                sprintf( 'Plugin "%s" is not installed. Use wp_list_plugins to see installed plugins.', $plugin ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return array(
            isset( $installed[ $plugin ]['Name'] ) ? (string) $installed[ $plugin ]['Name'] : '',
            isset( $installed[ $plugin ]['Version'] ) ? (string) $installed[ $plugin ]['Version'] : '',
        );
    }

    





    private function assert_update_available( string $plugin, string $name, string $old_version ) {
        $response = $this->update_response( get_site_transient( 'update_plugins' ) );
        if ( ! isset( $response[ $plugin ] ) ) {
            throw new \RuntimeException(
                sprintf( 'No update is available for %s (installed version %s). Use wp_list_plugin_updates to see which plugins can be updated.', '' !== $name ? $name : $plugin, $old_version ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    









    private function version_after_upgrade( $item ): string {
        if ( ! is_array( $item ) || empty( $item['destination_name'] ) ) {
            return '';
        }
        $fresh = get_plugins( '/' . $item['destination_name'] );
        $fresh = is_array( $fresh ) ? reset( $fresh ) : false;
        return ( is_array( $fresh ) && ! empty( $fresh['Version'] ) ) ? (string) $fresh['Version'] : '';
    }
}
