<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-package-guard.php';











class Install_Plugin extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    public function get_name() {
        return 'wp_install_plugin';
    }

    public function get_description() {
        return 'Installs a plugin from the wordpress.org plugin directory by its slug, as the Install Now button on Plugins → Add New does. The plugin is NOT activated: call wp_activate_plugin with the returned `plugin` to activate it. Required: `slug` (the wordpress.org slug, e.g. "akismet"; a zip file, server path or URL is refused). Optional: `version` (install that wordpress.org version instead of the latest, or "dev" for the development version), `force` (boolean, default false: replace a copy that is already installed in the same folder). Returns { slug, plugin (the plugin file, e.g. "akismet/akismet.php"), name, version, changed, replaced, active, messages (the installer log, every URL cut to its host) }. Refused when the plugin is already installed (pass force: true to replace it, or use wp_update_plugin), when wordpress.org does not list the slug or version, when WordPress refuses the package (its Requires PHP / Requires at least headers), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the plugins folder or wp-content directly, on multisite unless you are a super admin with manage_network_plugins, and for Easy MCP AI\'s own folder. Off by default; an administrator switches it on. Runs within this request and reaches wordpress.org.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'install_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    
    public function is_disabled_by_default() {
        return true;
    }

    
    public function get_approval_policy( array $arguments ) {
        return 'always';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'slug'    => array(
                    'type'        => 'string',
                    'description' => 'The plugin\'s wordpress.org slug: the last part of its wordpress.org address, e.g. "akismet" for wordpress.org/plugins/akismet. Not a zip file, path or URL.',
                ),
                'version' => array(
                    'type'        => 'string',
                    'description' => 'Optional. A version wordpress.org lists for this plugin, e.g. "5.3.2", or "dev" for the development version. Omit to install the latest version.',
                ),
                'force'   => array(
                    'type'        => 'boolean',
                    'description' => 'Replace a copy that is already installed in the plugin\'s folder (its files are overwritten; it keeps its active state). Default false: an installed plugin is refused.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'slug' ),
        );
    }

    public function describe( array $arguments ) {
        try {
            $slug    = $this->parse_wporg_slug( $arguments, 'plugin' );
            $version = $this->parse_package_version( $arguments, true );
        } catch ( \Throwable $e ) {
            return parent::describe( $arguments );
        }
        return sprintf(
            /* translators: 1: plugin slug, 2: "the latest version" or "version 1.2", 3: optional replacement note */
            __( 'Install the plugin "%1$s" from wordpress.org (%2$s) into wp-content/plugins/%1$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            $slug,
            null === $version ? __( 'the latest version', 'easy-mcp-ai' ) : sprintf( /* translators: %s: version */ __( 'version %s', 'easy-mcp-ai' ), $version ),
            $this->parse_force( $arguments ) ? __( ', replacing any copy already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    public function execute( array $arguments ) {
        $slug    = $this->parse_wporg_slug( $arguments, 'plugin' );
        $version = $this->parse_package_version( $arguments, true );
        $force   = $this->parse_force( $arguments );

        $this->refuse_when_file_mods_are_off( 'plugins cannot be installed' );
        
        if ( ! current_user_can( 'install_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to install plugins on this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_plugins', 'installing plugins' );

        $this->load_plugin_admin_functions();
        $replacing = $this->refuse_existing_install( $slug, $force );

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( WP_PLUGIN_DIR, 'wp-content/plugins' );
        $this->raise_time_limit();

        $offer = $this->wporg_package( 'plugin', $slug, $version );

        
        
        
        
        $skin     = $this->new_upgrader_skin();
        $upgrader = $this->new_plugin_upgrader( $skin );
        $package  = $offer['package'];
        list( $result, $printed ) = $this->without_output(
            function () use ( $upgrader, $package, $replacing ) {
                return $upgrader->install( $package, array( 'overwrite_package' => $replacing ) );
            }
        );

        $this->interpret_install_result( $result, $skin, 'Plugin installation failed.' );

        return $this->result( $slug, $offer, $upgrader, $skin, $replacing, '' !== $printed );
    }

    



    private function parse_force( array $arguments ): bool {
        return isset( $arguments['force'] ) && rest_sanitize_boolean( $arguments['force'] );
    }

    





















    private function refuse_existing_install( string $slug, bool $force ): bool {
        self::refuse_self_reinstall( $slug, $force );

        $installed = get_plugins();
        $file      = self::plugin_in_folder( $installed, $slug );
        if ( null !== $file ) {
            if ( ! $force ) {
                throw new \RuntimeException( sprintf( 'The plugin "%s" is already installed as %s. Pass force: true to replace it, use wp_update_plugin to update it, or wp_activate_plugin to activate it.', $slug, $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return true;
        }

        $this->refuse_copy_in_another_folder( $installed, $slug );

        if ( $this->plugin_folder_exists( $slug ) ) {
            if ( ! $force ) {
                throw new \RuntimeException( sprintf( 'A folder named "%s" already exists in wp-content/plugins but holds no plugin. Pass force: true to replace it.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return true;
        }
        return false;
    }

    








    private static function refuse_self_reinstall( string $slug, bool $force ): void {
        $own = defined( 'EASY_MCP_AI_PLUGIN_BASENAME' ) ? (string) EASY_MCP_AI_PLUGIN_BASENAME : '';
        if ( $force && '' !== $own && false !== strpos( $own, '/' ) && dirname( $own ) === $slug ) {
            throw new \RuntimeException( 'Easy MCP AI cannot reinstall itself over MCP: replacing its files mid-request would cut off this connection. Use wp-admin → Plugins instead.' );
        }
    }

    






    private static function plugin_in_folder( array $installed, string $slug ): ?string {
        foreach ( array_keys( $installed ) as $file ) {
            if ( 0 === strpos( (string) $file, $slug . '/' ) ) {
                return (string) $file;
            }
        }
        return null;
    }

    









    private function refuse_copy_in_another_folder( array $installed, string $slug ): void {
        $transient = get_site_transient( 'update_plugins' );
        foreach ( array( 'response', 'no_update' ) as $bucket ) {
            $entries = ( is_object( $transient ) && isset( $transient->$bucket ) && is_array( $transient->$bucket ) ) ? $transient->$bucket : array();
            foreach ( $entries as $file => $entry ) {
                if ( isset( $installed[ $file ] ) && $slug === (string) $this->update_field( $entry, 'slug' ) ) {
                    throw new \RuntimeException( sprintf( 'The plugin "%s" is already installed as %s. Installing it again would add a second copy; use wp_update_plugin to update it.', $slug, $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
        }
    }

    



    protected function plugin_folder_exists( string $slug ): bool {
        return is_dir( WP_PLUGIN_DIR . '/' . $slug );
    }

    





    protected function new_plugin_upgrader( \WP_Ajax_Upgrader_Skin $skin ): object {
        return new \Plugin_Upgrader( $skin );
    }

    










    private function result( string $slug, array $offer, object $upgrader, \WP_Ajax_Upgrader_Skin $skin, bool $replacing, bool $printed ): array {
        $file = self::installed_main_file( $upgrader );
        $data = null !== $file ? self::installed_headers( $file ) : array();

        $result = array(
            'slug'     => $slug,
            'plugin'   => $file,
            'name'     => ! empty( $data['Name'] ) ? (string) $data['Name'] : $offer['name'],
            'version'  => ! empty( $data['Version'] ) ? (string) $data['Version'] : $offer['version'],
            'changed'  => true,
            'replaced' => $replacing,
            'active'   => null !== $file && ( is_plugin_active( $file ) || is_plugin_active_for_network( $file ) ),
            'messages' => $this->upgrade_messages( $skin ),
        );
        if ( null === $file ) {
            $result['note'] = 'WordPress installed the package but could not tell which file is its main plugin file; use wp_list_plugins to find it.';
        }
        if ( $printed ) {
            $result['output_discarded'] = true;
        }
        return $result;
    }

    






    private static function installed_main_file( object $upgrader ): ?string {
        $file = method_exists( $upgrader, 'plugin_info' ) ? $upgrader->plugin_info() : false;
        return is_string( $file ) && '' !== $file ? $file : null;
    }

    





    private static function installed_headers( string $file ): array {
        $installed = get_plugins();
        return isset( $installed[ $file ] ) ? $installed[ $file ] : array();
    }
}
