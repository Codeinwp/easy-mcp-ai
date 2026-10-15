<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-package-guard.php';













class Uninstall_Plugin extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    public function get_name() {
        return 'wp_uninstall_plugin';
    }

    public function get_description() {
        return 'Uninstalls an inactive plugin, as `wp plugin uninstall` does and as the Delete link on the Plugins screen does: runs the plugin\'s own uninstall routine (its uninstall.php or uninstall hook — typically deleting its settings and database tables), then deletes its files and translations. Required: `plugin` (e.g. "hello-dolly/hello.php" — the `plugin` field from wp_list_plugins). Optional: `deactivate` (boolean, default false: deactivate an active plugin first instead of refusing), `skip_delete` (boolean, default false: run only the uninstall routine and keep the files). Returns { plugin, name, version, deactivated, uninstall_routine ("uninstall.php" | "uninstall hook" | "none"), files_deleted, changed }; with skip_delete and no uninstall routine nothing runs and changed is false. Irreversible: change history records option deletions but not dropped tables or deleted files. The plugin\'s uninstall code runs in this request, so a fatal error in it fails the call. Refused when the plugin is not installed, when it is active (unless deactivate: true) or network-active, when other installed plugins require it, when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the plugins folder or wp-content directly, on multisite unless you are a super admin with manage_network_plugins, and for Easy MCP AI itself. Use wp_delete_plugin to delete the files without the uninstall routine. Off by default; an administrator switches it on.';
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
                'plugin'      => array(
                    'type'        => 'string',
                    'description' => 'Plugin file relative to the plugins directory, e.g. "hello-dolly/hello.php" (the `plugin` field from wp_list_plugins).',
                ),
                'deactivate'  => array(
                    'type'        => 'boolean',
                    'description' => 'Deactivate the plugin first when it is active on this site (its deactivation hooks run). Default false: an active plugin is refused. A network-active plugin is always refused.',
                    'default'     => false,
                ),
                'skip_delete' => array(
                    'type'        => 'boolean',
                    'description' => 'Run only the uninstall routine and keep the plugin\'s files (and its translations). Default false: the files are deleted too.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'plugin' ),
        );
    }

    public function describe( array $arguments ) {
        if ( $this->parse_flag( $arguments, 'skip_delete' ) ) {
            /* translators: 1: quoted plugin name and version, 2: plugin file */
            $format = __( 'Run the uninstall routine of plugin %1$s (%2$s), which typically deletes its settings and tables, and keep its files.', 'easy-mcp-ai' );
        } else {
            /* translators: 1: quoted plugin name and version, 2: plugin file */
            $format = __( 'Uninstall plugin %1$s (%2$s): run its uninstall routine, which typically deletes its settings and tables, then delete its files. Nothing can be restored.', 'easy-mcp-ai' );
        }
        if ( $this->parse_flag( $arguments, 'deactivate' ) ) {
            $format .= ' ' . __( 'It is deactivated first if active.', 'easy-mcp-ai' );
        }
        return $this->describe_removal( $arguments, $format );
    }

    public function execute( array $arguments ) {
        $plugin      = $this->parse_plugin_argument( $arguments );
        $deactivate  = $this->parse_flag( $arguments, 'deactivate' );
        $skip_delete = $this->parse_flag( $arguments, 'skip_delete' );

        $this->refuse_when_file_mods_are_off( 'plugins cannot be uninstalled or deleted' );
        if ( ! current_user_can( 'delete_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to uninstall plugins on this site: it needs the delete_plugins capability, as wp-admin runs uninstall routines only when deleting.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_plugins', 'uninstalling plugins' );
        $this->refuse_own_plugin_files( $plugin, 'its uninstall routine would delete its own data mid-request' );

        $this->load_plugin_admin_functions();
        $data   = $this->installed_plugin( $plugin );
        $label  = $this->plugin_label( $plugin, $data );
        $active = $this->refuse_active( $plugin, $label, $deactivate );
        $this->refuse_required_plugin( $plugin, $label );

        $routine = $this->uninstall_routine( $plugin );
        if ( $skip_delete && 'none' === $routine ) {
            
            return $this->result( $plugin, $data, false, $routine, false, false, false, 'The plugin has no uninstall routine (no uninstall.php and no registered uninstall hook), so with skip_delete nothing ran and nothing changed.' );
        }
        $this->refuse_second_uninstall_file( $routine, $label );
        if ( ! $skip_delete ) {
            $this->refuse_unless_direct_filesystem( WP_PLUGIN_DIR, 'wp-content/plugins' );
        }

        $deact_printed = $active ? $this->deactivate_first( $plugin, $label ) : '';
        $deactivated   = $active;

        if ( $skip_delete ) {
            list( , $printed ) = $this->without_output(
                function () use ( $plugin ) {
                    return $this->run_uninstall_routine( $plugin );
                }
            );
            return $this->result( $plugin, $data, $deactivated, $routine, false, true, '' !== ( $deact_printed . $printed ) );
        }

        $printed = $this->delete_files( $plugin, $label, $routine, $deactivated );
        return $this->result( $plugin, $data, $deactivated, $routine, true, true, '' !== ( $deact_printed . $printed ) );
    }

    










    private function refuse_second_uninstall_file( string $routine, string $label ): void {
        if ( 'uninstall.php' === $routine && $this->uninstall_constant_defined() ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" was not uninstalled: another uninstall routine already ran in this request (WP_UNINSTALL_PLUGIN is set). Uninstall it in a call of its own.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private function deactivate_first( string $plugin, string $label ): string {
        list( $still_active, $printed ) = $this->without_output(
            function () use ( $plugin ): bool {
                deactivate_plugins( $plugin, false, false );
                return is_plugin_active( $plugin );
            }
        );
        if ( $still_active ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" could not be deactivated, so it was not uninstalled.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $printed;
    }

    










    private function delete_files( string $plugin, string $label, string $routine, bool $deactivated ): string {
        list( $outcome, $printed ) = $this->without_output(
            function () use ( $plugin ) {
                return $this->run_delete_plugins( $plugin );
            }
        );
        if ( true !== $outcome ) {
            $why = is_wp_error( $outcome ) ? $this->upgrader_error_text( $outcome ) : 'WordPress could not reach the filesystem.';
            $ran = 'none' === $routine ? '' : ' The uninstall routine had already run.';
            throw new \RuntimeException( sprintf( 'Plugin "%s": the files were not deleted (%s).%s%s', $label, $why, $ran, $deactivated ? ' It was deactivated.' : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $printed;
    }

    




    private function parse_flag( array $arguments, string $name ): bool {
        return isset( $arguments[ $name ] ) && rest_sanitize_boolean( $arguments[ $name ] );
    }

    











    private function refuse_active( string $plugin, string $label, bool $deactivate ): bool {
        if ( is_plugin_active_for_network( $plugin ) ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" is network-active. A super admin must deactivate it network-wide first (wp_deactivate_plugin with network_wide: true).', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! is_plugin_active( $plugin ) ) {
            return false;
        }
        if ( ! $deactivate ) {
            throw new \RuntimeException( sprintf( 'Plugin "%s" is active. Pass deactivate: true to deactivate it first, or use wp_deactivate_plugin.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        
        if ( ! current_user_can( 'deactivate_plugin', $plugin ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to deactivate this plugin, so it cannot be uninstalled while active.' );
        }
        return true;
    }

    







    protected function uninstall_routine( string $plugin ): string {
        if ( file_exists( WP_PLUGIN_DIR . '/' . dirname( $plugin ) . '/uninstall.php' ) ) {
            return 'uninstall.php';
        }
        $registered = (array) get_option( 'uninstall_plugins' );
        return isset( $registered[ $plugin ] ) ? 'uninstall hook' : 'none';
    }

    
    protected function uninstall_constant_defined(): bool {
        return defined( 'WP_UNINSTALL_PLUGIN' );
    }

    



    protected function run_uninstall_routine( string $plugin ) {
        return uninstall_plugin( $plugin );
    }

    







    protected function run_delete_plugins( string $plugin ) {
        return delete_plugins( array( $plugin ) );
    }

    










    private function result( string $plugin, array $data, bool $deactivated, string $routine, bool $files_deleted, bool $changed, bool $printed, string $reason = '' ): array {
        $result = array(
            'plugin'            => $plugin,
            'name'              => isset( $data['Name'] ) ? (string) $data['Name'] : '',
            'version'           => isset( $data['Version'] ) ? (string) $data['Version'] : '',
            'deactivated'       => $deactivated,
            'uninstall_routine' => $routine,
            'files_deleted'     => $files_deleted,
            'changed'           => $changed,
        );
        if ( '' !== $reason ) {
            $result['reason'] = $reason;
        }
        if ( $printed ) {
            $result['output_discarded'] = true;
        }
        return $result;
    }
}
