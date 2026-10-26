<?php







namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






























final class Shortcode_Editor {

    
    const OPS = array( 'set_text', 'set_link', 'set_image', 'set_fields' );

    
    const MAX_OPS = 50;

    
    const TEXT_KINDS = array( 'text', 'html' );

    
    const CONTENT = 'content';

    
    private $adapter;

    
    private $media;

    
    private $unfiltered;

    
    private $kses;

    





    public function __construct( Shortcode_Adapter $adapter, callable $media, bool $unfiltered, ?callable $kses = null ) {
        $this->adapter    = $adapter;
        $this->media      = $media;
        $this->unfiltered = $unfiltered;
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
        if ( array() !== $errors ) {
            return self::refused( $source, $errors );
        }

        $shape   = self::shape( $this->adapter->layout_items( $source ) );
        $content = $source;
        $results = array();
        foreach ( array_values( $ops ) as $index => $op ) {
            $applied = is_array( $op ) ? $this->apply( $content, $op ) : 'must be an object.';
            if ( is_string( $applied ) ) {
                $errors[] = sprintf( 'Operation %d: %s', $index + 1, $applied );
                continue;
            }
            
            
            if ( self::shape( $this->adapter->layout_items( $applied['content'] ) ) !== $shape ) {
                $errors[] = sprintf( 'Operation %d: the value would change which elements the layout holds, so it was not applied.', $index + 1 );
                continue;
            }
            $content   = $applied['content'];
            $results[] = $applied['result'];
        }
        if ( array() !== $errors ) {
            return self::refused( $source, $errors );
        }
        return array(
            'content' => $content,
            'results' => $results,
            'errors'  => array(),
        );
    }

    









    public static function value( Shortcode_Adapter $adapter, array $item, string $op, string $field ) {
        if ( Shortcode_Tree::TEXT === $item['tag'] ) {
            return $item['content'];
        }
        if ( self::CONTENT === $field ) {
            return null === $item['content'] ? null : $adapter->decoded_content( $item['tag'], $item['content'] );
        }
        $decoded = $adapter->decoded_atts( $item['tag'], $item['atts'] );
        if ( 'set_link' === $op ) {
            return $adapter->link_view( $item['tag'], $field, $decoded );
        }
        return $decoded[ $field ] ?? null;
    }

    






    public static function find( array $items, string $address ): ?array {
        $path = self::path( $items, $address );
        return null === $path ? null : $path[ count( $path ) - 1 ];
    }

    






    public static function path( array $items, string $address ): ?array {
        if ( 1 !== preg_match( '/^\d+(?:\.\d+)*$/', $address ) ) {
            return null;
        }
        $path = array();
        foreach ( explode( '.', $address ) as $index ) {
            $item = $items[ (int) $index ] ?? null;
            if ( null === $item ) {
                return null;
            }
            $path[] = $item;
            $items  = $item['children'];
        }
        return $path;
    }

    








    public static function shape( array $items, string $prefix = '' ): array {
        $shape = array();
        foreach ( array_values( $items ) as $index => $item ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            if ( Shortcode_Tree::TEXT === $item['tag'] ) {
                continue;
            }
            $shape[] = $address . ':' . $item['tag'];
            $shape   = array_merge( $shape, self::shape( $item['children'], $address ) );
        }
        return $shape;
    }

    






    private static function refused( string $source, array $errors ): array {
        return array(
            'content' => $source,
            'results' => array(),
            'errors'  => $errors,
        );
    }

    






    private function apply( string $content, array $op ) {
        $name    = isset( $op['op'] ) && is_string( $op['op'] ) ? $op['op'] : '';
        $address = isset( $op['element'] ) && is_scalar( $op['element'] ) ? trim( (string) $op['element'] ) : '';
        if ( ! in_array( $name, self::OPS, true ) ) {
            return sprintf( 'unknown `op` "%s"; one of %s.', $name, implode( ', ', self::OPS ) );
        }
        if ( '' === $address ) {
            return '`element` is required: the element\'s address from wp_get_page_layout, such as "0.1.2".';
        }
        $items = $this->adapter->layout_items( $content );
        $path  = self::path( $items, $address );
        if ( null === $path ) {
            return sprintf( 'no element "%s" in this layout. Read it again with wp_get_page_layout.', $address );
        }
        $item   = $path[ count( $path ) - 1 ];
        $type   = $item['tag'];
        $expect = isset( $op['expect'] ) && is_string( $op['expect'] ) ? $op['expect'] : '';
        if ( '' !== $expect && $expect !== $type ) {
            return sprintf( 'element "%s" is a %s, not the expected %s.', $address, $type, $expect );
        }
        
        
        
        foreach ( $path as $holder ) {
            if ( Shortcode_Tree::TEXT === $holder['tag'] ) {
                continue;
            }
            $refusal = $this->adapter->element_refusal( $holder['tag'] ) ?? $this->adapter->element_access_refusal( $holder['tag'] );
            if ( null !== $refusal ) {
                $inside = $holder === $item ? '' : sprintf( ' is inside a %s, which', $holder['tag'] );
                return sprintf( 'element "%s" (%s)%s: %s', $address, $type, $inside, $refusal );
            }
        }
        if ( Shortcode_Tree::TEXT === $type ) {
            return $this->text_item( $content, $item, $name, $op, $address );
        }
        $fields = $this->adapter->element_fields( $type );
        if ( null === $fields ) {
            return sprintf( '%s has no %s element we know the fields of, so "%s" cannot be edited.', $this->adapter->label(), $type, $address );
        }
        $fields = array_values( $fields );

        if ( 'set_text' === $name && self::CONTENT === $this->text_field( $op, $type ) ) {
            return $this->enclosed( $content, $item, $op, $address );
        }
        $changes = $this->attribute_changes( $name, $op, $item, $fields );
        if ( is_string( $changes ) ) {
            return $changes;
        }
        foreach ( $changes as $stored ) {
            if ( null !== $stored && false !== strpos( $stored, '\\' ) ) {
                
                return sprintf( 'a value for "%s" holds a backslash, which WordPress removes from shortcode attributes when it reads the page; send it without one.', $address );
            }
            if ( null !== $stored && ! self::safe_value( $stored ) ) {
                return sprintf( 'a value for "%s" could not be encoded safely for %s.', $address, $this->adapter->label() );
            }
        }
        $written = self::set_attributes( $item['atts_raw'], $changes );
        $next    = substr_replace( $content, $written, $item['atts_at'], strlen( $item['atts_raw'] ) );
        $after = self::find( $this->adapter->layout_items( $next ), $address );
        if ( null === $after ) {
            return sprintf( 'the value would change which elements the layout holds, so "%s" was not changed.', $address );
        }
        foreach ( $changes as $attribute => $stored ) {
            $read = $after['atts'][ $attribute ] ?? null;
            if ( null !== $stored && $read !== $stored ) {
                
                $why = false !== strpos( $stored, '<' ) ? ' A "<" that does not open a closed HTML tag empties a shortcode attribute; write &lt; instead.' : '';
                return sprintf( 'WordPress would read field "%s" of "%s" back as "%s", not as sent, so it was not changed.%s', $attribute, $address, (string) $read, $why );
            }
        }
        $result            = self::result( $name, $address, $type, self::written_fields( $name, $changes ), $this->views( $item, $name, $changes ), $this->views( $after, $name, $changes ), $written !== $item['atts_raw'] );
        $result['written'] = array_map( 'strval', array_keys( $changes ) );
        return array(
            'content' => $next,
            'result'  => $result,
        );
    }

    









    private function text_item( string $content, array $item, string $name, array $op, string $address ) {
        if ( 'set_text' !== $name ) {
            return sprintf( '"%s" is text between elements; only set_text changes it.', $address );
        }
        
        
        $text   = (string) $item['content'];
        $lead   = strlen( $text ) - strlen( ltrim( $text ) );
        $length = strlen( trim( $text ) );
        $at     = $item['start'] + $lead;
        $own    = substr( $content, $at, $length );
        if ( $own !== substr( $this->adapter->masked( $content ), $at, $length ) ) {
            return sprintf( 'the text of "%s" runs into the builder\'s own wrapper, so it is not edited here.', $address );
        }
        $value = $this->html_value( $op );
        if ( is_array( $value ) ) {
            return $value['error'];
        }
        $stored = $this->adapter->encode_content( Shortcode_Tree::TEXT, $value );
        $next   = substr_replace( $content, $stored, $at, $length );
        return array(
            'content' => $next,
            'result'  => self::result( $name, $address, Shortcode_Tree::TEXT, self::CONTENT, $own, $stored, $stored !== $own ),
        );
    }

    








    private function enclosed( string $content, array $item, array $op, string $address ) {
        if ( null === $item['content'] || $item['content_at'] < 0 ) {
            return sprintf( 'element "%s" (%s) has no enclosed content to write; give `field`.', $address, $item['tag'] );
        }
        
        
        foreach ( $this->adapter->layout_items( (string) $item['content'] ) as $child ) {
            if ( Shortcode_Tree::TEXT !== $child['tag'] ) {
                return sprintf( 'element "%s" (%s) holds other elements; edit those by their own addresses.', $address, $item['tag'] );
            }
        }
        if ( substr( $content, $item['content_at'], strlen( $item['content'] ) ) !== $item['content'] ) {
            return sprintf( 'the content of "%s" runs into the builder\'s own wrapper, so it is not edited here.', $address );
        }
        $value = $this->html_value( $op );
        if ( is_array( $value ) ) {
            return $value['error'];
        }
        $stored = $this->adapter->encode_content( $item['tag'], $value );
        $next   = substr_replace( $content, $stored, $item['content_at'], strlen( $item['content'] ) );
        $after  = self::find( $this->adapter->layout_items( $next ), $address );
        if ( null === $after ) {
            return sprintf( 'the value would change which elements the layout holds, so "%s" was not changed.', $address );
        }
        return array(
            'content' => $next,
            'result'  => self::result( 'set_text', $address, $item['tag'], self::CONTENT, self::value( $this->adapter, $item, 'set_text', self::CONTENT ), self::value( $this->adapter, $after, 'set_text', self::CONTENT ), $stored !== $item['content'] ),
        );
    }

    





    private function text_field( array $op, string $type ): string {
        if ( isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field'] ) {
            return $op['field'];
        }
        return $this->adapter->element_roles( $type )['text'] ?? '';
    }

    








    private function attribute_changes( string $name, array $op, array $item, array $fields ) {
        $type    = $item['tag'];
        $roles   = $this->adapter->element_roles( $type );
        $decoded = $this->adapter->decoded_atts( $type, $item['atts'] );
        if ( 'set_fields' === $name ) {
            return $this->field_changes( $op, $fields, $type );
        }
        $role  = array(
            'set_text'  => 'text',
            'set_link'  => 'link',
            'set_image' => 'image',
        )[ $name ];
        $kinds = 'set_text' === $name ? self::TEXT_KINDS : array( $role );
        $field = isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field'] ? $op['field'] : ( $roles[ $role ] ?? '' );
        if ( '' === $field ) {
            return sprintf( '%s elements have no %s field; give `field` (see wp_list_layout_elements with type "%s").', $type, $role, $type );
        }
        $spec = self::field_spec( $fields, $field );
        if ( null === $spec ) {
            return sprintf( '%s elements have no field "%s" (see wp_list_layout_elements with type "%s").', $type, $field, $type );
        }
        if ( ! in_array( $spec['kind'] ?? '', $kinds, true ) ) {
            return sprintf( 'field "%s" holds %s, which %s does not write.', $field, (string) ( $spec['kind'] ?? 'other' ), $name );
        }

        if ( 'set_text' === $name ) {
            $value = $this->html_value( $op );
            return is_array( $value ) ? $value['error'] : array( $field => $this->adapter->encode_attribute( $type, $field, $spec, $value ) );
        }
        if ( 'set_link' === $name ) {
            if ( ! isset( $op['url'] ) || ! is_string( $op['url'] ) ) {
                return 'set_link needs `url`, a string ("" removes the link).';
            }
            $asked = trim( $op['url'] );
            $url   = '' === $asked ? '' : esc_url_raw( $asked, wp_allowed_protocols() );
            if ( '' !== $asked && '' === $url ) {
                return sprintf( '"%s" is not a URL WordPress accepts.', $asked );
            }
            $new_tab  = isset( $op['new_tab'] ) ? (bool) $op['new_tab'] : null;
            $nofollow = isset( $op['nofollow'] ) ? (bool) $op['nofollow'] : null;
            return $this->adapter->link_attributes( $type, $field, $spec, $decoded, $url, $new_tab, $nofollow, $fields );
        }
        $id = isset( $op['attachment_id'] ) && is_numeric( $op['attachment_id'] ) ? (int) $op['attachment_id'] : 0;
        if ( $id <= 0 ) {
            return 'set_image needs `attachment_id`, the ID of an image in the media library (wp_list_media, or upload one first).';
        }
        if ( null === ( $this->media )( $id ) ) {
            return sprintf( 'attachment %d is not an image in the media library that this account can use.', $id );
        }
        return array( $field => (string) $id );
    }

    







    private function field_changes( array $op, array $fields, string $type ) {
        if ( ! isset( $op['fields'] ) || ! is_array( $op['fields'] ) || array() === $op['fields'] ) {
            return 'set_fields needs `fields`, an object of field name to value.';
        }
        $changes  = array();
        $problems = array();
        foreach ( $op['fields'] as $field => $value ) {
            $field = (string) $field;
            $spec  = self::field_spec( $fields, $field );
            if ( null === $spec || self::CONTENT === $field ) {
                $problems[] = null === $spec ? sprintf( '%s elements have no field "%s"', $type, $field ) : 'field "content" is the enclosed content; use set_text';
                continue;
            }
            $checked = $this->field_value( $type, $field, $spec, $value );
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

    









    private function field_value( string $type, string $field, array $spec, $value ) {
        $kind = (string) ( $spec['kind'] ?? 'other' );
        if ( ! in_array( $kind, $this->adapter->field_kinds(), true ) || ( 'boolean' === $kind && ! isset( $spec['on_value'] ) ) ) {
            $hint = array(
                'link'  => '; use set_link',
                'image' => '; use set_image',
                'html'  => '; use set_text with this field',
            );
            return array( 'error' => sprintf( 'holds %s, which set_fields does not write%s', $kind, $hint[ $kind ] ?? '' ) );
        }
        if ( 'boolean' === $kind ) {
            if ( ! is_bool( $value ) ) {
                return array( 'error' => 'must be true or false' );
            }
            
            
            $default = $spec['default'] ?? '';
            return $value ? (string) $spec['on_value'] : ( is_scalar( $default ) && '' !== (string) $default ? '' : null );
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
                return array( 'error' => sprintf( 'must be one of: %s', implode( ', ', array_map( array( __CLASS__, 'quoted' ), $options ) ) ) );
            }
            
            
            if ( array() === $options && 1 !== preg_match( '/^[\w-]+$/', $value ) ) {
                return array( 'error' => 'has no list of allowed values here, so it takes only a plain value of letters, digits, - and _' );
            }
        } elseif ( 'number' === $kind ) {
            $units = isset( $spec['units'] ) && is_array( $spec['units'] ) ? array_map( 'strval', $spec['units'] ) : array();
            if ( array() !== $units ) {
                $pattern = '/^\d*\.?\d+(?:' . implode( '|', array_map( 'preg_quote', $units, array_fill( 0, count( $units ), '/' ) ) ) . ')?$/';
                
                if ( 1 !== preg_match( $pattern, $value ) || ! is_finite( (float) $value ) ) {
                    return array( 'error' => sprintf( 'must be a finite number, optionally with a unit: %s', implode( ', ', $units ) ) );
                }
            } elseif ( ! is_numeric( $value ) || ! is_finite( (float) $value ) ) {
                
                return array( 'error' => 'must be a finite number' );
            }
        } elseif ( 'color' === $kind ) {
            if ( 1 !== preg_match( '/^(?:#[0-9a-fA-F]{3,8}|rgba?\([0-9.,%\s]+\)|hsla?\([0-9.,%\s]+\))$/', $value ) ) {
                return array( 'error' => 'must be a colour such as #1a2b3c, or "" to clear it' );
            }
        } elseif ( in_array( $kind, array( 'size', 'spacing' ), true ) ) {
            if ( 1 !== preg_match( '/^[\w.%#\s,()+-]+$/', $value ) ) {
                return array( 'error' => 'must be a size such as 40px, 50% or "0px 10px", or "" to clear it' );
            }
        } else {
            $checked = $this->html_value( array( 'value' => $value ) );
            if ( is_array( $checked ) ) {
                return array( 'error' => lcfirst( $checked['error'] ) );
            }
            $value = $checked;
        }
        return $this->adapter->encode_attribute( $type, $field, $spec, $value );
    }

    





    private function html_value( array $op ) {
        if ( ! isset( $op['value'] ) || ! is_string( $op['value'] ) ) {
            return array( 'error' => 'set_text needs `value`, a string.' );
        }
        $value = $op['value'];
        if ( $this->unfiltered ) {
            return $value;
        }
        
        
        $clean = (string) ( $this->kses )( $value );
        if ( ! self::same_text( $clean, $value ) ) {
            return array( 'error' => 'Holds HTML this account cannot save (it may not post unfiltered HTML), so WordPress would cut part of it. Send it without scripts, styles, iframes or event attributes.' );
        }
        return $clean;
    }

    





    public static function same_text( string $clean, string $original ): bool {
        return html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( $original, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    }

    











    public static function set_attributes( string $raw, array $changes ): string {
        preg_match_all( get_shortcode_atts_regex(), $raw, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
        $spans = array();
        foreach ( $matches as $match ) {
            foreach ( array( 1, 3, 5 ) as $group ) {
                if ( isset( $match[ $group ] ) && -1 !== $match[ $group ][1] && '' !== $match[ $group ][0] ) {
                    $spans[] = array( strtolower( $match[ $group ][0] ), $match[ $group ][0], $match[0][1], strlen( rtrim( $match[0][0] ) ) );
                    break;
                }
            }
        }
        
        foreach ( array_reverse( $spans ) as $span ) {
            list( $key, $name, $at, $length ) = $span;
            if ( ! array_key_exists( $key, $changes ) ) {
                continue;
            }
            $stored = $changes[ $key ];
            if ( null === $stored ) {
                
                $from = $at;
                while ( $from > 0 && ctype_space( $raw[ $from - 1 ] ) ) {
                    --$from;
                }
                $raw = substr_replace( $raw, '', $from, $at + $length - $from );
                continue;
            }
            $raw = substr_replace( $raw, $name . '="' . $stored . '"', $at, $length );
        }
        $present = array_unique( array_column( $spans, 0 ) );
        foreach ( $changes as $key => $stored ) {
            if ( null === $stored || in_array( strtolower( (string) $key ), $present, true ) ) {
                continue;
            }
            $trimmed = rtrim( $raw );
            $raw     = $trimmed . ' ' . $key . '="' . $stored . '"' . substr( $raw, strlen( $trimmed ) );
        }
        return $raw;
    }

    





    private static function safe_value( string $stored ): bool {
        return false === strpbrk( $stored, '"[]' );
    }

    





    private static function written_fields( string $name, array $changes ): string {
        if ( 'set_fields' === $name ) {
            return implode( ', ', array_map( 'strval', array_keys( $changes ) ) );
        }
        
        $keys = array_keys( $changes );
        return (string) reset( $keys );
    }

    









    private function views( array $item, string $name, array $changes ) {
        if ( 'set_fields' === $name ) {
            $views = array();
            foreach ( array_keys( $changes ) as $field ) {
                $views[ $field ] = self::value( $this->adapter, $item, $name, (string) $field );
            }
            return $views;
        }
        return self::value( $this->adapter, $item, $name, self::written_fields( $name, $changes ) );
    }

    











    private static function result( string $op, string $address, string $type, string $field, $before, $after, bool $changed ): array {
        return array(
            'op'      => $op,
            'element' => $address,
            'type'    => $type,
            'field'   => $field,
            'before'  => $before,
            'after'   => $after,
            'changed' => $changed,
        );
    }

    






    private static function field_spec( array $fields, string $name ): ?array {
        foreach ( $fields as $spec ) {
            $devices = isset( $spec['devices'] ) && is_array( $spec['devices'] ) ? $spec['devices'] : array();
            if ( ( $spec['name'] ?? '' ) === $name || in_array( $name, $devices, true ) ) {
                return $spec;
            }
        }
        return null;
    }

    
    private static function quoted( string $value ): string {
        return '"' . $value . '"';
    }
}
