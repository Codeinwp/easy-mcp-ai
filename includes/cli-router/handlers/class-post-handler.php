<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
















final class Post_Handler extends Command_Handler {

    const MSG_READ_CAP = "Sorry, you are not allowed to do this: wp_cli_run needs the read_post capability for post %d.";

    
    public function exists( Invocation $inv, Context $ctx ): void {
        $arg  = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $post = Fetchers::of( 'post' )->get( $arg );
        if ( $post ) {
            $this->require_read( $ctx, $post );
            $ctx->out->success( "Post with ID {$arg} exists." );
        } else {
            $ctx->out->halt( 1 );
        }
    }

    
    public function url_to_id( Invocation $inv, Context $ctx ): void {
        $url     = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        $post_id = \url_to_postid( $url );

        
        
        $post = $post_id ? \get_post( $post_id ) : null;

        if ( null === $post ) {
            $ctx->out->error( "Could not get post with url {$url}." );
        }
        $this->require_read( $ctx, $post );

        Values::print_value( $ctx->out, $post_id, $inv->assoc );
    }

    





    private function require_read( Context $ctx, object $post ): void {
        if ( ! \current_user_can( 'read_post', (int) $post->ID ) ) {
            $ctx->out->error( sprintf( self::MSG_READ_CAP, (int) $post->ID ) );
        }
    }
}
