<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



























final class Envelope {

    const OUTPUT_MAX_BYTES = 65536;

    
    const LIST_MAX = 100;

    
    const ITEM_MAX_BYTES = 2048;

    






    public static function build( Context $ctx, int $exit_code, bool $delegated ): array {
        $stdout    = $ctx->out->stdout();
        $stderr    = $ctx->out->stderr();
        $output    = self::cut( $stdout, self::OUTPUT_MAX_BYTES );
        $error     = self::cut( $stderr, self::OUTPUT_MAX_BYTES );
        $warnings  = self::cut_list( $ctx->out->warnings() );
        $notes     = self::cut_list( $ctx->notes() );
        $truncated = strlen( $output ) < strlen( $stdout ) || strlen( $error ) < strlen( $stderr ) || $warnings[1] || $notes[1];

        
        
        
        if ( $delegated && ( array() !== $ctx->calls() || $ctx->changed() ) ) {
            $recorded_as = 'inner tools';
        } elseif ( $ctx->changed() ) {
            $recorded_as = 'wp_cli_run';
        } else {
            $recorded_as = 'none';
        }

        return array(
            'output'           => $output,
            'stderr'           => $error,
            'exit_code'        => $exit_code,
            'changed'          => $ctx->changed(),
            'warnings'         => $warnings[0],
            'truncated'        => $truncated,
            'output_bytes'     => strlen( $stdout ),
            'stderr_bytes'     => strlen( $stderr ),
            'calls'            => $ctx->calls(),
            'pending_approval' => $ctx->pending_approval(),
            'not_run'          => $ctx->not_run_items(),
            'history'          => array(
                'recorded_as'  => $recorded_as,
                'not_captured' => $ctx->not_captured_phrases(),
            ),
            'redacted'         => $ctx->redacted_fields(),
            'notes'            => $notes[0],
        );
    }

    



    public static function cut( string $text, int $max ): string {
        if ( strlen( $text ) <= $max ) {
            return $text;
        }
        if ( function_exists( 'mb_strcut' ) ) {
            $cut = mb_strcut( $text, 0, $max, 'UTF-8' );
            
            
            if ( is_string( $cut ) && strlen( $cut ) <= $max ) {
                return $cut;
            }
        }
        return substr( $text, 0, $max );
    }

    





    private static function cut_list( array $items ): array {
        $cut  = count( $items ) > self::LIST_MAX;
        $kept = array();
        foreach ( array_slice( array_values( $items ), 0, self::LIST_MAX ) as $item ) {
            $short = self::cut( (string) $item, self::ITEM_MAX_BYTES );
            $cut   = $cut || strlen( $short ) < strlen( (string) $item );
            $kept[] = $short;
        }
        return array( $kept, $cut );
    }
}
