<?php






namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





























final class Elementor_Editor {

    
    const OPS = array( 'set_text', 'set_link', 'set_image', 'set_fields' );

    
    const MAX_OPS = 50;

    
    const TEXT_KINDS = array( 'text', 'html' );

    
    const FIELD_KINDS = array( 'text', 'html', 'code', 'select', 'number', 'boolean', 'color' );

    
    const CONTAINERS = array( 'section', 'column', 'container' );

    
    const DYNAMIC_KEY = '__dynamic__';
    const GLOBALS_KEY = '__globals__';

    
    private $fields_of;

    
    private $media;

    
    private $unfiltered;

    
    private $kses;

    





    public function __construct( callable $fields_of, callable $media, bool $unfiltered, ?callable $kses = null ) {
        $this->fields_of  = $fields_of;
        $this->media      = $media;
        $this->unfiltered = $unfiltered;
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

        $next    = $data;
        $results = array();
        foreach ( array_values( $ops ) as $index => $op ) {
            $applied = is_array( $op ) ? $this->apply( $next, $op ) : 'must be an object.';
            if ( is_string( $applied ) ) {
                $errors[] = sprintf( 'Operation %d: %s', $index + 1, $applied );
                continue;
            }
            $next      = $applied['data'];
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
        foreach ( self::elements( $data ) as $element ) {
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
        $name    = isset( $op['op'] ) && is_string( $op['op'] ) ? $op['op'] : '';
        $address = isset( $op['element'] ) && is_scalar( $op['element'] ) ? trim( (string) $op['element'] ) : '';
        if ( ! in_array( $name, self::OPS, true ) ) {
            return sprintf( 'unknown `op` "%s"; one of %s.', $name, implode( ', ', self::OPS ) );
        }
        if ( '' === $address ) {
            return '`element` is required: the element\'s address from wp_get_page_layout.';
        }
        $element = self::find( $data, $address );
        if ( null === $element ) {
            return sprintf( 'no element "%s" in this layout. Read it again with wp_get_page_layout.', $address );
        }
        $type   = self::type_of( $element );
        $expect = isset( $op['expect'] ) && is_string( $op['expect'] ) ? $op['expect'] : '';
        if ( '' !== $expect && $expect !== $type ) {
            return sprintf( 'element "%s" is a %s, not the expected %s.', $address, $type, $expect );
        }
        $refusal = self::element_refusal( $element, $type, $address );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $fields = ( $this->fields_of )( $type );
        if ( null === $fields ) {
            return sprintf( 'Elementor has no %s widget registered, so "%s" cannot be edited.', $type, $address );
        }
        $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

        $changes = 'set_fields' === $name
            ? $this->field_changes( $op, array_values( $fields ), $type )
            : $this->single_change( $name, $op, array_values( $fields ), $settings, $type );
        if ( is_string( $changes ) ) {
            return $changes;
        }
        $bound = self::bound_fields( $settings, array_keys( $changes ) );
        if ( array() !== $bound ) {
            return sprintf( 'field %s of "%s" shows a dynamic tag or a global value set in Elementor, so a value written there would not appear. Remove it in Elementor first.', implode( ', ', $bound ), $address );
        }

        $before = array();
        $after  = array();
        foreach ( $changes as $field => $value ) {
            $before[ $field ]   = $settings[ $field ] ?? null;
            $after[ $field ]    = $value;
            $settings[ $field ] = $value;
        }
        
        $single = 'set_fields' !== $name;
        return array(
            'data'   => self::replace_settings( $data, $address, $settings ),
            'result' => array(
                'op'      => $name,
                'element' => $address,
                'type'    => $type,
                'field'   => implode( ', ', array_map( 'strval', array_keys( $changes ) ) ),
                'before'  => $single ? reset( $before ) : $before,
                'after'   => $single ? reset( $after ) : $after,
                'changed' => $before !== $after,
            ),
        );
    }

    






    private static function element_refusal( array $element, string $type, string $address ): ?string {
        $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
        if ( 0 === strpos( $type, 'e-' ) || self::has_transformables( $settings ) ) {
            return sprintf( 'element "%s" is an Elementor V4 element; Elementor\'s own manage-elements ability (wp_ability_elementor_manage_elements) edits those.', $address );
        }
        if ( 'widget' !== ( $element['elType'] ?? '' ) || in_array( $type, self::CONTAINERS, true ) ) {
            return sprintf( 'element "%s" is a %s; only widgets can be edited with these operations.', $address, $type );
        }
        return null;
    }

    









    private function single_change( string $name, array $op, array $fields, array $settings, string $type ) {
        $writes = array(
            'set_text'  => array( 'text', self::TEXT_KINDS ),
            'set_link'  => array( 'link', array( 'link' ) ),
            'set_image' => array( 'image', array( 'image' ) ),
        );
        list( $role, $kinds ) = $writes[ $name ];

        $field = isset( $op['field'] ) && is_string( $op['field'] ) && '' !== $op['field']
            ? $op['field']
            : self::default_field( $type, $role, $kinds, $fields );
        if ( '' === $field ) {
            return sprintf( '%s widgets have no %s field; give `field` (see wp_list_layout_elements with type "%s").', $type, $role, $type );
        }
        $spec = self::field_spec( $fields, $field );
        if ( null === $spec ) {
            return sprintf( '%s widgets have no content field "%s" (see wp_list_layout_elements with type "%s").', $type, $field, $type );
        }
        if ( ! in_array( $spec['kind'], $kinds, true ) ) {
            return sprintf( 'field "%s" holds %s, which %s does not write.', $field, $spec['kind'], $name );
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

    







    private function field_changes( array $op, array $fields, string $type ) {
        if ( ! isset( $op['fields'] ) || ! is_array( $op['fields'] ) || array() === $op['fields'] ) {
            return 'set_fields needs `fields`, an object of field name to value.';
        }
        $changes  = array();
        $problems = array();
        foreach ( $op['fields'] as $field => $value ) {
            $field = (string) $field;
            $spec  = self::field_spec( $fields, $field );
            if ( null === $spec ) {
                $problems[] = sprintf( '%s widgets have no content field "%s"', $type, $field );
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
        $kind = $spec['kind'];
        if ( ! in_array( $kind, self::FIELD_KINDS, true ) ) {
            $hint = array(
                'link'  => '; use set_link',
                'image' => '; use set_image',
            );
            return array( 'error' => sprintf( 'holds %s, which set_fields does not write%s', $kind, $hint[ $kind ] ?? '' ) );
        }
        if ( 'select' === $kind ) {
            $options = $spec['options'] ?? array();
            if ( ! is_scalar( $value ) || is_bool( $value ) || ( array() !== $options && ! in_array( (string) $value, $options, true ) ) ) {
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

    





    private static function elements( array $data ): array {
        $all = array();
        foreach ( $data as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $element['settings'] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
            $all[]               = $element;
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $all = array_merge( $all, self::elements( $element['elements'] ) );
            }
        }
        return $all;
    }

    






    private static function find( array $data, string $address ): ?array {
        foreach ( self::elements( $data ) as $element ) {
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) && (string) $element['id'] === $address ) {
                return $element;
            }
        }
        return null;
    }

    







    private static function replace_settings( array $data, string $address, array $settings ): array {
        foreach ( $data as $index => $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) && (string) $element['id'] === $address ) {
                $data[ $index ]['settings'] = $settings;
                return $data;
            }
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $data[ $index ]['elements'] = self::replace_settings( $element['elements'], $address, $settings );
            }
        }
        return $data;
    }
}
