<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Cli_Exit;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Self_Protection;
use Easy_MCP_AI\Cli_Router\Handlers\Cron_Handler;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










































class Cron_Adapter extends Command_Handler {

    const TOOL = 'wp_run_cron_event';

    const DUPLICATE_RECURRING = "wp_cli_run refuses this selection: the recurring cron event '%s' is scheduled more than once with the same arguments, and one of those instances lies in the future. wp_run_cron_event runs the earliest instance of an event, which after the first run is the rescheduled one, so it cannot run each of them by its time as WP-CLI does. Run that hook on its own once per instance, or delete the duplicates with `cron event delete`.";

    const CALLBACK_ERROR = "The cron event '%s' stopped with an error: %s";

    const NOTE_OUTPUT_CUT = "The output of the cron event '%s' was cut at 16 KB by wp_run_cron_event, which also masks secrets in it.";

    
    const NOT_RECORDED = 'Cron: change history does not record the cron option, by design, so the removal and rescheduling of each event is not recorded; what a callback wrote is recorded under wp_run_cron_event.';

    




    public function run( Invocation $inv, Context $ctx ): void {
        Fetchers::require_tool( $ctx, $inv, self::TOOL );
        $refusal = $this->network_refusal();
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
        foreach ( array_values( $inv->args ) as $hook ) {
            $this->guard( (string) $hook, $ctx );
        }

        $events = $this->selection( $inv, $ctx->out );

        list( $events, $omitted ) = Cron_Handler::split_protected( $events );
        if ( $omitted ) {
            $ctx->out->warning( Cron_Handler::skipped_warning( $omitted, 'runs' ) );
        }
        $this->refuse_duplicate_recurring( $events, $ctx );

        $ctx->not_captured( self::NOT_RECORDED );
        $events   = array_values( $events );
        $executed = 0;
        $index    = 0;
        $batch    = new Batch( $inv, $ctx, 'cron event', 'execute' );
        $label    = static function ( object $event ): string {
            return sprintf( "cron event '%s' at %d", $event->hook, $event->time );
        };
        $result   = $batch->run(
            $events,
            function ( $event, Batch $batch ) use ( $ctx, $events, $label, &$executed, &$index ) {
                $outcome = $this->run_one( $event, array_slice( $events, ++$index ), $label, $batch, $ctx );
                if ( Batch::SUCCESS === $outcome ) {
                    ++$executed;
                }
                return $outcome;
            },
            $label
        );

        if ( $result['failures'] ) {
            $batch->report();
        }

        
        
        
        
        
        
        $message = ( 1 === $executed ) ? 'Executed a total of %d cron event.' : 'Executed a total of %d cron events.';
        $ctx->out->success( sprintf( $message, $executed ) );
    }

    







    private function run_one( object $event, array $later, callable $label, Batch $batch, Context $ctx ): string {
        $call = $batch->delegate( self::TOOL, self::arguments( $event ) );
        if ( ! $call['ok'] ) {
            if ( ! Batch::is_rate_limited( $call ) ) {
                $ctx->out->warning( (string) $call['error'] );
            }
            return Batch::FAILURE;
        }
        
        $ctx->mark_changed();
        $payload = $call['payload'];
        $ctx->out->out( isset( $payload['output'] ) && is_string( $payload['output'] ) ? $payload['output'] : '' );
        if ( ! empty( $payload['output_truncated'] ) ) {
            $ctx->note( sprintf( self::NOTE_OUTPUT_CUT, $event->hook ) );
        }
        self::stop_on_callback_error( $event, $payload, $later, $label, $ctx );
        $ms = isset( $payload['duration_ms'] ) && is_numeric( $payload['duration_ms'] ) ? (float) $payload['duration_ms'] : 0.0;
        $ctx->out->log( sprintf( "Executed the cron event '%s' in %ss.", $event->hook, round( $ms / 1000, 3 ) ) );
        return Batch::SUCCESS;
    }

    








    private static function stop_on_callback_error( object $event, array $payload, array $later, callable $label, Context $ctx ): void {
        if ( ! isset( $payload['error'] ) || '' === $payload['error'] ) {
            return;
        }
        foreach ( $later as $next ) {
            $ctx->not_run( $label( $next ) );
        }
        $ctx->out->error( sprintf( self::CALLBACK_ERROR, $event->hook, (string) $payload['error'] ) );
    }

    







    public function plan_run( Invocation $inv ): array {
        if ( ! $this->caller_may_use() || null !== $this->network_refusal() ) {
            return array();
        }
        foreach ( array_values( $inv->args ) as $hook ) {
            if ( null !== Self_Protection::cron_hook( (string) $hook ) ) {
                return array();
            }
        }
        $scratch = new Output();
        try {
            $events = Cron_Handler::split_protected( $this->selection( $inv, $scratch ) )[0];
        } catch ( Cli_Exit $e ) {
            return array();
        }
        if ( null !== self::duplicate_recurring( $events, $this->now() ) ) {
            return array();
        }
        $plan = array();
        foreach ( $events as $event ) {
            $plan[] = array(
                'tool'      => self::TOOL,
                'arguments' => self::arguments( $event ),
            );
        }
        return $plan;
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
            array( $gmt, Cron_Handler::TIME_FORMAT ),
            static function ( $gmt, $format ) {
                return \get_date_from_gmt( $gmt, $format );
            }
        );
    }

    




    protected function network_refusal(): ?string {
        $refusal = $this->seam(
            'network_refusal',
            array(),
            static function () {
                try {
                    \Easy_MCP_AI\Tools\Site_Health\Site_Health_Guard::require_network_admin_on_multisite( 'run cron events' );
                } catch ( \RuntimeException $e ) {
                    return $e->getMessage();
                }
                return null;
            }
        );
        return is_string( $refusal ) ? $refusal : null;
    }

    




    protected function caller_may_use(): bool {
        return (bool) $this->seam(
            'current_call_may_use',
            array( self::TOOL ),
            static function ( $tool ) {
                if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server', false ) || ! method_exists( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' ) ) {
                    return true;
                }
                return false !== \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
            }
        );
    }

    
    
    

    





    private function selection( Invocation $inv, Output $out ): array {
        $clock  = array(
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
        $events = Cron_Handler::select_events(
            $inv->args,
            $inv->assoc,
            function () use ( $out, $clock ) {
                return Cron_Handler::cron_events( $this->cron_array(), $this->schedules(), $out, $clock );
            },
            $out
        );
        if ( null === $events ) {
            $out->error( Cron_Handler::NO_EVENTS );
        }
        return (array) $events;
    }

    




    private static function arguments( object $event ): array {
        return array(
            'hook' => (string) $event->hook,
            'key'  => (string) $event->sig,
        );
    }

    




    private function guard( string $hook, Context $ctx ): void {
        $refusal = Self_Protection::cron_hook( $hook );
        if ( null !== $refusal ) {
            $ctx->out->error( $refusal );
        }
    }

    





    private static function duplicate_recurring( array $events, int $now ): ?string {
        $seen = array();
        foreach ( $events as $event ) {
            $id          = (string) $event->hook . "\0" . (string) $event->sig;
            $seen[ $id ] = isset( $seen[ $id ] ) ? $seen[ $id ] : array();
            $seen[ $id ][] = $event;
        }
        foreach ( $seen as $group ) {
            if ( count( $group ) < 2 ) {
                continue;
            }
            foreach ( $group as $event ) {
                if ( false !== $event->schedule && null !== $event->schedule && (int) $event->time > $now ) {
                    return (string) $event->hook;
                }
            }
        }
        return null;
    }

    



    private function refuse_duplicate_recurring( array $events, Context $ctx ): void {
        $hook = self::duplicate_recurring( $events, $this->now() );
        if ( null !== $hook ) {
            $ctx->out->error( sprintf( self::DUPLICATE_RECURRING, $hook ) );
        }
    }
}
