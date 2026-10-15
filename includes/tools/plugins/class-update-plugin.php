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
        return 'Updates one installed plugin to its available new version, exactly as the "Update now" link on the Plugins screen does (download, replace files, the plugin keeps its active state). Required: `plugin` (e.g. "akismet/akismet.php" — the `plugin` field from wp_list_plugin_updates). Optional: `version` — install that exact wordpress.org version instead (a downgrade is allowed; "dev" is the development version), as `wp plugin update --version` does; it needs the install_plugins capability as well and works only for a plugin wordpress.org\'s update data identifies as its own; refused when the plugin is already at that version or wordpress.org does not list it. Returns { plugin, name, old_version, new_version, active (whether it is active after the update), network_active, messages (the upgrader log, last 20 lines, with every URL cut to its host because package URLs can carry licence keys) }. Check wp_list_plugin_updates first: without `version`, refused when no update is available. Also refused when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress would need FTP/SSH credentials to write the files, for Easy MCP AI itself, and on multisite unless you are a super admin with manage_network_plugins. The update runs within this request and can take a minute; while an active plugin is being replaced WordPress puts the site in maintenance mode.';
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
                'plugin'  => array(
                    'type'        => 'string',
                    'description' => 'Plugin file relative to the plugins directory, e.g. "akismet/akismet.php" (the `plugin` field from wp_list_plugin_updates).',
                ),
                'version' => array(
                    'type'        => 'string',
                    'description' => 'Optional. Install this exact version from wordpress.org instead of the offered update, e.g. "5.3.2" (a downgrade is allowed), or "dev" for the development version. Omit to install the offered update. Needs install_plugins too.',
                ),
            ),
            'required'   => array( 'plugin' ),
        );
    }

    public function execute( array $arguments ) {
        
        
        if ( isset( $arguments['plugin'] ) && is_string( $arguments['plugin'] ) ) {
            $arguments['plugin'] = sanitize_text_field( $arguments['plugin'] );
        }
        $plugin  = $this->parse_plugin_argument( $arguments );
        $version = $this->parse_version_argument( $arguments );

        $this->assert_may_update( $plugin );
        if ( null !== $version ) {
            $this->assert_may_install_a_version();
        }

        $this->load_plugin_admin_functions();
        list( $name, $old_version ) = $this->installed_header( $plugin );

        $this->load_upgrader();
        $this->assert_filesystem_available( WP_PLUGIN_DIR );
        $this->raise_time_limit();

        
        wp_update_plugins();
        $offer = $this->chosen_offer( $plugin, $name, $old_version, $version );

        
        
        
        
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader( $skin );
        $result   = $this->run_bulk_upgrade( $upgrader, $plugin, $offer );
        self::refuse_ignored_offer( $offer, $result, $plugin, (string) $version );

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

    










    private function chosen_offer( string $plugin, string $name, string $old_version, ?string $version ): ?object {
        if ( null === $version ) {
            $this->assert_update_available( $plugin, $name, $old_version );
            return null;
        }
        return $this->version_offer( $plugin, '' !== $name ? $name : $plugin, $old_version, $version );
    }

    








    private function run_bulk_upgrade( \Plugin_Upgrader $upgrader, string $plugin, ?object $offer ) {
        $filter = null === $offer ? null : $this->offer_once( $plugin, $offer );
        try {
            return $upgrader->bulk_upgrade( array( $plugin ) );
        } finally {
            if ( null !== $filter ) {
                remove_filter( 'site_transient_update_plugins', $filter, PHP_INT_MAX );
            }
        }
    }

    












    private static function refuse_ignored_offer( ?object $offer, $result, string $plugin, string $version ): void {
        if ( null !== $offer && is_array( $result ) && isset( $result[ $plugin ] ) && true === $result[ $plugin ] ) {
            throw new \RuntimeException( sprintf( 'Version %s was not installed: WordPress read its update data without the chosen version (a plugin on this site replaces that data), so nothing changed.', $version ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
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

    










    private function parse_version_argument( array $arguments ): ?string {
        if ( ! isset( $arguments['version'] ) ) {
            return null;
        }
        $raw = $arguments['version'];
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( '"version" must be a version string such as "5.3.2", or "dev" for the development version. Omit it to install the offered update.' );
        }
        $version = trim( $raw );
        if ( strlen( $version ) > 64 || ! preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_-]*\z/', $version ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid version "%s". Pass a version wordpress.org lists for this plugin, such as "5.3.2", or "dev".', substr( $version, 0, 64 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $version;
    }

    









    private function assert_may_install_a_version(): void {
        if ( ! current_user_can( 'install_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, installing a chosen plugin version also needs the install_plugins capability: wp-admin installs a specific version only through Upload Plugin, which requires it.' );
        }
    }

    























    private function version_offer( string $plugin, string $label, string $old_version, string $version ): object {
        $entry = $this->update_entry( $plugin );
        $slug  = null !== $entry ? (string) $this->update_field( $entry, 'slug' ) : '';

        if ( '' === $slug || 'w.org/plugins/' . $slug !== (string) $this->update_field( $entry, 'id' ) ) {
            throw new \RuntimeException( sprintf( '%s cannot be installed at a chosen version: wordpress.org\'s update data does not identify it as a wordpress.org plugin, so there is no version list to install from.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'dev' !== $version && $version === $old_version ) {
            throw new \RuntimeException( sprintf( '%s is already at version %s; nothing was installed.', $label, $version ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $package = $this->version_package( $slug, $version );

        $offer              = is_object( $entry ) ? clone $entry : (object) $entry;
        $offer->new_version = $version;
        $offer->package     = $package;
        
        
        
        unset( $offer->requires, $offer->requires_php, $offer->tested, $offer->upgrade_notice );
        return $offer;
    }

    






    private function update_entry( string $plugin ) {
        $transient = get_site_transient( 'update_plugins' );
        $response  = $this->update_response( $transient );
        $no_update = ( is_object( $transient ) && isset( $transient->no_update ) && is_array( $transient->no_update ) ) ? $transient->no_update : array();
        return isset( $response[ $plugin ] ) ? $response[ $plugin ] : ( isset( $no_update[ $plugin ] ) ? $no_update[ $plugin ] : null );
    }

    










    private function version_package( string $slug, string $version ): string {
        $versions = $this->version_list( $slug );
        $key      = 'dev' === $version ? 'trunk' : $version;
        if ( 'trunk' === $version || ! isset( $versions[ $key ] ) || ! is_string( $versions[ $key ] ) ) {
            $listed = array_values( array_diff( array_map( 'strval', array_keys( $versions ) ), array( 'trunk' ) ) );
            usort( $listed, 'version_compare' );
            $listed = array_slice( $listed, -8 );
            throw new \RuntimeException( sprintf( 'wordpress.org lists no version "%s" of %s.%s', $version, $slug, empty( $listed ) ? '' : ' Latest listed: ' . implode( ', ', $listed ) . '.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $package = $versions[ $key ];
        if ( 0 !== strpos( $package, 'https://downloads.wordpress.org/plugin/' ) ) {
            throw new \RuntimeException( sprintf( 'Version %s of %s is not offered from downloads.wordpress.org (a plugin on this site answers wordpress.org\'s plugin information requests), so it was not installed.', $version, $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $package;
    }

    






    private function version_list( string $slug ): array {
        if ( ! function_exists( 'plugins_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        $info = plugins_api(
            'plugin_information',
            array(
                'slug'   => $slug,
                'fields' => array(
                    'versions' => true,
                    'sections' => false,
                ),
            )
        );
        if ( is_wp_error( $info ) ) {
            throw new \RuntimeException( $this->redact_urls( sprintf( 'wordpress.org did not return the version list for %s: %s', $slug, $info->get_error_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return ( is_object( $info ) && isset( $info->versions ) && ( is_array( $info->versions ) || is_object( $info->versions ) ) ) ? (array) $info->versions : array();
    }

    












    private function offer_once( string $plugin, object $offer ): \Closure {
        $filter = null;
        $filter = static function ( $value ) use ( &$filter, $plugin, $offer ) {
            if ( ! is_object( $value ) ) {
                return $value;
            }
            remove_filter( 'site_transient_update_plugins', $filter, PHP_INT_MAX );
            
            $value              = clone $value;
            $response           = ( isset( $value->response ) && is_array( $value->response ) ) ? $value->response : array();
            $response[ $plugin ] = $offer;
            $value->response    = $response;
            return $value;
        };
        add_filter( 'site_transient_update_plugins', $filter, PHP_INT_MAX );
        return $filter;
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
