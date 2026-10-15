<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Handlers\Option_Handler;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






































final class Option_Adapter extends Command_Handler {

    const TOOL = 'wp_update_site_settings';

    const AUTOLOAD = "wp_cli_run cannot pass --autoload for '%s': wp_update_site_settings, which writes this setting, has no autoload argument. Run the command without --autoload.";
    const NOT_TEXT = "wp_cli_run cannot store a JSON array or object in '%s': wp_update_site_settings stores this setting as text or a number.";
    const NOT_MINE = "wp_cli_run cannot hand '%s' to wp_update_site_settings: it is not one of that tool's settings.";

    




    public function preflight_update( Invocation $inv, Context $ctx ): void {
        if ( null !== Read_Value::flag( $inv->assoc, 'autoload' ) ) {
            $args = array_values( $inv->args );
            $ctx->out->error( sprintf( self::AUTOLOAD, isset( $args[0] ) ? (string) $args[0] : '' ) );
        }
    }

    
    public function update( Invocation $inv, Context $ctx ): void {
        $args     = array_values( $inv->args );
        $key      = isset( $args[0] ) ? (string) $args[0] : '';
        $argument = Option_Handler::site_setting_argument( $key );
        if ( null === $argument ) {
            $ctx->out->error( sprintf( self::NOT_MINE, $key ) );
            return;
        }
        if ( null !== Read_Value::flag( $inv->assoc, 'autoload' ) ) {
            $ctx->out->error( sprintf( self::AUTOLOAD, $key ) );
        }

        $value = Read_Value::from_arg_or_stdin( $ctx->out, $args, 1 );
        $value = Read_Value::read( $ctx->out, $value, $inv->assoc );
        if ( is_array( $value ) || is_object( $value ) ) {
            $ctx->out->error( sprintf( self::NOT_TEXT, $key ) );
        }

        
        $value  = 'posts_per_page' === $argument ? (int) $value : ( is_bool( $value ) ? ( $value ? '1' : '' ) : (string) $value );
        self::report( $ctx, $key, $ctx->delegate( self::TOOL, array( $argument => $value ) ) );
    }

    




    private static function report( Context $ctx, string $key, array $result ): void {
        if ( ! $result['ok'] ) {
            $ctx->out->error( "Could not update option '{$key}': " . (string) $result['error'] );
            return;
        }
        if ( ! empty( $result['payload']['updated'] ) ) {
            $ctx->mark_changed();
            $ctx->out->success( "Updated '{$key}' option." );
        } else {
            $ctx->out->success( "Value passed for '{$key}' option is unchanged." );
        }
    }
}
