<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shortcode_Structure {

    const OPS = array( 'insert', 'remove', 'move', 'duplicate' );

    const POSITIONS = array( 'before', 'after', 'parent' );

    private $adapter;

    private $rules;

    public function __construct( Shortcode_Adapter $adapter, Structure_Rules $rules ) {
        $this->adapter = $adapter;
        $this->rules   = $rules;
    }

    public function apply( string $content, array $op, Shortcode_Marks $marks, int $number ) {
        $name    = (string) $op['op'];
        $refusal = self::argument_refusal( $name, $op );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( 'insert' === $name ) {
            return $this->insert( $content, $op, $marks, $number );
        }
        $key   = trim( (string) $op['element'] );
        $items = $this->adapter->layout_items( $content );
        $found = $marks->find( $key, $items );
        if ( is_string( $found ) ) {
            return $found;
        }
        $item   = $found['path'][ count( $found['path'] ) - 1 ];
        $expect = isset( $op['expect'] ) && is_string( $op['expect'] ) ? $op['expect'] : '';
        if ( '' !== $expect && $expect !== $item['tag'] ) {
            return sprintf( 'element "%s" is a %s, not the expected %s.', $key, $item['tag'], $expect );
        }
        $refusal = $this->holder_refusal( $found['path'], $key );
        if ( null !== $refusal ) {
            return $refusal;
        }
        if ( Shortcode_Tree::TEXT === $item['tag'] && 'remove' !== $name ) {
            return sprintf( '"%s" is text between elements, which remove takes away but %s does not handle.', $key, $name );
        }
        $fixed = Shortcode_Tree::TEXT === $item['tag'] ? null : $this->rules->fixed_refusal( $item['tag'] );
        if ( null !== $fixed ) {
            return sprintf( 'element "%s" (%s): %s', $key, $item['tag'], $fixed );
        }
        if ( 'remove' === $name ) {
            return $this->remove( $content, $items, $found, $key, $marks, $number );
        }
        if ( self::too_deep( $item ) ) {
            return sprintf( 'element "%s" holds content nested more than %d levels deep, which %s does not handle; change it in the builder.', $key, Shortcode_Tree::MAX_DEPTH, $name );
        }
        if ( 'duplicate' === $name ) {
            return $this->duplicate( $content, $items, $found, $key, $op, $marks, $number );
        }
        return $this->move( $content, $items, $found, $key, $op, $marks, $number );
    }

    public static function skeleton( array $items ): array {
        $skeleton = array();
        foreach ( $items as $item ) {
            if ( Shortcode_Tree::TEXT !== $item['tag'] ) {
                $skeleton[] = array( $item['tag'], self::skeleton( $item['children'] ) );
            }
        }
        return $skeleton;
    }

    private static function argument_refusal( string $name, array $op ): ?string {
        $element = isset( $op['element'] ) && is_scalar( $op['element'] ) ? trim( (string) $op['element'] ) : '';
        if ( 'insert' === $name && isset( $op['element'] ) ) {
            return 'insert takes no `element`; say where the new element goes with `before`, `after` or `parent`.';
        }
        if ( 'insert' !== $name && '' === $element ) {
            return sprintf( '%s needs `element`, an address from wp_get_page_layout such as "0.1.2".', $name );
        }
        if ( isset( $op['ref'] ) && ! in_array( $name, array( 'insert', 'duplicate' ), true ) ) {
            return '`ref` names a new element; only insert and duplicate take it.';
        }
        $placed = array_filter(
            self::POSITIONS,
            static function ( string $key ) use ( $op ): bool {
                return isset( $op[ $key ] );
            }
        );
        if ( in_array( $name, array( 'remove', 'duplicate' ), true ) && array() !== $placed ) {
            return sprintf( '%s takes no `%s`%s.', $name, (string) reset( $placed ), 'duplicate' === $name ? '; the copy goes right after the element, and move puts it elsewhere' : '' );
        }
        if ( isset( $op['index'] ) && ! isset( $op['parent'] ) ) {
            return '`index` goes with `parent`.';
        }
        return null;
    }

    private function remove( string $content, array $items, array $found, string $key, Shortcode_Marks $marks, int $number ) {
        $item = $found['path'][ count( $found['path'] ) - 1 ];
        $span = $this->span( $content, $item, $key );
        if ( is_string( $span ) ) {
            return $span;
        }
        $next  = substr_replace( $content, '', $span[0], $span[1] );
        $check = $this->check( $next, self::without( $items, self::indexes( $found['address'] ) ) );
        if ( null !== $check ) {
            return $check;
        }
        $parent = self::parent_key( array_slice( $found['path'], 0, -1 ), $marks );
        $joined = self::joined_texts( $items, $found['address'], $marks );
        $marks->removed( $span[0], $span[1], $number );
        $marks->joined( $joined, $number );
        return self::applied( $next, 'remove', $key, $item['tag'], true, null, null, $parent );
    }

    private function duplicate( string $content, array $items, array $found, string $key, array $op, Shortcode_Marks $marks, int $number ) {
        $ref = $this->new_ref( $op, $marks, $number );
        if ( ! $ref['ok'] ) {
            return $ref['text'];
        }
        $chain   = array_slice( $found['path'], 0, -1 );
        $item    = $found['path'][ count( $found['path'] ) - 1 ];
        $refusal = $this->copy_refusal( $item, $key ) ?? $this->placement( $item, $chain );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $span = $this->span( $content, $item, $key );
        if ( is_string( $span ) ) {
            return $span;
        }
        $copy  = $this->without_copy_drops( substr( $content, $span[0], $span[1] ) );
        $at    = $span[0] + $span[1];
        $next  = substr_replace( $content, $copy, $at, 0 );
        $parts = $this->adapter->layout_items( $copy );
        $check = $this->check( $next, self::with( $items, self::indexes( self::parent_address( $found['address'] ) ), $parts, $at ) );
        if ( null !== $check ) {
            return $check;
        }
        $parent = self::parent_key( $chain, $marks );
        $marks->inserted( $at, strlen( $copy ) );
        $marks->name_new( $ref['text'], $parts, $at );
        return self::applied( $next, 'duplicate', $key, $item['tag'], true, $ref['text'], $ref['text'], $parent );
    }

    private function move( string $content, array $items, array $found, string $key, array $op, Shortcode_Marks $marks, int $number ) {
        $item     = $found['path'][ count( $found['path'] ) - 1 ];
        $position = self::position( $op );
        if ( is_string( $position ) ) {
            return $position;
        }
        $host  = '';
        $count = count( $items );
        if ( '' !== $position['key'] ) {
            $target = $marks->find( $position['key'], $items );
            if ( is_string( $target ) ) {
                return $target;
            }
            if ( 'parent' !== $position['kind'] && self::beside_itself( $found['address'], $target['address'], $position['kind'] ) ) {
                return self::applied( $content, 'move', $key, $item['tag'], false, $key, null, null );
            }
            if ( 0 === strpos( $target['address'] . '.', $found['address'] . '.' ) ) {
                return sprintf( 'element "%s" cannot be moved into itself.', $key );
            }
            $host  = $target['address'];
            $count = count( $target['path'][ count( $target['path'] ) - 1 ]['children'] );
        }
        if ( 'parent' === $position['kind'] && self::in_place( $found['address'], $host, $count, $op ) ) {
            return self::applied( $content, 'move', $key, $item['tag'], false, $key, null, null );
        }
        $span = $this->span( $content, $item, $key );
        if ( is_string( $span ) ) {
            return $span;
        }
        $source = self::parent_key( array_slice( $found['path'], 0, -1 ), $marks );
        $joined = self::joined_texts( $items, $found['address'], $marks );
        $spot = 'parent' === $position['kind'] ? null : $this->beside( $position, $content, $items, $marks );
        if ( is_string( $spot ) ) {
            return $spot;
        }
        $block   = substr( $content, $span[0], $span[1] );
        $carried = $marks->cut( $span[0], $span[1] );
        $marks->joined( $joined, $number );
        $cut     = substr_replace( $content, '', $span[0], $span[1] );
        $rest    = $this->adapter->layout_items( $cut );
        $check   = $this->check( $cut, self::without( $items, self::indexes( $found['address'] ) ) );
        if ( null !== $check ) {
            return $check;
        }
        $place = null === $spot
            ? $this->destination( $position, $op, $cut, $rest, $marks, $span[0] )
            : $this->placed_in( $spot['parent'], $spot['at'] > $span[0] ? $spot['at'] - $span[1] : $spot['at'], $rest, $marks );
        if ( is_string( $place ) ) {
            return $place;
        }
        $refusal = $this->placement( $item, $place['chain'] );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $renamed = $this->renamed( $block, $place['chain'], $carried );
        if ( is_string( $renamed ) ) {
            return $renamed;
        }
        $next  = substr_replace( $cut, $renamed['text'], $place['at'], 0 );
        $parts = $this->adapter->layout_items( $renamed['text'] );
        $check = $this->check( $next, self::with( $rest, self::indexes( $place['address'] ), $parts, $place['at'] ) );
        if ( null !== $check ) {
            return $check;
        }
        $marks->pasted( $place['at'], strlen( $renamed['text'] ), $renamed['carried'] );
        $applied = self::applied( $next, 'move', $key, $parts[0]['tag'], $next !== $content, $key, null, $source );
        $target  = self::parent_key( $place['chain'], $marks );
        if ( null !== $target && ! in_array( $target, $applied['parents'], true ) ) {
            $applied['parents'][] = $target;
        }
        return $applied;
    }

    private function insert( string $content, array $op, Shortcode_Marks $marks, int $number ) {
        $type = isset( $op['element_type'] ) && is_string( $op['element_type'] ) ? trim( $op['element_type'] ) : '';
        if ( '' === $type || $this->rules->base_tag( $type ) !== $type || null === $this->adapter->element_fields( $type ) || ! $this->rules->insertable( $type ) ) {
            return sprintf( '`element_type` "%s" is not an element insert writes; give a type wp_list_layout_elements lists%s.', $type, 'ux_text' === $type ? ' (not ux_text: UX Builder stores text as plain HTML)' : '' );
        }
        if ( null !== $this->adapter->element_refusal( $type ) ) {
            return sprintf( 'a %s holds code (HTML or script), which insert never creates; add it in the builder.', $type );
        }
        $follow = $this->follow( $op, $type );
        if ( is_string( $follow ) ) {
            return $follow;
        }
        $ref = $this->new_ref( $op, $marks, $number );
        if ( ! $ref['ok'] ) {
            return $ref['text'];
        }
        $position = self::position( $op );
        if ( is_string( $position ) ) {
            return $position;
        }
        $items = $this->adapter->layout_items( $content );
        $place = $this->destination( $position, $op, $content, $items, $marks );
        if ( is_string( $place ) ) {
            return $place;
        }
        $chain   = $place['chain'];
        $refusal = $this->rules->placement_refusal( $type, $chain, array() );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $tag = $this->tag_at( $type, $chain );
        if ( ! $tag['ok'] ) {
            return $tag['text'];
        }
        $block = $this->rules->markup( $tag['text'], Shortcode_Editor::set_attributes( '', $this->rules->always_saved( $type, array() === $chain ? '' : (string) $chain[ count( $chain ) - 1 ]['tag'] ) ) );
        $next  = substr_replace( $content, $block, $place['at'], 0 );
        $parts = $this->adapter->layout_items( $block );
        $check = $this->check( $next, self::with( $items, self::indexes( $place['address'] ), $parts, $place['at'] ) );
        if ( null !== $check ) {
            return $check;
        }
        $parent = self::parent_key( $place['chain'], $marks );
        $marks->inserted( $place['at'], strlen( $block ) );
        $marks->name_new( $ref['text'], $parts, $place['at'] );
        $applied = self::applied( $next, 'insert', $ref['text'], $tag['text'], true, $ref['text'], $ref['text'], $parent );
        foreach ( $follow as $edit ) {
            $applied['follow'][] = array_merge( $edit, array( 'element' => $ref['text'] ) );
        }
        return $applied;
    }

    private function follow( array $op, string $type ) {
        if ( isset( $op['fields'] ) && ! is_array( $op['fields'] ) ) {
            return '`fields` must be an object of field name to value.';
        }
        $asked  = isset( $op['fields'] ) ? $op['fields'] : array();
        $follow = array();
        $group  = array();
        foreach ( self::field_order( (array) $this->adapter->element_fields( $type ), array_map( 'strval', array_keys( $asked ) ) ) as $field => $kind ) {
            if ( ! in_array( $kind, array( 'image', 'link' ), true ) ) {
                $group[ $field ] = $asked[ $field ];
                continue;
            }
            if ( array() !== $group ) {
                $follow[] = array(
                    'op'     => 'set_fields',
                    'fields' => $group,
                );
                $group    = array();
            }
            $follow[] = 'image' === $kind
                ? array(
                    'op'            => 'set_image',
                    'field'         => $field,
                    'attachment_id' => $asked[ $field ],
                )
                : array(
                    'op'    => 'set_link',
                    'field' => $field,
                    'url'   => $asked[ $field ],
                );
        }
        if ( array() !== $group ) {
            $follow[] = array(
                'op'     => 'set_fields',
                'fields' => $group,
            );
        }
        if ( isset( $op['value'] ) ) {
            $follow[] = array(
                'op'    => 'set_text',
                'value' => $op['value'],
            );
        }
        return $follow;
    }

    private static function field_order( array $specs, array $asked ): array {
        $ordered = array();
        foreach ( $specs as $spec ) {
            $names = isset( $spec['devices'] ) && is_array( $spec['devices'] ) ? $spec['devices'] : array( $spec['name'] ?? '' );
            foreach ( $names as $name ) {
                if ( in_array( (string) $name, $asked, true ) ) {
                    $ordered[ (string) $name ] = (string) ( $spec['kind'] ?? '' );
                }
            }
        }
        foreach ( $asked as $name ) {
            $ordered += array( $name => '' );
        }
        return $ordered;
    }

    private static function position( array $op ) {
        $given = array();
        foreach ( self::POSITIONS as $kind ) {
            if ( isset( $op[ $kind ] ) ) {
                $given[ $kind ] = is_scalar( $op[ $kind ] ) ? trim( (string) $op[ $kind ] ) : null;
            }
        }
        if ( 1 !== count( $given ) ) {
            return sprintf( '%s needs exactly one of `before`, `after` or `parent` (with `index`) to say where the element goes.', (string) $op['op'] );
        }
        $kind = (string) key( $given );
        $key  = reset( $given );
        if ( null === $key || ( '' === $key && 'parent' !== $kind ) ) {
            return sprintf( '`%s` must be an element address such as "0.1.2"%s.', $kind, 'parent' === $kind ? ', or "" for the page itself' : '' );
        }
        return array(
            'kind' => $kind,
            'key'  => $key,
        );
    }

    private function destination( array $position, array $op, string $content, array $items, Shortcode_Marks $marks, ?int $empty_at = null ) {
        if ( 'parent' !== $position['kind'] ) {
            $spot = $this->beside( $position, $content, $items, $marks );
            return is_string( $spot ) ? $spot : $this->placed_in( $spot['parent'], $spot['at'], $items, $marks );
        }
        $chain = array();
        $kids  = $items;
        $host  = '';
        if ( '' !== $position['key'] ) {
            $target = $marks->find( $position['key'], $items );
            if ( is_string( $target ) ) {
                return $target;
            }
            $refusal = $this->holder_refusal( $target['path'], $position['key'] );
            if ( null !== $refusal ) {
                return $refusal;
            }
            $chain  = $target['path'];
            $host   = $target['address'];
            $parent = $chain[ count( $chain ) - 1 ];
            if ( Shortcode_Tree::TEXT === $parent['tag'] || null === $parent['content'] || ! $this->rules->holds_elements( $parent['tag'] ) ) {
                return sprintf( 'element "%s" (%s) holds no elements in %s.', $position['key'], $parent['tag'], $this->rules->name() );
            }
            $refusal = $this->adapter->layout_refusal( $parent, 'inside' );
            if ( null !== $refusal ) {
                return sprintf( 'element "%s" (%s): %s', $position['key'], $parent['tag'], $refusal );
            }
            $kids = $parent['children'];
        }
        $index = self::index( $op, count( $kids ) );
        if ( is_string( $index ) ) {
            return $index;
        }
        if ( $index < count( $kids ) ) {
            $at = Shortcode_Marks::begin( $kids[ $index ] );
        } elseif ( array() !== $kids ) {
            $at = $this->after( $content, $kids[ count( $kids ) - 1 ] );
        } elseif ( array() !== $chain ) {
            $parent = $chain[ count( $chain ) - 1 ];
            $at     = $parent['content_at'] + self::breaks( $content, $parent['content_at'], 2 );
        } elseif ( null !== $empty_at ) {
            $at = $empty_at;
        } else {
            return 'the page holds no items left to place an element among; insert the new element before removing the last one.';
        }
        return array(
            'chain'   => $chain,
            'address' => $host,
            'at'      => $at,
        );
    }

    private static function index( array $op, int $default ) {
        $index = $op['index'] ?? $default;
        if ( is_float( $index ) && floor( $index ) === $index && abs( $index ) < PHP_INT_MAX ) {
            $index = (int) $index;
        }
        if ( ! is_int( $index ) && ! ( is_string( $index ) && ctype_digit( $index ) ) || (int) $index < 0 ) {
            return '`index` must be a whole number, 0 or more.';
        }
        return (int) $index;
    }

    private function beside( array $position, string $content, array $items, Shortcode_Marks $marks ) {
        $target = $marks->find( $position['key'], $items );
        if ( is_string( $target ) ) {
            return $target;
        }
        $refusal = $this->holder_refusal( $target['path'], $position['key'], true );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $chain   = $target['path'];
        $sibling = array_pop( $chain );
        return array(
            'parent' => (string) self::parent_key( $chain, $marks ),
            'at'     => 'before' === $position['kind'] ? Shortcode_Marks::begin( $sibling ) : $this->after( $content, $sibling ),
        );
    }

    private function placed_in( string $parent, int $at, array $items, Shortcode_Marks $marks ) {
        if ( '' === $parent ) {
            return array(
                'chain'   => array(),
                'address' => '',
                'at'      => $at,
            );
        }
        $found = $marks->find( $parent, $items );
        if ( is_string( $found ) ) {
            return $found;
        }
        return array(
            'chain'   => $found['path'],
            'address' => $found['address'],
            'at'      => $at,
        );
    }

    private static function beside_itself( string $element, string $target, string $kind ): bool {
        if ( $target === $element ) {
            return true;
        }
        if ( self::parent_address( $target ) !== self::parent_address( $element ) ) {
            return false;
        }
        $step = self::own_index( $target ) - self::own_index( $element );
        return ( 'after' === $kind && -1 === $step ) || ( 'before' === $kind && 1 === $step );
    }

    private static function in_place( string $element, string $parent, int $count, array $op ): bool {
        return self::parent_address( $element ) === $parent && self::index( $op, $count - 1 ) === self::own_index( $element );
    }

    private static function own_index( string $address ): int {
        return (int) substr( (string) strrchr( '.' . $address, '.' ), 1 );
    }

    private function holder_refusal( array $path, string $key, bool $beside = false ): ?string {
        $item = $path[ count( $path ) - 1 ];
        foreach ( $path as $depth => $holder ) {
            if ( Shortcode_Tree::TEXT === $holder['tag'] || ( $beside && count( $path ) - 1 === $depth ) ) {
                continue;
            }
            if ( $depth < count( $path ) - 1 && ! $this->rules->holds_elements( $holder['tag'] ) ) {
                return sprintf( 'element "%s" is inside a %s, which %s keeps as text, not as elements.', $key, $holder['tag'], $this->rules->name() );
            }
            $refusal = $this->adapter->element_access_refusal( $holder['tag'] ) ?? $this->adapter->layout_refusal( $holder, $depth < count( $path ) - 1 ? 'inside' : 'own' );
            if ( null !== $refusal ) {
                return sprintf( 'element "%s" (%s): %s', $key, $item['tag'], $refusal );
            }
        }
        return null;
    }

    private function copy_refusal( array $item, string $key ): ?string {
        foreach ( array_merge( array( $item['tag'] ), $this->inner_tags( $item, true ) ) as $tag ) {
            if ( null !== $this->adapter->element_refusal( $tag ) ) {
                return sprintf( 'element "%s" %s a %s, which holds code (HTML or script); duplicate never creates code, so copy it in the builder.', $key, $tag === $item['tag'] ? 'is' : 'holds', $tag );
            }
        }
        $holder = $this->stray_text_holder( $item );
        if ( null !== $holder ) {
            return sprintf( 'element "%s" holds text directly inside a %s: %s Remove that text first.', $key, $holder['tag'], $holder['reason'] );
        }
        return null;
    }

    private function stray_text_holder( array $item ): ?array {
        if ( ! $this->rules->holds_elements( $item['tag'] ) ) {
            return null;
        }
        foreach ( $item['children'] as $child ) {
            if ( Shortcode_Tree::TEXT === $child['tag'] ) {
                $reason = $this->rules->placement_refusal( 'text', array( $item ), array() );
                if ( null !== $reason ) {
                    return array(
                        'tag'    => $item['tag'],
                        'reason' => $reason,
                    );
                }
                continue;
            }
            $inner = $this->stray_text_holder( $child );
            if ( null !== $inner ) {
                return $inner;
            }
        }
        return null;
    }

    private static function too_deep( array $item ): bool {
        if ( Shortcode_Tree::TEXT !== $item['tag'] && null !== $item['content'] && array() === $item['children'] && '' !== trim( $item['content'] ) ) {
            return true;
        }
        foreach ( $item['children'] as $child ) {
            if ( self::too_deep( $child ) ) {
                return true;
            }
        }
        return false;
    }

    private static function joined_texts( array $items, string $address, Shortcode_Marks $marks ): array {
        $indexes  = self::indexes( $address );
        $index    = (int) array_pop( $indexes );
        $siblings = $items;
        foreach ( $indexes as $parent ) {
            $siblings = $siblings[ $parent ]['children'];
        }
        $before = $siblings[ $index - 1 ] ?? null;
        $after  = $siblings[ $index + 1 ] ?? null;
        if ( null === $before || null === $after || Shortcode_Tree::TEXT !== $before['tag'] || Shortcode_Tree::TEXT !== $after['tag'] ) {
            return array();
        }
        return array_values( array_filter( array( $marks->key_at( Shortcode_Marks::begin( $before ) ), $marks->key_at( Shortcode_Marks::begin( $after ) ) ), 'is_string' ) );
    }

    private function placement( array $item, array $chain ): ?string {
        return $this->rules->placement_refusal( $item['tag'], $chain, $this->inner_tags( $item, false ) );
    }

    private function tag_at( string $tag, array $ancestors ): array {
        $name = $this->rules->tag_at( $tag, $ancestors );
        if ( $name !== $tag && ! shortcode_exists( $name ) ) {
            return array(
                'ok'   => false,
                'text' => sprintf( 'a %s there would be written as [%s], which %s does not register, so the page would print it as text.', $this->rules->base_tag( $tag ), $name, $this->adapter->label() ),
            );
        }
        return array(
            'ok'   => true,
            'text' => $name,
        );
    }

    private function renamed( string $block, array $ancestors, array $carried ) {
        $renames = $this->renames( $this->adapter->layout_items( $block ), $ancestors );
        if ( is_string( $renames ) ) {
            return $renames;
        }
        krsort( $renames );
        foreach ( $renames as $at => $rename ) {
            list( $length, $name ) = $rename;
            $block = substr_replace( $block, $name, $at, $length );
            foreach ( $carried as $key => $offset ) {
                if ( $offset > $at ) {
                    $carried[ $key ] = $offset + strlen( $name ) - $length;
                }
            }
        }
        return array(
            'text'    => $block,
            'carried' => $carried,
        );
    }

    private function renames( array $items, array $ancestors ) {
        $renames = array();
        foreach ( $items as $item ) {
            if ( Shortcode_Tree::TEXT === $item['tag'] ) {
                continue;
            }
            $tag = $this->tag_at( $item['tag'], $ancestors );
            if ( ! $tag['ok'] ) {
                return $tag['text'];
            }
            $length = strlen( $item['tag'] );
            if ( $tag['text'] !== $item['tag'] ) {
                $renames[ $item['start'] + 1 ] = array( $length, $tag['text'] );
                if ( null !== $item['content'] ) {
                    $renames[ $item['end'] - $length - 1 ] = array( $length, $tag['text'] );
                }
            }
            if ( ! $this->rules->holds_elements( $item['tag'] ) ) {
                continue;
            }
            $inner = $this->renames( $item['children'], array_merge( $ancestors, array( array_merge( $item, array( 'tag' => $tag['text'] ) ) ) ) );
            if ( is_string( $inner ) ) {
                return $inner;
            }
            $renames += $inner;
        }
        return $renames;
    }

    private function without_copy_drops( string $block ): string {
        $edits = array();
        $this->collect_copy_drops( $this->adapter->layout_items( $block ), $edits );
        krsort( $edits );
        foreach ( $edits as $at => $edit ) {
            $block = substr_replace( $block, $edit[1], $at, $edit[0] );
        }
        return $block;
    }

    private function collect_copy_drops( array $items, array &$edits ): void {
        foreach ( $items as $item ) {
            if ( Shortcode_Tree::TEXT === $item['tag'] ) {
                continue;
            }
            $raw = Shortcode_Editor::set_attributes( $item['atts_raw'], array_fill_keys( $this->rules->copy_drops(), null ) );
            if ( $raw !== $item['atts_raw'] ) {
                $edits[ $item['atts_at'] ] = array( strlen( $item['atts_raw'] ), $raw );
            }
            if ( $this->rules->holds_elements( $item['tag'] ) ) {
                $this->collect_copy_drops( $item['children'], $edits );
            }
        }
    }

    private function span( string $content, array $item, string $key ) {
        $from   = Shortcode_Marks::begin( $item );
        $length = $this->after( $content, $item ) - $from;
        if ( substr( $content, $from, $length ) !== substr( $this->adapter->masked( $content ), $from, $length ) ) {
            return sprintf( 'element "%s" runs into the builder\'s own wrapper, so it is not changed here.', $key );
        }
        return array( $from, $length );
    }

    private function after( string $content, array $item ): int {
        $end = Shortcode_Tree::TEXT === $item['tag'] ? Shortcode_Marks::begin( $item ) + strlen( trim( (string) $item['content'] ) ) : $item['end'];
        return $end + self::breaks( $content, $end, $this->rules->trailing_breaks( $item['tag'] ) );
    }

    private static function breaks( string $content, int $at, int $count ): int {
        $length = 0;
        $seen   = 0;
        while ( $seen < $count && $at + $length < strlen( $content ) && ctype_space( $content[ $at + $length ] ) ) {
            $seen += "\n" === $content[ $at + $length ] ? 1 : 0;
            ++$length;
        }
        return $length;
    }

    private function check( string $content, array $planned ): ?string {
        if ( self::skeleton( $this->adapter->layout_items( $content ) ) === self::skeleton( $planned ) ) {
            return null;
        }
        return 'the layout would not hold the elements planned (an element would open, close or swallow another), so it was not changed.';
    }

    private function new_ref( array $op, Shortcode_Marks $marks, int $number ): array {
        if ( ! isset( $op['ref'] ) ) {
            return array(
                'ok'   => true,
                'text' => '$op' . $number,
            );
        }
        $ref = is_string( $op['ref'] ) ? trim( $op['ref'] ) : '';
        if ( 1 !== preg_match( '/^\$[A-Za-z][\w-]{0,31}\z/', $ref ) || 1 === preg_match( '/^\$op\d+\z/', $ref ) ) {
            return array(
                'ok'   => false,
                'text' => '`ref` must be a name starting with $ and a letter, such as "$promo" (names like "$op3" are given to new elements without one).',
            );
        }
        if ( $marks->has( $ref ) ) {
            return array(
                'ok'   => false,
                'text' => sprintf( '`ref` "%s" already names another element in this call.', $ref ),
            );
        }
        return array(
            'ok'   => true,
            'text' => $ref,
        );
    }

    private static function parent_key( array $chain, Shortcode_Marks $marks ): ?string {
        if ( array() === $chain ) {
            return '';
        }
        return $marks->key_at( Shortcode_Marks::begin( $chain[ count( $chain ) - 1 ] ) );
    }

    private static function applied( string $content, string $op, string $element, string $type, bool $changed, ?string $track, ?string $ref, ?string $parent ): array {
        $result = array(
            'op'      => $op,
            'element' => $element,
            'type'    => $type,
            'changed' => $changed,
            'track'   => $track,
        );
        if ( null !== $ref ) {
            $result['ref'] = $ref;
        }
        return array(
            'content' => $content,
            'result'  => $result,
            'parents' => null === $parent ? array() : array( $parent ),
            'follow'  => array(),
        );
    }

    private static function without( array $items, array $indexes ): array {
        $index = (int) array_shift( $indexes );
        if ( array() === $indexes ) {
            array_splice( $items, $index, 1 );
            return $items;
        }
        $items[ $index ]['children'] = self::without( $items[ $index ]['children'], $indexes );
        return $items;
    }

    private static function with( array $items, array $indexes, array $added, int $at ): array {
        if ( array() === $indexes ) {
            $position = 0;
            foreach ( $items as $item ) {
                $position += Shortcode_Marks::begin( $item ) < $at ? 1 : 0;
            }
            array_splice( $items, $position, 0, $added );
            return $items;
        }
        $index                       = (int) array_shift( $indexes );
        $items[ $index ]['children'] = self::with( $items[ $index ]['children'], $indexes, $added, $at );
        return $items;
    }

    private static function indexes( string $address ): array {
        return '' === $address ? array() : array_map( 'intval', explode( '.', $address ) );
    }

    private static function parent_address( string $address ): string {
        $cut = strrpos( $address, '.' );
        return false === $cut ? '' : substr( $address, 0, $cut );
    }

    private function inner_tags( array $item, bool $into_text ): array {
        if ( ! $into_text && ! $this->rules->holds_elements( $item['tag'] ) ) {
            return array();
        }
        $tags = array();
        foreach ( $item['children'] as $child ) {
            if ( Shortcode_Tree::TEXT !== $child['tag'] ) {
                $tags[] = $child['tag'];
                $tags   = array_merge( $tags, $this->inner_tags( $child, $into_text ) );
            }
        }
        return $tags;
    }
}
