<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Handlers\Plugin_Handler;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin_Adapter extends Command_Handler {

    const LIST_TOOL  = 'wp_list_plugins';
    const ACTIVATE   = 'wp_activate_plugin';
    const DEACTIVATE = 'wp_deactivate_plugin';
    const INSTALL    = 'wp_install_plugin';
    const UPDATE     = 'wp_update_plugin';
    const DELETE     = 'wp_delete_plugin';
    const UNINSTALL  = 'wp_uninstall_plugin';

    const INSTALL_CAP = 5;

    const UNINSTALL_CAP = 5;

    const INSTALL_PLACEHOLDER = '%s/<main plugin file, known once installed>';

    const NOT_CAPTURED_FILES = 'Plugin files are outside change history: a plugin installed, updated, deleted or uninstalled here cannot be restored from it.';

    const UPDATE_CAP = 5;

    const MSG_NOT_PLANNED  = "%s: its update appeared when wp_list_plugins refreshed the update data, after this command's plan (the approval preview) was built from the stored data, so it was not updated. Run the command again to update it.";
    const NOTE_REQUIREMENTS = "WP-CLI checks the wordpress.org plugin directory's Requires at least and Requires PHP before it downloads a plugin (--ignore-requirements skips that check). wp_cli_run never makes that check, with or without the flag; WordPress's own check of the downloaded package's headers still refuses an incompatible plugin, as it does under WP-CLI.";
    const NOT_CAPTURED_HOOKS = "Activation, deactivation and uninstall routines run the plugin's own code: database tables they create or drop are recorded only while database capture is on, and files they write never.";

    const MSG_NETWORK_SINGLE = '--%s applies only to a multisite network, and this is a single site, where WP-CLI reports a network-wide change it does not make. Run the command without --%s.';
    const MSG_INSTALL_SHAPE  = "wp_cli_run installs plugins only from a wordpress.org slug, and '%s' is %s: installing from a URL, a zip file or a server path is not available, because reading server files belongs to the privileged file tools. Pass the slug from the plugin's wordpress.org address.";
    const MSG_OVER_CAP       = "wp_cli_run runs at most %d items in one 'wp %s' command, and this one names %d. Run it in smaller groups.";
    const MSG_DELETE_ACTIVE  = "The '%s' plugin is active, and WordPress never deletes an active plugin, so wp_cli_run does not either. Deactivate it first with `plugin deactivate`.";
    const MSG_LIST_FIELD     = "wp_cli_run cannot print the '%s' field of `plugin list`: %s.";
    const MSG_LIST_FILTER    = '--%s is not a field `plugin list` can filter on through wp_cli_run: %s.';

    const LIST_REFUSED_FIELDS = array(
        'update_package'     => 'a package URL can carry a licence key, so the plugin never returns it',
        'tested_up_to'       => 'wp_list_plugins does not read readme files yet',
        'wporg_status'       => 'wp_list_plugins does not query wordpress.org for each plugin yet',
        'wporg_last_updated' => 'wp_list_plugins does not query wordpress.org for each plugin yet',
    );

    const LIST_FILTERS = array( 'name', 'status', 'update', 'version', 'update_version', 'auto_update' );

    private $planning = false;

    public function preflight_activate( Invocation $inv, Context $ctx ): void {
        self::refuse_network_on_single( $inv, $ctx, 'network' );
    }

    public function preflight_switch( Invocation $inv, Context $ctx ): void {
        self::refuse_own_names( $inv, $ctx );
        self::refuse_network_on_single( $inv, $ctx, 'network' );
    }

    public function preflight_named( Invocation $inv, Context $ctx ): void {
        self::refuse_own_names( $inv, $ctx );
    }

    public function preflight_update( Invocation $inv, Context $ctx ): void {
        self::refuse_own_names( $inv, $ctx );
        $count = count( self::positional( $inv ) );
        if ( $count > self::UPDATE_CAP ) {
            $ctx->out->error( sprintf( self::MSG_OVER_CAP, self::UPDATE_CAP, $inv->command, $count ) );
        }
    }

    public function preflight_install( Invocation $inv, Context $ctx ): void {
        $slugs = self::positional( $inv );
        foreach ( $slugs as $slug ) {
            if ( false !== strpos( $slug, '://' ) ) {
                $ctx->out->error( sprintf( self::MSG_INSTALL_SHAPE, $slug, 'a URL' ) );
            }
            if ( '.zip' === strtolower( substr( $slug, -4 ) ) ) {
                $ctx->out->error( sprintf( self::MSG_INSTALL_SHAPE, $slug, 'a zip file' ) );
            }
            if ( false !== strpos( $slug, '/' ) || false !== strpos( $slug, '\\' ) ) {
                $ctx->out->error( sprintf( self::MSG_INSTALL_SHAPE, $slug, 'a path' ) );
            }
        }
        if ( count( $slugs ) > self::INSTALL_CAP ) {
            $ctx->out->error( sprintf( self::MSG_OVER_CAP, self::INSTALL_CAP, $inv->command, count( $slugs ) ) );
        }
        if ( Read_Value::flag( $inv->assoc, 'force' ) ) {
            self::refuse_own_names( $inv, $ctx );
        }
        self::refuse_network_on_single( $inv, $ctx, 'activate-network' );
    }

    public function plan_activate( Invocation $inv ): array {
        return $this->plan( $inv, 'activate' );
    }

    public function plan_deactivate( Invocation $inv ): array {
        return $this->plan( $inv, 'deactivate' );
    }

    public function plan_toggle( Invocation $inv ): array {
        return $this->plan( $inv, 'toggle' );
    }

    public function plan_install( Invocation $inv ): array {
        return $this->plan( $inv, 'install' );
    }

    public function plan_update( Invocation $inv ): array {
        $calls = $this->plan( $inv, 'update' );
        return count( self::update_targets( $calls ) ) > self::UPDATE_CAP ? array() : $calls;
    }

    public function plan_delete( Invocation $inv ): array {
        return $this->plan( $inv, 'delete' );
    }

    public function plan_uninstall( Invocation $inv ): array {
        return $this->plan( $inv, 'uninstall' );
    }

    private function plan( Invocation $inv, string $method ): array {
        $calls      = array();
        $dispatcher = static function ( $tool, $arguments ) use ( &$calls ) {
            $calls[] = array(
                'tool'      => (string) $tool,
                'arguments' => (array) $arguments,
            );
            return array(
                'response' => array( 'result' => array( 'structuredContent' => self::planned_response( (string) $tool, (array) $arguments ) ) ),
                'approval' => array(
                    'kind'        => 'run',
                    'approval_id' => null,
                ),
                'audit_id' => null,
            );
        };
        $ctx            = new Context( new Output( true ), $dispatcher, array( __CLASS__, 'caller_may_use' ) );
        $this->planning = true;
        try {
            $this->$method( $inv, $ctx );
        } catch ( Cli_Exit $e ) {
            unset( $e );
        } finally {
            $this->planning = false;
        }
        return $calls;
    }

    private static function planned_response( string $tool, array $arguments ): array {
        $plugin = isset( $arguments['plugin'] ) ? (string) $arguments['plugin'] : '';
        switch ( $tool ) {
            case self::ACTIVATE:
                return array(
                    'plugin'       => $plugin,
                    'status'       => ! empty( $arguments['network_wide'] ) ? 'network-active' : 'active',
                    'changed'      => true,
                    'network_wide' => ! empty( $arguments['network_wide'] ),
                );
            case self::INSTALL:
                $slug = isset( $arguments['slug'] ) ? (string) $arguments['slug'] : '';
                return array(
                    'slug'     => $slug,
                    'plugin'   => sprintf( self::INSTALL_PLACEHOLDER, $slug ),
                    'name'     => $slug,
                    'version'  => isset( $arguments['version'] ) ? (string) $arguments['version'] : '',
                    'changed'  => true,
                    'messages' => array(),
                );
            case self::UNINSTALL:
                return array(
                    'plugin'        => $plugin,
                    'deactivated'   => ! empty( $arguments['deactivate'] ),
                    'files_deleted' => empty( $arguments['skip_delete'] ),
                    'changed'       => true,
                );
            default:
                return array(
                    'plugin'   => $plugin,
                    'changed'  => true,
                    'messages' => array(),
                );
        }
    }

    public static function caller_may_use( string $tool ): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
            return true;
        }
        return false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
    }

    public function activate( Invocation $inv, Context $ctx ): void {
        $assoc   = $inv->assoc;
        $network = (bool) Read_Value::flag( $assoc, 'network', false );
        $all     = (bool) Read_Value::flag( $assoc, 'all', false );
        $names   = $this->names( $inv, $ctx, self::ACTIVATE, $all, 'activate', self::exclude_flag( $inv ) );
        if ( ! $names ) {
            return;
        }
        $ctx->not_captured( self::NOT_CAPTURED_HOOKS );
        $this->run_activate( $inv, $ctx, $names, $network, $all, false, null );
    }

    public function deactivate( Invocation $inv, Context $ctx ): void {
        $assoc     = $inv->assoc;
        $network   = (bool) Read_Value::flag( $assoc, 'network', false );
        $all       = (bool) Read_Value::flag( $assoc, 'all', false );
        $uninstall = (bool) Read_Value::flag( $assoc, 'uninstall', false );
        $names     = $this->names( $inv, $ctx, self::DEACTIVATE, $all, 'deactivate', self::exclude_flag( $inv ) );
        if ( ! $names ) {
            return;
        }
        if ( $uninstall ) {
            Fetchers::require_tool( $ctx, $inv, self::UNINSTALL );
            $ctx->not_captured( self::NOT_CAPTURED_FILES );
        }
        $ctx->not_captured( self::NOT_CAPTURED_HOOKS );
        $this->run_deactivate( $inv, $ctx, $names, $network, $all, $uninstall, false, null );
    }

    public function toggle( Invocation $inv, Context $ctx ): void {
        $network = (bool) Read_Value::flag( $inv->assoc, 'network', false );
        $args    = self::positional( $inv );
        Fetchers::require_tool( $ctx, $inv, self::ACTIVATE );
        Fetchers::require_tool( $ctx, $inv, self::DEACTIVATE );
        $ctx->not_captured( self::NOT_CAPTURED_HOOKS );

        $successes = 0;
        $plugins   = Fetchers::of( 'plugin' )->get_many( $args, $ctx->out );
        $errors    = count( $args ) - count( $plugins );

        $this->each(
            $inv,
            $ctx,
            $plugins,
            function ( $plugin, ?Batch $batch ) use ( $inv, $ctx, $network, &$successes, &$errors ) {
                $required = $network ? 'active-network' : 'active';
                if ( $required === Plugin_Handler::status_of( (string) $plugin->file ) ) {
                    $done = $this->run_deactivate( $inv, $ctx, array( (string) $plugin->name ), $network, false, false, true, $batch );
                } else {
                    $done = $this->run_activate( $inv, $ctx, array( (string) $plugin->name ), $network, false, true, $batch );
                }
                if ( $done[0] > 0 ) {
                    ++$successes;
                    return Batch::SUCCESS;
                }
                ++$errors;
                return Batch::FAILURE;
            },
            'plugin',
            'toggle'
        );

        Values::report_batch( $ctx->out, 'plugin', 'toggle', count( $args ), $successes, $errors );
    }

    private function run_activate( Invocation $inv, Context $ctx, array $names, bool $network, bool $all, bool $chained, ?Batch $outer ): array {
        $plugins   = Fetchers::of( 'plugin' )->get_many( array_values( $names ), $ctx->out );
        $errors    = count( $names ) - count( $plugins );
        $successes = 0;
        if ( $all ) {
            $plugins = self::by_activity( $plugins, false );
        }

        $handled = array();

        $each = function ( object $plugin, ?Batch $batch ) use ( $ctx, $network, $all, &$successes, &$errors, &$handled ): string {
            if ( $all && isset( $handled[ (string) $plugin->file ] ) ) {
                return Batch::SKIP;
            }
            $outcome = $this->activate_one( $ctx, $plugin, $batch, $network, $handled );
            self::tally( $outcome, $successes, $errors );
            return $outcome;
        };

        $verb = $network ? 'network activate' : 'activate';
        $this->run_each_plugin( $inv, $ctx, $plugins, $each, $chained, $outer, $verb );
        if ( ! $chained ) {
            Values::report_batch( $ctx->out, 'plugin', $verb, count( $names ), $successes, $errors );
        }
        return array( $successes, $errors );
    }

    private function activate_one( Context $ctx, object $plugin, ?Batch $batch, bool $network, array &$handled ): string {
        $file   = (string) $plugin->file;
        $name   = (string) $plugin->name;
        $status = Plugin_Handler::status_of( $file );
        if ( $network && 'active' === $status && ! $this->deactivate_for_network( $ctx, $batch, $file ) ) {
            return Batch::FAILURE;
        }
        $arguments = array( 'plugin' => $file );
        if ( $network ) {
            $arguments['network_wide'] = true;
        }
        $result = $this->call( $ctx, $batch, self::ACTIVATE, $arguments );
        if ( ! $result['ok'] ) {
            if ( 'active-network' === $status ) {
                $ctx->out->warning( "Plugin '{$name}' is already network active." );
                return Batch::SKIP;
            }
            $ctx->out->warning( 'Failed to activate plugin. ' . self::clean_error( (string) $result['error'] ) );
            return Batch::FAILURE;
        }
        $handled[ $file ] = true;
        return self::report_activation( $ctx, $result['payload'], $name, $file, $status, $network );
    }

    private function deactivate_for_network( Context $ctx, ?Batch $batch, string $file ): bool {
        $off = $this->call( $ctx, $batch, self::DEACTIVATE, array( 'plugin' => $file ) );
        if ( ! $off['ok'] ) {
            $ctx->out->warning( 'Failed to activate plugin. ' . self::clean_error( (string) $off['error'] ) );
            return false;
        }
        if ( ! empty( $off['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        return true;
    }

    private static function report_activation( Context $ctx, array $payload, string $name, string $file, string $status, bool $network ): string {
        if ( empty( $payload['changed'] ) ) {
            $ctx->out->warning( 'active-network' === $status ? "Plugin '{$name}' is already network active." : "Plugin '{$name}' is already active." );
            return Batch::SKIP;
        }
        $ctx->mark_changed();
        if ( isset( $payload['warning'] ) && '' !== (string) $payload['warning'] ) {
            $ctx->out->warning( 'Failed to activate plugin. ' . self::clean_error( (string) $payload['warning'] ) );
            return Batch::FAILURE;
        }
        $network_wide = $network || ( \is_multisite() && \is_network_only_plugin( $file ) );
        $ctx->out->log( $network_wide ? "Plugin '{$name}' network activated." : "Plugin '{$name}' activated." );
        return Batch::SUCCESS;
    }

    private function run_deactivate( Invocation $inv, Context $ctx, array $names, bool $network, bool $all, bool $uninstall, bool $chained, ?Batch $outer ): array {
        $plugins   = Fetchers::of( 'plugin' )->get_many( array_values( $names ), $ctx->out );
        $errors    = count( $names ) - count( $plugins );
        $successes = 0;
        $skips     = 0;
        $plugins   = self::deactivation_targets( $inv, $ctx, $plugins, $all, $uninstall );

        $handled = array();

        $each = function ( object $plugin, ?Batch $batch ) use ( $ctx, $network, $all, $uninstall, &$successes, &$errors, &$skips, &$handled ): string {
            $file = (string) $plugin->file;
            if ( $all && isset( $handled[ $file ] ) ) {
                return Batch::SKIP;
            }
            $status = Plugin_Handler::status_of( $file );
            $own    = self::own_plugin( (string) $plugin->name, $file );
            if ( null !== $own ) {
                $ctx->out->warning( $own );
                ++$skips;
                return Batch::SKIP;
            }
            $outcome = $this->deactivate_one( $ctx, $plugin, $status, $batch, $network, $handled );
            self::tally( $outcome, $successes, $errors );
            if ( Batch::SUCCESS === $outcome && $uninstall && ! $this->uninstall_after_deactivation( $ctx, $batch, $plugin ) ) {
                ++$errors;
            }
            return $outcome;
        };

        $verb = $network ? 'network deactivate' : 'deactivate';
        $this->run_each_plugin( $inv, $ctx, $plugins, $each, $chained, $outer, $verb );
        if ( ! $chained ) {
            Values::report_batch( $ctx->out, 'plugin', $verb, count( $names ), $successes, $errors, $skips > 0 ? $skips : null );
        }
        return array( $successes, $errors );
    }

    private static function deactivation_targets( Invocation $inv, Context $ctx, array $plugins, bool $all, bool $uninstall ): array {
        if ( $all ) {
            $plugins = self::by_activity( $plugins, true );
        }
        if ( $uninstall ) {
            $targets = count( self::by_activity( $plugins, true ) );
            if ( $targets > self::UNINSTALL_CAP ) {
                $ctx->out->error( sprintf( self::MSG_OVER_CAP, self::UNINSTALL_CAP, $inv->command . ' --uninstall', $targets ) );
            }
        }
        return $plugins;
    }

    private function deactivate_one( Context $ctx, object $plugin, string $status, ?Batch $batch, bool $network, array &$handled ): string {
        $file = (string) $plugin->file;
        $name = (string) $plugin->name;
        if ( ! $network && 'active-network' === $status ) {
            $ctx->out->warning( "Plugin '{$name}' is network active and must be deactivated with --network flag." );
            return Batch::FAILURE;
        }
        $arguments = array( 'plugin' => $file );
        if ( $network ) {
            $arguments['network_wide'] = true;
        }
        $result = $this->call( $ctx, $batch, self::DEACTIVATE, $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->warning( "Could not deactivate the '{$name}' plugin. " . (string) $result['error'] );
            return Batch::FAILURE;
        }
        $handled[ $file ] = true;
        if ( empty( $result['payload']['changed'] ) ) {
            $ctx->out->warning( "Plugin '{$name}' isn't active." );
            return Batch::SKIP;
        }
        $ctx->mark_changed();
        $network_wide = $network || ( \is_multisite() && \is_network_only_plugin( $file ) );
        $ctx->out->log( $network_wide ? "Plugin '{$name}' network deactivated." : "Plugin '{$name}' deactivated." );
        return Batch::SUCCESS;
    }

    private function uninstall_after_deactivation( Context $ctx, ?Batch $batch, object $plugin ): bool {
        $name = (string) $plugin->name;
        $ctx->out->log( "Uninstalling '{$name}'..." );
        $removed = $this->call( $ctx, $batch, self::UNINSTALL, array( 'plugin' => (string) $plugin->file ) );
        return $this->report_uninstall( $ctx, $name, $removed, false );
    }

    private function run_each_plugin( Invocation $inv, Context $ctx, array $plugins, callable $each, bool $chained, ?Batch $outer, string $verb ): void {
        if ( ! $chained ) {
            $this->each( $inv, $ctx, $plugins, $each, 'plugin', $verb );
            return;
        }
        foreach ( $plugins as $plugin ) {
            $each( $plugin, $outer );
        }
    }

    private static function by_activity( array $plugins, bool $active ): array {
        return array_values(
            array_filter(
                $plugins,
                static function ( object $plugin ) use ( $active ): bool {
                    return $active === in_array( Plugin_Handler::status_of( (string) $plugin->file ), array( 'active', 'active-network' ), true );
                }
            )
        );
    }

    private static function tally( string $outcome, int &$successes, int &$errors ): void {
        if ( Batch::SUCCESS === $outcome ) {
            ++$successes;
        } elseif ( Batch::FAILURE === $outcome ) {
            ++$errors;
        }
    }

    public function install( Invocation $inv, Context $ctx ): void {
        $assoc   = $inv->assoc;
        $slugs   = self::positional( $inv );
        $options = array(
            'version'          => isset( $assoc['version'] ) && is_string( $assoc['version'] ) ? $assoc['version'] : null,
            'force'            => (bool) Read_Value::flag( $assoc, 'force', false ),
            'activate'         => (bool) Read_Value::flag( $assoc, 'activate', false ),
            'activate_network' => (bool) Read_Value::flag( $assoc, 'activate-network', false ),
        );

        Fetchers::require_tool( $ctx, $inv, self::INSTALL );
        if ( $options['activate'] || $options['activate_network'] ) {
            Fetchers::require_tool( $ctx, $inv, self::ACTIVATE );
            $ctx->not_captured( self::NOT_CAPTURED_HOOKS );
        }
        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        if ( null === $options['version'] ) {
            $ctx->note( self::NOTE_REQUIREMENTS );
        }

        $successes = 0;
        $errors    = 0;

        $this->each(
            $inv,
            $ctx,
            $slugs,
            function ( string $slug, ?Batch $batch ) use ( $inv, $ctx, $options, &$successes, &$errors ): string {
                $outcome = $this->install_one( $inv, $ctx, $slug, $batch, $options );
                self::tally( $outcome, $successes, $errors );
                return $outcome;
            },
            'plugin',
            'install'
        );

        Values::report_batch( $ctx->out, 'plugin', 'install', count( $slugs ), $successes, $errors );
    }

    private function install_one( Invocation $inv, Context $ctx, string $slug, ?Batch $batch, array $options ): string {
        if ( '' === $slug ) {
            $ctx->out->warning( 'Ignoring ambiguous empty slug value.' );
            return Batch::SKIP;
        }
        $arguments = array( 'slug' => $slug );
        if ( null !== $options['version'] ) {
            $arguments['version'] = $options['version'];
        }
        if ( $options['force'] ) {
            $arguments['force'] = true;
        }
        $result  = $this->call( $ctx, $batch, self::INSTALL, $arguments );
        $outcome = $result['ok'] ? self::report_installed( $ctx, $slug, $options['version'], $result['payload'] ) : self::report_install_error( $ctx, $slug, (string) $result['error'] );

        $extension = Fetchers::of( 'plugin' )->get_many( array( $slug ), $ctx->out );
        if ( Batch::FAILURE !== $outcome && ( $options['activate'] || $options['activate_network'] ) ) {
            $this->activate_installed( $inv, $ctx, $slug, $batch, $options, (bool) $extension );
        }
        return $outcome;
    }

    private static function report_installed( Context $ctx, string $slug, ?string $version, array $payload ): string {
        $shown = 'dev' === $version ? 'Development Version' : ( isset( $payload['version'] ) ? (string) $payload['version'] : '' );
        $ctx->out->log( sprintf( 'Installing %s (%s)', html_entity_decode( isset( $payload['name'] ) ? (string) $payload['name'] : $slug, ENT_QUOTES ), $shown ) );
        self::upgrader_lines( $ctx, $payload );
        if ( ! empty( $payload['changed'] ) ) {
            $ctx->mark_changed();
        }
        return Batch::SUCCESS;
    }

    private static function report_install_error( Context $ctx, string $slug, string $error ): string {
        if ( 0 === strpos( $error, 'The plugin "' . $slug . '" is already installed as ' ) ) {
            $ctx->out->warning( "{$slug}: Plugin already installed." );
            return Batch::SKIP;
        }
        if ( 0 === strpos( $error, 'The plugin "' . $slug . '" was not found in the wordpress.org plugin directory.' ) ) {
            $ctx->out->warning( "{$slug}: Plugin not found." );
            return Batch::FAILURE;
        }
        $ctx->out->warning( "{$slug}: {$error}" );
        return Batch::FAILURE;
    }

    private function activate_installed( Invocation $inv, Context $ctx, string $slug, ?Batch $batch, array $options, bool $installed ): void {
        if ( ! $installed ) {
            if ( $this->planning ) {
                $this->plan_install_activation( $ctx, $slug, $batch, $options );
            }
            return;
        }
        if ( $options['activate_network'] ) {
            $ctx->out->log( "Network-activating '{$slug}'..." );
            $this->run_activate( $inv, $ctx, array( $slug ), true, false, true, $batch );
        }
        if ( $options['activate'] ) {
            $ctx->out->log( "Activating '{$slug}'..." );
            $this->run_activate( $inv, $ctx, array( $slug ), false, false, true, $batch );
        }
    }

    private function plan_install_activation( Context $ctx, string $slug, ?Batch $batch, array $options ): void {
        $placeholder = sprintf( self::INSTALL_PLACEHOLDER, $slug );
        if ( $options['activate_network'] ) {
            $this->call(
                $ctx,
                $batch,
                self::ACTIVATE,
                array(
                    'plugin'       => $placeholder,
                    'network_wide' => true,
                )
            );
        }
        if ( $options['activate'] ) {
            $this->call( $ctx, $batch, self::ACTIVATE, array( 'plugin' => $placeholder ) );
        }
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $all   = (bool) Read_Value::flag( $assoc, 'all', false );
        $args  = self::positional( $inv );
        if ( $all || $args ) {
            Fetchers::require_tool( $ctx, $inv, self::UPDATE );
        }
        $args = Plugin_Handler::check_optional_args_and_all( $ctx->out, $args, $all );
        if ( ! $args ) {
            return;
        }
        if ( isset( $assoc['version'] ) && isset( $assoc['dry-run'] ) ) {
            $ctx->out->error( '--dry-run cannot be used together with --version.' );
        }
        if ( ! Read_Value::flag( $assoc, 'dry-run' ) ) {
            $ctx->not_captured( self::NOT_CAPTURED_FILES );
        }

        if ( isset( $assoc['version'] ) ) {
            $this->update_to_version( $inv, $ctx, $args, (string) $assoc['version'] );
            return;
        }
        $this->update_many( $inv, $ctx, array_values( $args ), $all );
    }

    private function update_to_version( Invocation $inv, Context $ctx, array $args, string $version ): void {
        $plugins = Fetchers::of( 'plugin' )->get_many( array_values( $args ), $ctx->out );
        if ( count( $plugins ) > self::UPDATE_CAP ) {
            $ctx->out->error( sprintf( self::MSG_OVER_CAP, self::UPDATE_CAP, $inv->command, count( $plugins ) ) );
        }
        $this->each(
            $inv,
            $ctx,
            $plugins,
            function ( $plugin, ?Batch $batch ) use ( $ctx, $version ) {
                $name   = (string) $plugin->name;
                $result = $this->call(
                    $ctx,
                    $batch,
                    self::UPDATE,
                    array(
                        'plugin'  => (string) $plugin->file,
                        'version' => $version,
                    )
                );
                if ( ! $result['ok'] ) {
                    $ctx->out->warning( "{$name}: " . (string) $result['error'] );
                    Values::report_batch( $ctx->out, 'plugin', 'install', 1, 0, 1 );
                    return Batch::FAILURE;
                }
                $payload = $result['payload'];
                $ctx->out->log( sprintf( 'Installing %s (%s)', isset( $payload['name'] ) && '' !== (string) $payload['name'] ? (string) $payload['name'] : $name, 'dev' === $version ? 'Development Version' : $version ) );
                self::upgrader_lines( $ctx, $payload );
                $ctx->mark_changed();
                Values::report_batch( $ctx->out, 'plugin', 'install', 1, 1, 0 );
                return Batch::SUCCESS;
            },
            'plugin',
            'install'
        );
    }

    private function update_many( Invocation $inv, Context $ctx, array $args, bool $all ): void {
        $dry_run = (bool) Read_Value::flag( $inv->assoc, 'dry-run' );
        Fetchers::require_tool( $ctx, $inv, self::LIST_TOOL );
        $format = self::update_format( $ctx, $inv->assoc );

        $planned = $this->planned_or_refuse( $inv, $ctx, $dry_run );

        $items   = $this->update_items( $ctx );
        $errors  = 0;
        $skipped = 0;
        if ( ! $all ) {
            $items  = self::picked_items( $ctx, $args, $items );
            $errors = count( $args ) - count( $items );
        }

        $exclude         = self::exclude_flag( $inv );
        $items_to_update = self::without_excluded( self::with_update( $items ), $exclude );
        $items_to_update = self::without_unavailable( $ctx, $items_to_update, $skipped );

        if ( $dry_run ) {
            $this->dry_run( $ctx, $items_to_update, $format, $exclude );
            return;
        }

        if ( ! $this->planning ) {
            $items_to_update = self::only_planned( $ctx, $items_to_update, $planned, $skipped );
        }

        $result  = $this->run_updates( $inv, $ctx, $items_to_update );
        $errors += self::report_updates( $ctx, $items_to_update, $result, $format );

        $num_updated = count( array_filter( $result ) );
        if ( 0 === $num_updated && $skipped ) {
            $errors  = $skipped;
            $skipped = null;
        }
        Values::report_batch( $ctx->out, 'plugin', 'update', $all ? count( $items_to_update ) : count( $args ), $num_updated, $errors, $skipped );
        if ( null !== $exclude ) {
            $ctx->out->log( "Skipped updates for: {$exclude}" );
        }
    }

    private static function update_format( Context $ctx, array $assoc_args ): ?string {
        $format = isset( $assoc_args['format'] ) && is_string( $assoc_args['format'] ) ? $assoc_args['format'] : null;
        if ( in_array( $format, array( 'json', 'csv' ), true ) ) {
            $ctx->out->set_quiet( true );
        }
        return $format;
    }

    private function planned_or_refuse( Invocation $inv, Context $ctx, bool $dry_run ): array {
        $planned = ( $this->planning || $dry_run ) ? array() : $this->planned_updates( $inv );
        if ( count( $planned ) > self::UPDATE_CAP ) {
            $ctx->out->error( sprintf( self::MSG_OVER_CAP, self::UPDATE_CAP, $inv->command, count( $planned ) ) );
        }
        return $planned;
    }

    private function update_items( Context $ctx ): array {
        $listed = $this->call( $ctx, null, self::LIST_TOOL, array( 'skip_update_check' => false ) );
        if ( $this->planning ) {
            return Plugin_Handler::item_list( (bool) \current_user_can( 'update_plugins' ) );
        }
        return self::listed_items( $ctx, $listed );
    }

    private static function picked_items( Context $ctx, array $args, array $items ): array {
        $picked = array();
        foreach ( Fetchers::of( 'plugin' )->get_many( $args, $ctx->out ) as $plugin ) {
            $picked[ (string) $plugin->file ] = isset( $items[ $plugin->file ] ) ? $items[ $plugin->file ] : null;
        }
        return $picked;
    }

    private static function with_update( array $items ): array {
        return array_filter(
            $items,
            static function ( $item ): bool {
                return isset( $item['update'] ) && 'none' !== $item['update'];
            }
        );
    }

    private static function without_excluded( array $items_to_update, ?string $exclude ): array {
        if ( null === $exclude ) {
            return $items_to_update;
        }
        foreach ( explode( ',', trim( $exclude, ',' ) ) as $item ) {
            $plugin = Fetchers::of( 'plugin' )->get( $item );
            if ( $plugin ) {
                unset( $items_to_update[ $plugin->file ] );
            }
        }
        return $items_to_update;
    }

    private static function without_unavailable( Context $ctx, array $items_to_update, int &$skipped ): array {
        foreach ( $items_to_update as $item_key => $item_info ) {
            if ( Plugin_Handler::INVALID_VERSION_MESSAGE === $item_info['update'] ) {
                $ctx->out->warning( "{$item_info['name']}: " . Plugin_Handler::INVALID_VERSION_MESSAGE . '.' );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
            if ( 'unavailable' === $item_info['update'] ) {
                $ctx->out->warning( "{$item_info['name']}: {$item_info['update_unavailable_reason']}" );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
        }
        foreach ( $items_to_update as $item_key => $item_info ) {
            $own = self::own_plugin( (string) $item_info['name'], (string) $item_key );
            if ( null !== $own ) {
                $ctx->out->warning( $own );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
        }
        return $items_to_update;
    }

    private static function only_planned( Context $ctx, array $items_to_update, array $planned, int &$skipped ): array {
        foreach ( $items_to_update as $item_key => $item_info ) {
            if ( ! in_array( (string) $item_key, $planned, true ) ) {
                $ctx->out->warning( sprintf( self::MSG_NOT_PLANNED, (string) $item_info['name'] ) );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
        }
        return $items_to_update;
    }

    private function run_updates( Invocation $inv, Context $ctx, array $items_to_update ): array {
        $result = array();
        $this->each(
            $inv,
            $ctx,
            array_values( $items_to_update ),
            function ( array $item, ?Batch $batch ) use ( $ctx, &$result ): string {
                $outcome = $this->call( $ctx, $batch, self::UPDATE, array( 'plugin' => (string) $item['update_id'] ) );
                if ( $outcome['ok'] ) {
                    self::upgrader_lines( $ctx, $outcome['payload'] );
                    $ctx->mark_changed();
                    $result[ $item['update_id'] ] = true;
                    return Batch::SUCCESS;
                }
                $ctx->out->warning( (string) $outcome['error'] );
                $result[ $item['update_id'] ] = null;
                return Batch::FAILURE;
            },
            'plugin',
            'update',
            static function ( array $item ): string {
                return 'plugin ' . $item['name'];
            }
        );
        return $result;
    }

    private static function report_updates( Context $ctx, array $items_to_update, array $result, ?string $format ): int {
        if ( ! $items_to_update ) {
            return 0;
        }
        if ( 'summary' === $format ) {
            foreach ( $items_to_update as $info ) {
                $message = null !== $result[ $info['update_id'] ] ? 'updated successfully' : 'did not update';
                $ctx->out->log( "{$info['title']} {$message} from version {$info['version']} to version {$info['update_version']}" );
            }
            return 0;
        }
        $errors = 0;
        $status = array();
        foreach ( $items_to_update as $item_to_update => $info ) {
            $status[ $item_to_update ] = array(
                'name'        => $info['name'],
                'old_version' => $info['version'],
                'new_version' => $info['update_version'],
                'status'      => null !== $result[ $info['update_id'] ] ? 'Updated' : 'Error',
            );
            if ( null === $result[ $info['update_id'] ] ) {
                ++$errors;
            }
        }
        self::format_items( $ctx, in_array( $format, array( 'json', 'csv' ), true ) ? (string) $format : 'table', $status, array( 'name', 'old_version', 'new_version', 'status' ) );
        return $errors;
    }

    private function planned_updates( Invocation $inv ): array {
        return self::update_targets( $this->plan( $inv, 'update' ) );
    }

    private static function update_targets( array $calls ): array {
        $targets = array();
        foreach ( $calls as $call ) {
            if ( self::UPDATE === $call['tool'] && isset( $call['arguments']['plugin'] ) ) {
                $targets[] = (string) $call['arguments']['plugin'];
            }
        }
        return $targets;
    }

    private function dry_run( Context $ctx, array $items_to_update, ?string $format, ?string $exclude ): void {
        if ( empty( $items_to_update ) ) {
            $ctx->out->log( 'No plugin updates available.' );
            if ( null !== $exclude ) {
                $ctx->out->log( "Skipped updates for: {$exclude}" );
            }
            return;
        }

        if ( in_array( $format, array( 'json', 'csv' ), true ) ) {
            self::format_items( $ctx, (string) $format, $items_to_update, array( 'name', 'status', 'version', 'update_version' ) );
        } elseif ( 'summary' === $format ) {
            $ctx->out->log( 'Available plugin updates:' );
            foreach ( $items_to_update as $info ) {
                $ctx->out->log( "{$info['title']} update from version {$info['version']} to version {$info['update_version']}" );
            }
        } else {
            $ctx->out->log( 'Available plugin updates:' );
            self::format_items( $ctx, 'table', $items_to_update, array( 'name', 'status', 'version', 'update_version' ) );
        }

        if ( null !== $exclude ) {
            $ctx->out->log( "Skipped updates for: {$exclude}" );
        }
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $all   = (bool) Read_Value::flag( $assoc, 'all', false );
        $names = $this->names( $inv, $ctx, self::DELETE, $all, 'delete', self::exclude_flag( $inv ) );
        if ( ! $names ) {
            return;
        }
        $ctx->not_captured( self::NOT_CAPTURED_FILES );

        $successes = 0;
        $errors    = 0;
        $skips     = 0;
        $plugins   = Fetchers::of( 'plugin' )->get_many( array_values( $names ), $ctx->out );
        $handled = array();

        $this->each(
            $inv,
            $ctx,
            $plugins,
            function ( $plugin, ?Batch $batch ) use ( $ctx, $all, &$successes, &$errors, &$skips, &$handled ) {
                $file = (string) $plugin->file;
                $name = (string) $plugin->name;
                if ( $all && isset( $handled[ $file ] ) ) {
                    return Batch::SKIP;
                }
                $own  = self::own_plugin( $name, $file );
                if ( null !== $own ) {
                    $ctx->out->warning( $own );
                    ++$skips;
                    return Batch::SKIP;
                }
                if ( 'inactive' !== Plugin_Handler::status_of( $file ) ) {
                    $ctx->out->warning( sprintf( self::MSG_DELETE_ACTIVE, $name ) );
                    ++$errors;
                    return Batch::FAILURE;
                }
                $result = $this->call( $ctx, $batch, self::DELETE, array( 'plugin' => $file ) );
                if ( $result['ok'] ) {
                    $handled[ $file ] = true;
                    $ctx->out->log( "Deleted '{$name}' plugin." );
                    $ctx->mark_changed();
                    ++$successes;
                    return Batch::SUCCESS;
                }
                $ctx->out->warning( "The '{$name}' plugin could not be deleted. " . (string) $result['error'] );
                ++$errors;
                return Batch::FAILURE;
            },
            'plugin',
            'delete'
        );

        $missing = self::missing_names( array_values( $names ), $plugins );
        if ( $missing ) {
            $ctx->fail_missing_target( sprintf( Fetchers::MESSAGES['plugin'], $missing[0] ) );
        }
        Values::report_batch( $ctx->out, 'plugin', 'delete', count( $names ), $successes, $errors, $skips > 0 ? $skips : null );
    }

    public function uninstall( Invocation $inv, Context $ctx ): void {
        $assoc       = $inv->assoc;
        $all         = (bool) Read_Value::flag( $assoc, 'all', false );
        $deactivate  = (bool) Read_Value::flag( $assoc, 'deactivate', false );
        $skip_delete = (bool) Read_Value::flag( $assoc, 'skip-delete', false );
        $names       = $this->names( $inv, $ctx, self::UNINSTALL, $all, 'uninstall', self::exclude_flag( $inv ) );
        if ( ! $names ) {
            return;
        }
        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        $ctx->not_captured( self::NOT_CAPTURED_HOOKS );

        $successes = 0;
        $skips     = 0;
        $plugins   = Fetchers::of( 'plugin' )->get_many( array_values( $names ), $ctx->out );
        $errors    = count( $names ) - count( $plugins );
        $handled = array();

        $this->each(
            $inv,
            $ctx,
            $plugins,
            function ( object $plugin, ?Batch $batch ) use ( $ctx, $all, $deactivate, $skip_delete, &$successes, &$errors, &$skips, &$handled ): string {
                $file = (string) $plugin->file;
                if ( $all && isset( $handled[ $file ] ) ) {
                    return Batch::SKIP;
                }
                $own    = self::own_plugin( (string) $plugin->name, $file );
                $status = Plugin_Handler::status_of( $file );
                if ( null !== $own ) {
                    $ctx->out->warning( $own );
                    ++$skips;
                    return Batch::SKIP;
                }
                $outcome = $this->uninstall_one( $ctx, $plugin, $status, $batch, $deactivate, $skip_delete, $handled );
                self::tally( $outcome, $successes, $errors );
                return $outcome;
            },
            'plugin',
            'uninstall'
        );

        Values::report_batch( $ctx->out, 'plugin', 'uninstall', count( $names ), $successes, $errors, $skips > 0 ? $skips : null );
    }

    private function uninstall_one( Context $ctx, object $plugin, string $status, ?Batch $batch, bool $deactivate, bool $skip_delete, array &$handled ): string {
        $file = (string) $plugin->file;
        $name = (string) $plugin->name;
        if ( 'inactive' !== $status && ! $deactivate ) {
            $ctx->out->warning( "The '{$name}' plugin is active." );
            return Batch::FAILURE;
        }
        if ( $deactivate && 'active-network' === $status ) {
            $ctx->out->warning( "Plugin '{$name}' is network active and must be deactivated with --network flag." );
            return Batch::FAILURE;
        }
        $result = $this->call( $ctx, $batch, self::UNINSTALL, self::uninstall_arguments( $file, $deactivate, $skip_delete ) );
        if ( $result['ok'] ) {
            $handled[ $file ] = true;
            if ( $deactivate ) {
                self::report_deactivated( $ctx, $name, $result['payload'] );
            }
        }
        return $this->report_uninstall( $ctx, $name, $result, $skip_delete ) ? Batch::SUCCESS : Batch::FAILURE;
    }

    private static function uninstall_arguments( string $file, bool $deactivate, bool $skip_delete ): array {
        $arguments = array( 'plugin' => $file );
        if ( $deactivate ) {
            $arguments['deactivate'] = true;
        }
        if ( $skip_delete ) {
            $arguments['skip_delete'] = true;
        }
        return $arguments;
    }

    private static function report_deactivated( Context $ctx, string $name, array $payload ): void {
        $ctx->out->log( "Deactivating '{$name}'..." );
        if ( ! empty( $payload['deactivated'] ) ) {
            $ctx->out->log( "Plugin '{$name}' deactivated." );
        } else {
            $ctx->out->warning( "Plugin '{$name}' isn't active." );
        }
    }

    private function report_uninstall( Context $ctx, string $name, array $result, bool $skip_delete ): bool {
        if ( ! $result['ok'] ) {
            $error = (string) $result['error'];
            if ( false !== strpos( $error, 'The uninstall routine had already run.' ) ) {
                $ctx->mark_changed();
            }
            $ctx->out->warning( "The '{$name}' plugin could not be uninstalled. {$error}" );
            return false;
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        $ctx->out->log( $skip_delete ? "Ran uninstall procedure for '{$name}' plugin without deleting." : "Uninstalled and deleted '{$name}' plugin." );
        return true;
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        self::refuse_list_fields( $ctx, $assoc_args );
        self::refuse_list_filters( $ctx, $assoc_args );

        $result    = $ctx->delegate(
            self::LIST_TOOL,
            array(
                'include_mu_dropins' => true,
                'skip_update_check'  => (bool) Read_Value::flag( $assoc_args, 'skip-update-check', false ),
            )
        );
        $all_items = self::listed_items( $ctx, $result );

        if ( false !== (bool) Read_Value::flag( $assoc_args, 'recently-active', false ) ) {
            $all_items = array_filter(
                $all_items,
                static function ( array $value ): bool {
                    return isset( $value['recently_active'] ) && true === $value['recently_active'];
                }
            );
        }

        $obj_fields = Plugin_Handler::OBJ_FIELDS;
        foreach ( $all_items as $key => $item ) {
            $item              = self::shown_item( $item, $obj_fields );
            $all_items[ $key ] = $item;
            if ( ! self::matches_filters( $item, $obj_fields, $assoc_args ) ) {
                unset( $all_items[ $key ] );
            }
        }

        $formatter = new Formatter( $ctx->out, $assoc_args, $obj_fields, 'plugin' );
        $formatter->display_items( $all_items );
    }

    private static function listed_items( Context $ctx, array $listed ): array {
        if ( ! $listed['ok'] ) {
            $ctx->out->error( (string) $listed['error'] );
        }
        foreach ( isset( $listed['payload']['warnings'] ) ? (array) $listed['payload']['warnings'] : array() as $warning ) {
            $ctx->out->warning( (string) $warning );
        }
        return self::list_items( isset( $listed['payload']['plugins'] ) ? (array) $listed['payload']['plugins'] : array() );
    }

    private static function shown_item( array $item, array &$obj_fields ): array {
        if ( empty( $item['version'] ) ) {
            $item['version'] = '';
        }
        if ( empty( $item['update_version'] ) ) {
            $item['update_version'] = '';
        }
        if ( array_key_exists( 'update', $item ) && 'unavailable' === $item['update'] ) {
            foreach ( array( 'requires', 'requires_php' ) as $shown ) {
                if ( ! in_array( $shown, $obj_fields, true ) ) {
                    $obj_fields[] = $shown;
                }
            }
        }
        if ( isset( $item['auto_update'] ) && is_bool( $item['auto_update'] ) ) {
            $item['auto_update'] = $item['auto_update'] ? 'on' : 'off';
        }
        return $item;
    }

    private static function matches_filters( array $item, array $obj_fields, array $assoc_args ): bool {
        foreach ( $obj_fields as $field ) {
            if ( ! array_key_exists( $field, $assoc_args ) ) {
                continue;
            }
            $field_filter = $assoc_args[ $field ];
            $value        = array_key_exists( $field, $item ) ? $item[ $field ] : null;
            if (
                $value !== $field_filter
                && ! in_array( $value, array_map( 'trim', explode( ',', (string) $field_filter ) ), true )
            ) {
                return false;
            }
        }
        return true;
    }

    private static function refuse_list_fields( Context $ctx, array $assoc_args ): void {
        $asked = array();
        if ( isset( $assoc_args['fields'] ) && is_string( $assoc_args['fields'] ) ) {
            $asked = array_map( 'trim', explode( ',', $assoc_args['fields'] ) );
        }
        if ( isset( $assoc_args['field'] ) && is_string( $assoc_args['field'] ) ) {
            $asked[] = trim( $assoc_args['field'] );
        }
        foreach ( $asked as $field ) {
            if ( isset( self::LIST_REFUSED_FIELDS[ $field ] ) ) {
                $ctx->out->error( sprintf( self::MSG_LIST_FIELD, $field, self::LIST_REFUSED_FIELDS[ $field ] ) );
            }
        }
    }

    private static function refuse_list_filters( Context $ctx, array $assoc_args ): void {
        $synopsis = array( 'field', 'fields', 'format', 'status', 'skip-update-check', 'recently-active' );
        foreach ( array_keys( $assoc_args ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, $synopsis, true ) || in_array( $key, self::LIST_FILTERS, true ) ) {
                continue;
            }
            if ( 'requires' === $key || 'requires_php' === $key ) {
                $ctx->out->error( sprintf( self::MSG_LIST_FILTER, $key, 'WP-CLI applies it only to the plugins listed after one whose update is unavailable, so its result depends on the order of the list' ) );
            }
            $ctx->out->error( sprintf( self::MSG_LIST_FILTER, $key, 'WP-CLI filters only on name, status, update, version, update_version and auto_update, and silently ignores any other key' ) );
        }
    }

    private static function list_items( array $rows ): array {
        $items      = array();
        $duplicates = array();
        foreach ( $rows as $row ) {
            $row    = (array) $row;
            $file   = isset( $row['plugin'] ) ? (string) $row['plugin'] : '';
            $status = isset( $row['status'] ) ? (string) $row['status'] : '';
            if ( 'must-use' === $status ) {
                $items[ $file ] = self::must_use_item( $file, $row );
                continue;
            }
            if ( 'dropin' === $status ) {
                $items[ $file ] = self::dropin_item( $file, $row );
                continue;
            }
            $name                  = Plugin_Handler::plugin_name( $file );
            $duplicates[ $name ][] = $file;
            $items[ $file ]        = self::plugin_item( $name, $file, $status, $row ) + self::plugin_item_details( $file, $row );
        }
        return self::rename_duplicates( $items, $duplicates );
    }

    private static function must_use_item( string $file, array $row ): array {
        $title = isset( $row['name'] ) ? (string) $row['name'] : '';
        return array(
            'name'               => Plugin_Handler::plugin_name( $file ),
            'status'             => 'must-use',
            'update'             => false,
            'update_version'     => null,
            'update_package'     => null,
            'version'            => isset( $row['version'] ) ? (string) $row['version'] : '',
            'update_id'          => '',
            'title'              => $title !== $file ? $title : '',
            'description'        => isset( $row['description'] ) ? (string) $row['description'] : '',
            'file'               => $file,
            'auto_update'        => false,
            'tested_up_to'       => '',
            'requires'           => '',
            'requires_php'       => '',
            'wporg_status'       => '',
            'wporg_last_updated' => '',
        );
    }

    private static function dropin_item( string $file, array $row ): array {
        $title = isset( $row['name'] ) ? (string) $row['name'] : '';
        return array(
            'name'               => $file,
            'title'              => $title !== $file ? $title : '',
            'description'        => isset( $row['description'] ) ? (string) $row['description'] : '',
            'status'             => 'dropin',
            'update'             => false,
            'update_version'     => null,
            'update_package'     => null,
            'update_id'          => '',
            'file'               => $file,
            'auto_update'        => false,
            'author'             => isset( $row['author'] ) ? (string) $row['author'] : '',
            'tested_up_to'       => '',
            'requires'           => '',
            'requires_php'       => '',
            'wporg_status'       => '',
            'wporg_last_updated' => '',
        );
    }

    private static function plugin_item( string $name, string $file, string $status, array $row ): array {
        return array(
            'name'           => $name,
            'status'         => 'network-active' === $status ? 'active-network' : $status,
            'update'         => array_key_exists( 'update', $row ) ? $row['update'] : null,
            'update_version' => array_key_exists( 'update_version', $row ) ? $row['update_version'] : null,
            'update_package' => null,
            'version'        => isset( $row['version'] ) ? $row['version'] : '',
            'update_id'      => $file,
            'title'          => isset( $row['name'] ) ? (string) $row['name'] : '',
        );
    }

    private static function plugin_item_details( string $file, array $row ): array {
        return array(
            'description'               => wordwrap( isset( $row['description'] ) ? (string) $row['description'] : '' ),
            'file'                      => $file,
            'auto_update'               => array_key_exists( 'auto_update', $row ) ? $row['auto_update'] : null,
            'author'                    => isset( $row['author'] ) ? $row['author'] : '',
            'tested_up_to'              => '',
            'requires'                  => isset( $row['requires'] ) ? $row['requires'] : '',
            'requires_php'              => isset( $row['requires_php'] ) ? $row['requires_php'] : '',
            'wporg_status'              => '',
            'wporg_last_updated'        => '',
            'recently_active'           => ! empty( $row['recently_active'] ),
            'update_unavailable_reason' => isset( $row['update_unavailable_reason'] ) ? $row['update_unavailable_reason'] : '',
        );
    }

    private static function rename_duplicates( array $items, array $duplicates ): array {
        foreach ( $duplicates as $files ) {
            if ( count( $files ) <= 1 ) {
                continue;
            }
            foreach ( $files as $file ) {
                $items[ $file ]['name'] = str_replace( '.' . pathinfo( $file, PATHINFO_EXTENSION ), '', $file );
            }
        }
        return $items;
    }

    private function names( Invocation $inv, Context $ctx, string $tool, bool $all, string $verb, ?string $exclude ): array {
        $args = self::positional( $inv );
        if ( $all || $args ) {
            Fetchers::require_tool( $ctx, $inv, $tool );
        }
        return Plugin_Handler::check_optional_args_and_all( $ctx->out, $args, $all, $verb, $exclude );
    }

    private static function exclude_flag( Invocation $inv ): ?string {
        $exclude = Read_Value::flag( $inv->assoc, 'exclude' );
        return null === $exclude ? null : (string) $exclude;
    }

    private function each( Invocation $inv, Context $ctx, array $items, callable $each, string $noun, string $verb, ?callable $label = null ): void {
        if ( $this->planning ) {
            foreach ( array_values( $items ) as $item ) {
                $each( $item, null );
            }
            return;
        }
        $batch = new Batch( $inv, $ctx, $noun, $verb );
        $batch->run(
            array_values( $items ),
            static function ( $item, Batch $run ) use ( $each ) {
                return (string) $each( $item, $run );
            },
            null !== $label ? $label : static function ( $item ) use ( $noun ) {
                return $noun . ' ' . ( is_object( $item ) && isset( $item->name ) ? (string) $item->name : ( is_scalar( $item ) ? (string) $item : '' ) );
            }
        );
    }

    private function call( Context $ctx, ?Batch $batch, string $tool, array $arguments ): array {
        return null !== $batch ? $batch->delegate( $tool, $arguments ) : $ctx->delegate( $tool, $arguments );
    }

    private static function positional( Invocation $inv ): array {
        return array_values( array_map( 'strval', $inv->args ) );
    }

    private static function own_plugin( string $name, string $file ): ?string {
        $refusal = Self_Protection::plugin( $name );
        return null !== $refusal ? $refusal : Self_Protection::plugin( $file );
    }

    private static function refuse_own_names( Invocation $inv, Context $ctx ): void {
        foreach ( self::positional( $inv ) as $name ) {
            $refusal = Self_Protection::plugin( $name );
            if ( null !== $refusal ) {
                $ctx->out->error( $refusal );
            }
        }
    }

    private static function refuse_network_on_single( Invocation $inv, Context $ctx, string $flag ): void {
        if ( Read_Value::flag( $inv->assoc, $flag ) && ! \is_multisite() ) {
            $ctx->out->error( sprintf( self::MSG_NETWORK_SINGLE, $flag, $flag ) );
        }
    }

    private static function missing_names( array $names, array $found ): array {
        $remaining = array();
        foreach ( $found as $plugin ) {
            $remaining[] = (string) $plugin->name;
        }
        $missing = array();
        foreach ( $names as $name ) {
            $at = array_search( $name, $remaining, true );
            if ( false === $at ) {
                $missing[] = $name;
            } else {
                unset( $remaining[ $at ] );
            }
        }
        return $missing;
    }

    private static function upgrader_lines( Context $ctx, array $payload ): void {
        foreach ( isset( $payload['messages'] ) ? (array) $payload['messages'] : array() as $message ) {
            $line = (string) $message;
            $line = function_exists( 'wp_strip_all_tags' ) ? \wp_strip_all_tags( $line ) : $line;
            $line = str_replace( '&#8230;', '...', $line );
            $ctx->out->log( html_entity_decode( $line, ENT_QUOTES, 'UTF-8' ) );
        }
    }

    private static function clean_error( string $message ): string {
        $message = (string) preg_replace( '/<a\s[^>]+>.*<\/a>/im', '', $message );
        $message = function_exists( 'wp_strip_all_tags' ) ? \wp_strip_all_tags( $message ) : $message;
        return str_replace( 'Error: ', '', $message );
    }

    private static function format_items( Context $ctx, string $format, iterable $items, array $fields ): void {
        $assoc_args = array(
            'format' => $format,
            'fields' => $fields,
        );
        $formatter  = new Formatter( $ctx->out, $assoc_args );
        $formatter->display_items( $items );
    }
}
