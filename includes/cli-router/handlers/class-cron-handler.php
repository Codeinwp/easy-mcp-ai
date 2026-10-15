<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Self_Protection;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



































class Cron_Handler extends Command_Handler {

    
    const ROW_CAP = 1000;

    
    const LIST_FILTERS = array( 'hook', 'next_run_gmt', 'next_run_relative', 'recurrence' );

    const TIME_FORMAT = 'Y-m-d H:i:s';

    const NO_EVENTS = 'You currently have no scheduled cron events.';

    const OVER_ROW_CAP = "wp_cli_run lists at most %1\$d cron events in one 'cron event list', and %2\$d match. Narrow it with --hook=<hook>, or ask for --format=count.";

    const NOT_A_FILTER = ' --%s parameter is not available through wp_cli_run: WP-CLI\'s cron event list filters only by hook, next_run_gmt, next_run_relative and recurrence, and would ignore it';

    const SKIPPED = 'Self-protection: wp_cli_run left out %1$d cron %2$s it never %3$s: %4$s.';

    const UNSCHEDULE_FAILED = "Failed to unschedule events for hook '%s'.";

    
    const NOT_RECORDED = 'Cron: change history does not record the cron option (scheduled events), by design.';

    
    
    

    
    public function preflight_hook( Invocation $inv, Context $ctx ): void {
        $this->guard( (string) ( array_key_exists( 0, $inv->args ) ? $inv->args[0] : '' ), $ctx );
    }

    



    public function preflight_delete( Invocation $inv, Context $ctx ): void {
        foreach ( array_values( $inv->args ) as $hook ) {
            $this->guard( (string) $hook, $ctx );
        }
        
        
        $scratch = new Output( $ctx->out->is_quiet() );
        try {
            $events = $this->selection( $inv, $scratch );
        } catch ( \Easy_MCP_AI\Cli_Router\Cli_Exit $e ) {
            foreach ( $scratch->warnings() as $warning ) {
                $ctx->out->warning( $warning );
            }
            foreach ( $scratch->errors() as $error ) {
                $ctx->out->error_continue( $error );
            }
            throw $e;
        }
        $count = count( self::split_protected( $events )[0] );
        if ( $count > Batch::cap( $inv ) ) {
            $ctx->out->error( sprintf( Batch::OVER_CAP, Batch::cap( $inv ), $inv->command, $count ) );
        }
    }

    
    public function preflight_list( Invocation $inv, Context $ctx ): void {
        $errors = array();
        foreach ( array_keys( $inv->assoc ) as $key ) {
            $key = (string) $key;
            if ( in_array( $key, array( 'fields', 'field', 'format' ), true ) || in_array( $key, self::LIST_FILTERS, true ) ) {
                continue;
            }
            $errors[] = sprintf( self::NOT_A_FILTER, $key );
        }
        if ( $errors ) {
            $ctx->out->error( "Parameter errors:\n" . implode( "\n", $errors ) );
        }
    }

    
    
    

    
    public function list_( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $formatter  = $this->formatter( $ctx, $assoc_args, self::LIST_FILTERS, 'event' );

        $events = self::cron_events( $this->cron_array(), $this->schedules(), $ctx->out, $this->clock() );

        if ( null === $events ) {
            $events = array();
        }

        $events = self::filter_events( $events, $assoc_args );

        if ( count( $events ) > self::ROW_CAP && 'count' !== $formatter->format ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP, self::ROW_CAP, count( $events ) ) );
        }

        if ( 'ids' === $formatter->format ) {
            $hooks = array();
            foreach ( $events as $event ) {
                $hooks[] = $event->hook;
            }
            $ctx->out->out( implode( ' ', $hooks ) );
        } else {
            if ( self::shows_args( $formatter ) && 'count' !== $formatter->format ) {
                $events = self::redact_args( $events, $ctx );
            }
            $formatter->display_items( $events );
        }
    }

    







    private static function filter_events( array $events, array $assoc_args ): array {
        foreach ( $events as $key => $event ) {
            foreach ( self::LIST_FILTERS as $field ) {
                if ( ! empty( $assoc_args[ $field ] ) && $event->{$field} !== $assoc_args[ $field ] ) {
                    unset( $events[ $key ] );
                    break;
                }
            }
        }
        return $events;
    }

    
    private static function shows_args( Formatter $formatter ): bool {
        return in_array( 'args', (array) $formatter->fields, true ) || in_array( 'event_args', (array) $formatter->fields, true )
            || in_array( $formatter->field, array( 'args', 'event_args' ), true );
    }

    
    public function schedule( Invocation $inv, Context $ctx ): void {
        $args       = $inv->args;
        $assoc_args = $inv->assoc;

        if ( count( $assoc_args ) && count( array_filter( array_keys( $assoc_args ), 'is_string' ) ) ) {
            $ctx->out->warning( 'Numeric keys should be used for the hook arguments.' );
        }

        $hook       = $args[0];
        $next_run   = isset( $args[1] ) ? $args[1] : 'now';
        $recurrence = isset( $args[2] ) ? $args[2] : false;
        $this->guard( (string) $hook, $ctx );

        $timestamp = $this->timestamp( $next_run );

        if ( ! $timestamp ) {
            $ctx->out->error( sprintf( "'%s' is not a valid datetime.", $next_run ) );
        }

        $ctx->not_captured( self::NOT_RECORDED );
        $before = serialize( $this->cron_array() );
        if ( ! empty( $recurrence ) ) {

            $schedules = $this->schedules();

            if ( ! isset( $schedules[ $recurrence ] ) ) {
                $ctx->out->error( sprintf( "'%s' is not a valid schedule name for recurrence.", $recurrence ) );
            }

            $event = $this->schedule_event( (int) $timestamp, (string) $recurrence, (string) $hook, $assoc_args );

        } else {

            $event = $this->schedule_single_event( (int) $timestamp, (string) $hook, $assoc_args );

        }
        if ( serialize( $this->cron_array() ) !== $before ) {
            $ctx->mark_changed();
        }

        if ( false !== $event ) {
            $ctx->out->success( sprintf( "Scheduled event with hook '%s' for %s GMT.", $hook, $this->date( self::TIME_FORMAT, (int) $timestamp ) ) );
        } else {
            $ctx->out->error( 'Event not scheduled.' );
        }
    }

    
    public function unschedule( Invocation $inv, Context $ctx ): void {
        $hook = (string) $inv->args[0];
        $this->guard( $hook, $ctx );

        $ctx->not_captured( self::NOT_RECORDED );
        $before      = serialize( $this->cron_array() );
        $unscheduled = $this->unschedule_hook( $hook );
        if ( serialize( $this->cron_array() ) !== $before ) {
            $ctx->mark_changed();
        }

        if ( empty( $unscheduled ) ) {
            $message = self::UNSCHEDULE_FAILED;

            
            if ( 0 === $unscheduled ) {
                $message = "No events found for hook '%1\$s'.";
            }

            $ctx->out->error( sprintf( $message, $hook ) );

        } else {
            $ctx->out->success(
                sprintf(
                    'Unscheduled %1$d %2$s for hook \'%3$s\'.',
                    $unscheduled,
                    self::pluralize( 'event', (int) $unscheduled ),
                    $hook
                )
            );
        }
    }

    
    public function delete( Invocation $inv, Context $ctx ): void {
        foreach ( array_values( $inv->args ) as $hook ) {
            $this->guard( (string) $hook, $ctx );
        }
        $events = $this->selection( $inv, $ctx->out );

        list( $events, $omitted ) = self::split_protected( $events );
        if ( $omitted ) {
            $ctx->out->warning( self::skipped_warning( $omitted, 'deletes' ) );
        }

        $ctx->not_captured( self::NOT_RECORDED );
        $deleted = 0;
        $batch   = new Batch( $inv, $ctx, 'cron event', 'delete' );
        $batch->run(
            $events,
            function ( $event ) use ( $ctx, &$deleted ) {
                $result = $this->delete_event( $event, $ctx );
                if ( $result ) {
                    ++$deleted;
                    return Batch::SUCCESS;
                }
                $ctx->out->warning( sprintf( "Failed to the delete the cron event '%s'.", $event->hook ) );
                return Batch::FAILURE;
            },
            static function ( $event ) {
                return sprintf( "cron event '%s' at %d", $event->hook, $event->time );
            }
        );

        $message = sprintf( 'Deleted a total of %d %s.', $deleted, self::pluralize( 'cron event', $deleted ) );
        $ctx->out->success( sprintf( $message, $deleted ) );
    }

    
    public function schedule_list( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $formatter  = $this->formatter( $ctx, $assoc_args, array( 'name', 'display', 'interval' ), 'schedule' );

        $schedules = self::sorted_schedules( $this->schedules() );

        if ( 'ids' === $formatter->format ) {
            $names = array();
            foreach ( $schedules as $schedule ) {
                $names[] = is_array( $schedule ) && isset( $schedule['name'] ) ? $schedule['name'] : null;
            }
            $ctx->out->out( implode( ' ', $names ) );
        } else {
            $formatter->display_items( $schedules );
        }
    }

    
    
    

    
    public function describe_schedule( Invocation $inv ): array {
        $hook       = (string) $inv->args[0];
        $next_run   = isset( $inv->args[1] ) ? $inv->args[1] : 'now';
        $recurrence = isset( $inv->args[2] ) ? $inv->args[2] : false;
        if ( empty( $next_run ) || 'now' === $next_run ) {
            $when = 'now (the time it runs)';
        } elseif ( is_numeric( $next_run ) ) {
            $when = gmdate( self::TIME_FORMAT, self::absint( $next_run ) ) . ' UTC';
        } else {
            $when = sprintf( "'%s' (resolved when it runs)", $next_run );
        }
        return array(
            sprintf(
                "Cron hook '%s' with arguments %s: %s → schedule %s at %s",
                $hook,
                self::args_preview( $inv->assoc ),
                self::instances_text( $this->instances( $hook ) ),
                empty( $recurrence ) ? 'once' : sprintf( "'%s'", $recurrence ),
                $when
            ),
        );
    }

    
    public function describe_unschedule( Invocation $inv ): array {
        $hook = (string) $inv->args[0];
        return array( sprintf( "Cron hook '%s': %s → unschedule every one", $hook, self::instances_text( $this->instances( $hook ) ) ) );
    }

    
    public function describe_delete( Invocation $inv ): array {
        $out   = new Output();
        $lines = array();
        try {
            $events = self::split_protected( $this->selection( $inv, $out ) )[0];
        } catch ( \Easy_MCP_AI\Cli_Router\Cli_Exit $e ) {
            return array( 'The selection cannot be built: ' . implode( ' ', $out->errors() ) );
        }
        foreach ( $events as $event ) {
            $lines[] = sprintf( "Cron event '%s' at %s UTC (%s): scheduled → delete", $event->hook, gmdate( self::TIME_FORMAT, (int) $event->time ), substr( (string) $event->sig, 0, 8 ) );
        }
        if ( ! $lines ) {
            $lines[] = 'No cron event matches: nothing to delete';
        }
        return $lines;
    }

    
    
    

    









    public static function cron_events( $crons, array $schedules, Output $out, array $clock ): ?array {
        $events = array();

        if ( empty( $crons ) ) {
            return null;
        }

        foreach ( (array) $crons as $time => $hooks ) {

            
            if ( is_string( $time ) ) {
                $out->warning( sprintf( 'Ignoring incorrectly registered cron event "%s".', $time ) );
                continue;
            }

            foreach ( (array) $hooks as $hook => $hook_events ) {
                foreach ( (array) $hook_events as $sig => $data ) {
                    $events[] = self::event_object( $hook, $time, $sig, $data );
                }
            }
        }

        foreach ( $events as $event ) {
            self::format_event( $event, $schedules, $clock );
        }

        return $events;
    }

    









    private static function event_object( $hook, int $time, $sig, $data ): object {
        return (object) array(
            'hook'     => $hook,
            'time'     => $time,
            'sig'      => $sig,
            'args'     => is_array( $data ) && array_key_exists( 'args', $data ) ? $data['args'] : null,
            'schedule' => is_array( $data ) && array_key_exists( 'schedule', $data ) ? $data['schedule'] : null,
            'interval' => is_array( $data ) && isset( $data['interval'] ) ? $data['interval'] : null,
        );
    }

    










    public static function select_events( array $args, array $assoc_args, callable $read, Output $out ): ?array {
        $args    = array_values( $args );
        $due_now = (bool) Read_Value::flag( $assoc_args, 'due-now' );
        $all     = (bool) Read_Value::flag( $assoc_args, 'all' );
        $exclude = Read_Value::flag( $assoc_args, 'exclude' );

        self::usage_errors( $args, $due_now, $all, $out );

        $events = $read();

        if ( null === $events ) {
            return null;
        }

        self::check_named_hooks( $events, $args, $out );

        
        if ( ! empty( $exclude ) ) {
            $exclude = explode( ',', (string) $exclude );
            $events  = array_filter(
                $events,
                function ( $event ) use ( $exclude ) {
                    return ! in_array( $event->hook, $exclude, true );
                }
            );
        }

        return array_values( self::narrow_events( $events, $args, $due_now, $all ) );
    }

    





    private static function usage_errors( array $args, bool $due_now, bool $all, Output $out ): void {
        if ( empty( $args ) && ! $due_now && ! $all ) {
            $out->error( 'Please specify one or more cron events, or use --due-now/--all.' );
        }

        if ( ! empty( $args ) && $all ) {
            $out->error( 'Please either specify cron events, or use --all.' );
        }

        if ( $due_now && $all ) {
            $out->error( 'Please use either --due-now or --all.' );
        }
    }

    







    private static function check_named_hooks( array $events, array $args, Output $out ): void {
        $hooks = array();
        foreach ( $events as $event ) {
            $hooks[] = $event->hook;
        }
        foreach ( $args as $hook ) {
            if ( ! in_array( $hook, $hooks, true ) ) {
                $out->error( sprintf( "Invalid cron event '%s'", $hook ) );
            }
        }
    }

    








    private static function narrow_events( array $events, array $args, bool $due_now, bool $all ): array {
        
        
        if ( $due_now ) {
            $due_events = array();
            foreach ( $events as $event ) {
                if ( ! empty( $args ) && ! in_array( $event->hook, $args, true ) ) {
                    continue;
                }
                if ( 'now' === $event->next_run_relative ) {
                    $due_events[] = $event;
                }
            }
            return $due_events;
        }
        if ( ! $all ) {
            
            
            $due_events = array();
            foreach ( $events as $event ) {
                if ( in_array( $event->hook, $args, true ) ) {
                    $due_events[] = $event;
                }
            }
            return $due_events;
        }
        return $events;
    }

    





    public static function split_protected( array $events ): array {
        $kept    = array();
        $omitted = array();
        foreach ( $events as $event ) {
            $hook = (string) $event->hook;
            if ( null === Self_Protection::cron_hook( $hook ) ) {
                $kept[] = $event;
                continue;
            }
            $category             = 1 === preg_match( '/^[\x20-\x7E]+\z/', trim( $hook ) ) ? 'plugin' : 'bytes';
            $omitted[ $category ] = ( $omitted[ $category ] ?? 0 ) + 1;
        }
        return array( $kept, $omitted );
    }

    






    public static function skipped_warning( array $omitted, string $verb ): string {
        $labels = array(
            'plugin' => '%d of the plugin that serves this MCP connection (its own scheduled tasks)',
            'bytes'  => '%d whose hook names are not plain ASCII',
        );
        $parts  = array();
        foreach ( $labels as $category => $label ) {
            if ( ! empty( $omitted[ $category ] ) ) {
                $parts[] = sprintf( $label, $omitted[ $category ] );
            }
        }
        $total = array_sum( $omitted );
        return sprintf( self::SKIPPED, $total, 1 === $total ? 'event' : 'events', 'runs' === $verb ? 'runs or deletes' : 'deletes or runs', implode( '; ', $parts ) );
    }

    




    public static function interval( $since ): string {
        if ( $since <= 0 ) {
            return 'now';
        }

        $since = self::absint( $since );

        
        $chunks = array(
            array( 60 * 60 * 24 * 365, 'year' ),
            array( 60 * 60 * 24 * 30, 'month' ),
            array( 60 * 60 * 24 * 7, 'week' ),
            array( 60 * 60 * 24, 'day' ),
            array( 60 * 60, 'hour' ),
            array( 60, 'minute' ),
            array( 1, 'second' ),
        );

        $count   = 0.0;
        $seconds = 1;
        $name    = 'second';
        
        for ( $i = 0, $j = count( $chunks ); $i < $j; $i++ ) {
            $seconds = $chunks[ $i ][0];
            $name    = $chunks[ $i ][1];

            
            $count = floor( $since / $seconds );
            if ( floatval( 0 ) !== $count ) {
                break;
            }
        }

        
        $output = sprintf( '%d %s', $count, self::pluralize( $name, self::absint( $count ) ) );

        
        if ( $i + 1 < $j ) {
            $seconds2 = $chunks[ $i + 1 ][0];
            $name2    = $chunks[ $i + 1 ][1];

            $count2 = floor( ( $since - ( $seconds * $count ) ) / $seconds2 );
            if ( floatval( 0 ) !== $count2 ) {
                
                $output .= ' ' . sprintf( '%d %s', $count2, self::pluralize( $name2, self::absint( $count2 ) ) );
            }
        }

        return $output;
    }

    





    public static function absint( $value ): int {
        return function_exists( 'absint' ) ? (int) \absint( $value ) : abs( (int) $value );
    }

    




    public static function pluralize( string $noun, ?int $count = null ): string {
        if ( 1 === $count ) {
            return $noun;
        }
        return $noun . 's';
    }

    






    public static function sorted_schedules( array $schedules ): array {
        if ( ! empty( $schedules ) ) {
            uasort(
                $schedules,
                static function ( array $a, array $b ) {
                    return $a['interval'] - $b['interval'];
                }
            );
            $schedules = array_map(
                static function ( array $schedule, $name ) {
                    $schedule['name'] = $name;
                    return $schedule;
                },
                $schedules,
                array_keys( $schedules )
            );
        }
        return $schedules;
    }

    
    
    

    








    protected function seam( string $name, array $args, callable $real ) {
        return call_user_func_array( $real, $args );
    }

    
    protected function cron_array() {
        return $this->seam(
            '_get_cron_array',
            array(),
            static function () {
                return \_get_cron_array();
            }
        );
    }

    
    protected function schedules(): array {
        return (array) $this->seam(
            'wp_get_schedules',
            array(),
            static function () {
                return \wp_get_schedules();
            }
        );
    }

    protected function now(): int {
        return (int) $this->seam(
            'time',
            array(),
            static function () {
                return time();
            }
        );
    }

    
    protected function date( string $format, int $timestamp ): string {
        return (string) $this->seam(
            'date',
            array( $format, $timestamp ),
            static function ( $format, $timestamp ) {
                return date( $format, $timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- the exact call WP-CLI makes.
            }
        );
    }

    protected function local_date( string $gmt ): string {
        return (string) $this->seam(
            'get_date_from_gmt',
            array( $gmt, self::TIME_FORMAT ),
            static function ( $gmt, $format ) {
                return \get_date_from_gmt( $gmt, $format );
            }
        );
    }

    
    protected function strtotime( string $text ) {
        return $this->seam(
            'strtotime',
            array( $text ),
            static function ( $text ) {
                return strtotime( $text );
            }
        );
    }

    
    protected function schedule_event( int $timestamp, string $recurrence, string $hook, array $args ): bool {
        return (bool) $this->seam(
            'wp_schedule_event',
            array( $timestamp, $recurrence, $hook, $args ),
            static function ( $timestamp, $recurrence, $hook, $args ) {
                return \wp_schedule_event( $timestamp, $recurrence, $hook, $args );
            }
        );
    }

    
    protected function schedule_single_event( int $timestamp, string $hook, array $args ): bool {
        return (bool) $this->seam(
            'wp_schedule_single_event',
            array( $timestamp, $hook, $args ),
            static function ( $timestamp, $hook, $args ) {
                return \wp_schedule_single_event( $timestamp, $hook, $args );
            }
        );
    }

    
    protected function unschedule_hook( string $hook ) {
        return $this->seam(
            'wp_unschedule_hook',
            array( $hook ),
            static function ( $hook ) {
                return \wp_unschedule_hook( $hook );
            }
        );
    }

    



    protected function unschedule_event( $timestamp, string $hook, $args ): bool {
        return (bool) $this->seam(
            'wp_unschedule_event',
            array( $timestamp, $hook, $args ),
            static function ( $timestamp, $hook, $args ) {
                return \wp_unschedule_event( $timestamp, $hook, $args );
            }
        );
    }

    
    
    

    




    protected function clock(): array {
        return array(
            'now'   => function () {
                return $this->now();
            },
            'date'  => function ( string $format, int $timestamp ) {
                return $this->date( $format, $timestamp );
            },
            'local' => function ( string $gmt ) {
                return $this->local_date( $gmt );
            },
        );
    }

    





    private function selection( Invocation $inv, Output $out ): array {
        $events = self::select_events(
            $inv->args,
            $inv->assoc,
            function () use ( $out ) {
                return self::cron_events( $this->cron_array(), $this->schedules(), $out, $this->clock() );
            },
            $out
        );
        if ( null === $events ) {
            $out->error( self::NO_EVENTS );
        }
        return (array) $events;
    }

    



    private function delete_event( object $event, Context $ctx ): bool {
        $crons = $this->cron_array();

        if ( ! isset( $crons[ $event->time ][ $event->hook ][ $event->sig ] ) ) {
            return false;
        }

        $before = serialize( $crons );
        $this->unschedule_event( $event->time, (string) $event->hook, $event->args );
        if ( serialize( $this->cron_array() ) !== $before ) {
            $ctx->mark_changed();
        }
        return true;
    }

    





    private static function format_event( object $event, array $schedules, array $clock ): void {
        $schedule                 = $event->schedule;
        
        $event->recurrence        = ( is_scalar( $schedule ) || null === $schedule ) && isset( $schedules[ $schedule ] ) ? self::interval( $event->interval ) : 'Non-repeating';
        $event->next_run          = call_user_func( $clock['local'], call_user_func( $clock['date'], self::TIME_FORMAT, (int) $event->time ) );
        $event->next_run_gmt      = call_user_func( $clock['date'], self::TIME_FORMAT, (int) $event->time );
        $event->next_run_relative = self::interval( (int) $event->time - (int) call_user_func( $clock['now'] ) );
    }

    





    private static function redact_args( array $events, Context $ctx ): array {
        if ( ! class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            require_once dirname( __DIR__, 2 ) . '/class-text-redactor.php';
        }
        foreach ( $events as $key => $event ) {
            $masked = \Easy_MCP_AI\Text_Redactor::redact_value( $event->args );
            if ( $masked !== $event->args ) {
                $copy           = clone $event;
                $copy->args     = $masked;
                $events[ $key ] = $copy;
                $ctx->redacted( "args of cron event '{$event->hook}' at {$event->time}" );
            }
        }
        return $events;
    }

    




    private function guard( string $hook, Context $ctx ): void {
        $refusal = Self_Protection::cron_hook( $hook );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
    }

    




    private function timestamp( string $next_run ) {
        if ( empty( $next_run ) ) {
            return $this->now();
        } elseif ( is_numeric( $next_run ) ) {
            return self::absint( $next_run );
        }
        return $this->strtotime( (string) $next_run );
    }

    
    private function instances( string $hook ): int {
        $count = 0;
        foreach ( (array) $this->cron_array() as $time => $hooks ) {
            if ( is_array( $hooks ) && isset( $hooks[ $hook ] ) && is_array( $hooks[ $hook ] ) ) {
                $count += count( $hooks[ $hook ] );
            }
        }
        return $count;
    }

    private static function instances_text( int $count ): string {
        return 0 === $count ? 'not scheduled' : sprintf( '%d %s scheduled', $count, 1 === $count ? 'event' : 'events' );
    }

    
    private static function args_preview( array $args ): string {
        if ( ! class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            require_once dirname( __DIR__, 2 ) . '/class-text-redactor.php';
        }
        $text = (string) json_encode( \Easy_MCP_AI\Text_Redactor::redact_value( $args ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
        return strlen( $text ) > 200 ? \Easy_MCP_AI\Cli_Router\Envelope::cut( $text, 197 ) . '...' : $text;
    }
}

