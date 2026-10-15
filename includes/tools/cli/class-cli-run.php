<?php
namespace Easy_MCP_AI\Tools\Cli;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Cli_Router\Loader;
use Easy_MCP_AI\Cli_Router\Router;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





















class Cli_Run extends Base_Tool {

    
    private $dispatcher = null;

    
    private $may_use = null;

    public function get_name() {
        return 'wp_cli_run';
    }

    public function get_title() {
        return 'WP-CLI Command';
    }

    




    const COVERS = 'object cache, transients, cron, rewrite rules, roles and capabilities, database maintenance and language packs';

    public function get_covers(): string {
        return self::COVERS;
    }

    public function get_description() {
        return 'Runs one WP-CLI command (official WP-CLI 2.12.0 syntax, as typed after `wp`) in-process as your WordPress user: no shell, no wp binary. Example: `post list --post_type=page --format=json`. Run `help`, `help <command>` or `cli has-command "<command>"` to see what you can run. A command that an existing tool performs runs through that tool, so that tool must be switched on and granted to you; writes may pause for approval when the site requires it. Refused by name: eval, eval-file, shell and other developer commands, db query/export/import/reset, config, arguments that read a file on the server, and commands added by other plugins. Values with spaces must be quoted; shell syntax (pipes, redirects, $variables, globs) is refused. Options it never writes, each with what does the job instead: siteurl, home (`search-replace`); active_plugins, recently_activated (`plugin activate`, `plugin deactivate`); template, stylesheet (`theme activate`); {prefix}user_roles (`role create`, `cap add`, `cap remove`); default_role, users_can_register, admin_email (Settings › General in wp-admin); cron (`cron event schedule`, `cron event delete`); rewrite_rules (`rewrite flush`); permalink_structure (`rewrite structure`); db_version, initial_db_version (`core update-db`); uninstall_plugins (`plugin uninstall`); auto_update_plugins (`plugin auto-updates enable`); auto_update_themes (`theme auto-updates enable`); site_admins (`super-admin add`); active_sitewide_plugins (`plugin activate --network`). Site transients it never writes (the update offers, in any storage form): _site_transient_update_core (`core update`); _site_transient_update_plugins (`plugin update`); _site_transient_update_themes (`theme update`). It never prints the salts, never reads or writes the options, transients or user meta of the plugin that serves this connection, and never runs, schedules or removes its cron events or grants or removes its capabilities. Returns { output, stderr, exit_code, changed, warnings, calls, pending_approval, history, redacted, notes }: `output` and `stderr` are exactly what WP-CLI prints, and `changed` says whether anything was written.';
    }

    public function get_category() {
        return 'cli';
    }

    public function get_required_capability() {
        
        
        
        return 'manage_options';
    }

    


    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'idempotentHint'  => false,
            
            
            'openWorldHint'   => true,
        );
    }

    public function is_disabled_by_default() {
        return true;
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'command'       => array(
                    'type'        => 'string',
                    'description' => 'One WP-CLI command as typed after `wp`, e.g. "post list --post_type=page --format=json". Quote values that contain spaces. No shell syntax.',
                ),
                'output_offset' => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'Only for `help` and `cli cmd-dump`, whose output can exceed 64 KB: the byte offset to continue from, as the previous result\'s `next_offset` gave it.',
                ),
            ),
            'required'   => array( 'command' ),
        );
    }

    





    public function set_dispatcher( callable $dispatcher, ?callable $may_use = null ): void {
        $this->dispatcher = $dispatcher;
        $this->may_use    = $may_use;
    }

    





    public function get_approval_policy( array $arguments ) {
        
        
        
        
        
        $classification = self::classify( $arguments );
        return Router::approval_policy( $classification );
    }

    public function is_composite( array $arguments = array() ) {
        return self::classify( $arguments )->is_composite();
    }

    public function is_read_only_call( array $arguments ) {
        return ! self::classify( $arguments )->write;
    }

    public function describe( array $arguments ) {
        $classification = self::classify( $arguments );
        return Router::describe( $classification );
    }

    







    public function approval_refusal( array $arguments ): ?string {
        $gate    = class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' );
        $max     = $gate ? \Easy_MCP_AI\Approvals\Approval_Gate::PREVIEW_MAX : 2000;
        $preview = (string) $this->describe( $arguments );
        
        $preview = $gate ? \Easy_MCP_AI\Approvals\Approval_Gate::flatten( $preview ) : trim( (string) preg_replace( '/\s+/', ' ', $preview ) );
        $length  = function_exists( 'mb_strlen' ) ? mb_strlen( $preview ) : strlen( $preview );
        if ( $length <= $max ) {
            return null;
        }
        return sprintf(
            /* translators: 1: preview length, 2: the most the approval page shows */
            __( 'This command\'s approval preview is %1$d characters long and the approval page shows %2$d, so it was not sent for approval: a person must see every write they approve. Run it for fewer targets at a time.', 'easy-mcp-ai' ),
            $length,
            $max
        );
    }

    public function redact_for_record( array $arguments ) {
        if ( isset( $arguments['command'] ) && is_string( $arguments['command'] ) ) {
            self::load();
            $arguments['command'] = Redactor::mask_command( $arguments['command'] )[0];
        }
        return $arguments;
    }

    






    public function execute( array $arguments ) {
        self::load();
        
        
        
        
        
        $redeemed = 'redeem' === \Easy_MCP_AI\MCP\Server::current_call_approval_kind();
        $ctx      = new Context(
            new Output(),
            $this->dispatcher,
            $this->may_use,
            $redeemed && null !== self::classify( $arguments )->plan
        );
        return Router::run( $arguments, $ctx );
    }

    


    private static function classify( array $arguments ): \Easy_MCP_AI\Cli_Router\Classification {
        self::load();
        return Router::classify( isset( $arguments['command'] ) && is_string( $arguments['command'] ) ? $arguments['command'] : '' );
    }

    
    private static function load(): void {
        if ( ! class_exists( '\\Easy_MCP_AI\\Cli_Router\\Loader', false ) ) {
            require_once dirname( __DIR__, 2 ) . '/cli-router/class-loader.php';
        }
        Loader::register();
    }
}
