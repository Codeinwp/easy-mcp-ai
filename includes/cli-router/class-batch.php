<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








































final class Batch {

    const SUCCESS = 'success';
    const FAILURE = 'failure';
    const SKIP    = 'skip';

    





    const RATE_LIMIT_MESSAGES = array(
        'Rate limit exceeded. Please try again later.',
        'Privileged tool rate limit exceeded. Please try again in a minute.',
    );

    
    const OVER_CAP = "wp_cli_run runs at most %d items in one 'wp %s' command, and this one has %d. Run it in smaller groups.";

    
    private $inv;

    
    private $ctx;

    
    private $noun;

    
    private $verb;

    
    private $total = 0;

    
    private $successes = 0;

    
    private $failures = 0;

    
    private $skips = 0;

    
    private $last = null;

    
    private $stopped = null;

    



    public function __construct( Invocation $inv, Context $ctx, string $noun, string $verb ) {
        $this->inv  = $inv;
        $this->ctx  = $ctx;
        $this->noun = $noun;
        $this->verb = $verb;
    }

    


    public static function cap( Invocation $inv ): int {
        return isset( $inv->route['bulk'] ) ? (int) $inv->route['bulk'] : Router::DEFAULT_BULK;
    }

    




    public static function is_rate_limited( array $delegated ): bool {
        return empty( $delegated['ok'] ) && isset( $delegated['error'] ) && in_array( (string) $delegated['error'], self::RATE_LIMIT_MESSAGES, true );
    }

    









    public function run( array $items, callable $each, ?callable $label = null ): array {
        $items       = array_values( $items );
        $this->total = count( $items );
        $cap         = self::cap( $this->inv );
        if ( $this->total > $cap ) {
            $this->ctx->out->error( sprintf( self::OVER_CAP, $cap, $this->inv->command, $this->total ) );
        }

        foreach ( $items as $index => $item ) {
            $this->stopped = null;
            try {
                $outcome = call_user_func( $each, $item, $this );
            } catch ( Cli_Exit $e ) {
                if ( null !== $this->ctx->pending_approval() ) {
                    $this->stop( $items, $index, $label );
                }
                throw $e;
            }
            if ( null !== $this->stopped ) {
                $this->ctx->out->error_continue( sprintf( '%s refused the next call: %s', $this->stopped['tool'], $this->stopped['error'] ) );
                $this->stop( $items, $index, $label );
            }
            $this->count( (string) $outcome );
        }

        return $this->result();
    }

    







    public function delegate( string $tool, array $arguments ): array {
        $result = $this->ctx->delegate( $tool, $arguments );
        if ( self::is_rate_limited( $result ) ) {
            $this->stopped = array(
                'tool'  => $tool,
                'error' => (string) $result['error'],
            );
        }
        return $result;
    }

    





    public function report( bool $with_skips = false ): void {
        Values::report_batch( $this->ctx->out, $this->noun, $this->verb, $this->total, $this->successes, $this->failures, $with_skips ? $this->skips : null );
    }

    
    public function result(): array {
        return array(
            'total'     => $this->total,
            'successes' => $this->successes,
            'failures'  => $this->failures,
            'skips'     => $this->skips,
            'last'      => $this->last,
        );
    }

    
    private function count( string $outcome ): void {
        $this->last = $outcome;
        if ( self::SUCCESS === $outcome ) {
            ++$this->successes;
        } elseif ( self::SKIP === $outcome ) {
            ++$this->skips;
        } else {
            ++$this->failures;
        }
    }

    








    private function stop( array $items, int $index, ?callable $label ): void {
        foreach ( array_slice( $items, $index ) as $item ) {
            $this->ctx->not_run( null !== $label ? (string) call_user_func( $label, $item ) : $this->noun . ' ' . self::text( $item ) );
        }
        $this->last = null;
        
        
        Values::report_batch( $this->ctx->out, $this->noun, $this->verb, $this->total, $this->successes, max( 1, $this->total - $this->successes - $this->skips ) );
        throw new Cli_Exit( 1 );
    }

    





    private static function text( $item ): string {
        if ( is_scalar( $item ) ) {
            return (string) $item;
        }
        return (string) json_encode( $item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR );
    }
}
