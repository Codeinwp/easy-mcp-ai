<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Elementor_Editor {

    const OPS = array( 'set_text', 'set_link', 'set_image', 'set_fields', 'add_item', 'remove_item', 'move_item', 'insert', 'remove', 'move', 'duplicate' );

    const SET_OPS = array( 'set_text', 'set_link', 'set_image', 'set_fields' );

    const ITEM_OPS = array( 'add_item', 'remove_item', 'move_item' );

    const STRUCTURE_OPS = array( 'remove', 'move', 'duplicate' );

    const MAX_OPS = 50;

    const MAX_CREATED = 150;

    const TEXT_KINDS = array( 'text', 'html' );

    const FIELD_KINDS = array( 'text', 'html', 'code', 'select', 'number', 'boolean', 'color' );

    const CONTAINERS = array( 'section', 'column', 'container' );

    const DYNAMIC_KEY = '__dynamic__';
    const GLOBALS_KEY = '__globals__';

    const ITEM_ID = '_id';

    const SAVED_DEFAULTS = array( 'text', 'textarea' );

    private $fields_of;

    private $media;

    private $nested;

    private $unfiltered;

    private $containers;

    private $kses;

    private $refs = array();

    private $taken = array();

    private $created = 0;

    public function __construct( callable $fields_of, callable $media, callable $nested, bool $unfiltered, bool $containers, ?callable $kses = null ) {
        $this->fields_of  = $fields_of;
        $this->media      = $media;
        $this->nested     = $nested;
        $this->unfiltered = $unfiltered;
        $this->containers = $containers;
        $this->kses       = $kses ?? 'wp_kses_post';
    }

    public function plan( array $data, array $ops ): array {
        $errors = array();
        if ( array() === $ops ) {
            $errors[] = '`ops` is empty: give at least one operation.';
        }
        if ( count( $ops ) > self::MAX_OPS ) {
            $errors[] = sprintf( 'At most %d operations per call; this call has %d.', self::MAX_OPS, count( $ops ) );
        }
        $repeated = self::repeated_ids( $data );
        if ( array() !== $repeated ) {
            $errors[] = self::repeated_message( $repeated );
        }
        if ( array() !== $errors ) {
            return self::refused( $data, $errors );
        }

        $this->refs     = array();
        $this->taken    = Elementor_Tree::ids( $data );
        $this->created  = 0;
        $next           = $data;
        $results        = array();
        foreach ( array_values( $ops ) as $index => $op ) {
            $applied = is_array( $op ) ? $this->apply( $next, $op ) : 'must be an object.';
            if ( is_string( $applied ) ) {
                $errors[] = sprintf( 'Operation %d: %s', $index + 1, $applied );
                continue;
            }
            $next      = array_values( $applied['data'] );
            $results[] = $applied['result'];
        }
        if ( array() !== $errors ) {
            return self::refused( $data, $errors );
        }
        return array(
            'data'    => $next,
            'results' => $results,
            'errors'  => array(),
        );
    }

    public static function repeated_message( array $repeated ): string {
        return sprintf( 'This layout has more than one element with the id %s, so no element can be addressed safely. Open the page in Elementor and save it once, which gives every element its own id.', implode( ', ', $repeated ) );
    }

    public static function repeated_ids( array $data ): array {
        $seen     = array();
        $repeated = array();
        foreach ( Elementor_Tree::elements( $data ) as $element ) {
            $id = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
            if ( '' === $id ) {
                continue;
            }
            if ( isset( $seen[ $id ] ) ) {
                $repeated[ $id ] = true;
            }
            $seen[ $id ] = true;
        }
        return array_map( 'strval', array_keys( $repeated ) );
    }

    private static function refused( array $data, array $errors ): array {
        return array(
            'data'    => $data,
            'results' => array(),
            'errors'  => $errors,
        );
    }

    private function apply( array $data, array $op ) {
        $name = isset( $op['op'] ) && is_string( $op['op'] ) ? $op['op'] : '';
        if ( ! in_array( $name, self::OPS, true ) ) {
            return sprintf( 'unknown `op` "%s"; one of %s.', $name, implode( ', ', self::OPS ) );
        }
        if ( 'insert' === $name ) {
            return $this->insert( $data, $op );
        }
        $address = $this->resolve( $op, 'element' );
        if ( '' === $address ) {
            return '`element` is required: the element\'s address from wp_get_page_layout.';
        }
        $path = Elementor_Tree::path( $data, $address );
        if ( null === $path ) {
            return sprintf( 'no element "%s" in this layout. Read it again with wp_get_page_layout.', $address );
        }
        $element = Elementor_Tree::at( $data, $path );
        $type    = self::type_of( $element );
        $expect  = isset( $op['expect'] ) && is_string( $op['expect'] ) ? $op['expect'] : '';
        if ( '' !== $expect && $expect !== $type ) {
            return sprintf( 'element "%s" is a %s, not the expected %s.', $address, $type, $expect );
        }
        if ( in_array( $name, self::STRUCTURE_OPS, true ) ) {
            return $this->restructure( $name, $data, $op, $path, $element, $type, $address );
        }
        $refusal = self::element_refusal( $element, $type, $address );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $fields = ( $this->fields_of )( $type );
        if ( null === $fields ) {
            return sprintf( 'Elementor has no %s widget registered, so "%s" cannot be edited.', $type, $address );
        }
        if ( in_array( $name, self::ITEM_OPS, true ) ) {
            return $this->item_op( $name, $data, $op, $path, $element, array_values( $fields ), $type, $address );
        }
        return $this->set_op( $name, $data, $op, $path, $element, array_values( $fields ), $type, $address );
    }

    private function set_op( string $name, array $data, array $op, array $path, array $element, array $fields, string $type, string $address ) {
        $settings = $element['settings'];
        $in_item  = isset( $op['list'] ) || isset( $op['item'] );
        $target   = $settings;
        $specs    = $fields;
        $owner    = $type . ' widgets';
        $hint     = self::hint( $type );
        $where    = '"' . $address . '"';
        if ( $in_item ) {
            $rows = $this->rows( $op, $fields, $settings, $type, $address );
            if ( is_string( $rows ) ) {
                return $rows;
            }
            $item  = self::text_arg( $op, 'item' );
            $index = self::row_index( $rows['rows'], $item );
            if ( null === $index ) {
                return '' === $item
                    ? '`item` is required with `list`: the id of one item, from wp_get_page_layout.'
                    : sprintf( 'list "%s" of "%s" has no item "%s". Read the layout again with wp_get_page_layout.', $rows['spec']['name'], $address, $item );
            }
            $target = $rows['rows'][ $index ];
            $specs  = $rows['spec']['item_fields'] ?? array();
            $owner  = sprintf( 'items of list "%s"', $rows['spec']['name'] );
            $hint   = self::item_hint( $type, $rows['spec']['name'] );
            $where  = sprintf( 'item "%s" of list "%s" in "%s"', $item, $rows['spec']['name'], $address );
        }

        $changes = 'set_fields' === $name
            ? $this->field_changes( $op, $specs, $owner, $hint )
            : $this->single_change( $name, $op, $specs, $target, $in_item ? '' : $type, $owner, $hint );
        if ( is_string( $changes ) ) {
            return $changes;
        }
        $bound = self::bound_fields( $target, array_keys( $changes ) );
        if ( array() !== $bound ) {
            return sprintf( 'field %s of %s shows a dynamic tag or a global value set in Elementor, so a value written there would not appear. Remove it in Elementor first.', implode( ', ', $bound ), $where );
        }

        $before = array();
        $after  = array();
        foreach ( $changes as $field => $value ) {
            $before[ $field ] = $target[ $field ] ?? null;
            $after[ $field ]  = $value;
            $target[ $field ] = $value;
        }
        $result = array(
            'op'      => $name,
            'element' => $address,
            'type'    => $type,
            'field'   => implode( ', ', array_map( 'strval', array_keys( $changes ) ) ),
            'before'  => 'set_fields' === $name ? $before : reset( $before ),
            'after'   => 'set_fields' === $name ? $after : reset( $after ),
            'changed' => $before !== $after,
        );
        if ( $in_item ) {
            $result['list']                    = $rows['spec']['name'];
            $result['item']                    = $item;
            $rows['rows'][ $index ]            = $target;
            $settings[ $rows['spec']['name'] ] = $rows['rows'];
        } else {
            $settings = $target;
        }
        $element['settings'] = $settings;
        return array(
            'data'   => Elementor_Tree::replace( $data, $path, $element ),
            'result' => $result,
        );
    }

    private function item_op( string $name, array $data, array $op, array $path, array $element, array $fields, string $type, string $address ) {
        $rows = $this->rows( $op, $fields, $element['settings'], $type, $address );
        if ( is_string( $rows ) ) {
            return $rows;
        }
        if ( $this->is_nested( $element, $type ) ) {
            return sprintf( 'each item of "%s" (%s) has its own container of content, and Elementor\'s editor adds, removes and reorders the two together; do that in Elementor. An item\'s fields can be changed here with `list` and `item`.', $address, $type );
        }
        $list   = $rows['spec']['name'];
        $where  = sprintf( 'list "%s" of "%s"', $list, $address );
        $result = array(
            'op'      => $name,
            'element' => $address,
            'type'    => $type,
            'field'   => $list,
            'list'    => $list,
        );

        if ( 'add_item' === $name ) {
            $max = $rows['spec']['max_items'] ?? 0;
            if ( $max > 0 && count( $rows['rows'] ) >= $max ) {
                return sprintf( '%s already holds %d items, the most Elementor allows there.', $where, $max );
            }
            $at = $this->row_place( $op, $rows['rows'], '' );
            if ( is_string( $at ) ) {
                return $at;
            }
            $row = $this->new_row( $op, $rows, $type, $where );
            if ( is_string( $row ) ) {
                return $row;
            }
            array_splice( $rows['rows'], $at ?? count( $rows['rows'] ), 0, array( $row ) );
            $result += array(
                'item'    => (string) $row[ self::ITEM_ID ],
                'before'  => null,
                'after'   => $row,
                'changed' => true,
            );
        } else {
            $item  = self::text_arg( $op, 'item' );
            $index = self::row_index( $rows['rows'], $item );
            if ( null === $index ) {
                return '' === $item ? sprintf( '`item` is required for %s: the id of one item, from wp_get_page_layout.', $name ) : sprintf( '%s has no item "%s". Read the layout again with wp_get_page_layout.', $where, $item );
            }
            $row = $rows['rows'][ $index ];
            array_splice( $rows['rows'], $index, 1 );
            $result['item'] = $item;
            if ( 'remove_item' === $name ) {
                $min = $rows['spec']['min_items'] ?? 0;
                if ( count( $rows['rows'] ) < $min ) {
                    return sprintf( '%s must keep at least %d %s, as Elementor\'s editor does.', $where, $min, 1 === $min ? 'item' : 'items' );
                }
                $result += array(
                    'before'  => $row,
                    'after'   => null,
                    'changed' => true,
                );
            } else {
                $at = $this->row_place( $op, $rows['rows'], $item );
                if ( is_string( $at ) ) {
                    return $at;
                }
                if ( null === $at ) {
                    return 'move_item needs `index` (the item\'s new position, from 0) or `after` (the item it follows).';
                }
                array_splice( $rows['rows'], $at, 0, array( $row ) );
                $result += array(
                    'before'  => $index,
                    'after'   => $at,
                    'changed' => $index !== $at,
                );
            }
        }
        $element['settings'][ $list ] = $rows['rows'];
        return array(
            'data'   => Elementor_Tree::replace( $data, $path, $element ),
            'result' => $result,
        );
    }

    private function new_row( array $op, array $rows, string $type, string $where ) {
        $specs = $rows['spec']['item_fields'] ?? array();
        $copy  = self::text_arg( $op, 'copy_of' );
        $row   = array();
        if ( '' !== $copy ) {
            $index = self::row_index( $rows['rows'], $copy );
            if ( null === $index ) {
                return sprintf( '%s has no item "%s" to copy.', $where, $copy );
            }
            $row = $rows['rows'][ $index ];
        } else {
            foreach ( $specs as $spec ) {
                if ( in_array( $spec['control'], self::SAVED_DEFAULTS, true ) && isset( $spec['default'] ) && '' !== (string) $spec['default'] ) {
                    $row[ $spec['name'] ] = $spec['default'];
                }
            }
        }
        if ( isset( $op['fields'] ) ) {
            if ( ! is_array( $op['fields'] ) ) {
                return '`fields` must be an object of field name to value.';
            }
            $changes = $this->checked_fields( $op['fields'], $specs, sprintf( 'items of list "%s"', $rows['spec']['name'] ), self::item_hint( $type, $rows['spec']['name'] ), true );
            if ( is_string( $changes ) ) {
                return $changes;
            }
            $bound = self::bound_fields( $row, array_keys( $changes ) );
            if ( array() !== $bound ) {
                return sprintf( 'field %s of item "%s" in %s shows a dynamic tag or a global value set in Elementor, so a value written in the copy would not appear.', implode( ', ', $bound ), $copy, $where );
            }
            $row = array_merge( $row, $changes );
        }
        return array( self::ITEM_ID => Elementor_Tree::new_id( $this->taken ) ) + $row;
    }

    private function row_place( array $op, array $rows, string $moving ) {
        $after = self::text_arg( $op, 'after' );
        $index = self::index_arg( $op, count( $rows ) );
        if ( '' !== $after && null !== $index ) {
            return 'give `index` or `after`, not both.';
        }
        if ( '' === $after ) {
            return $index;
        }
        if ( $after === $moving ) {
            return 'an item cannot be placed after itself.';
        }
        $position = self::row_index( $rows, $after );
        return null === $position ? sprintf( 'no item "%s" in this list to place it after.', $after ) : $position + 1;
    }

    private function rows( array $op, array $fields, array $settings, string $type, string $address ) {
        $name = self::text_arg( $op, 'list' );
        if ( '' === $name ) {
            return sprintf( '`list` is required: the name of a list of items in %s widgets %s.', $type, self::hint( $type ) );
        }
        $spec = self::field_spec( $fields, $name );
        if ( null === $spec ) {
            return sprintf( '%s widgets have no list "%s" %s.', $type, $name, self::hint( $type ) );
        }
        if ( 'list' !== $spec['kind'] || ! isset( $spec['item_fields'] ) ) {
            return sprintf( 'field "%s" of %s widgets is not a list of items.', $name, $type );
        }
        $where  = sprintf( 'list "%s" of "%s"', $name, $address );
        $stored = $settings[ $name ] ?? null;
        if ( ! is_array( $stored ) ) {
            return sprintf( '%s is not stored with the page: it shows the widget\'s default items, which Elementor stores once the page is saved in its editor. Open the page in Elementor and save it once, then read it again.', $where );
        }
        $rows = array_values( $stored );
        $ids  = array();
        foreach ( $rows as $row ) {
            $id = is_array( $row ) && isset( $row[ self::ITEM_ID ] ) && is_scalar( $row[ self::ITEM_ID ] ) ? (string) $row[ self::ITEM_ID ] : '';
            if ( '' === $id ) {
                return sprintf( '%s has an item without an id, so no item can be addressed safely. Open the page in Elementor and save it once, which gives every item an id.', $where );
            }
            if ( isset( $ids[ $id ] ) ) {
                return sprintf( '%s has two items with the id "%s", so no item can be addressed safely. Delete one of them in Elementor and add it again.', $where, $id );
            }
            $ids[ $id ] = true;
        }
        return array(
            'spec' => $spec,
            'rows' => $rows,
        );
    }

    private static function row_index( array $rows, string $id ): ?int {
        foreach ( $rows as $index => $row ) {
            if ( '' !== $id && (string) $row[ self::ITEM_ID ] === $id ) {
                return $index;
            }
        }
        return null;
    }

    private function insert( array $data, array $op ) {
        $place = $this->place( $data, $op );
        if ( is_string( $place ) ) {
            return $place;
        }
        $created = array();
        $built   = $this->build( $op, $place['parent'], $created );
        if ( is_string( $built ) ) {
            return $built;
        }
        $children = Elementor_Tree::children( $data, $place['path'] );
        array_splice( $children, $place['index'], 0, array( $built ) );
        $result = array(
            'op'      => 'insert',
            'element' => (string) $built['id'],
            'type'    => self::type_of( $built ),
            'field'   => '',
            'before'  => null,
            'after'   => self::position( $place['parent'], $place['index'] ),
            'changed' => true,
            'created' => $created,
        );
        $ref = self::text_arg( $op, 'ref' );
        if ( '' !== $ref ) {
            $result['ref'] = $ref;
        }
        return array(
            'data'   => Elementor_Tree::with_children( $data, $place['path'], $children ),
            'result' => $result,
        );
    }

    private function build( $spec, ?array $parent, array &$created ) {
        if ( ! is_array( $spec ) ) {
            return 'each entry of `children` must be an object.';
        }
        $type = self::text_arg( $spec, 'element_type' );
        if ( '' === $type ) {
            return '`element_type` is required: a widget type from wp_list_layout_elements, or "container".';
        }
        if ( in_array( $type, array( 'section', 'column' ), true ) ) {
            return 'sections and columns are not inserted here: Elementor\'s editor sizes a section\'s columns from its own structure presets. Insert a container, or add widgets to an existing column.';
        }
        if ( 0 === strpos( $type, 'e-' ) ) {
            return sprintf( '"%s" is an Elementor V4 element; Elementor\'s own manage-elements ability (wp_ability_elementor_manage_elements) adds those.', $type );
        }
        $is_container = 'container' === $type;
        if ( $is_container && ! $this->containers ) {
            return 'Elementor\'s container experiment (Flexbox Container) is off on this site, so a container would not show. Turn it on under Elementor › Settings › Features, or add widgets to an existing column.';
        }
        $fields = $is_container ? array() : ( $this->fields_of )( $type );
        if ( null === $fields ) {
            return sprintf( 'Elementor has no %s widget registered.', $type );
        }
        if ( ! $is_container && ( $this->nested )( $type ) ) {
            return sprintf( 'a %s widget keeps a container of content for each of its items, which Elementor\'s editor creates with it; add it in Elementor, then edit its items here.', $type );
        }
        $misplaced = self::misplaced( $is_container ? 'container' : 'widget', $parent );
        if ( null !== $misplaced ) {
            return sprintf( 'a %s cannot go %s.', $type, $misplaced );
        }
        if ( $this->created >= self::MAX_CREATED ) {
            return self::too_many();
        }
        ++$this->created;

        $settings = array();
        if ( isset( $spec['fields'] ) ) {
            if ( ! is_array( $spec['fields'] ) ) {
                return '`fields` must be an object of field name to value.';
            }
            if ( $is_container && array() !== $spec['fields'] ) {
                return 'a container\'s settings are not set here; insert it without `fields` and set its layout in Elementor.';
            }
            $settings = $this->checked_fields( $spec['fields'], array_values( $fields ), $type . ' widgets', self::hint( $type ), true );
            if ( is_string( $settings ) ) {
                return $settings;
            }
        }
        $ref = self::text_arg( $spec, 'ref' );
        if ( '' !== $ref && ( isset( $this->refs[ $ref ] ) || isset( $this->taken[ $ref ] ) ) ) {
            return sprintf( '`ref` "%s" is already used in this layout or this call; give each inserted element its own.', $ref );
        }

        $id        = Elementor_Tree::new_id( $this->taken );
        $created[] = $id;
        $element   = $is_container
            ? array( 'id' => $id, 'elType' => 'container', 'settings' => array(), 'elements' => array(), 'isInner' => null !== $parent )
            : array( 'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'settings' => $settings, 'elements' => array() );
        if ( '' !== $ref ) {
            $this->refs[ $ref ] = $id;
        }
        if ( ! isset( $spec['children'] ) ) {
            return $element;
        }
        if ( ! $is_container ) {
            return sprintf( 'a %s widget holds no elements, so it takes no `children`.', $type );
        }
        if ( ! is_array( $spec['children'] ) ) {
            return '`children` must be a list of elements to insert.';
        }
        $problems = array();
        foreach ( array_values( $spec['children'] ) as $child ) {
            $built = $this->build( $child, $element, $created );
            if ( is_string( $built ) ) {
                $problems[] = $built;
                continue;
            }
            $element['elements'][] = $built;
        }
        return array() === $problems ? $element : implode( '; ', array_unique( $problems ) );
    }

    private function restructure( string $name, array $data, array $op, array $path, array $element, string $type, string $address ) {
        if ( 0 === strpos( $type, 'e-' ) || self::has_transformables( $element['settings'] ) ) {
            return sprintf( 'element "%s" is an Elementor V4 element; Elementor\'s own manage-elements ability (wp_ability_elementor_manage_elements) changes those.', $address );
        }
        if ( 'column' === $type ) {
            return sprintf( 'element "%s" is a column: Elementor\'s editor resizes a section\'s other columns when one is added or removed, which this tool does not reproduce. Change columns in Elementor, or move the widgets inside them.', $address );
        }
        $parent_path = array_slice( $path, 0, -1 );
        $parent      = array() === $parent_path ? null : Elementor_Tree::at( $data, $parent_path );
        if ( null !== $parent && 'widget' === ( $parent['elType'] ?? '' ) ) {
            return sprintf( 'element "%s" is the content of one item of "%s" (%s), matched to it by position; Elementor\'s editor moves, copies and removes the two together. Do that in Elementor.', $address, (string) $parent['id'], self::type_of( $parent ) );
        }
        $siblings    = Elementor_Tree::children( $data, $parent_path );
        $index       = (int) array_search( end( $path ), array_keys( array() === $parent_path ? $data : $parent['elements'] ), true );
        $before      = self::position( $parent, $index );
        $result      = array(
            'op'      => $name,
            'element' => $address,
            'type'    => $type,
            'field'   => '',
        );

        $kind = in_array( $type, array( 'section', 'container' ), true ) ? $type : 'widget';
        if ( 'section' === $kind && ! empty( $element['isInner'] ) && 'remove' !== $name ) {
            return sprintf( 'element "%s" is an inner section; %s it in Elementor.', $address, 'move' === $name ? 'move' : 'copy' );
        }
        if ( 'duplicate' === $name ) {
            return $this->duplicate( $data, $op, $element, $kind, $result, self::position( $parent, $index + 1 ) + array( 'path' => $parent_path ) );
        }

        array_splice( $siblings, $index, 1 );
        $without = Elementor_Tree::with_children( $data, $parent_path, $siblings );
        if ( 'remove' === $name ) {
            if ( array() === $without ) {
                return sprintf( 'element "%s" is the last one at the top of the layout; with no elements Elementor shows the post content instead. Insert its replacement first.', $address );
            }
            return array(
                'data'   => $without,
                'result' => $result + array(
                    'before'  => $before,
                    'after'   => null,
                    'changed' => true,
                ),
            );
        }

        foreach ( array( 'parent', 'after' ) as $key ) {
            $target = $this->resolve( $op, $key );
            if ( '' !== $target && ( $target === $address || null !== Elementor_Tree::path( $element['elements'], $target ) ) ) {
                return sprintf( 'element "%s" cannot be moved inside itself.', $address );
            }
        }
        if ( '' === $this->resolve( $op, 'parent' ) && '' === $this->resolve( $op, 'after' ) && ! isset( $op['index'] ) ) {
            return 'move needs `parent` (with an optional `index`), `after`, or `index` alone for the top level.';
        }
        $place = $this->place( $without, $op );
        if ( is_string( $place ) ) {
            return $place;
        }
        $misplaced = self::misplaced( $kind, $place['parent'] );
        if ( null !== $misplaced ) {
            return sprintf( 'a %s cannot go %s.', $type, $misplaced );
        }
        if ( 'container' === $kind ) {
            $element['isInner'] = null !== $place['parent'];
        }
        $children = Elementor_Tree::children( $without, $place['path'] );
        array_splice( $children, $place['index'], 0, array( $element ) );
        $after = self::position( $place['parent'], $place['index'] );
        return array(
            'data'   => Elementor_Tree::with_children( $without, $place['path'], $children ),
            'result' => $result + array(
                'before'  => $before,
                'after'   => $after,
                'changed' => $before !== $after,
            ),
        );
    }

    private function duplicate( array $data, array $op, array $element, string $kind, array $result, array $next ) {
        $placed = '' !== $this->resolve( $op, 'parent' ) || '' !== $this->resolve( $op, 'after' ) || isset( $op['index'] );
        $place  = $placed ? $this->place( $data, $op ) : array(
            'path'   => $next['path'],
            'parent' => array() === $next['path'] ? null : Elementor_Tree::at( $data, $next['path'] ),
            'index'  => $next['index'],
        );
        if ( is_string( $place ) ) {
            return $place;
        }
        $misplaced = self::misplaced( $kind, $place['parent'] );
        if ( null !== $misplaced ) {
            return sprintf( 'a %s cannot go %s.', $result['type'], $misplaced );
        }
        $size = count( Elementor_Tree::elements( array( $element ) ) );
        if ( $this->created + $size > self::MAX_CREATED ) {
            return self::too_many();
        }
        $ref = self::text_arg( $op, 'ref' );
        if ( '' !== $ref && ( isset( $this->refs[ $ref ] ) || isset( $this->taken[ $ref ] ) ) ) {
            return sprintf( '`ref` "%s" is already used in this layout or this call; give each new element its own.', $ref );
        }
        $this->created += $size;
        $created        = array();
        $copy           = $this->copied( $element, $created );
        if ( 'container' === $kind ) {
            $copy['isInner'] = null !== $place['parent'];
        }
        if ( '' !== $ref ) {
            $this->refs[ $ref ] = (string) $copy['id'];
            $result['ref']      = $ref;
        }
        $children = Elementor_Tree::children( $data, $place['path'] );
        array_splice( $children, $place['index'], 0, array( $copy ) );
        return array(
            'data'   => Elementor_Tree::with_children( $data, $place['path'], $children ),
            'result' => $result + array(
                'before'  => null,
                'after'   => self::position( $place['parent'], $place['index'] ),
                'changed' => true,
                'copy'    => (string) $copy['id'],
                'created' => $created,
            ),
        );
    }

    private function copied( array $element, array &$created ): array {
        $element['id'] = Elementor_Tree::new_id( $this->taken );
        $created[]     = $element['id'];
        $children      = array();
        foreach ( isset( $element['elements'] ) && is_array( $element['elements'] ) ? $element['elements'] : array() as $child ) {
            $children[] = is_array( $child ) ? $this->copied( $child, $created ) : $child;
        }
        $element['elements'] = $children;
        return $element;
    }

    private function place( array $data, array $op ) {
        $parent_id = $this->resolve( $op, 'parent' );
        $after_id  = $this->resolve( $op, 'after' );
        if ( '' !== $parent_id && '' !== $after_id ) {
            return 'give `parent` (with an optional `index`) or `after`, not both.';
        }
        if ( '' !== $after_id ) {
            if ( isset( $op['index'] ) ) {
                return 'give `after` or `index`, not both.';
            }
            $after = Elementor_Tree::path( $data, $after_id );
            if ( null === $after ) {
                return sprintf( 'no element "%s" in this layout to place it after.', $after_id );
            }
            $path   = array_slice( $after, 0, -1 );
            $parent = array() === $path ? null : Elementor_Tree::at( $data, $path );
            $keys   = array_keys( null === $parent ? $data : $parent['elements'] );
            return array(
                'path'   => $path,
                'parent' => $parent,
                'index'  => (int) array_search( end( $after ), $keys, true ) + 1,
            );
        }
        $path = array();
        if ( '' !== $parent_id ) {
            $path = Elementor_Tree::path( $data, $parent_id );
            if ( null === $path ) {
                return sprintf( 'no element "%s" in this layout to place it in.', $parent_id );
            }
        }
        $count = count( Elementor_Tree::children( $data, $path ) );
        $index = self::index_arg( $op, $count );
        if ( is_string( $index ) ) {
            return $index;
        }
        return array(
            'path'   => $path,
            'parent' => array() === $path ? null : Elementor_Tree::at( $data, $path ),
            'index'  => $index ?? $count,
        );
    }

    private function is_nested( array $element, string $type ): bool {
        return ( $this->nested )( $type ) || ( isset( $element['elements'] ) && is_array( $element['elements'] ) && array() !== $element['elements'] );
    }

    private static function too_many(): string {
        return sprintf( 'At most %d elements can be created in one call, inserted or copied, nested ones included.', self::MAX_CREATED );
    }

    private static function misplaced( string $kind, ?array $parent ): ?string {
        if ( null === $parent ) {
            return 'widget' === $kind ? 'at the top level of the layout; put it in a container or a column' : null;
        }
        $type = self::type_of( $parent );
        $id   = isset( $parent['id'] ) && is_scalar( $parent['id'] ) ? (string) $parent['id'] : '';
        if ( 0 === strpos( $type, 'e-' ) || self::has_transformables( $parent['settings'] ?? array() ) ) {
            return sprintf( 'in "%s", an Elementor V4 element', $id );
        }
        $accepts = array(
            'container' => array( 'container', 'widget' ),
            'column'    => array( 'widget' ),
        );
        if ( in_array( $kind, $accepts[ $type ] ?? array(), true ) ) {
            return null;
        }
        if ( 'section' === $kind ) {
            return 'anywhere but the top level of the layout';
        }
        return sprintf( 'in "%s", a %s', $id, $type );
    }

    private static function position( ?array $parent, int $index ): array {
        return array(
            'parent' => null === $parent ? null : (string) $parent['id'],
            'index'  => $index,
        );
    }

    private function resolve( array $op, string $key ): string {
        $value = self::text_arg( $op, $key );
        return $this->refs[ $value ] ?? $value;
    }

    private static function text_arg( array $op, string $key ): string {
        return isset( $op[ $key ] ) && is_scalar( $op[ $key ] ) && ! is_bool( $op[ $key ] ) ? trim( (string) $op[ $key ] ) : '';
    }

    private static function index_arg( array $op, int $max ) {
        if ( ! isset( $op['index'] ) ) {
            return null;
        }
        $value = $op['index'];
        if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
            if ( (int) $value >= 0 && (int) $value <= $max ) {
                return (int) $value;
            }
        }
        return sprintf( '`index` must be a whole number from 0 to %d.', $max );
    }

    private static function element_refusal( array $element, string $type, string $address ): ?string {
        $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
        if ( 0 === strpos( $type, 'e-' ) || self::has_transformables( $settings ) ) {
            return sprintf( 'element "%s" is an Elementor V4 element; Elementor\'s own manage-elements ability (wp_ability_elementor_manage_elements) edits those.', $address );
        }
        if ( 'widget' !== ( $element['elType'] ?? '' ) || in_array( $type, self::CONTAINERS, true ) ) {
            return sprintf( 'element "%s" is a %s; only widgets have fields these operations write.', $address, $type );
        }
        return null;
    }

    private function single_change( string $name, array $op, array $fields, array $settings, string $roles_type, string $owner, string $hint ) {
        $writes = array(
            'set_text'  => array( 'text', self::TEXT_KINDS ),
            'set_link'  => array( 'link', array( 'link' ) ),
            'set_image' => array( 'image', array( 'image' ) ),
        );
        list( $role, $kinds ) = $writes[ $name ];

        $field = isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field']
            ? $op['field']
            : self::default_field( $roles_type, $role, $kinds, $fields );
        if ( '' === $field ) {
            return sprintf( '%s have no %s field; give `field` %s.', $owner, $role, $hint );
        }
        $spec = self::field_spec( $fields, $field );
        if ( null === $spec ) {
            return sprintf( '%s have no content field "%s" %s.', $owner, $field, $hint );
        }
        if ( ! in_array( $spec['kind'], $kinds, true ) ) {
            return sprintf( 'field "%s" holds %s, which %s does not write%s.', $field, $spec['kind'], $name, $this->writer_hint( $spec['kind'], $field ) );
        }

        if ( 'set_text' === $name ) {
            if ( ! isset( $op['value'] ) || ! is_string( $op['value'] ) ) {
                return 'set_text needs `value`, a string.';
            }
            $value = $this->html_value( $op['value'], $spec['kind'] );
            return is_array( $value ) ? $value['error'] : array( $field => $value );
        }
        if ( 'set_link' === $name ) {
            return $this->link_value( $op, $settings[ $field ] ?? null, $field );
        }
        return $this->image_value( $op, $settings[ $field ] ?? null, $field );
    }

    private function writer_hint( string $kind, string $field ): string {
        if ( 'code' === $kind && ! $this->unfiltered ) {
            return '; set_fields writes it, but only for an account that may post unfiltered HTML, and this one may not';
        }
        $writers = array(
            'text'  => 'set_text',
            'html'  => 'set_text',
            'link'  => 'set_link',
            'image' => 'set_image',
        );
        if ( isset( $writers[ $kind ] ) ) {
            return '; use ' . $writers[ $kind ];
        }
        return in_array( $kind, self::FIELD_KINDS, true ) ? sprintf( '; use set_fields with fields: { "%s": … }', $field ) : '';
    }

    private function field_changes( array $op, array $fields, string $owner, string $hint ) {
        if ( ! isset( $op['fields'] ) || ! is_array( $op['fields'] ) || array() === $op['fields'] ) {
            return 'set_fields needs `fields`, an object of field name to value.';
        }
        return $this->checked_fields( $op['fields'], $fields, $owner, $hint, false );
    }

    private function checked_fields( array $values, array $fields, string $owner, string $hint, bool $creating ) {
        $changes  = array();
        $problems = array();
        foreach ( $values as $field => $value ) {
            $field = (string) $field;
            $spec  = self::field_spec( $fields, $field );
            if ( null === $spec ) {
                $problems[] = sprintf( '%s have no content field "%s"', $owner, $field );
                continue;
            }
            if ( $creating && in_array( $spec['kind'], array( 'link', 'image' ), true ) ) {
                $checked = is_array( $value )
                    ? ( 'link' === $spec['kind'] ? $this->link_value( $value, null, $field ) : $this->image_value( $value, null, $field ) )
                    : sprintf( 'takes an object: %s', 'link' === $spec['kind'] ? '{ "url": "…", "new_tab"?: true, "nofollow"?: true }' : '{ "attachment_id": 123 }' );
                if ( is_string( $checked ) ) {
                    $problems[] = sprintf( 'field "%s" %s', $field, lcfirst( rtrim( $checked, '.' ) ) );
                    continue;
                }
                $changes[ $field ] = $checked[ $field ];
                continue;
            }
            $checked = $this->field_value( $spec, $value );
            if ( is_array( $checked ) && isset( $checked['error'] ) ) {
                $problems[] = sprintf( 'field "%s" %s', $field, $checked['error'] );
                continue;
            }
            $changes[ $field ] = $checked;
        }
        if ( array() !== $problems ) {
            return implode( '; ', $problems ) . ' ' . $hint . '.';
        }
        return $changes;
    }

    private static function hint( string $type ): string {
        return sprintf( '(see wp_list_layout_elements with type "%s")', $type );
    }

    private static function item_hint( string $type, string $list ): string {
        return sprintf( '(see `item_fields` of "%s" in wp_list_layout_elements with type "%s")', $list, $type );
    }

    private function field_value( array $spec, $value ) {
        $kind = $spec['kind'];
        if ( ! in_array( $kind, self::FIELD_KINDS, true ) ) {
            $hint = array(
                'link'  => '; use set_link',
                'image' => '; use set_image',
                'list'  => '; use add_item, remove_item and move_item, or `list` and `item` on the set operations',
            );
            return array( 'error' => sprintf( 'holds %s, which set_fields does not write%s', $kind, $hint[ $kind ] ?? '' ) );
        }
        if ( 'select' === $kind ) {
            $options = $spec['options'] ?? array();
            if ( ! empty( $spec['multiple'] ) ) {
                return self::choices_value( $value, $options );
            }
            if ( ! self::is_option( $value, $options ) ) {
                return array( 'error' => sprintf( 'must be one of: %s', implode( ', ', array_map( array( __CLASS__, 'quoted' ), $options ) ) ) );
            }
            return (string) $value;
        }
        if ( 'number' === $kind ) {
            $number = is_numeric( $value ) && ! is_bool( $value ) ? $value + 0 : null;
            return is_int( $number ) || ( is_float( $number ) && is_finite( $number ) ) ? $number : array( 'error' => 'must be a finite number' );
        }
        if ( 'boolean' === $kind ) {
            if ( ! is_bool( $value ) ) {
                return array( 'error' => 'must be true or false' );
            }
            return $value ? ( $spec['on_value'] ?? 'yes' ) : '';
        }
        if ( 'color' === $kind ) {
            $valid = is_string( $value ) && 1 === preg_match( '/^(?:#[0-9a-fA-F]{3,8}|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\)|)$/', $value );
            return $valid ? $value : array( 'error' => 'must be a colour such as #1a2b3c, or "" to clear it' );
        }
        if ( ! is_string( $value ) ) {
            return array( 'error' => 'must be a string' );
        }
        $checked = $this->html_value( $value, $kind );
        return is_array( $checked ) ? array( 'error' => lcfirst( $checked['error'] ) ) : $checked;
    }

    private static function choices_value( $value, array $options ) {
        $choices = is_array( $value ) ? $value : array( $value );
        $valid   = array_values( $choices ) === $choices;
        foreach ( $choices as $choice ) {
            $valid = $valid && self::is_option( $choice, $options );
        }
        if ( ! $valid ) {
            $each = array() === $options ? '' : ', each one of: ' . implode( ', ', array_map( array( __CLASS__, 'quoted' ), $options ) );
            return array( 'error' => 'takes a list of options' . $each );
        }
        return array_values( array_unique( array_map( 'strval', $choices ) ) );
    }

    private static function is_option( $value, array $options ): bool {
        return is_scalar( $value ) && ! is_bool( $value ) && ( array() === $options || in_array( (string) $value, $options, true ) );
    }

    private function html_value( string $value, string $kind ) {
        if ( $this->unfiltered ) {
            return $value;
        }
        if ( 'code' === $kind ) {
            return array( 'error' => 'Holds code (HTML or script), which only accounts that may post unfiltered HTML can change.' );
        }
        $clean = (string) ( $this->kses )( $value );
        if ( ! self::same_text( $clean, $value ) ) {
            return array( 'error' => 'Holds HTML this account cannot save (it may not post unfiltered HTML), so WordPress would cut part of it. Send it without scripts, styles, iframes or event attributes.' );
        }
        return $clean;
    }

    private static function same_text( string $clean, string $original ): bool {
        return html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( $original, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    private function link_value( array $op, $current, string $field ) {
        if ( ! isset( $op['url'] ) || ! is_string( $op['url'] ) ) {
            return 'set_link needs `url`, a string ("" removes the link).';
        }
        $asked = trim( $op['url'] );
        $url   = '' === $asked ? '' : esc_url_raw( $asked, wp_allowed_protocols() );
        if ( '' !== $asked && '' === $url ) {
            return sprintf( '"%s" is not a URL WordPress accepts.', $asked );
        }
        $link        = is_array( $current ) ? $current : array();
        $link['url'] = $url;
        foreach ( array( 'new_tab' => 'is_external', 'nofollow' => 'nofollow' ) as $arg => $key ) {
            if ( isset( $op[ $arg ] ) ) {
                $link[ $key ] = $op[ $arg ] ? 'on' : '';
            } elseif ( ! isset( $link[ $key ] ) ) {
                $link[ $key ] = '';
            }
        }
        return array( $field => $link );
    }

    private function image_value( array $op, $current, string $field ) {
        $id = isset( $op['attachment_id'] ) && is_numeric( $op['attachment_id'] ) ? (int) $op['attachment_id'] : 0;
        if ( $id <= 0 ) {
            return 'set_image needs `attachment_id`, the ID of an image in the media library (wp_list_media, or upload one first).';
        }
        $media = ( $this->media )( $id );
        if ( null === $media ) {
            return sprintf( 'attachment %d is not an image in the media library that this account can use.', $id );
        }
        $image = is_array( $current ) ? $current : array();
        unset( $image['alt'] );
        $image['id']     = $media['id'];
        $image['url']    = $media['url'];
        $image['source'] = 'library';
        return array( $field => $image );
    }

    private static function bound_fields( array $settings, array $names ): array {
        $bound = array();
        foreach ( $names as $name ) {
            foreach ( array( self::DYNAMIC_KEY, self::GLOBALS_KEY ) as $key ) {
                if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) && ! empty( $settings[ $key ][ $name ] ) ) {
                    $bound[] = '"' . $name . '"';
                    break;
                }
            }
        }
        return $bound;
    }

    private static function default_field( string $type, string $role, array $kinds, array $fields ): string {
        $roles = Elementor_Adapter::ROLES[ $type ] ?? array();
        if ( isset( $roles[ $role ] ) ) {
            return $roles[ $role ];
        }
        foreach ( $fields as $spec ) {
            if ( in_array( $spec['kind'], $kinds, true ) ) {
                return $spec['name'];
            }
        }
        return '';
    }

    private static function field_spec( array $fields, string $name ): ?array {
        foreach ( $fields as $spec ) {
            if ( $spec['name'] === $name ) {
                return $spec;
            }
        }
        return null;
    }

    private static function type_of( array $element ): string {
        $el_type = isset( $element['elType'] ) && is_scalar( $element['elType'] ) ? (string) $element['elType'] : '';
        return 'widget' === $el_type && isset( $element['widgetType'] ) && is_scalar( $element['widgetType'] ) ? (string) $element['widgetType'] : $el_type;
    }

    private static function has_transformables( array $settings ): bool {
        foreach ( $settings as $value ) {
            if ( is_array( $value ) && array_key_exists( '$$type', $value ) ) {
                return true;
            }
        }
        return false;
    }

    private static function quoted( string $value ): string {
        return '"' . $value . '"';
    }
}
