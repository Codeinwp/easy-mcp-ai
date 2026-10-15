<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
















final class Tabular {

    





    public static function render( array $headers, array $rows ): string {
        $text = self::row( $headers ) . "\n";
        foreach ( $rows as $row ) {
            $text .= self::body_lines( $row );
        }
        return $text;
    }

    





    public static function body_lines( array $row ): string {
        $text = '';
        foreach ( explode( PHP_EOL, self::row( $row ) ) as $line ) {
            $text .= $line . "\n";
        }
        return $text;
    }

    







    public static function row( array $row ): string {
        $rows        = array();
        $output      = '';
        $col         = null;
        $split_lines = array();

        foreach ( $row as $col => $value ) {
            $value       = isset( $value ) ? (string) $value : '';
            $value       = str_replace( "\t", '    ', $value );
            $split_lines = preg_split( '/\r\n|\n/', $value );
            
            $row[ $col ] = array_shift( $split_lines );
        }

        $rows[] = $row;

        foreach ( is_array( $split_lines ) ? $split_lines : array() as $i => $line ) {
            if ( ! isset( $rows[ $i + 1 ] ) ) {
                $rows[ $i + 1 ] = array_fill_keys( array_keys( $row ), '' );
            }
            $rows[ $i + 1 ][ $col ] = $line;
        }

        foreach ( $rows as $r ) {
            $output .= implode( "\t", array_values( $r ) ) . PHP_EOL;
        }
        return rtrim( $output, PHP_EOL );
    }
}
