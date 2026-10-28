<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Widget_Adapter extends Command_Handler {

    const INACTIVE = 'wp_inactive_widgets';

    const LIST_FIELDS = array( 'name', 'id', 'position', 'options' );

    const LEGACY_TYPE  = "wp_cli_run refuses 'wp widget add' for the widget type '%s': it has no REST representation (show_instance_in_rest), so wp_create_widget cannot store its settings. Add it in Appearance > Widgets.";
    const BAD_POSITION = "wp_cli_run refuses the position %s for 'wp %s': positions count from 1. WP-CLI subtracts 1 from it, so 0 places the widget before the last one and a word stops with a PHP error.";
    const STRING_FLAG  = "--%s needs a value (--%s=<%s>) when wp_cli_run runs 'wp %s'.";
    const OVER_CAP     = "wp_cli_run moves at most %d widgets in one 'wp %s' command, and these sidebars hold %d. Reset fewer sidebars at a time.";

    private static $reader = null;

    public function list_widgets( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_list_widgets' );
        $sidebar = (string) $inv->args[0];
        self::validate_sidebar( $sidebar, $ctx );

        $result = $ctx->delegate( 'wp_list_widgets', array( 'sidebar' => $sidebar ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $widgets = self::widget_rows( isset( $result['payload']['widgets'] ) ? (array) $result['payload']['widgets'] : array(), $sidebar );

        $assoc = $inv->assoc;
        if ( ! empty( $assoc['format'] ) && 'ids' === $assoc['format'] ) {
            $ids = array();
            foreach ( $widgets as $widget ) {
                $ids[] = $widget->id;
            }
            $widgets = $ids;
        }
        $this->formatter( $ctx, $assoc, self::LIST_FIELDS )->display_items( $widgets );
    }

    private static function widget_rows( array $rows, string $sidebar ): array {
        $widgets = array();
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) || ! isset( $row['id'] ) || ( isset( $row['sidebar'] ) && (string) $row['sidebar'] !== $sidebar ) ) {
                continue;
            }
            $widgets[] = self::widget_row( $row, count( $widgets ) );
        }
        return $widgets;
    }

    private static function widget_row( array $row, int $listed ): object {
        $id                    = (string) $row['id'];
        list( $name, $number ) = self::split_id( $id );
        $options               = ! empty( $row['settings_editable'] ) ? ( isset( $row['settings'] ) ? $row['settings'] : array() ) : self::wp( 'widget_instance', array( $name, $number ), array( __CLASS__, 'live_widget_instance' ) );
        return (object) array(
            'name'     => $name,
            'id'       => $id,
            'position' => ( isset( $row['position'] ) ? (int) $row['position'] : $listed ) + 1,
            'options'  => $options,
        );
    }

    public function add( Invocation $inv, Context $ctx ): void {
        $id_base  = (string) $inv->args[0];
        $sidebar  = (string) $inv->args[1];
        $position = null;
        if ( array_key_exists( 2, $inv->args ) ) {
            $position = self::position( (string) $inv->args[2], $inv, $ctx );
        }
        Fetchers::require_tool( $ctx, $inv, 'wp_create_widget' );
        self::validate_sidebar( $sidebar, $ctx );
        $type = (string) self::wp( 'widget_type', array( $id_base ), array( __CLASS__, 'live_widget_type' ) );
        if ( 'unknown' === $type ) {
            $ctx->out->error( 'Invalid widget type.' );
        }
        if ( 'legacy' === $type ) {
            $ctx->out->error( sprintf( self::LEGACY_TYPE, $id_base ) );
        }

        $arguments = array(
            'sidebar' => $sidebar,
            'id_base' => $id_base,
        );
        if ( $inv->assoc ) {
            $arguments['instance'] = $inv->assoc;
        }
        if ( null !== $position ) {
            $arguments['position'] = $position - 1;
        }
        $result = $ctx->delegate( 'wp_create_widget', $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $ctx->mark_changed();
        self::payload_warning( $result['payload'], $ctx );
        $ctx->out->success( 'Added widget to sidebar.' );
    }

    public function update( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_update_widget' );
        $widget_id = (string) $inv->args[0];
        if ( null === self::locate( $widget_id ) ) {
            $ctx->out->error( "Widget doesn't exist." );
        }
        if ( empty( $inv->assoc ) ) {
            $ctx->out->error( 'No options specified to update.' );
        }
        $result = $ctx->delegate(
            'wp_update_widget',
            array(
                'widget_id' => $widget_id,
                'instance'  => $inv->assoc,
            )
        );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        $ctx->out->success( 'Widget updated.' );
    }

    public function move( Invocation $inv, Context $ctx ): void {
        $given    = self::given_flag( $inv, 'position', $ctx );
        $position = null === $given ? null : self::position( $given, $inv, $ctx );
        $target   = self::given_flag( $inv, 'sidebar-id', $ctx );
        Fetchers::require_tool( $ctx, $inv, 'wp_update_widget' );

        $widget_id = (string) $inv->args[0];
        $current   = self::locate( $widget_id );
        if ( null === $current ) {
            $ctx->out->error( "Widget doesn't exist." );
        }
        if ( null === $position && null === $target ) {
            $ctx->out->error( 'A new position or new sidebar must be specified.' );
        }
        $new_sidebar = null !== $target ? $target : $current['sidebar'];
        self::validate_sidebar( $new_sidebar, $ctx );

        $arguments = array(
            'widget_id' => $widget_id,
            'position'  => self::move_index( $position, $new_sidebar, $current ),
        );
        if ( $new_sidebar !== $current['sidebar'] ) {
            $arguments['sidebar'] = $new_sidebar;
        }
        $result = $ctx->delegate( 'wp_update_widget', $arguments );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        if ( ! empty( $result['payload']['changed'] ) ) {
            $ctx->mark_changed();
        }
        self::payload_warning( $result['payload'], $ctx );
        $ctx->out->success( 'Widget moved.' );
    }

    private static function given_flag( Invocation $inv, string $flag, Context $ctx ): ?string {
        if ( ! array_key_exists( $flag, $inv->assoc ) || empty( $inv->assoc[ $flag ] ) ) {
            return null;
        }
        if ( ! is_string( $inv->assoc[ $flag ] ) ) {
            $ctx->out->error( sprintf( self::STRING_FLAG, $flag, $flag, $flag, $inv->command ) );
        }
        return $inv->assoc[ $flag ];
    }

    private static function move_index( ?int $position, string $new_sidebar, array $current ): int {
        if ( null !== $position ) {
            return $position - 1;
        }
        if ( $new_sidebar !== $current['sidebar'] ) {
            return 0;
        }
        return $current['index'];
    }

    public function deactivate( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_delete_widget' );
        $batch = new Batch( $inv, $ctx, 'widget', 'deactivate' );
        $batch->run(
            array_values( $inv->args ),
            static function ( $widget_id, Batch $batch ) use ( $ctx ) {
                $widget_id = (string) $widget_id;
                $current   = self::locate( $widget_id );
                if ( null === $current ) {
                    $ctx->out->warning( "Widget '{$widget_id}' doesn't exist." );
                    return Batch::FAILURE;
                }
                if ( self::INACTIVE === $current['sidebar'] ) {
                    $ctx->out->warning( sprintf( "'%s' is already deactivated.", $widget_id ) );
                    return Batch::SKIP;
                }
                return self::remove_one( $widget_id, false, $batch, $ctx );
            },
            array( __CLASS__, 'item_label' )
        );
        $batch->report();
    }

    public function delete( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_delete_widget' );
        $batch = new Batch( $inv, $ctx, 'widget', 'delete' );
        $batch->run(
            array_values( $inv->args ),
            static function ( $widget_id, Batch $batch ) use ( $ctx ) {
                $widget_id = (string) $widget_id;
                if ( null === self::locate( $widget_id ) ) {
                    $ctx->out->warning( "Widget '{$widget_id}' doesn't exist." );
                    return Batch::FAILURE;
                }
                return self::remove_one( $widget_id, true, $batch, $ctx );
            },
            array( __CLASS__, 'item_label' )
        );
        $batch->report();
    }

    public function reset( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, 'wp_delete_widget' );
        $all        = Read_Value::flag( $inv->assoc, 'all', false );
        $registered = self::registered_sidebars();
        $sidebars   = array_values( array_map( 'strval', $inv->args ) );
        if ( ! $all && empty( $sidebars ) ) {
            $ctx->out->error( 'Please specify one or more sidebars, or use --all.' );
        }
        if ( $all ) {
            $sidebars = array_map( 'strval', array_keys( $registered ) );
        }
        if ( empty( $sidebars ) ) {
            $ctx->out->error( 'No sidebar registered.' );
        }

        $stored = self::sidebars_widgets();
        $moving = 0;
        foreach ( array_unique( $sidebars ) as $sidebar_id ) {
            $moving += count( self::resettable_widgets( $sidebar_id, $registered, $stored ) );
        }
        $cap = Batch::cap( $inv );
        if ( $moving > $cap ) {
            $ctx->out->error( sprintf( self::OVER_CAP, $cap, $inv->command, $moving ) );
        }

        $batch = new Batch( $inv, $ctx, 'sidebar', 'reset' );
        $batch->run(
            $sidebars,
            static function ( $sidebar_id, Batch $batch ) use ( $ctx, $registered ) {
                return self::reset_one( (string) $sidebar_id, $registered, $batch, $ctx );
            },
            static function ( $sidebar_id ): string {
                return 'sidebar ' . (string) $sidebar_id;
            }
        );
        $batch->report();
    }

    private static function reset_one( string $sidebar_id, array $registered, Batch $batch, Context $ctx ): string {
        if ( ! array_key_exists( $sidebar_id, $registered ) ) {
            $ctx->out->warning( sprintf( 'Invalid sidebar: %s', $sidebar_id ) );
            return Batch::FAILURE;
        }
        $stored  = self::sidebars_widgets();
        $widgets = isset( $stored[ $sidebar_id ] ) && is_array( $stored[ $sidebar_id ] ) ? array_values( $stored[ $sidebar_id ] ) : array();
        if ( empty( $widgets ) ) {
            $ctx->out->warning( sprintf( "Sidebar '%s' is already empty.", $sidebar_id ) );
            return Batch::SKIP;
        }
        $failed = false;
        foreach ( $widgets as $widget_id ) {
            $outcome = self::remove_one( (string) $widget_id, false, $batch, $ctx );
            if ( null === $outcome ) {
                return Batch::FAILURE;
            }
            if ( Batch::SUCCESS !== $outcome ) {
                $failed = true;
            }
        }
        if ( $failed ) {
            return Batch::FAILURE;
        }
        $ctx->out->log( sprintf( "Sidebar '%s' reset.", $sidebar_id ) );
        return Batch::SUCCESS;
    }

    private static function resettable_widgets( string $sidebar_id, array $registered, array $stored ): array {
        if ( ! array_key_exists( $sidebar_id, $registered ) || empty( $stored[ $sidebar_id ] ) || ! is_array( $stored[ $sidebar_id ] ) ) {
            return array();
        }
        return array_values( array_map( 'strval', $stored[ $sidebar_id ] ) );
    }

    public function plan_reset( Invocation $inv ): array {
        if ( ! self::plan_may_read() ) {
            return array();
        }
        $registered = self::registered_sidebars();
        $sidebars   = Read_Value::flag( $inv->assoc, 'all', false ) ? array_map( 'strval', array_keys( $registered ) ) : array_values( array_map( 'strval', $inv->args ) );
        $stored     = self::sidebars_widgets();
        $plan       = array();
        foreach ( array_unique( $sidebars ) as $sidebar_id ) {
            foreach ( self::resettable_widgets( $sidebar_id, $registered, $stored ) as $widget_id ) {
                $plan[] = array(
                    'tool'      => 'wp_delete_widget',
                    'arguments' => array(
                        'widget_id' => $widget_id,
                        'force'     => false,
                    ),
                );
            }
        }
        return count( $plan ) > Batch::cap( $inv ) ? array() : $plan;
    }

    private static function plan_may_read(): bool {
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) && method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' )
            && false === \Easy_MCP_AI\MCP\Server::current_call_may_use( 'wp_delete_widget' ) ) {
            return false;
        }
        return \current_user_can( 'edit_theme_options' );
    }

    public function plan_deactivate( Invocation $inv ): array {
        return self::plan_remove( $inv, false );
    }

    public function plan_delete( Invocation $inv ): array {
        return self::plan_remove( $inv, true );
    }

    public static function registered_sidebars(): array {
        $sidebars = self::wp( 'registered_sidebars', array(), array( __CLASS__, 'live_registered_sidebars' ) );
        return is_array( $sidebars ) ? $sidebars : array();
    }

    public static function wp( string $key, array $args, callable $live ) {
        if ( null !== self::$reader ) {
            return call_user_func( self::$reader, $key, $args, $live );
        }
        return call_user_func_array( $live, $args );
    }

    public static function use_reader( ?callable $reader ): void {
        self::$reader = $reader;
    }

    public static function live_registered_sidebars(): array {
        global $wp_registered_sidebars;
        $sidebars = array();
        foreach ( is_array( $wp_registered_sidebars ) ? $wp_registered_sidebars : array() as $id => $sidebar ) {
            if ( is_array( $sidebar ) ) {
                $sidebars[ (string) $id ] = $sidebar;
            }
        }
        return $sidebars;
    }

    public static function live_sidebars_widgets(): array {
        $sidebars_widgets = \get_option( 'sidebars_widgets', array() );
        if ( is_array( $sidebars_widgets ) && isset( $sidebars_widgets['array_version'] ) ) {
            unset( $sidebars_widgets['array_version'] );
        }
        return is_array( $sidebars_widgets ) ? $sidebars_widgets : array();
    }

    public static function live_widget_type( string $id_base ): string {
        global $wp_widget_factory;
        if ( ! is_object( $wp_widget_factory ) || ! isset( $wp_widget_factory->widgets ) ) {
            return 'unknown';
        }
        $widget = \wp_filter_object_list( $wp_widget_factory->widgets, array( 'id_base' => $id_base ) );
        if ( empty( $widget ) ) {
            return 'unknown';
        }
        $widget = array_pop( $widget );
        return ! empty( $widget->widget_options['show_instance_in_rest'] ) ? 'editable' : 'legacy';
    }

    public static function live_widget_instance( string $name, string $number ) {
        $options = \get_option( 'widget_' . $name );
        return is_array( $options ) && array_key_exists( $number, $options ) ? $options[ $number ] : null;
    }

    public static function item_label( string $widget_id ): string {
        return 'widget ' . (string) $widget_id;
    }

    private static function validate_sidebar( string $sidebar_id, Context $ctx ): void {
        if ( self::INACTIVE !== $sidebar_id && ! array_key_exists( $sidebar_id, self::registered_sidebars() ) ) {
            $ctx->out->error( 'Invalid sidebar.' );
        }
    }

    private static function sidebars_widgets(): array {
        $stored = self::wp( 'sidebars_widgets', array(), array( __CLASS__, 'live_sidebars_widgets' ) );
        return is_array( $stored ) ? $stored : array();
    }

    private static function locate( string $widget_id ): ?array {
        foreach ( self::sidebars_widgets() as $sidebar_id => $widgets ) {
            if ( ! is_array( $widgets ) || ! in_array( $widget_id, $widgets, true ) ) {
                continue;
            }
            $registered = self::wp( 'registered_widgets', array(), array( __CLASS__, 'live_registered_widgets' ) );
            $registered = is_array( $registered ) ? $registered : array();
            $index      = 0;
            foreach ( $widgets as $id ) {
                if ( $id === $widget_id ) {
                    break;
                }
                if ( in_array( $id, $registered, true ) ) {
                    ++$index;
                }
            }
            return array(
                'sidebar' => (string) $sidebar_id,
                'index'   => $index,
            );
        }
        return null;
    }

    public static function live_registered_widgets(): array {
        global $wp_registered_widgets;
        return is_array( $wp_registered_widgets ) ? array_map( 'strval', array_keys( $wp_registered_widgets ) ) : array();
    }

    private static function remove_one( string $widget_id, bool $force, Batch $batch, Context $ctx ): ?string {
        $result = $batch->delegate(
            'wp_delete_widget',
            array(
                'widget_id' => $widget_id,
                'force'     => $force,
            )
        );
        if ( Batch::is_rate_limited( $result ) ) {
            return null;
        }
        if ( ! $result['ok'] ) {
            $ctx->out->warning( sprintf( "wp_delete_widget refused '%s': %s", $widget_id, (string) $result['error'] ) );
            return Batch::FAILURE;
        }
        $ctx->mark_changed();
        return Batch::SUCCESS;
    }

    private static function plan_remove( Invocation $inv, bool $force ): array {
        $plan = array();
        foreach ( $inv->args as $widget_id ) {
            $plan[] = array(
                'tool'      => 'wp_delete_widget',
                'arguments' => array(
                    'widget_id' => (string) $widget_id,
                    'force'     => $force,
                ),
            );
        }
        return $plan;
    }

    private static function position( string $value, Invocation $inv, Context $ctx ): int {
        if ( ! preg_match( '/^[0-9]+\z/', $value ) || (int) $value < 1 ) {
            $ctx->out->error( sprintf( self::BAD_POSITION, $value, $inv->command ) );
        }
        return (int) $value;
    }

    private static function split_id( string $widget_id ): array {
        $parts  = explode( '-', $widget_id );
        $number = (string) array_pop( $parts );
        return array( implode( '-', $parts ), $number );
    }

    private static function payload_warning( array $payload, Context $ctx ): void {
        if ( isset( $payload['position_applied'] ) && false === $payload['position_applied'] && isset( $payload['warning'] ) && is_string( $payload['warning'] ) ) {
            $ctx->out->warning( $payload['warning'] );
        }
    }
}
