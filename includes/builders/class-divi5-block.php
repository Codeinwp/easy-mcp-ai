<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi5_Block {

    const TEXT = '#text';

    public $name = '';

    public $attrs;

    public $opener = '';

    public $closer = '';

    public $void = true;

    public $lead = '';

    public $tail = '';

    public $text = '';

    public $children = array();

    public $dirty = false;

    public function __construct() {
        $this->attrs = new \stdClass();
    }

    public function __clone() {
        $this->attrs    = self::deep_copy( $this->attrs );
        $this->children = array_map(
            static function ( Divi5_Block $child ): Divi5_Block {
                return clone $child;
            },
            $this->children
        );
    }

    public static function make( string $name, \stdClass $attrs, bool $void ): self {
        $block        = new self();
        $block->name  = $name;
        $block->attrs = $attrs;
        $block->void  = $void;
        $block->dirty = true;
        return $block;
    }

    public function is_text(): bool {
        return self::TEXT === $this->name;
    }

    public function get( array $path ) {
        $value = $this->attrs;
        foreach ( $path as $key ) {
            if ( $value instanceof \stdClass && property_exists( $value, $key ) ) {
                $value = $value->{$key};
            } elseif ( is_array( $value ) && array_key_exists( $key, $value ) ) {
                $value = $value[ $key ];
            } else {
                return null;
            }
        }
        return $value;
    }

    public function set( array $path, $value ): void {
        $this->attrs = self::set_in( $this->attrs, $path, $value );
        $this->dirty = true;
    }

    private static function set_in( $node, array $path, $value ) {
        $key = (string) array_shift( $path );
        if ( is_array( $node ) && array() === $node ) {
            $node = new \stdClass();
        }
        if ( ! $node instanceof \stdClass && ! is_array( $node ) ) {
            $node = new \stdClass();
        }
        if ( array() === $path ) {
            if ( null === $value ) {
                if ( $node instanceof \stdClass ) {
                    unset( $node->{$key} );
                } else {
                    unset( $node[ $key ] );
                }
                return $node;
            }
            if ( $node instanceof \stdClass ) {
                $node->{$key} = $value;
            } else {
                $node[ $key ] = $value;
            }
            return $node;
        }
        $inner = $node instanceof \stdClass ? ( $node->{$key} ?? null ) : ( $node[ $key ] ?? null );
        $inner = self::set_in( $inner, $path, $value );
        if ( $node instanceof \stdClass ) {
            $node->{$key} = $inner;
        } else {
            $node[ $key ] = $inner;
        }
        return $node;
    }

    private static function deep_copy( $value ) {
        if ( $value instanceof \stdClass ) {
            $copy = new \stdClass();
            foreach ( get_object_vars( $value ) as $key => $inner ) {
                $copy->{$key} = self::deep_copy( $inner );
            }
            return $copy;
        }
        return is_array( $value ) ? array_map( array( __CLASS__, 'deep_copy' ), $value ) : $value;
    }
}
