<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Refusal_Text {

    public static function fit( string $format, array $parts, int $index = 0, int $limit = Detailed_Tool_Error::MAX_MESSAGE ): string {
        $message = vsprintf( $format, $parts );
        $over    = self::length( $message ) - $limit;
        $value   = (string) $parts[ $index ];
        $keep    = mb_strlen( $value );
        while ( $over > 0 && $keep > 0 ) {
            $per_char        = max( 1, strlen( (string) mb_substr( $value, 0, $keep ) ) / $keep );
            $keep            = max( 0, min( $keep - (int) ceil( $over / $per_char ), $limit ) );
            $parts[ $index ] = self::head( $value, $keep );
            $message         = vsprintf( $format, $parts );
            $over            = self::length( $message ) - $limit;
        }
        return $message;
    }

    public static function fits( string $message ): bool {
        return self::length( $message ) <= Detailed_Tool_Error::MAX_MESSAGE;
    }

    public static function length( string $message ): int {
        try {
            return strlen( (string) Server::sanitize_error_message( $message, PHP_INT_MAX ) );
        } catch ( \Throwable $e ) {
            return strlen( $message );
        }
    }

    private static function head( string $value, int $chars ): string {
        $head = (string) mb_substr( $value, 0, $chars );
        $half = strlen( $head ) / 2;
        $at   = max( (int) strrpos( $head, ', ' ), (int) strrpos( $head, '; ' ), (int) strrpos( $head, '. ' ) );
        if ( $at < $half ) {
            $at = (int) strrpos( $head, ' ' );
        }
        if ( $at > 0 && $at >= $half ) {
            $head = substr( $head, 0, $at );
        }
        return rtrim( $head, ' ,;:.' ) . '…';
    }
}
