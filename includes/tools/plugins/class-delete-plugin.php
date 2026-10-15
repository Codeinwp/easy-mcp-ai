<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-package-guard.php';














class Delete_Plugin extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    public function get_name() {
        return 'wp_delete_plugin';
    }

    public function get_description() {
        return 'Deletes an inactive plugin\'s files from wp-content/plugins WITHOUT running its uninstall routine, as `wp plugin delete` does: the plugin\'s settings and database tables stay. To remove those too, use wp_uninstall_plugin. Required: `plugin` (e.g. "hello-dolly/hello.php" — the `plugin` field from wp_list_plugins). Returns { plugin, name, version, deleted, changed, uninstall_ran (always false) }. Irreversible: the files are not backed up and change history cannot restore them. Refused when the plugin is not installed, when it is active or network-active (deactivate it first with wp_deactivate_plugin), when other installed plugins require it, when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the plugins folder or wp-content directly, on multisite unless you are a super admin with manage_network_plugins, and for Easy MCP AI itself. Off by default; an administrator switches it on.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'delete_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
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
                'plugin' => array(
                    'type'        => 'string',
                    'description' => 'Plugin file relative to the plugins directory, e.g. "hello-dolly/hello.php" (the `plugin` field from wp_list_plugins).',
                ),
            ),
            'required'   => array( 'plugin' ),
        );
    }

    public function describe( array $arguments ) {
        return $this->describe_removal(
            $arguments,
            /* translators: 1: quoted plugin name and version, 2: plugin file */
            __( 'Delete the files of plugin %1$s (%2$s). Its uninstall routine does not run, so its settings and tables stay. The files cannot be restored.', 'easy-mcp-ai' )
        );
    }

    public function execute( array $arguments ) {
        $plugin = $this->parse_plugin_argument( $arguments );

        $this->refuse_when_file_mods_are_off( 'plugins cannot be deleted' );
        
        if ( ! current_user_can( 'delete_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to delete plugins for this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_plugins', 'deleting plugins' );
        $this->refuse_own_plugin_files( $plugin, 'deleting it would cut off this MCP connection' );

        $this->load_plugin_admin_functions();
        $data  = $this->installed_plugin( $plugin );
        $label = $this->plugin_label( $plugin, $data );
        
        
        
        
        if ( is_plugin_active_for_network( $plugin ) ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" is network-active. A super admin must deactivate it network-wide first (wp_deactivate_plugin with network_wide: true).', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( is_plugin_active( $plugin ) ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" is active. Deactivate it first with wp_deactivate_plugin: WordPress never deletes an active plugin.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $this->refuse_required_plugin( $plugin, $label );

        $this->refuse_unless_direct_filesystem( WP_PLUGIN_DIR, 'wp-content/plugins' );

        list( $deleted, $printed ) = $this->without_output(
            function () use ( $plugin ) {
                return $this->remove_plugin_files( $plugin );
            }
        );
        if ( is_wp_error( $deleted ) ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" was not deleted: %s', $plugin, $this->upgrader_error_text( $deleted ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( true !== $deleted ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" could not be deleted.', $plugin ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $result = array(
            'plugin'        => $plugin,
            'name'          => isset( $data['Name'] ) ? (string) $data['Name'] : '',
            'version'       => isset( $data['Version'] ) ? (string) $data['Version'] : '',
            'deleted'       => true,
            'changed'       => true,
            'uninstall_ran' => false,
        );
        if ( '' !== $printed ) {
            $result['output_discarded'] = true;
        }
        return $result;
    }

    






    protected function remove_plugin_files( string $plugin ) {
        global $wp_filesystem;
        $plugins_dir = self::filesystem_plugins_dir();
        if ( is_wp_error( $plugins_dir ) ) {
            return $plugins_dir;
        }

        
        do_action( 'delete_plugin', $plugin ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own hook, fired as delete_plugins() fires it.

        
        $this_plugin_dir = rtrim( dirname( $plugins_dir . $plugin ), '/' ) . '/';
        if ( strpos( $plugin, '/' ) && $this_plugin_dir !== $plugins_dir ) {
            $deleted = $wp_filesystem->delete( $this_plugin_dir, true );
        } else {
            $deleted = $wp_filesystem->delete( $plugins_dir . $plugin );
        }

        
        do_action( 'deleted_plugin', $plugin, $deleted ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own hook.

        if ( ! $deleted ) {
            return new \WP_Error( 'could_not_remove_plugin', sprintf( 'Could not fully remove the plugin %s.', $plugin ) );
        }
        
        
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache( false );
        }
        return true;
    }

    







    private static function filesystem_plugins_dir() {
        global $wp_filesystem;
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
            return new \WP_Error( 'fs_unavailable', 'Could not access filesystem.' );
        }
        if ( is_wp_error( $wp_filesystem->errors ) && $wp_filesystem->errors->has_errors() ) {
            return new \WP_Error( 'fs_error', 'Filesystem error.', $wp_filesystem->errors->get_error_message() );
        }
        $plugins_dir = $wp_filesystem->wp_plugins_dir();
        if ( empty( $plugins_dir ) ) {
            return new \WP_Error( 'fs_no_plugins_dir', 'Unable to locate WordPress plugin directory.' );
        }
        return rtrim( $plugins_dir, '/' ) . '/';
    }
}
