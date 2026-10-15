<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
























final class Yaml_Dumper {

    
    const REMPTY = "\0\0\0\0\0";

    
    const INDENT = 2;

    



    const LITERAL_BLOCK_NEEDLES = array( "\n", ': ', '- ', '*', '#', '<', '>', '%', '  ', '[', ']', '{', '}', '&', "'" );

    




    public static function dump( $data ): string {
        
        $string = "---\n";

        
        if ( $data ) {
            $array       = (array) $data;
            $is_sequence = self::is_sequence( $array );
            foreach ( $array as $key => $value ) {
                $string .= self::yamlize( $key, $value, 0, $is_sequence );
            }
        }
        return $string;
    }

    







    public static function sequence_entry( $value ): string {
        return self::yamlize( 0, $value, 0, true );
    }

    





    private static function yamlize( $key, $value, int $indent, bool $in_sequence ): string {
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }
        if ( is_array( $value ) ) {
            if ( empty( $value ) ) {
                return self::dump_node( $key, array(), $indent, $in_sequence );
            }
            
            $string = self::dump_node( $key, self::REMPTY, $indent, $in_sequence );
            
            $indent += self::INDENT;
            $string .= self::yamlize_array( $value, $indent );
            return $string;
        }
        
        return self::dump_node( $key, $value, $indent, $in_sequence );
    }

    




    private static function yamlize_array( array $array, int $indent ): string {
        $string      = '';
        $is_sequence = self::is_sequence( $array );
        foreach ( $array as $key => $value ) {
            $string .= self::yamlize( $key, $value, $indent, $is_sequence );
        }
        return $string;
    }

    




    private static function is_sequence( array $array ): bool {
        return array() !== $array && array_keys( $array ) === range( 0, count( $array ) - 1 );
    }

    





    private static function dump_node( $key, $value, int $indent, bool $in_sequence ): string {
        $value  = self::node_value( $value, $indent );
        $spaces = str_repeat( ' ', $indent );

        if ( $in_sequence ) {
            
            return $spaces . '- ' . $value . "\n";
        }

        
        if ( false !== strpos( (string) $key, ':' ) || false !== strpos( (string) $key, '#' ) ) {
            $key = '"' . $key . '"';
        }
        return rtrim( $spaces . $key . ': ' . $value ) . "\n";
    }

    








    private static function node_value( $value, int $indent ) {
        
        $value = self::needs_literal_block( $value ) ? self::do_literal_block( $value, $indent ) : self::do_folding( $value );

        if ( array() === $value ) {
            $value = '[ ]';
        }
        if ( '' === $value ) {
            $value = '""';
        }
        if ( self::is_translation_word( $value ) ) {
            $value = self::do_literal_block( $value, $indent );
        }
        if ( trim( (string) $value ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual,WordPress.PHP.StrictComparisons.LooseComparison -- Spyc's comparison, see the docblock.
            $value = self::do_literal_block( $value, $indent );
        }
        return self::plain_value( $value );
    }

    





    private static function needs_literal_block( $value ): bool {
        if ( ! is_string( $value ) ) {
            return false;
        }
        foreach ( self::LITERAL_BLOCK_NEEDLES as $needle ) {
            if ( false !== strpos( $value, $needle ) ) {
                return true;
            }
        }
        return 0 === strpos( $value, '!' ) || ':' === substr( $value, -1, 1 );
    }

    






    private static function plain_value( $value ) {
        if ( is_bool( $value ) ) {
            $value = $value ? 'true' : 'false';
        }

        if ( null === $value ) {
            $value = 'null';
        }
        if ( "'" . self::REMPTY . "'" === $value ) {
            $value = null;
        }
        return $value;
    }

    






    private static function do_literal_block( $value, int $indent ): string {
        if ( "\n" === $value ) {
            return '\n';
        }
        $text = (string) $value;
        if ( false === strpos( $text, "\n" ) && false === strpos( $text, "'" ) ) {
            return sprintf( "'%s'", $value );
        }
        if ( false === strpos( $text, "\n" ) && false === strpos( $text, '"' ) ) {
            return sprintf( '"%s"', $value );
        }
        return self::literal_block( $text, $indent );
    }

    





    private static function literal_block( string $text, int $indent ): string {
        $exploded  = explode( "\n", $text );
        $new_value = '|';
        if ( isset( $exploded[0] ) && ( '|' === $exploded[0] || '|-' === $exploded[0] || '>' === $exploded[0] ) ) {
            $new_value = $exploded[0];
            unset( $exploded[0] );
        }
        $spaces = str_repeat( ' ', $indent + self::INDENT );
        foreach ( $exploded as $line ) {
            $new_value .= "\n" . $spaces . self::unquote_line( trim( $line ) );
        }
        return $new_value;
    }

    
    private static function unquote_line( string $line ): string {
        if ( 0 === strpos( $line, '"' ) && strrpos( $line, '"' ) === ( strlen( $line ) - 1 ) || 0 === strpos( $line, "'" ) && strrpos( $line, "'" ) === ( strlen( $line ) - 1 ) ) {
            $line = (string) substr( $line, 1, -1 );
        }
        return $line;
    }

    







    private static function do_folding( $value ) {
        if ( is_numeric( $value ) && is_string( $value ) ) {
            $value = '"' . $value . '"';
        }
        return $value;
    }

    





    private static function is_translation_word( $value ): bool {
        static $words = null;
        if ( null === $words ) {
            $words = array();
            foreach ( array( 'true', 'on', 'yes', 'y', 'false', 'off', 'no', 'n', 'null', '~' ) as $word ) {
                $words = array_merge( $words, array( ucfirst( $word ), strtoupper( $word ), strtolower( $word ) ) );
            }
        }
        return in_array( $value, $words, true );
    }
}
