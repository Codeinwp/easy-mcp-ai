<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













final class Csv {

    
    private static $stream = null;

    





    public static function write( Output $out, array $rows, array $headers = array() ): void {
        self::write_each( $out, $rows, $headers );
    }

    









    public static function write_each( Output $out, iterable $rows, array $headers = array(), ?callable $map = null ): void {
        if ( ! empty( $headers ) ) {
            $headers = array_map( array( __CLASS__, 'escape_value' ), $headers );
            $out->out( self::line( $headers ) );
        }

        foreach ( $rows as $row ) {
            if ( Formatter::past_cap( $out ) ) {
                break;
            }
            if ( null !== $map ) {
                $row = call_user_func( $map, $row );
            }
            if ( ! empty( $headers ) ) {
                $row = Values::pick_fields( $row, $headers );
            }

            $row = array_map( array( __CLASS__, 'escape_value' ), $row );
            $out->out( self::line( array_values( $row ) ) );
        }
    }

    





    public static function escape_value( $value ): string {
        if ( null === $value ) {
            return '';
        }

        
        $value = (string) $value;

        if (
            in_array(
                substr( $value, 0, 1 ),
                array( '=', '+', '-', '@', "\t", "\r" ),
                true
            )
        ) {
            return "'{$value}";
        }

        return $value;
    }

    





    public static function line( array $fields ): string {
        if ( null === self::$stream ) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream, never a file: fputcsv() needs a handle.
            $stream = fopen( 'php://temp', 'w+' );
            if ( false === $stream ) {
                throw new \RuntimeException( 'wp_cli_run could not open a memory stream for CSV output.' );
            }
            self::$stream = $stream;
        }
        $stream = self::$stream;
        rewind( $stream );
        ftruncate( $stream, 0 );
        fputcsv( $stream, $fields, ',', '"', '\\' );
        rewind( $stream );
        return (string) stream_get_contents( $stream );
    }
}
