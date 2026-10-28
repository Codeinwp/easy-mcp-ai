<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Handlers\Theme_Handler;
use Easy_MCP_AI\Cli_Router\Handlers\Theme_Mod_Handler;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Theme_Adapter extends Command_Handler {

    const SWITCH  = 'wp_switch_theme';
    const LISTER  = 'wp_list_themes';
    const INSTALL = 'wp_install_theme';
    const DELETE  = 'wp_delete_theme';
    const UPDATE  = 'wp_update_theme';
    const MOD_SET = 'wp_update_theme_mod';
    const MOD_DEL = 'wp_delete_theme_mod';

    const INSTALL_MAX = 5;

    const BATCH_MAX = 5;

    const VERSION_HIGHER = 'version higher than expected';

    const TOOL_ALREADY_INSTALLED = 'The theme "%s" is already installed';

    const REFUSE_ZIP      = "wp_cli_run installs themes only by their wordpress.org slug: '%s' is a zip file, a server path or a URL, and installing from a file or a URL reads a file on the server or from another host (plan §4.1). Pass the slug from the theme's wordpress.org address.";
    const REFUSE_TOO_MANY = "wp_cli_run installs at most %d themes in one 'wp theme install' command, and this one names %d. Run it in smaller groups.";
    const REFUSE_FIELD    = "wp_cli_run cannot print the '%s' field of 'wp theme list': %s";
    const REASON_DESC     = 'wp_list_themes, which answers this command, does not return theme descriptions yet. `theme get <theme> --field=description` prints one.';
    const REASON_PACKAGE  = 'the update package URL can carry a licence key, and wp_list_themes never returns it.';
    const REFUSE_FILTER   = "wp_cli_run refuses the filter --%s: WP-CLI's theme list filters only on %s, and silently ignores any other --<field>=<value>.";

    const REFUSE_OVER_CAP = "wp_cli_run runs at most %d items in one 'wp %s' command, and this one names %d. Run it in smaller groups.";
    const NOT_PLANNED     = "%s: its update appeared when wp_list_themes refreshed the update data, after this command's plan (the approval preview) was built from the stored data, so it was not updated. Run the command again to update it.";

    const NOTE_MOD_STORED    = "The setting's sanitizer stored %s for '%s', so WP-CLI's check (the value read back equals the string passed) prints 'Could not update'.";

    const MOD_STAGED = "Theme mod %1\$s was not set live: %2\$s";
    const NOTE_ACTIVE_NOOP   = "Nothing changed: '%s' was already the active theme.";
    const NOTE_REQUIREMENTS  = "WP-CLI checks the wordpress.org theme directory's Requires at least and Requires PHP before it downloads a theme (--ignore-requirements skips that check). wp_cli_run never makes that check, with or without the flag; WordPress's own check of the downloaded package's headers still refuses an incompatible theme, as it does under WP-CLI.";
    const NOT_CAPTURED_FILES = 'Theme files are outside change history: a theme installed, updated or deleted here cannot be restored from it.';

    public function activate( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, self::SWITCH );
        $this->do_activate( (string) $inv->args[0], $ctx, null );
    }

    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        Fetchers::require_tool( $ctx, $inv, self::LISTER );
        self::refuse_list_fields( $assoc, $ctx );
        $obj_fields = Theme_Handler::list_fields();
        self::refuse_list_filters( $assoc, array_merge( $obj_fields, array( 'requires', 'requires_php' ) ), $ctx );

        $skip      = (bool) Read_Value::flag( $assoc, 'skip-update-check', false );
        $all_items = self::listed_items( $ctx, $ctx->delegate( self::LISTER, array( 'skip_update_check' => $skip ) ) );
        self::refuse_requires_filters( $assoc, $obj_fields, $all_items, $ctx );

        foreach ( $all_items as $key => $item ) {
            $item              = self::shown_item( $item, $obj_fields );
            $all_items[ $key ] = $item;
            if ( ! self::matches_filters( $item, $obj_fields, $assoc ) ) {
                unset( $all_items[ $key ] );
            }
        }

        $this->formatter( $ctx, $assoc, $obj_fields, 'theme' )->display_items( $all_items );
    }

    private static function listed_items( Context $ctx, array $listed ): array {
        if ( ! $listed['ok'] ) {
            $ctx->out->error( (string) $listed['error'] );
        }
        foreach ( (array) ( $listed['payload']['warnings'] ?? array() ) as $warning ) {
            $ctx->out->warning( (string) $warning );
        }
        return self::items( (array) ( $listed['payload']['themes'] ?? array() ) );
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

    private static function matches_filters( array $item, array $obj_fields, array $assoc ): bool {
        foreach ( $obj_fields as $field ) {
            if ( ! array_key_exists( $field, $assoc ) ) {
                continue;
            }
            $field_filter = $assoc[ $field ];
            if (
                $item[ $field ] !== $field_filter
                && ! in_array( $item[ $field ], array_map( 'trim', explode( ',', (string) $field_filter ) ), true )
            ) {
                return false;
            }
        }
        return true;
    }

    public function preflight_install( Invocation $inv, Context $ctx ): void {
        $args = array_values( array_map( 'strval', $inv->args ) );
        if ( count( $args ) > self::INSTALL_MAX ) {
            $ctx->out->error( sprintf( self::REFUSE_TOO_MANY, self::INSTALL_MAX, count( $args ) ) );
        }
        foreach ( $args as $slug ) {
            if ( false !== strpos( $slug, '://' ) || '.zip' === strtolower( substr( $slug, -4 ) ) || false !== strpos( $slug, '/' ) || false !== strpos( $slug, '\\' ) ) {
                $ctx->out->error( sprintf( self::REFUSE_ZIP, $slug ) );
            }
        }
    }

    public function plan_install( Invocation $inv ): array {
        $plan  = array();
        $slugs = array();
        foreach ( $inv->args as $slug ) {
            $slug = (string) $slug;
            if ( '' === $slug ) {
                continue;
            }
            $slugs[] = $slug;
            $plan[]  = array(
                'tool'      => self::INSTALL,
                'arguments' => self::install_arguments( $slug, $inv->assoc ),
            );
        }
        if ( Read_Value::flag( $inv->assoc, 'activate', false ) ) {
            foreach ( $slugs as $slug ) {
                $plan[] = array(
                    'tool'      => self::SWITCH,
                    'arguments' => array( 'stylesheet' => $slug ),
                );
            }
        }
        return $plan;
    }

    public function install( Invocation $inv, Context $ctx ): void {
        $args     = array_values( array_map( 'strval', $inv->args ) );
        $activate = (bool) Read_Value::flag( $inv->assoc, 'activate', false );
        Fetchers::require_tool( $ctx, $inv, self::INSTALL );
        if ( $activate ) {
            Fetchers::require_tool( $ctx, $inv, self::SWITCH );
        }
        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        if ( ! isset( $inv->assoc['version'] ) ) {
            $ctx->note( self::NOTE_REQUIREMENTS );
        }

        if ( count( $args ) > 1 && $activate ) {
            $ctx->out->warning( sprintf( 'Only this single theme will be activated: %s', end( $args ) ) );
        }

        $batch = new Batch( $inv, $ctx, 'theme', 'install' );
        $batch->run(
            $args,
            function ( string $slug, Batch $batch ) use ( $inv, $ctx, $activate ): string {
                return $this->install_slug( $slug, $inv, $ctx, $batch, $activate );
            }
        );
        $result = $batch->result();
        Values::report_batch( $ctx->out, 'theme', 'install', count( $args ), $result['successes'], $result['failures'] );
    }

    private function install_slug( string $slug, Invocation $inv, Context $ctx, Batch $batch, bool $activate ): string {
        if ( empty( $slug ) ) {
            $ctx->out->warning( 'Ignoring ambiguous empty slug value.' );
            return Batch::SKIP;
        }
        $outcome = $this->install_one( $slug, $inv, $ctx, $batch );

        $extension        = Fetchers::of( 'theme' )->get_many( array( $slug ), $ctx->out );
        $allow_activation = in_array( $outcome, array( 'installed', 'already' ), true );
        if ( $allow_activation && count( $extension ) > 0 && $activate ) {
            $ctx->out->log( "Activating '$slug'..." );
            $this->do_activate( $slug, $ctx, $batch );
        }
        if ( 'installed' === $outcome ) {
            return Batch::SUCCESS;
        }
        return 'already' === $outcome ? Batch::SKIP : Batch::FAILURE;
    }

    public function preflight_batch( Invocation $inv, Context $ctx ): void {
        if ( count( $inv->args ) > self::BATCH_MAX ) {
            $ctx->out->error( sprintf( self::REFUSE_OVER_CAP, self::BATCH_MAX, $inv->command, count( $inv->args ) ) );
        }
    }

    public function plan_delete( Invocation $inv ): array {
        if ( count( $inv->args ) > self::BATCH_MAX || ! self::plan_may_read( self::DELETE, 'delete_themes' ) ) {
            return array();
        }
        $plan = array();
        foreach ( self::deletable( self::plan_targets( $inv ) ) as $theme ) {
            $plan[] = array(
                'tool'      => self::DELETE,
                'arguments' => array( 'stylesheet' => (string) $theme->get_stylesheet() ),
            );
        }
        return $plan;
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, self::DELETE );
        $all  = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        $args = Theme_Handler::check_optional_args_and_all( array_values( array_map( 'strval', $inv->args ) ), $all, $ctx, 'delete' );
        if ( ! $args ) {
            return;
        }
        $ctx->not_captured( self::NOT_CAPTURED_FILES );

        $themes  = Fetchers::of( 'theme' )->get_many( $args, $ctx->out );
        $missing = self::missing( $args, $themes );
        if ( $missing ) {
            $ctx->fail_missing_target( sprintf( 'No theme named %s is installed, so nothing was deleted for it. WP-CLI prints a Warning and does not count it.', implode( ', ', $missing ) ) );
        }
        if ( $all ) {
            $themes = self::deletable( $themes );
        }

        $batch  = new Batch( $inv, $ctx, 'theme', 'delete' );
        $result = $batch->run(
            $themes,
            static function ( $theme, Batch $batch ) use ( $ctx, $all ) {
                $theme_slug = (string) $theme->get_stylesheet();
                $status     = Theme_Handler::status_of( Theme_Handler::theme_data( $theme_slug ) );
                if ( 'active' === $status ) {
                    if ( ! $all ) {
                        $ctx->out->warning( "Can't delete the currently active theme: $theme_slug" );
                        return Batch::FAILURE;
                    }
                    return Batch::SKIP;
                }
                if ( 'parent' === $status ) {
                    if ( ! $all ) {
                        $ctx->out->warning( "Can't delete the parent of the currently active theme: $theme_slug" );
                        return Batch::FAILURE;
                    }
                    return Batch::SKIP;
                }
                $r = $batch->delegate( self::DELETE, array( 'stylesheet' => $theme_slug ) );
                if ( ! $r['ok'] ) {
                    if ( ! Batch::is_rate_limited( $r ) ) {
                        $ctx->out->warning( (string) $r['error'] );
                    }
                    return Batch::FAILURE;
                }
                $ctx->mark_changed();
                $ctx->out->log( "Deleted '$theme_slug' theme." );
                return Batch::SUCCESS;
            },
            static function ( $theme ) {
                return 'theme ' . (string) $theme->get_stylesheet();
            }
        );
        Values::report_batch( $ctx->out, 'theme', 'delete', count( $args ), $result['successes'], $result['failures'] );
    }

    public function update( Invocation $inv, Context $ctx ): void {
        $assoc = $inv->assoc;
        $all   = (bool) Read_Value::flag( $assoc, 'all', false );
        Fetchers::require_tool( $ctx, $inv, self::UPDATE );

        $args = Theme_Handler::check_optional_args_and_all( array_values( array_map( 'strval', $inv->args ) ), $all, $ctx );
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
            $themes = Fetchers::of( 'theme' )->get_many( $args, $ctx->out );
            if ( count( $themes ) > self::BATCH_MAX ) {
                $ctx->out->error( sprintf( self::REFUSE_OVER_CAP, self::BATCH_MAX, $inv->command, count( $themes ) ) );
            }
            foreach ( $themes as $theme ) {
                $this->install_version( (string) $theme->get_stylesheet(), (string) $assoc['version'], $inv, $ctx );
            }
            return;
        }

        $this->update_many( $args, $assoc, $inv, $ctx );
    }

    public function plan_update( Invocation $inv ): array {
        $assoc = $inv->assoc;
        if ( ( isset( $assoc['version'] ) && isset( $assoc['dry-run'] ) ) || count( $inv->args ) > self::BATCH_MAX ) {
            return array();
        }
        if ( ! self::plan_may_read( self::UPDATE, 'update_themes' ) ) {
            return array();
        }
        if ( isset( $assoc['version'] ) ) {
            return self::plan_update_to_version( $inv, (string) $assoc['version'] );
        }
        if ( ! self::plan_may_read( self::LISTER, 'update_themes' ) ) {
            return array();
        }
        return self::plan_update_many( $inv );
    }

    private static function plan_update_to_version( Invocation $inv, string $version ): array {
        $plan = array();
        foreach ( self::plan_targets( $inv ) as $theme ) {
            $plan[] = array(
                'tool'      => self::UPDATE,
                'arguments' => array(
                    'stylesheet' => (string) $theme->get_stylesheet(),
                    'version'    => $version,
                ),
            );
        }
        return count( $plan ) > self::BATCH_MAX ? array() : $plan;
    }

    private static function plan_update_many( Invocation $inv ): array {
        $plan = array(
            array(
                'tool'      => self::LISTER,
                'arguments' => array( 'skip_update_check' => false ),
            ),
        );
        if ( Read_Value::flag( $inv->assoc, 'dry-run' ) ) {
            return $plan;
        }
        $updates = self::planned_updates( $inv );
        if ( count( $updates ) > self::BATCH_MAX ) {
            return array();
        }
        foreach ( $updates as $stylesheet ) {
            $plan[] = array(
                'tool'      => self::UPDATE,
                'arguments' => array( 'stylesheet' => $stylesheet ),
            );
        }
        return $plan;
    }

    public function mod_set( Invocation $inv, Context $ctx ): void {
        list( $mod, $value ) = array_values( array_map( 'strval', $inv->args ) );

        $result = $ctx->delegate(
            self::MOD_SET,
            array(
                'key'   => $mod,
                'value' => $value,
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( isset( $result['payload']['published'] ) && false === $result['payload']['published'] ) {
            $ctx->mark_changed();
            $ctx->out->error( sprintf( self::MOD_STAGED, $mod, isset( $result['payload']['message'] ) ? (string) $result['payload']['message'] : 'it was staged in the Customizer draft for a person to publish.' ) );
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }

        if ( array_key_exists( 'value', $result['payload'] ) && self::mod_set_holds( $value, $result['payload']['value'] ) ) {
            $ctx->out->success( "Theme mod {$mod} set to {$value}." );
        } else {
            $stored = array_key_exists( 'stored', $result['payload'] ) ? $result['payload']['stored'] : null;
            $ctx->note( sprintf( self::NOTE_MOD_STORED, (string) json_encode( $stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR ), $mod ) );
            $ctx->out->success( "Could not update theme mod {$mod}." );
        }
    }

    private static function mod_set_holds( string $value, $read_back ): bool {
        return $value === $read_back || ( is_array( $read_back ) && json_decode( $value, true ) === $read_back );
    }

    public function plan_mod_remove( Invocation $inv ): array {
        return array(
            array(
                'tool'      => self::MOD_DEL,
                'arguments' => self::mod_remove_arguments( $inv ),
            ),
        );
    }

    private static function mod_remove_arguments( Invocation $inv ): array {
        return array(
            'keys'                 => array_values( array_unique( array_map( 'strval', $inv->args ) ) ),
            'include_unregistered' => true,
        );
    }

    public function mod_remove( Invocation $inv, Context $ctx ): void {
        $mods = array_values( array_map( 'strval', $inv->args ) );
        Fetchers::require_tool( $ctx, $inv, self::MOD_DEL );
        $ctx->not_captured( Theme_Mod_Handler::NOT_CAPTURED );

        $r = $ctx->delegate( self::MOD_DEL, self::mod_remove_arguments( $inv ) );
        if ( ! $r['ok'] ) {
            $ctx->out->error( (string) $r['error'] );
        }
        $not_stored = array();
        foreach ( (array) ( $r['payload']['removed'] ?? array() ) as $item ) {
            if ( ! empty( $item['deleted'] ) ) {
                $ctx->mark_changed();
            } else {
                $not_stored[] = "'" . ( isset( $item['key'] ) ? (string) $item['key'] : '' ) . "'";
            }
        }

        if ( ! $ctx->changed() ) {
            $ctx->fail_missing_target( sprintf( Theme_Mod_Handler::MISSING_NONE, implode( ', ', $not_stored ) ) );
        } elseif ( $not_stored ) {
            $ctx->note( sprintf( Theme_Mod_Handler::NOTE_NOT_STORED, implode( ', ', $not_stored ) ) );
        }
        $count = count( $mods );
        $ctx->out->success( sprintf( 1 === $count ? '%d mod removed.' : '%d mods removed.', $count ) );
    }

    private static function plan_may_read( string $tool, string $cap ): bool {
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' )
            && false === \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool ) ) {
            return false;
        }
        return (bool) \current_user_can( $cap );
    }

    private static function plan_targets( Invocation $inv ): array {
        $names = array_values( array_map( 'strval', $inv->args ) );
        if ( Read_Value::flag( $inv->assoc, 'all', false ) ) {
            $names = array();
            foreach ( array_keys( (array) Theme_Handler::wp( 'themes' ) ) as $stylesheet ) {
                $names[] = basename( (string) $stylesheet );
            }
        }
        return $names ? array_values( Fetchers::of( 'theme' )->get_many( $names, new Output( true ) ) ) : array();
    }

    private static function deletable( array $themes ): array {
        $out = array();
        foreach ( $themes as $theme ) {
            $status = Theme_Handler::status_of( Theme_Handler::theme_data( (string) $theme->get_stylesheet() ) );
            if ( 'active' !== $status && 'parent' !== $status ) {
                $out[] = $theme;
            }
        }
        return $out;
    }

    private static function planned_updates( Invocation $inv ): array {
        if ( ! self::plan_may_read( self::UPDATE, 'update_themes' ) || ! self::plan_may_read( self::LISTER, 'update_themes' ) ) {
            return array();
        }
        $exclude  = self::exclude_flag( $inv->assoc );
        $skip     = null !== $exclude ? explode( ',', trim( $exclude, ',' ) ) : array();
        $offers   = self::stored_offers();
        $versions = (array) Theme_Handler::wp( 'versions' );
        $out      = array();
        foreach ( self::plan_targets( $inv ) as $theme ) {
            $stylesheet = (string) $theme->get_stylesheet();
            if ( ! isset( $offers[ $stylesheet ] ) || in_array( $stylesheet, $skip, true ) || in_array( $stylesheet, $out, true ) ) {
                continue;
            }
            if ( self::offer_fits( (array) $offers[ $stylesheet ], $versions ) ) {
                $out[] = $stylesheet;
            }
        }
        return $out;
    }

    private static function stored_offers(): array {
        $update = Theme_Handler::wp( 'update_transient' );
        return is_array( $update ) && isset( $update['response'] ) ? (array) $update['response'] : array();
    }

    private static function offer_fits( array $offer, array $versions ): bool {
        if ( ! empty( $offer['requires_php'] ) && ! version_compare( (string) ( $versions['php'] ?? PHP_VERSION ), (string) $offer['requires_php'], '>=' ) ) {
            return false;
        }
        return empty( $offer['requires'] ) || version_compare( (string) ( $versions['wp'] ?? '' ), (string) $offer['requires'], '>=' );
    }

    private function do_activate( string $name, Context $ctx, ?Batch $batch ): void {
        $fetcher = Fetchers::of( 'theme' );
        $theme   = $fetcher->get_check( $name, $ctx->out );
        $data    = Theme_Handler::theme_data( (string) $theme->get_stylesheet() );
        if ( null !== $data['errors'] ) {
            $ctx->out->error( (string) $data['errors'] );
        }

        $label = Theme_Handler::header_text( $data['Name'] );
        if ( 'active' === Theme_Handler::status_of( $data ) ) {
            $ctx->out->warning( "The '$label' theme is already active." );
            $ctx->note( sprintf( self::NOTE_ACTIVE_NOOP, $data['stylesheet'] ) );
            return;
        }

        if ( $data['stylesheet'] !== $data['template'] && ! $fetcher->get( $data['template'] ) ) {
            $ctx->out->error( "The '{$data['stylesheet']}' theme cannot be activated without its parent, '{$data['template']}'." );
        }

        $arguments = array( 'stylesheet' => $data['stylesheet'] );
        $result    = null !== $batch ? $batch->delegate( self::SWITCH, $arguments ) : $ctx->delegate( self::SWITCH, $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( empty( $result['payload']['changed'] ) ) {
            $ctx->out->warning( "The '$label' theme is already active." );
            $ctx->note( sprintf( self::NOTE_ACTIVE_NOOP, $data['stylesheet'] ) );
            return;
        }
        $ctx->mark_changed();
        $ctx->out->success( "Switched to '$label' theme." );
    }

    private function install_one( string $slug, Invocation $inv, Context $ctx, Batch $batch ): string {
        $result = $batch->delegate( self::INSTALL, self::install_arguments( $slug, $inv->assoc ) );
        if ( ! $result['ok'] ) {
            if ( Batch::is_rate_limited( $result ) ) {
                return 'failed';
            }
            $error = (string) $result['error'];
            if ( 0 === strpos( $error, sprintf( self::TOOL_ALREADY_INSTALLED, $slug ) ) ) {
                $ctx->out->warning( "$slug: Theme already installed." );
                return 'already';
            }
            $ctx->out->warning( "$slug: " . $error );
            return 'failed';
        }

        $payload = $result['payload'];
        $ctx->mark_changed();
        $ctx->out->log( sprintf( 'Installing %s (%s)', html_entity_decode( (string) ( $payload['name'] ?? $slug ), ENT_QUOTES ), (string) ( $payload['version'] ?? '' ) ) );
        self::print_messages( (array) ( $payload['messages'] ?? array() ), $ctx );
        return 'installed';
    }

    private function install_version( string $stylesheet, string $version, Invocation $inv, Context $ctx ): void {
        $batch = new Batch( $inv, $ctx, 'theme', 'install' );
        $batch->run(
            array( $stylesheet ),
            static function ( $stylesheet, Batch $batch ) use ( $ctx, $version ) {
                $result = $batch->delegate(
                    self::UPDATE,
                    array(
                        'stylesheet' => (string) $stylesheet,
                        'version'    => $version,
                    )
                );
                if ( ! $result['ok'] ) {
                    if ( ! Batch::is_rate_limited( $result ) ) {
                        $ctx->out->warning( $stylesheet . ': ' . (string) $result['error'] );
                    }
                    return Batch::FAILURE;
                }
                $payload = $result['payload'];
                $ctx->mark_changed();
                $ctx->out->log( sprintf( 'Installing %s (%s)', html_entity_decode( (string) ( $payload['name'] ?? $stylesheet ), ENT_QUOTES ), (string) ( $payload['new_version'] ?? $version ) ) );
                self::print_messages( (array) ( $payload['messages'] ?? array() ), $ctx );
                return Batch::SUCCESS;
            }
        );
        $batch->report();
    }

    private function update_many( array $args, array $assoc_args, Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, self::LISTER );
        $format  = self::update_format( $ctx, $assoc_args );
        $dry_run = (bool) Read_Value::flag( $assoc_args, 'dry-run' );

        $planned = $dry_run ? array() : self::planned_or_refuse( $inv, $ctx );

        $items = self::listed_items( $ctx, $ctx->delegate( self::LISTER, array( 'skip_update_check' => false ) ) );

        $errors  = 0;
        $skipped = 0;
        $all     = (bool) Read_Value::flag( $assoc_args, 'all', false );
        if ( ! $all ) {
            $items  = self::picked_items( $ctx, $args, $items );
            $errors = count( $args ) - count( $items );
        }

        $exclude         = self::exclude_flag( $assoc_args );
        $items_to_update = self::without_excluded( self::with_update( $items ), $exclude );
        $items_to_update = self::without_unavailable( $ctx, $items_to_update, $skipped );

        if ( $dry_run ) {
            $this->dry_run( $items_to_update, $format, $exclude, $ctx );
            return;
        }

        $items_to_update = self::only_planned( $ctx, $items_to_update, $planned, $skipped );
        $result          = self::run_updates( $inv, $ctx, $items_to_update );
        $errors         += self::report_updates( $ctx, $items_to_update, $result, $format );

        $num_updated = count( array_filter( $result ) );
        if ( 0 === $num_updated && $skipped ) {
            $errors  = $skipped;
            $skipped = null;
        }
        Values::report_batch( $ctx->out, 'theme', 'update', $all ? count( $items_to_update ) : count( $args ), $num_updated, $errors, $skipped );
        if ( null !== $exclude ) {
            $ctx->out->log( "Skipped updates for: $exclude" );
        }
    }

    private static function update_format( Context $ctx, array $assoc_args ): ?string {
        $format = isset( $assoc_args['format'] ) && is_string( $assoc_args['format'] ) ? $assoc_args['format'] : null;
        if ( in_array( $format, array( 'json', 'csv' ), true ) ) {
            $ctx->out->set_quiet( true );
        }
        return $format;
    }

    private static function exclude_flag( array $assoc_args ): ?string {
        $exclude = Read_Value::flag( $assoc_args, 'exclude' );
        return null === $exclude ? null : (string) $exclude;
    }

    private static function planned_or_refuse( Invocation $inv, Context $ctx ): array {
        $planned = self::planned_updates( $inv );
        if ( count( $planned ) > self::BATCH_MAX ) {
            $ctx->out->error( sprintf( self::REFUSE_OVER_CAP, self::BATCH_MAX, $inv->command, count( $planned ) ) );
        }
        return $planned;
    }

    private static function picked_items( Context $ctx, array $args, array $items ): array {
        $theme_files = array();
        foreach ( $args as $arg ) {
            $theme_files[] = (string) Fetchers::of( 'theme' )->get_check( $arg, $ctx->out )->get_stylesheet();
        }
        return Values::pick_fields( $items, $theme_files );
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
            $theme = (array) Theme_Handler::wp( 'exists', array( $item ) );
            if ( ! empty( $theme['exists'] ) ) {
                unset( $items_to_update[ $item ] );
            }
        }
        return $items_to_update;
    }

    private static function without_unavailable( Context $ctx, array $items_to_update, int &$skipped ): array {
        foreach ( $items_to_update as $item_key => $item_info ) {
            if ( self::VERSION_HIGHER === $item_info['update'] ) {
                $ctx->out->warning( "{$item_info['name']}: " . self::VERSION_HIGHER . '.' );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
            if ( 'unavailable' === $item_info['update'] ) {
                $ctx->out->warning( "{$item_info['name']}: {$item_info['update_unavailable_reason']}" );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
        }
        return $items_to_update;
    }

    private static function only_planned( Context $ctx, array $items_to_update, array $planned, int &$skipped ): array {
        foreach ( $items_to_update as $item_key => $item_info ) {
            if ( ! in_array( (string) $item_info['update_id'], $planned, true ) ) {
                $ctx->out->warning( sprintf( self::NOT_PLANNED, (string) $item_info['name'] ) );
                ++$skipped;
                unset( $items_to_update[ $item_key ] );
            }
        }
        return $items_to_update;
    }

    private static function run_updates( Invocation $inv, Context $ctx, array $items_to_update ): array {
        $result = array();
        if ( empty( $items_to_update ) ) {
            return $result;
        }
        $batch = new Batch( $inv, $ctx, 'theme', 'update' );
        $batch->run(
            array_values( $items_to_update ),
            static function ( array $item, Batch $batch ) use ( $ctx, &$result ): string {
                $r = $batch->delegate( self::UPDATE, array( 'stylesheet' => (string) $item['update_id'] ) );
                if ( ! $r['ok'] ) {
                    $result[ $item['update_id'] ] = null;
                    if ( ! Batch::is_rate_limited( $r ) ) {
                        $ctx->out->log( (string) $r['error'] );
                    }
                    return Batch::FAILURE;
                }
                $result[ $item['update_id'] ] = true;
                $ctx->mark_changed();
                self::print_messages( (array) ( $r['payload']['messages'] ?? array() ), $ctx );
                return Batch::SUCCESS;
            },
            static function ( array $item ): string {
                return 'theme ' . (string) $item['update_id'];
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

    private function dry_run( array $items_to_update, ?string $format, ?string $exclude, Context $ctx ): void {
        if ( empty( $items_to_update ) ) {
            $ctx->out->log( 'No theme updates available.' );
            if ( null !== $exclude ) {
                $ctx->out->log( "Skipped updates for: $exclude" );
            }
            return;
        }
        if ( in_array( $format, array( 'json', 'csv' ), true ) ) {
            self::format_items( $ctx, (string) $format, $items_to_update, array( 'name', 'status', 'version', 'update_version' ) );
        } elseif ( 'summary' === $format ) {
            $ctx->out->log( 'Available theme updates:' );
            foreach ( $items_to_update as $info ) {
                $ctx->out->log( "{$info['title']} update from version {$info['version']} to version {$info['update_version']}" );
            }
        } else {
            $ctx->out->log( 'Available theme updates:' );
            self::format_items( $ctx, 'table', $items_to_update, array( 'name', 'status', 'version', 'update_version' ) );
        }
        if ( null !== $exclude ) {
            $ctx->out->log( "Skipped updates for: $exclude" );
        }
    }

    private static function items( array $themes ): array {
        $items = array();
        foreach ( $themes as $theme ) {
            $stylesheet           = (string) ( $theme['stylesheet'] ?? '' );
            $items[ $stylesheet ] = self::item( $stylesheet, (array) $theme );
        }
        return $items;
    }

    private static function item( string $stylesheet, array $theme ): array {
        $item = self::item_columns( $stylesheet, self::item_status( $theme ), $theme ) + self::item_details( $theme );
        if ( array_key_exists( 'enabled', $theme ) && null !== $theme['enabled'] ) {
            $item['enabled'] = $theme['enabled'];
        }
        return $item;
    }

    private static function item_status( array $theme ): string {
        $status = (string) ( $theme['status'] ?? '' );
        if ( 'active' === $status ) {
            return $status;
        }
        return ! empty( $theme['active_parent'] ) ? 'parent' : 'inactive';
    }

    private static function item_columns( string $stylesheet, string $status, array $theme ): array {
        return array(
            'name'           => $stylesheet,
            'status'         => $status,
            'update'         => $theme['update'] ?? null,
            'update_version' => $theme['update_version'] ?? null,
            'version'        => $theme['version'] ?? '',
            'update_id'      => $stylesheet,
            'title'          => $theme['name'] ?? '',
        );
    }

    private static function item_details( array $theme ): array {
        return array(
            'author'                    => $theme['author'] ?? '',
            'auto_update'               => $theme['auto_update'] ?? null,
            'requires'                  => $theme['requires'] ?? '',
            'requires_php'              => $theme['requires_php'] ?? '',
            'update_unavailable_reason' => $theme['update_unavailable_reason'] ?? '',
        );
    }

    private static function refuse_list_fields( array $assoc, Context $ctx ): void {
        $asked = array();
        if ( isset( $assoc['field'] ) && is_string( $assoc['field'] ) ) {
            $asked[] = $assoc['field'];
        }
        if ( isset( $assoc['fields'] ) && is_string( $assoc['fields'] ) ) {
            $asked = array_merge( $asked, array_map( 'trim', explode( ',', $assoc['fields'] ) ) );
        }
        foreach ( $asked as $field ) {
            $field = (string) $field;
            if ( in_array( $field, array( 'description', 'theme_description' ), true ) ) {
                $ctx->out->error( sprintf( self::REFUSE_FIELD, $field, self::REASON_DESC ) );
            }
            if ( in_array( $field, array( 'update_package', 'theme_update_package' ), true ) ) {
                $ctx->out->error( sprintf( self::REFUSE_FIELD, $field, self::REASON_PACKAGE ) );
            }
        }
    }

    private static function refuse_list_filters( array $assoc, array $filters, Context $ctx ): void {
        foreach ( array_keys( $assoc ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'field', 'fields', 'format', 'status', 'skip-update-check' ), true ) || in_array( $key, $filters, true ) ) {
                continue;
            }
            $ctx->out->error( sprintf( self::REFUSE_FILTER, $key, implode( ', ', array_values( array_unique( $filters ) ) ) ) );
        }
    }

    private static function refuse_requires_filters( array $assoc, array $obj_fields, array $items, Context $ctx ): void {
        foreach ( $items as $item ) {
            if ( 'unavailable' === $item['update'] ) {
                return;
            }
        }
        foreach ( array( 'requires', 'requires_php' ) as $key ) {
            if ( array_key_exists( $key, $assoc ) ) {
                $ctx->out->error( sprintf( self::REFUSE_FILTER, $key, implode( ', ', $obj_fields ) ) );
            }
        }
    }

    private static function install_arguments( string $slug, array $assoc ): array {
        $arguments = array( 'slug' => $slug );
        if ( isset( $assoc['version'] ) ) {
            $arguments['version'] = is_string( $assoc['version'] ) ? $assoc['version'] : (string) (int) $assoc['version'];
        }
        if ( Read_Value::flag( $assoc, 'force', false ) ) {
            $arguments['force'] = true;
        }
        return $arguments;
    }

    private static function print_messages( array $messages, Context $ctx ): void {
        foreach ( $messages as $message ) {
            $string = str_replace( '&#8230;', '...', (string) $message );
            $ctx->out->log( html_entity_decode( $string, ENT_QUOTES, 'UTF-8' ) );
        }
    }

    private static function format_items( Context $ctx, string $format, iterable $items, array $fields ): void {
        $assoc_args = array(
            'format' => $format,
            'fields' => $fields,
        );
        ( new Formatter( $ctx->out, $assoc_args ) )->display_items( $items );
    }

    private static function missing( array $args, array $themes ): array {
        $found = array();
        foreach ( $themes as $theme ) {
            $found[] = (string) $theme->get_stylesheet();
        }
        $missing = array();
        foreach ( $args as $arg ) {
            if ( ! in_array( $arg, $found, true ) ) {
                $missing[] = "'" . $arg . "'";
            }
        }
        return $missing;
    }
}
