<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Ids;
use Easy_MCP_AI\Cli_Router\Invocation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

























class Embed_Adapter extends Command_Handler {

    const TOOL = 'wp_delete_post_meta';

    





    public function preflight_clear( Invocation $inv, Context $ctx ): void {
        Ids::require_digits( $ctx, (string) $inv->args[0], 'a post ID' );
    }

    
    public function cache_clear( Invocation $inv, Context $ctx ): void {
        $post_id = (string) $inv->args[0];

        Fetchers::require_tool( $ctx, $inv, self::TOOL );

        $exists = $this->post_exists( $post_id );
        if ( $exists && ! $this->user_can( 'edit_post', (int) $post_id ) ) {
            $ctx->out->error( sprintf( "Sorry, you are not allowed to do this: 'wp embed cache clear' deletes post %d's _oembed_ meta through %s, which needs the edit_post capability for that post.", (int) $post_id, self::TOOL ) );
        }

        $keys = $exists ? $this->oembed_keys( $post_id ) : array();
        if ( empty( $keys ) ) {
            $ctx->out->error( 'No cache to clear!' );
        }

        $batch = new Batch( $inv, $ctx, 'oEmbed cache key', 'clear' );
        $batch->run(
            $keys,
            static function ( string $key, Batch $batch ) use ( $ctx, $post_id ): string {
                $result = $batch->delegate(
                    self::TOOL,
                    array(
                        'post_id'   => (int) $post_id,
                        'key'       => $key,
                        'exact_key' => true,
                    )
                );
                if ( Batch::is_rate_limited( $result ) ) {
                    return Batch::FAILURE;
                }
                if ( ! $result['ok'] ) {
                    $ctx->out->warning( (string) $result['error'] );
                    return Batch::FAILURE;
                }
                if ( ! empty( $result['payload']['deleted'] ) ) {
                    $ctx->mark_changed();
                }
                return Batch::SUCCESS;
            },
            static function ( string $key ) use ( $post_id ): string {
                return "post {$post_id} meta {$key}";
            }
        );
        $counts = $batch->result();
        if ( $counts['failures'] ) {
            $batch->report();
        }
        $ctx->out->success( 'Cleared oEmbed cache.' );
    }

    
    
    

    







    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    
    protected function post_exists( string $post_id ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array( $post_id ),
            static function () use ( $post_id ): bool {
                return null !== \get_post( $post_id );
            }
        );
    }

    
    protected function user_can( string $cap, int ...$args ): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array_merge( array( $cap ), $args ),
            static function () use ( $cap, $args ): bool {
                return \current_user_can( $cap, ...$args );
            }
        );
    }

    
    protected function oembed_keys( string $post_id ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $post_id ),
            static function () use ( $post_id ): array {
                $keys = array();
                foreach ( (array) \get_post_custom_keys( $post_id ) as $key ) {
                    if ( '_oembed_' === substr( (string) $key, 0, 8 ) ) {
                        $keys[] = (string) $key;
                    }
                }
                return $keys;
            }
        );
    }
}
