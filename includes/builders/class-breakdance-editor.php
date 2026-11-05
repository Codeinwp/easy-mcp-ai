<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Breakdance_Editor {

    const OPS = array( 'set_text', 'set_link', 'set_image', 'set_fields' );

    const MAX_OPS = 50;

    const TEXT_KINDS = array( 'text', 'html' );

    const FIELD_KINDS = array( 'text', 'html', 'select', 'number', 'boolean', 'color' );

    const DYNAMIC_PATTERN = '/\[\s*breakdance_dynamic/i';

    const DYNAMIC_META_SUFFIX = '_dynamic_meta';

    private $fields_of;

    private $media;

    private $unfiltered;

    private $conditions;

    private $kses;

    public function __construct( callable $fields_of, callable $media, bool $unfiltered, ?callable $kses = null, bool $conditions = true ) {
        $this->fields_of  = $fields_of;
        $this->media      = $media;
        $this->unfiltered = $unfiltered;
        $this->kses       = $kses ?? 'wp_kses_post';
        $this->conditions = $conditions;
    }

    public function plan( array $tree, array $ops ): array {
        $errors = array();
        if ( array() === $ops ) {
            $errors[] = '`ops` is empty: give at least one operation.';
        }
        if ( count( $ops ) > self::MAX_OPS ) {
            $errors[] = sprintf( 'At most %d operations per call; this call has %d.', self::MAX_OPS, count( $ops ) );
        }
        if ( array() !== $errors ) {
            return self::refused( $tree, $errors );
        }
        $next    = $tree;
        $results = array();
        foreach ( array_values( $ops ) as $index => $op ) {
            $applied = is_array( $op ) ? $this->apply( $next, $op ) : 'must be an object.';
            if ( is_string( $applied ) ) {
                $errors[] = sprintf( 'Operation %d: %s', $index + 1, $applied );
                continue;
            }
            $next      = $applied['tree'];
            $results[] = $applied['result'];
        }
        if ( array() !== $errors ) {
            return self::refused( $tree, $errors );
        }
        return array(
            'tree'    => $next,
            'results' => $results,
            'errors'  => array(),
        );
    }

    private function apply( array $tree, array $op ) {
        $name = isset( $op['op'] ) && is_string( $op['op'] ) ? $op['op'] : '';
        if ( ! in_array( $name, self::OPS, true ) ) {
            return sprintf( '`op` must be one of %s.', implode( ', ', self::OPS ) );
        }
        $address = isset( $op['element'] ) && is_scalar( $op['element'] ) ? trim( (string) $op['element'] ) : '';
        $path    = '' === $address ? null : self::find( isset( $tree['root'] ) && is_array( $tree['root'] ) ? $tree['root'] : array(), $address );
        if ( null === $path ) {
            return sprintf( 'no element "%s" in this layout; use an address from wp_get_page_layout.', $address );
        }
        $node = self::node_at( $tree, $path );
        $type = (string) ( $node['data']['type'] ?? '' );
        if ( isset( $op['expect'] ) && is_scalar( $op['expect'] ) && '' !== (string) $op['expect'] && (string) $op['expect'] !== $type ) {
            return sprintf( 'element %s is a %s, not the %s the call expects.', $address, $type, (string) $op['expect'] );
        }
        $fields = ( $this->fields_of )( $type );
        if ( null === $fields ) {
            return sprintf( 'element %s is a %s, a type Breakdance does not register here, so its fields are unknown.', $address, $type );
        }
        foreach ( $fields as $field ) {
            if ( 'code' === $field['kind'] ) {
                return sprintf( 'element %s is a %s, which runs code (its field %s); it is never edited here.', $address, $type, $field['name'] );
            }
        }
        $properties = isset( $node['data']['properties'] ) && is_array( $node['data']['properties'] ) ? $node['data']['properties'] : array();
        $writes     = $this->writes( $name, $op, $fields, $properties, $address );
        if ( is_string( $writes ) ) {
            return $writes;
        }
        $before = array();
        $after  = array();
        foreach ( $writes as $field_name => $value ) {
            $before[ $field_name ] = self::value_at( $properties, $field_name );
            $properties            = self::set( $properties, $field_name, $value );
            $after[ $field_name ]  = $value;
        }
        $node['data']['properties'] = $properties;
        $single                     = 1 === count( $writes ) && 'set_fields' !== $name;
        $field                      = (string) array_key_first( $writes );
        return array(
            'tree'   => self::put( $tree, $path, $node ),
            'result' => array(
                'op'      => $name,
                'element' => $address,
                'type'    => $type,
                'field'   => $single ? $field : implode( ', ', array_keys( $writes ) ),
                'before'  => $single ? $before[ $field ] : $before,
                'after'   => $single ? $after[ $field ] : $after,
                'changed' => $before !== $after,
            ),
        );
    }

    private function writes( string $name, array $op, array $fields, array $properties, string $address ) {
        switch ( $name ) {
            case 'set_text':
                $field = $this->field( $op, $fields, self::TEXT_KINDS, $address, 'text' );
                if ( is_string( $field ) ) {
                    return $field;
                }
                if ( ! isset( $op['value'] ) || ! is_string( $op['value'] ) ) {
                    return 'set_text needs `value`, a string.';
                }
                $problem = $this->field_problem( $field, $properties, $op['value'] );
                return null === $problem ? array( $field['name'] => $op['value'] ) : $problem;

            case 'set_link':
                return $this->link_writes( $op, $fields, $properties, $address );

            case 'set_image':
                $field = $this->field( $op, $fields, array( 'image' ), $address, 'image' );
                if ( is_string( $field ) ) {
                    return $field;
                }
                $id    = isset( $op['attachment_id'] ) && is_numeric( $op['attachment_id'] ) ? (int) $op['attachment_id'] : 0;
                $media = $id > 0 ? ( $this->media )( $id ) : null;
                if ( null === $media ) {
                    return sprintf( 'set_image needs `attachment_id`, an image in the media library you can use; %s is not one.', isset( $op['attachment_id'] ) && is_scalar( $op['attachment_id'] ) ? (string) $op['attachment_id'] : 'nothing was given' );
                }
                $problem = $this->media_problem( $media ) ?? $this->field_problem( $field, $properties, null );
                return null === $problem ? array( $field['name'] => $media ) : $problem;

            default:
                return $this->field_writes( $op, $fields, $properties );
        }
    }

    private function link_writes( array $op, array $fields, array $properties, string $address ) {
        $field = $this->field( $op, $fields, array( 'link' ), $address, 'link' );
        if ( is_string( $field ) ) {
            return $field;
        }
        if ( ! isset( $op['url'] ) || ! is_string( $op['url'] ) ) {
            return 'set_link needs `url`, a string ("" removes the link).';
        }
        if ( array_key_exists( 'nofollow', $op ) ) {
            return 'Breakdance links have no nofollow setting; leave `nofollow` out.';
        }
        $url = trim( $op['url'] );
        if ( '' !== $url && esc_url_raw( $url, wp_allowed_protocols() ) !== $url ) {
            return sprintf( '"%s" is not a URL WordPress would keep as it is; send a full http(s) address, a relative path, mailto: or tel:.', $url );
        }
        $problem = $this->field_problem( $field, $properties, $url );
        if ( null !== $problem ) {
            return $problem;
        }
        $new_tab = array_key_exists( 'new_tab', $op ) ? rest_sanitize_boolean( $op['new_tab'] ) : null;
        if ( 'link' === $field['control'] ) {
            $stored        = self::value_at( $properties, $field['name'] );
            $value         = is_array( $stored ) ? $stored : array();
            $value['type'] = 'url';
            $value['url']  = $url;
            if ( null !== $new_tab ) {
                $value['openInNewTab'] = $new_tab;
            }
            return array( $field['name'] => $value );
        }
        $writes = array( $field['name'] => $url );
        if ( null !== $new_tab ) {
            $toggle = self::sibling( $field['name'], 'open_in_new_tab' );
            if ( null === self::named( $fields, $toggle, array( 'boolean' ) ) ) {
                return sprintf( 'element %s has no new-tab setting for %s; leave `new_tab` out.', $address, $field['name'] );
            }
            $writes[ $toggle ] = $new_tab;
        }
        return $writes;
    }

    private function field_writes( array $op, array $fields, array $properties ) {
        if ( empty( $op['fields'] ) || ! is_array( $op['fields'] ) ) {
            return 'set_fields needs `fields`, an object of field name to value.';
        }
        $writes = array();
        foreach ( $op['fields'] as $name => $value ) {
            $field = self::named( $fields, (string) $name, null );
            if ( null === $field ) {
                return sprintf( '%s is not a content field of this element; wp_list_layout_elements lists them.', (string) $name );
            }
            if ( ! in_array( $field['kind'], self::FIELD_KINDS, true ) ) {
                return sprintf( '%s is a %s field, which set_fields does not write%s.', $field['name'], $field['kind'], 'link' === $field['kind'] ? '; use set_link' : ( 'image' === $field['kind'] ? '; use set_image' : '' ) );
            }
            $checked = self::checked_value( $field, $value );
            if ( is_string( $checked ) ) {
                return $checked;
            }
            $problem = $this->field_problem( $field, self::apply_writes( $properties, $writes ), is_string( $checked[0] ) ? $checked[0] : null );
            if ( null !== $problem ) {
                return $problem;
            }
            $writes[ $field['name'] ] = $checked[0];
        }
        return $writes;
    }

    private static function checked_value( array $field, $value ) {
        switch ( $field['kind'] ) {
            case 'boolean':
                return is_bool( $value ) || in_array( $value, array( 'true', 'false', 1, 0, '1', '0' ), true ) ? array( rest_sanitize_boolean( $value ) ) : sprintf( '%s takes true or false.', $field['name'] );
            case 'number':
                return is_numeric( $value ) ? array( 0 + $value ) : sprintf( '%s takes a number.', $field['name'] );
            case 'select':
                $options = $field['options'] ?? array();
                return is_scalar( $value ) && in_array( (string) $value, $options, true ) ? array( (string) $value ) : sprintf( '%s takes one of %s.', $field['name'], implode( ', ', $options ) );
            case 'color':
                return is_string( $value ) && 1 === preg_match( '/^(?:#[0-9a-fA-F]{3,8}|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\)|)$/', $value ) ? array( $value ) : sprintf( '%s takes a colour such as #1a2b3c, or "" to clear it.', $field['name'] );
            default:
                return is_string( $value ) ? array( $value ) : sprintf( '%s takes a string.', $field['name'] );
        }
    }

    private function field( array $op, array $fields, array $kinds, string $address, string $what ) {
        if ( isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field'] ) {
            $field = self::named( $fields, $op['field'], null );
            if ( null === $field ) {
                return sprintf( '%s is not a content field of element %s; wp_list_layout_elements lists them.', $op['field'], $address );
            }
            return in_array( $field['kind'], $kinds, true ) ? $field : sprintf( '%s is a %s field, not a %s field.', $field['name'], $field['kind'], $what );
        }
        foreach ( $fields as $field ) {
            if ( in_array( $field['kind'], $kinds, true ) ) {
                return $field;
            }
        }
        return sprintf( 'element %s has no %s field.', $address, $what );
    }

    private function field_problem( array $field, array $properties, ?string $value ): ?string {
        if ( ! empty( $field['responsive'] ) ) {
            return sprintf( '%s is stored per screen size, which this tool does not write.', $field['name'] );
        }
        if ( self::bound( $properties, $field['name'] ) ) {
            return sprintf( '%s is filled from dynamic data, so a value written here would not show; change the binding in Breakdance.', $field['name'] );
        }
        if ( null !== $value && 1 === preg_match( self::DYNAMIC_PATTERN, $value ) ) {
            return sprintf( 'the value for %s holds Breakdance\'s dynamic-data shortcode, which runs when the page renders; it is never written here.', $field['name'] );
        }
        if ( null !== $value && ! $this->unfiltered && ( $this->kses )( $value ) !== $value ) {
            return sprintf( 'the value for %s holds HTML your account may not save (WordPress would remove part of it); send it without that markup.', $field['name'] );
        }
        return $this->conditions ? $this->condition_problem( $field, $properties ) : null;
    }

    private function media_problem( array $media ): ?string {
        $found = self::unsafe_string( $media, $this->unfiltered, $this->kses );
        if ( null === $found ) {
            return null;
        }
        $names = array(
            'alt'     => 'alt text',
            'caption' => 'caption',
        );
        return sprintf(
            'the image\'s %s in the media library %s; change it there first.',
            $names[ $found['path'] ] ?? $found['path'],
            'dynamic' === $found['why'] ? 'holds Breakdance\'s dynamic-data shortcode, which runs when the page renders' : 'holds HTML your account may not save'
        );
    }

    private static function unsafe_string( $value, bool $unfiltered, callable $kses, string $path = '' ): ?array {
        if ( is_string( $value ) ) {
            if ( 1 === preg_match( self::DYNAMIC_PATTERN, $value ) ) {
                return array(
                    'path' => $path,
                    'why'  => 'dynamic',
                );
            }
            return ! $unfiltered && $kses( $value ) !== $value ? array(
                'path' => $path,
                'why'  => 'html',
            ) : null;
        }
        foreach ( is_array( $value ) ? $value : array() as $key => $item ) {
            $found = self::unsafe_string( $item, $unfiltered, $kses, '' === $path ? (string) $key : $path . '.' . $key );
            if ( null !== $found ) {
                return $found;
            }
        }
        return null;
    }

    private function condition_problem( array $field, array $properties ): ?string {
        $groups = $field['condition'] ?? array();
        if ( array() === $groups ) {
            return null;
        }
        $wanted = array();
        foreach ( $groups as $group ) {
            $holds = true;
            foreach ( $group as $clause ) {
                $stored = self::value_at( $properties, $clause['path'] );
                if ( 'equals' === $clause['operand'] ) {
                    $holds    = $holds && $stored === $clause['value'];
                    $wanted[] = sprintf( '%s is %s', $clause['path'], wp_json_encode( $clause['value'] ) );
                } elseif ( 'not equals' === $clause['operand'] ) {
                    $holds    = $holds && $stored !== $clause['value'];
                    $wanted[] = sprintf( '%s is not %s', $clause['path'], wp_json_encode( $clause['value'] ) );
                }
            }
            if ( $holds ) {
                return null;
            }
        }
        return sprintf( 'Breakdance shows %s only while %s; set that with set_fields earlier in the same call.', $field['name'], implode( ' or ', array_unique( $wanted ) ) );
    }

    private static function bound( array $properties, string $name ): bool {
        if ( null !== self::value_at( $properties, $name . self::DYNAMIC_META_SUFFIX ) ) {
            return true;
        }
        $value = self::value_at( $properties, $name );
        if ( is_array( $value ) ) {
            return ! empty( $value['dynamicMeta'] ) || ( isset( $value['url'] ) && is_string( $value['url'] ) && 1 === preg_match( self::DYNAMIC_PATTERN, $value['url'] ) );
        }
        return is_string( $value ) && 1 === preg_match( self::DYNAMIC_PATTERN, $value );
    }

    private static function named( array $fields, string $name, ?array $kinds ): ?array {
        foreach ( $fields as $field ) {
            if ( $field['name'] === $name && ( null === $kinds || in_array( $field['kind'], $kinds, true ) ) ) {
                return $field;
            }
        }
        return null;
    }

    private static function sibling( string $path, string $slug ): string {
        $cut = strrpos( $path, '.' );
        return false === $cut ? $slug : substr( $path, 0, $cut + 1 ) . $slug;
    }

    private static function apply_writes( array $properties, array $writes ): array {
        foreach ( $writes as $name => $value ) {
            $properties = self::set( $properties, $name, $value );
        }
        return $properties;
    }

    public static function properties_of( array $tree, string $address ): ?array {
        $path = self::find( isset( $tree['root'] ) && is_array( $tree['root'] ) ? $tree['root'] : array(), $address );
        if ( null === $path ) {
            return null;
        }
        $properties = self::node_at( $tree, $path )['data']['properties'] ?? array();
        return is_array( $properties ) ? $properties : array();
    }

    public static function value_at( array $values, string $path ) {
        foreach ( explode( '.', $path ) as $key ) {
            if ( ! is_array( $values ) || ! array_key_exists( $key, $values ) ) {
                return null;
            }
            $values = $values[ $key ];
        }
        return $values;
    }

    private static function set( array $values, string $path, $value ): array {
        $keys   = explode( '.', $path );
        $first  = array_shift( $keys );
        $nested = isset( $values[ $first ] ) && is_array( $values[ $first ] ) ? $values[ $first ] : array();
        $values[ $first ] = array() === $keys ? $value : self::set( $nested, implode( '.', $keys ), $value );
        return $values;
    }

    private static function find( array $node, string $address ): ?array {
        foreach ( isset( $node['children'] ) && is_array( $node['children'] ) ? array_values( $node['children'] ) : array() as $index => $child ) {
            if ( ! is_array( $child ) ) {
                continue;
            }
            if ( isset( $child['id'] ) && is_scalar( $child['id'] ) && (string) $child['id'] === $address ) {
                return array( $index );
            }
            $below = self::find( $child, $address );
            if ( null !== $below ) {
                return array_merge( array( $index ), $below );
            }
        }
        return null;
    }

    private static function node_at( array $tree, array $path ): array {
        $node = $tree['root'];
        foreach ( $path as $index ) {
            $node = array_values( $node['children'] )[ $index ];
        }
        return $node;
    }

    private static function put( array $tree, array $path, array $node ): array {
        $tree['root'] = self::replaced( $tree['root'], $path, $node );
        return $tree;
    }

    private static function replaced( array $parent, array $path, array $node ): array {
        $index              = array_shift( $path );
        $children           = array_values( $parent['children'] );
        $children[ $index ] = array() === $path ? $node : self::replaced( $children[ $index ], $path, $node );
        $parent['children'] = $children;
        return $parent;
    }

    private static function refused( array $tree, array $errors ): array {
        return array(
            'tree'    => $tree,
            'results' => array(),
            'errors'  => $errors,
        );
    }
}
