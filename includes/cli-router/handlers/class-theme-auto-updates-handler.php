<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



























class Theme_Auto_Updates_Handler extends Command_Handler {

    const SITE_OPTION = 'auto_update_themes';

    const REFUSE_FILE_MODS = 'File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter), so WordPress updates no theme automatically and offers no auto-update setting, in wp-admin or here.';
    const REFUSE_TYPE_OFF  = 'Automatic updates for themes are turned off on this site (the auto_update_theme filter, or AUTOMATIC_UPDATER_DISABLED, makes wp_is_auto_update_enabled_for_type( \'theme\' ) false), so WordPress offers no auto-update toggle on Appearance › Themes and wp_cli_run does not change the list either (plan Q15).';

    const NOT_CAPTURED_SINGLE  = 'The auto_update_themes option is recorded by change history only while it records every option (not in allowlist mode); wp_history_apply cannot restore it.';
    const NOT_CAPTURED_NETWORK = 'The auto_update_themes network option (wp_sitemeta) is recorded by change history only while its meta capture setting is on (the default), and a deleted network option is recorded without its previous value; wp_history_apply cannot restore it.';

    






    public function preflight( Invocation $inv, Context $ctx ): void {
        if ( ! Theme_Handler::file_mods_allowed() ) {
            $ctx->out->error( self::REFUSE_FILE_MODS );
        }
        $ctx->require_cap( 'update_themes' );
    }

    




    public function enable( Invocation $inv, Context $ctx ): void {
        $this->refuse_when_type_off( $ctx );
        $all           = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        $disabled_only = (bool) Read_Value::flag( $inv->assoc, 'disabled-only', false );

        $args = Theme_Handler::check_optional_args_and_all( array_values( $inv->args ), $all, $ctx );
        if ( ! $args ) {
            return;
        }

        $themes       = Fetchers::of( 'theme' )->get_many( $args, $ctx->out );
        $auto_updates = self::stored_list();

        $count     = 0;
        $successes = 0;
        foreach ( $themes as $theme ) {
            $stylesheet = (string) $theme->get_stylesheet();
            $enabled    = in_array( $stylesheet, $auto_updates, true );
            if ( $disabled_only && $enabled ) {
                continue;
            }
            ++$count;
            if ( $enabled ) {
                $ctx->out->warning( "Auto-updates already enabled for theme {$stylesheet}." );
            } else {
                $auto_updates[] = $stylesheet;
                ++$successes;
            }
        }

        if ( 0 === $count ) {
            $ctx->out->error( 'No themes provided to enable auto-updates for.' );
        }

        $ctx->not_captured( $this->not_captured_phrase() );
        if ( Theme_Handler::wp( 'update_site_option', array( self::SITE_OPTION, $auto_updates ) ) ) {
            $ctx->mark_changed();
        }

        Values::report_batch( $ctx->out, 'theme auto-update', 'enable', $count, $successes, $count - $successes );
    }

    




    public function disable( Invocation $inv, Context $ctx ): void {
        $this->refuse_when_type_off( $ctx );
        $all          = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        $enabled_only = (bool) Read_Value::flag( $inv->assoc, 'enabled-only', false );

        $args = Theme_Handler::check_optional_args_and_all( array_values( $inv->args ), $all, $ctx );
        if ( ! $args ) {
            return;
        }

        $themes       = Fetchers::of( 'theme' )->get_many( $args, $ctx->out );
        $auto_updates = self::stored_list();

        $count     = 0;
        $successes = 0;
        foreach ( $themes as $theme ) {
            $stylesheet = (string) $theme->get_stylesheet();
            $enabled    = in_array( $stylesheet, $auto_updates, true );
            if ( $enabled_only && ! $enabled ) {
                continue;
            }
            ++$count;
            if ( ! $enabled ) {
                $ctx->out->warning( "Auto-updates already disabled for theme {$stylesheet}." );
            } else {
                $auto_updates = array_diff( $auto_updates, array( $stylesheet ) );
                ++$successes;
            }
        }

        if ( 0 === $count ) {
            $ctx->out->error( 'No themes provided to disable auto-updates for.' );
        }

        $ctx->not_captured( $this->not_captured_phrase() );
        if ( count( $auto_updates ) > 0 ) {
            $written = Theme_Handler::wp( 'update_site_option', array( self::SITE_OPTION, $auto_updates ) );
        } else {
            $written = Theme_Handler::wp( 'delete_site_option', array( self::SITE_OPTION ) );
        }
        if ( $written ) {
            $ctx->mark_changed();
        }

        Values::report_batch( $ctx->out, 'theme auto-update', 'disable', $count, $successes, $count - $successes );
    }

    




    public function status( Invocation $inv, Context $ctx ): void {
        $all           = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        $enabled_only  = (bool) Read_Value::flag( $inv->assoc, 'enabled-only', false );
        $disabled_only = (bool) Read_Value::flag( $inv->assoc, 'disabled-only', false );

        if ( $enabled_only && $disabled_only ) {
            $ctx->out->error( '--enabled-only and --disabled-only are mutually exclusive and cannot be used at the same time.' );
        }

        $args = Theme_Handler::check_optional_args_and_all( array_values( $inv->args ), $all, $ctx );
        if ( ! $args ) {
            return;
        }

        $themes       = Fetchers::of( 'theme' )->get_many( $args, $ctx->out );
        $auto_updates = self::stored_list();

        $results = self::status_rows( $themes, $auto_updates, $enabled_only, $disabled_only );

        $assoc = $inv->assoc;
        $this->formatter( $ctx, $assoc, array( 'name', 'status' ), 'theme' )->display_items( $results );
    }

    







    private static function status_rows( array $themes, array $auto_updates, bool $enabled_only, bool $disabled_only ): array {
        $results = array();
        foreach ( $themes as $theme ) {
            $stylesheet = (string) $theme->get_stylesheet();
            $enabled    = in_array( $stylesheet, $auto_updates, true );
            if ( $enabled_only && ! $enabled ) {
                continue;
            }
            if ( $disabled_only && $enabled ) {
                continue;
            }
            $results[] = array(
                'name'   => $stylesheet,
                'status' => $enabled ? 'enabled' : 'disabled',
            );
        }
        return $results;
    }

    





    public function describe( Invocation $inv ): array {
        $enable = 'enable' === (string) $inv->leaf['name'];
        $names  = array_values( array_map( 'strval', $inv->args ) );
        if ( Read_Value::flag( $inv->assoc, 'all', false ) ) {
            $names = array();
            foreach ( array_keys( (array) Theme_Handler::wp( 'themes' ) ) as $stylesheet ) {
                $names[] = basename( (string) $stylesheet );
            }
        }
        $auto_updates = self::stored_list();
        $lines        = array();
        foreach ( $names as $name ) {
            $lines[] = self::describe_line( $name, in_array( $name, $auto_updates, true ), $enable );
        }
        return $lines ? $lines : array( 'No theme named → nothing changes' );
    }

    
    private static function describe_line( string $name, bool $on, bool $enable ): string {
        if ( $enable ) {
            return sprintf( "Theme '%s': auto-updates %s → %s", $name, $on ? 'on' : 'off', $on ? 'on (no change; WP-CLI counts it as a failure)' : 'on' );
        }
        return sprintf( "Theme '%s': auto-updates %s → %s", $name, $on ? 'on' : 'off', $on ? 'off' : 'off (no change; WP-CLI counts it as a failure)' );
    }

    





    private static function stored_list(): array {
        $auto_updates = Theme_Handler::wp( 'site_option', array( self::SITE_OPTION ) );
        return is_array( $auto_updates ) ? $auto_updates : array();
    }

    





    private function refuse_when_type_off( Context $ctx ): void {
        if ( ! Theme_Handler::wp( 'auto_update_enabled' ) ) {
            $ctx->out->error( self::REFUSE_TYPE_OFF );
        }
    }

    
    private function not_captured_phrase(): string {
        return Theme_Handler::wp( 'is_multisite' ) ? self::NOT_CAPTURED_NETWORK : self::NOT_CAPTURED_SINGLE;
    }
}
