<?php
namespace Easy_MCP_AI\Cli_Router\Adapters;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



































class Core_Adapter extends Command_Handler {

    const TOOL = 'wp_update_core';

    
    const UP_TO_DATE   = 'WordPress is up to date.';
    const LATEST_MINOR = 'WordPress is at the latest minor release.';

    const NOTE_REASON = 'wp_update_core reported: %s';

    
    const NOT_CAPTURED_FILES = 'Core files: change history does not record files; a core update cannot be undone from it.';

    
    public function update( Invocation $inv, Context $ctx ): void {
        $result = $ctx->delegate( self::TOOL, self::update_arguments( $inv ) );
        if ( ! $result['ok'] ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $payload = (array) $result['payload'];

        if ( empty( $payload['changed'] ) ) {
            self::report_unchanged( $ctx, $payload );
            return;
        }
        self::report_updated( $ctx, $payload );
    }

    





    private static function update_arguments( Invocation $inv ): array {
        $arguments = array();
        if ( Read_Value::flag( $inv->assoc, 'minor' ) ) {
            $arguments['minor'] = true;
        }
        $version = Read_Value::flag( $inv->assoc, 'version' );
        if ( ! empty( $version ) ) {
            $arguments['version'] = (string) $version;
        }
        $locale = Read_Value::flag( $inv->assoc, 'locale' );
        if ( null !== $locale && false !== $locale ) {
            $arguments['locale'] = (string) $locale;
        }
        return $arguments;
    }

    





    private static function report_unchanged( Context $ctx, array $payload ): void {
        $reason = isset( $payload['reason'] ) ? (string) $payload['reason'] : '';
        if ( '' !== $reason && self::UP_TO_DATE !== $reason && self::LATEST_MINOR !== $reason ) {
            $ctx->note( sprintf( self::NOTE_REASON, $reason ) );
        }
        $ctx->out->success( self::LATEST_MINOR === $reason ? self::LATEST_MINOR : self::UP_TO_DATE );
    }

    





    private static function report_updated( Context $ctx, array $payload ): void {
        $ctx->mark_changed();
        $ctx->not_captured( self::NOT_CAPTURED_FILES );
        $ctx->out->log( sprintf( 'Updating to version %s (%s)...', (string) ( $payload['new_version'] ?? '' ), (string) ( $payload['locale'] ?? '' ) ) );
        foreach ( (array) ( $payload['messages'] ?? array() ) as $message ) {
            
            $line = html_entity_decode( str_replace( '&#8230;', '...', (string) $message ), ENT_QUOTES, 'UTF-8' );
            if ( '' !== $line ) {
                $ctx->out->log( $line );
            }
        }
        $ctx->out->success( 'WordPress updated successfully.' );
    }
}
