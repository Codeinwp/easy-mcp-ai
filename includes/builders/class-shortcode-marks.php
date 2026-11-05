<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shortcode_Marks {

    private $at = array();

    private $gone = array();

    public static function of( array $items ): self {
        $marks = new self();
        $marks->name( '', $items, 0 );
        return $marks;
    }

    public function name_new( string $ref, array $items, int $base ): void {
        $item = $items[0] ?? null;
        if ( null === $item ) {
            return;
        }
        $this->at[ $ref ] = $base + self::begin( $item );
        $this->name( $ref, $item['children'], $base );
    }

    public function has( string $key ): bool {
        return isset( $this->at[ $key ] ) || isset( $this->gone[ $key ] );
    }

    public function replaced( int $at, int $length, int $with ): void {
        $this->shift( $at, $length, $with, 0, 'replaced' );
    }

    public function edited( string $key, array $item ): void {
        unset( $this->gone[ $key ] );
        $this->at[ $key ] = self::begin( $item );
        $this->name_free( $key, $item['children'] );
    }

    public function removed( int $at, int $length, int $op ): void {
        $this->shift( $at, $length, 0, $op, 'removed' );
    }

    public function joined( array $keys, int $op ): void {
        foreach ( $keys as $key ) {
            $this->forget( $key, $op, 'joined' );
        }
    }

    public function inserted( int $at, int $length ): void {
        foreach ( $this->at as $key => $offset ) {
            if ( $offset >= $at ) {
                $this->at[ $key ] = $offset + $length;
            }
        }
    }

    public function cut( int $at, int $length ): array {
        $carried = array();
        foreach ( $this->at as $key => $offset ) {
            if ( $offset >= $at && $offset < $at + $length ) {
                $carried[ $key ] = $offset - $at;
                unset( $this->at[ $key ] );
            } elseif ( $offset >= $at + $length ) {
                $this->at[ $key ] = $offset - $length;
            }
        }
        return $carried;
    }

    public function pasted( int $at, int $length, array $carried ): void {
        $this->inserted( $at, $length );
        foreach ( $carried as $key => $offset ) {
            $this->at[ $key ] = $at + $offset;
        }
    }

    public function find( string $key, array $items ) {
        if ( isset( $this->gone[ $key ] ) ) {
            list( $op, $why ) = $this->gone[ $key ];
            if ( 'removed' === $why ) {
                return sprintf( 'element "%s" was removed by operation %d.', $key, $op );
            }
            if ( 'joined' === $why ) {
                return sprintf( 'element "%s" is text that became one with the text next to it when operation %d took away the element between them; read the layout again with wp_get_page_layout.', $key, $op );
            }
            return sprintf( 'element "%s" was replaced by an earlier operation in this call.', $key );
        }
        if ( ! isset( $this->at[ $key ] ) ) {
            return 0 === strpos( $key, '$' )
                ? sprintf( 'no earlier operation in this call named an element "%s".', $key )
                : sprintf( 'no element "%s" in this layout. Read it again with wp_get_page_layout.', $key );
        }
        $found = self::locate( $items, $this->at[ $key ], '' );
        if ( null === $found ) {
            return sprintf( 'element "%s" is no longer a separate item after the earlier operations in this call.', $key );
        }
        return $found;
    }

    public function key_at( int $offset ): ?string {
        $key = array_search( $offset, $this->at, true );
        return false === $key ? null : (string) $key;
    }

    public static function begin( array $item ): int {
        if ( Shortcode_Tree::TEXT !== $item['tag'] ) {
            return $item['start'];
        }
        $text = (string) $item['content'];
        return $item['start'] + strlen( $text ) - strlen( ltrim( $text ) );
    }

    private function name( string $prefix, array $items, int $base ): void {
        foreach ( array_values( $items ) as $index => $item ) {
            $key              = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            $this->at[ $key ] = $base + self::begin( $item );
            $this->name( $key, $item['children'], $base );
        }
    }

    private function name_free( string $prefix, array $items ): void {
        foreach ( array_values( $items ) as $index => $item ) {
            $key = $prefix . '.' . $index;
            $why = isset( $this->gone[ $key ] ) ? $this->gone[ $key ][1] : 'replaced';
            if ( isset( $this->at[ $key ] ) || 'replaced' !== $why ) {
                continue;
            }
            unset( $this->gone[ $key ] );
            $this->at[ $key ] = self::begin( $item );
            $this->name_free( $key, $item['children'] );
        }
    }

    private function shift( int $at, int $length, int $with, int $op, string $why ): void {
        foreach ( $this->at as $key => $offset ) {
            if ( $offset >= $at && $offset < $at + $length ) {
                $this->forget( (string) $key, $op, $why );
            } elseif ( $offset >= $at + $length ) {
                $this->at[ $key ] = $offset + $with - $length;
            }
        }
    }

    private static function locate( array $items, int $offset, string $prefix ): ?array {
        foreach ( array_values( $items ) as $index => $item ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            if ( self::begin( $item ) === $offset ) {
                return array(
                    'path'    => array( $item ),
                    'address' => $address,
                );
            }
            if ( $offset > $item['start'] && $offset < $item['end'] ) {
                $inner = self::locate( $item['children'], $offset, $address );
                if ( null === $inner ) {
                    return null;
                }
                array_unshift( $inner['path'], $item );
                return $inner;
            }
        }
        return null;
    }

    private function forget( string $key, int $op, string $why ): void {
        unset( $this->at[ $key ] );
        $this->gone[ $key ] = array( $op, $why );
    }
}
