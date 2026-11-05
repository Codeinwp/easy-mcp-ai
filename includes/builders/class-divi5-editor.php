<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi5_Editor {

    const MAX_OPS = 50;

    const FIELD_KINDS = array( 'text', 'select', 'number', 'color', 'boolean', 'size' );

    const CODE_MODULES = array( 'divi/code', 'divi/fullwidth-code' );

    const VARIABLE = '$variable(';

    private $rules;

    private $fields_of;

    private $roles_of;

    private $media;

    private $unfiltered;

    private $kses;

    private $version;

    private $tree;

    private $read = array();

    private $refs = array();

    private $removed = array();

    private $removed_blocks = array();

    private $warnings = array();

    private $parents = array();

    public function __construct( Divi5_Rules $rules, callable $fields_of, callable $roles_of, callable $media, bool $unfiltered, string $version, ?callable $kses = null ) {
        $this->rules      = $rules;
        $this->fields_of  = $fields_of;
        $this->roles_of   = $roles_of;
        $this->media      = $media;
        $this->unfiltered = $unfiltered;
        $this->version    = $version;
        $this->kses       = $kses ?? 'wp_kses_post';
    }

    public function plan( string $source, array $ops ): array {
        $errors = array();
        if ( array() === $ops ) {
            $errors[] = '`ops` is empty: give at least one operation.';
        }
        if ( count( $ops ) > self::MAX_OPS ) {
            $errors[] = sprintf( 'At most %d operations per call; this call has %d.', self::MAX_OPS, count( $ops ) );
        }
        $tree = Divi5_Tree::parse( $source );
        if ( null === $tree ) {
            $errors[] = 'The stored layout is not well-formed block markup, so it is not edited here; open it in the Divi Builder.';
        }
        if ( array() !== $errors || null === $tree ) {
            return self::refused( $source, $errors );
        }
        $this->tree = $tree;
        $this->read = Divi5_Tree::addresses( $tree->items() );
        $results    = array();
        foreach ( array_values( $ops ) as $index => $op ) {
            $applied = is_array( $op ) ? $this->apply_op( $op, $index + 1 ) : 'must be an object.';
            if ( is_string( $applied ) ) {
                $errors[] = sprintf( 'Operation %d: %s', $index + 1, $applied );
                continue;
            }
            $results = array_merge( $results, $applied );
        }
        if ( array() !== $errors ) {
            return self::refused( $source, $errors );
        }
        $where = $this->where();
        foreach ( $results as $index => $result ) {
            $block = $result['block'];
            unset( $results[ $index ]['block'] );
            $results[ $index ]['address'] = $block instanceof Divi5_Block && isset( $where[ spl_object_id( $block ) ] ) ? $where[ spl_object_id( $block ) ] : null;
        }
        return array(
            'content'  => $tree->serialize(),
            'results'  => array_values( $results ),
            'errors'   => array(),
            'warnings' => array_values( array_unique( $this->warnings ) ),
            'parents'  => $this->parents,
            'tree'     => $tree,
        );
    }

    public function where(): array {
        $where = array();
        foreach ( Divi5_Tree::addresses( $this->tree->items() ) as $address => $block ) {
            $where[ spl_object_id( $block ) ] = (string) $address;
        }
        return $where;
    }

    private function apply_op( array $op, int $number ) {
        $name = isset( $op['op'] ) && is_string( $op['op'] ) ? $op['op'] : '';
        if ( ! in_array( $name, Shortcode_Editor::OPS, true ) ) {
            return sprintf( 'unknown `op` "%s"; one of %s.', $name, implode( ', ', Shortcode_Editor::OPS ) );
        }
        if ( in_array( $name, Shortcode_Structure::OPS, true ) ) {
            $type    = 'insert' === $name && isset( $op['element_type'] ) && is_string( $op['element_type'] ) ? $this->rules->role_key( trim( $op['element_type'] ) ) : '';
            $refusal = Divi_Access::op_refusal( $name, $type );
            if ( null !== $refusal ) {
                return $refusal;
            }
        }
        if ( 'insert' === $name ) {
            return $this->insert( $op, $number );
        }
        $key   = isset( $op['element'] ) && is_scalar( $op['element'] ) ? trim( (string) $op['element'] ) : '';
        $block = $this->resolve( $key );
        if ( is_string( $block ) ) {
            return $block;
        }
        $place   = $this->locate( $block );
        $refusal = self::lock_refusal( $block, null === $place ? array() : $place['chain'] );
        if ( null !== $refusal ) {
            return sprintf( 'element "%s" (%s): %s', $key, $block->is_text() ? 'text' : $block->name, $refusal );
        }
        $expect = isset( $op['expect'] ) && is_string( $op['expect'] ) ? $op['expect'] : '';
        if ( '' !== $expect && $expect !== $block->name ) {
            return sprintf( 'element "%s" is a %s, not the expected %s.', $key, $block->name, $expect );
        }
        if ( in_array( $name, array( 'set_text', 'set_link', 'set_image', 'set_fields' ), true ) ) {
            $set = $this->set_op( $block, $op, $key );
            return is_string( $set ) ? $set : array( $set );
        }
        if ( $block->is_text() && 'remove' !== $name ) {
            return sprintf( '"%s" is text between elements, which remove takes away but %s does not handle.', $key, $name );
        }
        $fixed = $block->is_text() ? null : $this->rules->fixed_refusal( $block->name );
        if ( null !== $fixed ) {
            return sprintf( 'element "%s" (%s): %s', $key, $block->name, $fixed );
        }
        if ( 'remove' === $name ) {
            return $this->remove( $block, $key, $number );
        }
        if ( 'duplicate' === $name ) {
            return $this->duplicate( $block, $key, $op, $number );
        }
        return $this->move( $block, $key, $op );
    }

    private function resolve( string $key ) {
        if ( '' === $key ) {
            return '`element` is required: the element\'s address from wp_get_page_layout, such as "0.1.2", or the `ref` of an element added earlier in the call.';
        }
        if ( '$' === $key[0] ) {
            $parts = explode( '.', $key );
            $block = $this->refs[ (string) array_shift( $parts ) ] ?? null;
            foreach ( $parts as $index ) {
                $block = null !== $block && ctype_digit( $index ) ? ( $block->children[ (int) $index ] ?? null ) : null;
            }
            if ( null === $block ) {
                return sprintf( 'no element "%s": no earlier op in this call added an element with that ref.', $key );
            }
        } else {
            $block = $this->read[ $key ] ?? null;
            if ( null === $block ) {
                return sprintf( 'no element "%s" in this layout. Read it again with wp_get_page_layout.', $key );
            }
        }
        $removed = $this->removed_by( $block );
        if ( null !== $removed ) {
            return sprintf( 'element "%s" was removed by operation %d of this call.', $key, $removed );
        }
        return $block;
    }

    private function removed_by( Divi5_Block $block ): ?int {
        if ( isset( $this->removed[ spl_object_id( $block ) ] ) ) {
            return $this->removed[ spl_object_id( $block ) ];
        }
        if ( null !== $this->locate( $block ) ) {
            return null;
        }
        foreach ( $this->removed_blocks as $id => $removed ) {
            if ( null !== self::search( $removed->children, $block, $removed, array() ) ) {
                return $this->removed[ $id ];
            }
        }
        return null;
    }

    private static function lock_refusal( Divi5_Block $block, array $above ): ?string {
        foreach ( array_merge( array( $block ), array_reverse( $above ) ) as $item ) {
            if ( 'on' === $item->get( array( 'locked', 'desktop', 'value' ) ) ) {
                return Divi_Access::lock_refusal( 'the ' . $item->name );
            }
        }
        return null;
    }

    private function locate( Divi5_Block $block ): ?array {
        return self::search( $this->tree->items(), $block, null, array() );
    }

    private static function search( array $list, Divi5_Block $block, ?Divi5_Block $parent, array $chain ): ?array {
        foreach ( array_values( $list ) as $index => $item ) {
            if ( $item === $block ) {
                return array(
                    'parent' => $parent,
                    'index'  => $index,
                    'chain'  => $chain,
                );
            }
            $found = self::search( $item->children, $block, $item, array_merge( $chain, array( $item ) ) );
            if ( null !== $found ) {
                return $found;
            }
        }
        return null;
    }

    private function &list_of( ?Divi5_Block $parent ): array {
        if ( null === $parent ) {
            return $this->tree->items();
        }
        return $parent->children;
    }

    private function remove( Divi5_Block $block, string $key, int $number ): array {
        $place = (array) $this->locate( $block );
        $list  = &$this->list_of( $place['parent'] );
        array_splice( $list, $place['index'], 1 );
        $this->removed[ spl_object_id( $block ) ]        = $number;
        $this->removed_blocks[ spl_object_id( $block ) ] = $block;
        $this->parents[]                                  = $place['parent'];
        return array( self::moved( 'remove', $key, $block, true ) );
    }

    private function duplicate( Divi5_Block $block, string $key, array $op, int $number ) {
        $refusal = $this->copy_refusal( $block, $key );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $ref = $this->new_ref( $op, $number );
        if ( ! $ref['ok'] ) {
            return $ref['text'];
        }
        $place = (array) $this->locate( $block );
        $copy  = clone $block;
        $list  = &$this->list_of( $place['parent'] );
        array_splice( $list, $place['index'] + 1, 0, array( $copy ) );
        $this->refs[ $ref['text'] ] = $copy;
        $this->parents[]            = $place['parent'];
        $result                     = self::moved( 'duplicate', $key, $copy, true );
        $result['ref']              = $ref['text'];
        return array( $result );
    }

    private function move( Divi5_Block $block, string $key, array $op ) {
        $place = $this->destination( $op );
        if ( is_string( $place ) ) {
            return $place;
        }
        $from = (array) $this->locate( $block );
        if ( $place['parent'] === $block || in_array( $block, $place['chain'], true ) ) {
            return sprintf( 'element "%s" cannot be moved into itself or into an element it holds.', $key );
        }
        if ( Divi5_Rules::GLOBAL_LAYOUT === $block->name && $place['parent'] !== $from['parent'] ) {
            return sprintf( 'element "%s" is a global Divi element; it is reordered among its siblings here, and moved elsewhere in the Divi Builder, which checks what the Library item holds.', $key );
        }
        if ( Divi5_Rules::GLOBAL_LAYOUT !== $block->name ) {
            $refusal = $this->rules->placement_refusal( $block->name, $place['parent'], self::holder( $place['chain'] ) );
            if ( null !== $refusal ) {
                return $refusal;
            }
        }
        $index = $place['index'];
        if ( $place['parent'] === $from['parent'] && $from['index'] < $index ) {
            --$index;
        }
        $result = self::moved( 'move', $key, $block, ! ( $place['parent'] === $from['parent'] && $from['index'] === $index ) );
        if ( ! $result['changed'] ) {
            return array( $result );
        }
        $source = &$this->list_of( $from['parent'] );
        array_splice( $source, $from['index'], 1 );
        $target = &$this->list_of( $place['parent'] );
        array_splice( $target, $index, 0, array( $block ) );
        $this->parents[] = $from['parent'];
        $this->parents[] = $place['parent'];
        return array( $result );
    }

    private function insert( array $op, int $number ) {
        $type = isset( $op['element_type'] ) && is_string( $op['element_type'] ) ? trim( $op['element_type'] ) : '';
        if ( '' === $type || ! $this->rules->insertable( $type ) ) {
            return sprintf( '`element_type` "%s" is not an element insert writes; give a module type wp_list_layout_elements lists (rows and sections are copied with duplicate).', $type );
        }
        if ( in_array( $type, self::CODE_MODULES, true ) ) {
            return sprintf( 'a %s holds code (HTML or script), which insert never creates; add it in the Divi Builder.', $type );
        }
        $place = $this->destination( $op );
        if ( is_string( $place ) ) {
            return $place;
        }
        $refusal = $this->rules->placement_refusal( $type, $place['parent'], self::holder( $place['chain'] ) );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $ref = $this->new_ref( $op, $number );
        if ( ! $ref['ok'] ) {
            return $ref['text'];
        }
        $attrs                 = new \stdClass();
        $attrs->builderVersion = $this->version; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Divi's own attribute name.
        $block                 = Divi5_Block::make( $type, $attrs, ! $this->rules->holds_elements( $type ) );
        $results               = array( array_merge( self::moved( 'insert', $ref['text'], $block, true ), array( 'ref' => $ref['text'] ) ) );
        foreach ( $this->follow( $op, $type ) as $edit ) {
            $set = $this->set_op( $block, $edit, $ref['text'] );
            if ( is_string( $set ) ) {
                return sprintf( '%s for the new %s: %s', $edit['op'], $type, $set );
            }
            $results[] = $set;
        }
        $list = &$this->list_of( $place['parent'] );
        array_splice( $list, $place['index'], 0, array( $block ) );
        $this->refs[ $ref['text'] ] = $block;
        $this->parents[]            = $place['parent'];
        return $results;
    }

    private function follow( array $op, string $type ): array {
        $asked  = isset( $op['fields'] ) && is_array( $op['fields'] ) ? $op['fields'] : array();
        $kinds  = array();
        $follow = array();
        $group  = array();
        foreach ( (array) ( $this->fields_of )( $type ) as $spec ) {
            $kinds[ (string) $spec['name'] ] = (string) ( $spec['kind'] ?? 'other' );
        }
        foreach ( $asked as $field => $value ) {
            $kind = $kinds[ (string) $field ] ?? '';
            if ( 'image' === $kind ) {
                $follow[] = array(
                    'op'            => 'set_image',
                    'field'         => (string) $field,
                    'attachment_id' => $value,
                );
            } elseif ( 'link' === $kind ) {
                $follow[] = array(
                    'op'    => 'set_link',
                    'field' => (string) $field,
                    'url'   => $value,
                );
            } else {
                $group[ (string) $field ] = $value;
            }
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

    private function destination( array $op ) {
        $place = $this->place( $op );
        if ( is_string( $place ) || null === $place['parent'] ) {
            return $place;
        }
        return self::lock_refusal( $place['parent'], $place['chain'] ) ?? $place;
    }

    private function place( array $op ) {
        $given = array_values(
            array_filter(
                Shortcode_Structure::POSITIONS,
                static function ( string $kind ) use ( $op ): bool {
                    return array_key_exists( $kind, $op ) && null !== $op[ $kind ];
                }
            )
        );
        if ( 1 !== count( $given ) ) {
            return 'give exactly one of `before`, `after` or `parent` (with `index`).';
        }
        $kind = $given[0];
        $key  = is_scalar( $op[ $kind ] ) ? trim( (string) $op[ $kind ] ) : '';
        if ( 'parent' !== $kind ) {
            $sibling = $this->resolve( $key );
            if ( is_string( $sibling ) ) {
                return $sibling;
            }
            $place = (array) $this->locate( $sibling );
            return array(
                'parent' => $place['parent'],
                'index'  => $place['index'] + ( 'after' === $kind ? 1 : 0 ),
                'chain'  => array_slice( $place['chain'], 0, -1 ),
            );
        }
        $parent = null;
        $chain  = array();
        if ( '' !== $key ) {
            $parent = $this->resolve( $key );
            if ( is_string( $parent ) ) {
                return $parent;
            }
            if ( $parent->is_text() || ! $this->rules->holds_elements( $parent->name ) ) {
                return sprintf( 'element "%s" (%s) holds no elements in the Divi Builder.', $key, $parent->name );
            }
            $chain = ( (array) $this->locate( $parent ) )['chain'];
        }
        $count = count( $this->list_of( $parent ) );
        $index = $op['index'] ?? $count;
        if ( is_float( $index ) && floor( $index ) === $index ) {
            $index = (int) $index;
        }
        if ( ! is_int( $index ) && ! ( is_string( $index ) && ctype_digit( $index ) ) || (int) $index < 0 ) {
            return '`index` must be a whole number, 0 or more.';
        }
        return array(
            'parent' => $parent,
            'index'  => min( (int) $index, $count ),
            'chain'  => $chain,
        );
    }

    private static function holder( array $chain ): ?Divi5_Block {
        return array() === $chain ? null : $chain[ count( $chain ) - 1 ];
    }

    private function new_ref( array $op, int $number ): array {
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
        if ( isset( $this->refs[ $ref ] ) ) {
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

    private function copy_refusal( Divi5_Block $block, string $key ): ?string {
        foreach ( self::flatten( array( $block ) ) as $inner ) {
            if ( in_array( $inner->name, self::CODE_MODULES, true ) || Divi5_Rules::SHORTCODE_MODULE === $inner->name ) {
                return sprintf( 'element "%s" %s a %s, which holds code or Divi 4 content; duplicate never creates those, so copy it in the Divi Builder.', $key, $inner === $block ? 'is' : 'holds', $inner->name );
            }
        }
        return null;
    }

    public static function flatten( array $blocks ): array {
        $all = array();
        foreach ( $blocks as $block ) {
            $all[] = $block;
            $all   = array_merge( $all, self::flatten( $block->children ) );
        }
        return $all;
    }

    private function set_op( Divi5_Block $block, array $op, string $key ) {
        $refusal = Divi_Access::op_refusal( (string) $op['op'] );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $refusal = $this->element_refusal( $block );
        if ( null !== $refusal ) {
            return sprintf( 'element "%s" (%s): %s', $key, $block->is_text() ? 'text' : $block->name, $refusal );
        }
        $fields = ( $this->fields_of )( $block->name );
        if ( null === $fields ) {
            return sprintf( 'Divi has no %s module we know the fields of, so "%s" cannot be edited.', $block->name, $key );
        }
        $name    = (string) $op['op'];
        $changes = 'set_fields' === $name ? $this->field_changes( $op, $fields, $block->name ) : $this->single_change( $name, $op, $block, $fields );
        if ( is_string( $changes ) ) {
            return $changes;
        }
        foreach ( array_keys( $changes ) as $field ) {
            $refusal = Divi_Access::field_refusal( (string) $field, (string) ( self::spec( $fields, (string) $field )['category'] ?? '' ) );
            if ( null !== $refusal ) {
                return sprintf( 'element "%s" (%s): %s', $key, $block->name, $refusal );
            }
        }
        $before = array();
        foreach ( $changes as $field => $value ) {
            $current = $block->get( self::value_path( $field ) );
            if ( is_string( $current ) && false !== strpos( $current, self::VARIABLE ) ) {
                return sprintf( 'field "%s" of "%s" is filled by Divi dynamic content or a design variable; change that binding in the Divi Builder.', $field, $key );
            }
            $before[ $field ] = $current;
        }
        foreach ( $changes as $field => $value ) {
            $block->set( self::value_path( $field ), $value );
            if ( null !== $block->get( self::breakpoint_path( $field, 'tablet' ) ) || null !== $block->get( self::breakpoint_path( $field, 'phone' ) ) ) {
                $this->warnings[] = sprintf( 'Element "%s": field "%s" also has a tablet or phone value, which still applies on those devices.', $key, $field );
            }
        }
        $fields_written = array_keys( $changes );
        $after          = array();
        foreach ( $fields_written as $field ) {
            $after[ $field ] = $block->get( self::value_path( $field ) );
        }
        $single = 1 === count( $fields_written );
        return array(
            'op'      => $name,
            'element' => $key,
            'type'    => $block->name,
            'field'   => $single ? $fields_written[0] : implode( ', ', $fields_written ),
            'before'  => $single ? $before[ $fields_written[0] ] : $before,
            'after'   => $single ? $after[ $fields_written[0] ] : $after,
            'changed' => $before !== $after,
            'block'   => $block,
        );
    }

    private function element_refusal( Divi5_Block $block ): ?string {
        if ( $block->is_text() ) {
            return 'it is text between elements, which Divi does not keep; remove it instead.';
        }
        if ( Divi5_Rules::GLOBAL_LAYOUT === $block->name ) {
            $id = $block->get( array( 'globalModule' ) );
            return sprintf( 'it is a global Divi element, which renders from Divi Library item %1$s; edit that item (wp_get_page_layout with post_id %1$s).', is_scalar( $id ) ? (string) $id : '?' );
        }
        if ( Divi5_Rules::SHORTCODE_MODULE === $block->name ) {
            return 'it is a Divi 4 module kept as a shortcode inside this Divi 5 page; convert it in the Divi Builder first.';
        }
        if ( in_array( $block->name, self::CODE_MODULES, true ) ) {
            return 'it holds code (HTML or script), which these operations do not write; change it in the Divi Builder.';
        }
        return null;
    }

    private function single_change( string $name, array $op, Divi5_Block $block, array $fields ) {
        $role  = array(
            'set_text'  => 'text',
            'set_link'  => 'link',
            'set_image' => 'image',
        )[ $name ];
        $field = isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field'] ? $op['field'] : ( ( $this->roles_of )( $block->name )[ $role ] ?? '' );
        if ( '' === $field ) {
            return sprintf( '%s elements have no %s field; give `field` (see wp_list_layout_elements with type "%s").', $block->name, $role, $block->name );
        }
        $spec = self::spec( $fields, $field );
        if ( null === $spec ) {
            return sprintf( '%s elements have no field "%s" (see wp_list_layout_elements with type "%s").', $block->name, $field, $block->name );
        }
        $kinds = 'set_text' === $name ? array( 'text', 'html' ) : array( $role );
        if ( ! in_array( $spec['kind'] ?? '', $kinds, true ) ) {
            return sprintf( 'field "%s" holds %s, which %s does not write.', $field, (string) ( $spec['kind'] ?? 'other' ), $name );
        }
        if ( 'set_text' === $name ) {
            $value = $this->html_value( $op['value'] ?? null );
            return is_array( $value ) ? $value['error'] : array( $field => $value );
        }
        if ( 'set_link' === $name ) {
            return $this->link_changes( $op, $field, $fields );
        }
        $id = isset( $op['attachment_id'] ) && is_numeric( $op['attachment_id'] ) ? (int) $op['attachment_id'] : 0;
        if ( $id <= 0 ) {
            return 'set_image needs `attachment_id`, the ID of an image in the media library (wp_list_media, or upload one first).';
        }
        $media = ( $this->media )( $id );
        if ( null === $media ) {
            return sprintf( 'attachment %d is not an image in the media library that this account can use.', $id );
        }
        return array( $field => $media['url'] );
    }

    private function link_changes( array $op, string $field, array $fields ) {
        if ( ! isset( $op['url'] ) || ! is_string( $op['url'] ) ) {
            return 'set_link needs `url`, a string ("" removes the link).';
        }
        if ( isset( $op['nofollow'] ) ) {
            return sprintf( 'Divi has no nofollow switch on "%s".', $field );
        }
        $asked = trim( $op['url'] );
        $url   = '' === $asked ? '' : esc_url_raw( $asked, wp_allowed_protocols() );
        if ( '' !== $asked && '' === $url ) {
            return sprintf( '"%s" is not a URL WordPress accepts.', $asked );
        }
        $changes = array( $field => $url );
        if ( ! isset( $op['new_tab'] ) ) {
            return $changes;
        }
        $switch = preg_replace( '/\.linkUrl$/', '.linkTarget', $field );
        if ( $switch === $field || null === self::spec( $fields, (string) $switch ) ) {
            return sprintf( 'field "%s" has no new-tab setting in Divi.', $field );
        }
        $changes[ (string) $switch ] = (bool) $op['new_tab'] ? 'on' : 'off';
        return $changes;
    }

    private function field_changes( array $op, array $fields, string $type ) {
        if ( ! isset( $op['fields'] ) || ! is_array( $op['fields'] ) || array() === $op['fields'] ) {
            return 'set_fields needs `fields`, an object of field name to value.';
        }
        $changes  = array();
        $problems = array();
        foreach ( $op['fields'] as $field => $value ) {
            $field = (string) $field;
            $spec  = self::spec( $fields, $field );
            if ( null === $spec ) {
                $problems[] = sprintf( '%s elements have no field "%s"', $type, $field );
                continue;
            }
            $checked = $this->field_value( $spec, $value );
            if ( is_array( $checked ) ) {
                $problems[] = sprintf( 'field "%s" %s', $field, $checked['error'] );
                continue;
            }
            $changes[ $field ] = $checked;
        }
        if ( array() !== $problems ) {
            return implode( '; ', $problems ) . sprintf( ' (see wp_list_layout_elements with type "%s").', $type );
        }
        return $changes;
    }

    private function field_value( array $spec, $value ) {
        $kind = (string) ( $spec['kind'] ?? 'other' );
        if ( ! in_array( $kind, self::FIELD_KINDS, true ) ) {
            $hint = array(
                'link'  => '; use set_link',
                'image' => '; use set_image',
                'html'  => '; use set_text with this field',
            );
            return array( 'error' => sprintf( 'holds %s, which set_fields does not write%s', $kind, $hint[ $kind ] ?? '' ) );
        }
        if ( 'boolean' === $kind ) {
            return is_bool( $value ) ? ( $value ? 'on' : 'off' ) : array( 'error' => 'must be true or false' );
        }
        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            return array( 'error' => 'must be a string or a number' );
        }
        $value = (string) $value;
        if ( '' === $value && 'text' !== $kind ) {
            return null;
        }
        if ( 'select' === $kind ) {
            $options = isset( $spec['options'] ) && is_array( $spec['options'] ) ? array_map( 'strval', $spec['options'] ) : array();
            if ( array() !== $options && ! in_array( $value, $options, true ) ) {
                return array( 'error' => sprintf( 'must be one of: %s', implode( ', ', $options ) ) );
            }
            if ( array() === $options && 1 !== preg_match( '/^[\w-]+$/', $value ) ) {
                return array( 'error' => 'has no list of allowed values here, so it takes only a plain value of letters, digits, - and _' );
            }
            return $value;
        }
        if ( 'number' === $kind ) {
            return is_numeric( $value ) && is_finite( (float) $value ) ? $value : array( 'error' => 'must be a finite number' );
        }
        if ( 'color' === $kind ) {
            return 1 === preg_match( '/^(?:#[0-9a-fA-F]{3,8}|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\))$/', $value ) ? $value : array( 'error' => 'must be a colour such as #1a2b3c, or "" to clear it' );
        }
        if ( 'size' === $kind ) {
            return 1 === preg_match( '/^[\w.%#\s,()+-]+$/', $value ) ? $value : array( 'error' => 'must be a size such as 40px or 50%, or "" to clear it' );
        }
        $checked = $this->html_value( $value );
        return is_array( $checked ) ? array( 'error' => lcfirst( $checked['error'] ) ) : $checked;
    }

    private function html_value( $value ) {
        if ( ! is_string( $value ) ) {
            return array( 'error' => 'set_text needs `value`, a string.' );
        }
        if ( $this->unfiltered ) {
            return $value;
        }
        $clean = (string) ( $this->kses )( $value );
        if ( ! Shortcode_Editor::same_text( $clean, $value ) ) {
            return array( 'error' => 'Holds HTML this account cannot save (it may not post unfiltered HTML), so WordPress would cut part of it. Send it without scripts, styles, iframes or event attributes.' );
        }
        return $clean;
    }

    private static function spec( array $fields, string $name ): ?array {
        foreach ( $fields as $field ) {
            if ( $name === ( $field['name'] ?? '' ) ) {
                return $field;
            }
        }
        return null;
    }

    public static function value_path( string $field ): array {
        return self::breakpoint_path( $field, 'desktop', true );
    }

    private static function breakpoint_path( string $field, string $breakpoint, bool $value = false ): array {
        $parts = explode( '.', $field );
        $at    = array_search( 'innerContent', $parts, true );
        $at    = false === $at ? count( $parts ) - 1 : (int) $at;
        $head  = array_slice( $parts, 0, $at + 1 );
        $tail  = array_slice( $parts, $at + 1 );
        return $value ? array_merge( $head, array( $breakpoint, 'value' ), $tail ) : array_merge( $head, array( $breakpoint ) );
    }

    private static function moved( string $op, string $element, Divi5_Block $block, bool $changed ): array {
        return array(
            'op'      => $op,
            'element' => $element,
            'type'    => $block->is_text() ? Shortcode_Tree::TEXT : $block->name,
            'changed' => $changed,
            'block'   => $block,
        );
    }

    private static function refused( string $source, array $errors ): array {
        return array(
            'content'  => $source,
            'results'  => array(),
            'errors'   => $errors,
            'warnings' => array(),
            'parents'  => array(),
            'tree'     => null,
        );
    }
}
