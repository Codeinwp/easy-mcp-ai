<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Theme_Mod_Value {

    const LIST_KIND   = 'a list';
    const OBJECT_KIND = 'an object';
    const SINGLE_KIND = 'a single value';
    const EMPTY_KIND  = 'an object or a list';
    const NULL_KIND   = 'null';

    const DEFAULT_SHOWN_LIMIT = 1500;

    const STRUCTURED_EXPECTED = '"%1$s" stores %2$s, not a single value, so "value" must be %2$s of the same shape as its default (see the details). Nothing was changed.';
    const SINGLE_EXPECTED     = '"%1$s" stores a single value (a string, number or boolean), so "value" cannot be an object or a list. Nothing was changed.';
    const SHAPE_MISMATCH      = '"value" does not fit "%1$s": at %2$s its default holds %3$s, but the value sent holds %4$s. Nothing was changed.';
    const NULL_INSIDE         = '"value" for "%1$s" holds null at %2$s. Leave that key out instead (wp_delete_theme_mod resets the whole setting). Nothing was changed.';
    const NOT_JSON            = '"value" for "%1$s" cannot be saved: the Customizer stores it as JSON, which cannot hold it (a number too large, or nesting too deep). Nothing was changed.';

    public static function conform( string $id, $default, $value ) {
        $value = self::shaped( $id, $default, $value );
        $json = wp_json_encode( array( array( 'value' => $value ) ) );
        if ( ! is_string( $json ) || null === json_decode( $json, true ) ) {
            throw new Detailed_Tool_Error( self::fit( self::NOT_JSON, array( $id ) ) );
        }
        return $value;
    }

    private static function shaped( string $id, $default, $value ) {
        if ( ! is_array( $default ) ) {
            if ( is_array( $value ) ) {
                throw new Detailed_Tool_Error( self::fit( self::SINGLE_EXPECTED, array( $id ) ) );
            }
            return $value;
        }

        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( is_array( $decoded ) ) {
                $value = $decoded;
            }
        }
        if ( ! is_array( $value ) ) {
            throw new Detailed_Tool_Error(
                self::fit( self::STRUCTURED_EXPECTED, array( $id, self::kind_of( $default ) ) ),
                self::details( $id, $default )
            );
        }

        $problem = self::mismatch( $default, $value, 'value' );
        if ( null !== $problem ) {
            throw new Detailed_Tool_Error(
                null === $problem['expected']
                    ? self::fit( self::NULL_INSIDE, array( $id, $problem['path'] ), 2 )
                    : self::fit( self::SHAPE_MISMATCH, array( $id, $problem['path'], $problem['expected'], $problem['sent'] ), 2 ),
                self::details( $id, $default )
            );
        }
        return $value;
    }

    private static function mismatch( $model, $value, string $path ): ?array {
        if ( null === $value ) {
            return self::null_inside( $value, $path );
        }
        if ( ! is_array( $model ) ) {
            if ( null === $model || is_object( $model ) ) {
                return self::null_inside( $value, $path );
            }
            return is_array( $value ) ? self::problem( $path, $model, $value ) : null;
        }
        if ( ! is_array( $value ) ) {
            return self::problem( $path, $model, $value );
        }
        if ( array() === $model || array() === $value ) {
            return self::null_inside( $value, $path );
        }

        if ( self::is_list( $model ) ) {
            if ( ! self::is_list( $value ) ) {
                return self::problem( $path, $model, $value );
            }
            $item = self::item_model( $model );
            foreach ( $value as $index => $entry ) {
                $at      = $path . '[' . $index . ']';
                $problem = null === $item ? self::null_inside( $entry, $at ) : self::mismatch( $item, $entry, $at );
                if ( null !== $problem ) {
                    return $problem;
                }
            }
            return null;
        }

        if ( self::is_list( $value ) ) {
            return self::problem( $path, $model, $value );
        }
        foreach ( $value as $key => $entry ) {
            $at      = $path . '.' . $key;
            $problem = array_key_exists( $key, $model ) ? self::mismatch( $model[ $key ], $entry, $at ) : self::null_inside( $entry, $at );
            if ( null !== $problem ) {
                return $problem;
            }
        }
        return null;
    }

    private static function null_inside( $value, string $path ): ?array {
        if ( null === $value ) {
            return array(
                'path'     => $path,
                'expected' => null,
                'sent'     => self::NULL_KIND,
            );
        }
        if ( ! is_array( $value ) ) {
            return null;
        }
        $list = self::is_list( $value );
        foreach ( $value as $key => $entry ) {
            $problem = self::null_inside( $entry, $list ? $path . '[' . $key . ']' : $path . '.' . $key );
            if ( null !== $problem ) {
                return $problem;
            }
        }
        return null;
    }

    private static function item_model( array $list ) {
        $kind = self::kind_of( $list[0] );
        foreach ( $list as $entry ) {
            if ( self::kind_of( $entry ) !== $kind ) {
                return null;
            }
        }
        return $list[0];
    }

    private static function problem( string $path, $model, $value ): array {
        return array(
            'path'     => $path,
            'expected' => self::kind_of( $model ),
            'sent'     => self::kind_of( $value ),
        );
    }

    private static function kind_of( $value ): string {
        if ( null === $value ) {
            return self::NULL_KIND;
        }
        if ( ! is_array( $value ) ) {
            return self::SINGLE_KIND;
        }
        if ( array() === $value ) {
            return self::EMPTY_KIND;
        }
        return self::is_list( $value ) ? self::LIST_KIND : self::OBJECT_KIND;
    }

    private static function is_list( array $value ): bool {
        return array_values( $value ) === $value;
    }

    private static function details( string $id, $default ): string {
        $json = wp_json_encode( $default, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        $tail = 'Send "value" as an object or list of that shape, or as JSON text of one. It replaces what is stored, so include every key you want to keep (wp_get_theme_mods shows the current value).';
        if ( ! is_string( $json ) || strlen( $json ) > self::DEFAULT_SHOWN_LIMIT ) {
            return sprintf( 'The default of "%s" is too long to show here; wp_get_theme_mods with include_editable: true lists it. ', $id ) . $tail;
        }
        return sprintf( "The default of \"%s\":\n%s\n", $id, $json ) . $tail;
    }

    private static function fit( string $format, array $parts, int $shortenable = 1 ): string {
        for ( $index = 0; $index < $shortenable; $index++ ) {
            if ( mb_strlen( $parts[ $index ] ) > Detailed_Tool_Error::MAX_MESSAGE ) {
                $parts[ $index ] = mb_substr( $parts[ $index ], 0, Detailed_Tool_Error::MAX_MESSAGE ) . '…';
            }
            while ( self::measure( vsprintf( $format, $parts ) ) > Detailed_Tool_Error::MAX_MESSAGE && mb_strlen( $parts[ $index ] ) > 9 ) {
                $parts[ $index ] = mb_substr( $parts[ $index ], 0, max( 8, mb_strlen( $parts[ $index ] ) - 9 ) ) . '…';
            }
        }
        return vsprintf( $format, $parts );
    }

    private static function measure( string $text ): int {
        return strlen( class_exists( '\Easy_MCP_AI\Config' ) ? \Easy_MCP_AI\Config::brand( $text ) : $text );
    }
}
