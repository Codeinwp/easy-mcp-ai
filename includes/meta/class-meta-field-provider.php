<?php














namespace Easy_MCP_AI\Meta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Meta_Field_Provider {

    





    abstract public function get_group_slug();

    




    abstract public function get_label();

    







    abstract public function get_key_prefixes();

    




    abstract public function is_active();

    















    abstract public function get_fields();

    









    public function can_edit_field( $key, $user_id, $post_id ) {
        $fields = $this->get_fields();
        if ( ! isset( $fields[ $key ] ) ) {
            return false;
        }
        $auth = isset( $fields[ $key ]['auth'] ) ? $fields[ $key ]['auth'] : null;
        if ( null === $auth || '' === $auth ) {
            return true;
        }
        if ( is_string( $auth ) && false === strpos( $auth, '::' ) ) {
            return (bool) user_can( (int) $user_id, $auth );
        }
        if ( is_callable( $auth ) ) {
            return (bool) call_user_func( $auth, (int) $user_id, (int) $post_id, $key );
        }
        return false;
    }

    





    public function get_dedicated_tool() {
        return '';
    }

    






    public function get_post_types() {
        $types = get_post_types(
            array(
                'public'       => true,
                'show_in_rest' => true,
            ),
            'names'
        );
        $types = is_array( $types ) ? array_values( $types ) : array();
        return array_values( array_diff( $types, array( 'attachment' ) ) );
    }

    






    public function owns_key( $key ) {
        $key = (string) $key;
        if ( array_key_exists( $key, $this->get_fields() ) ) {
            return true;
        }
        foreach ( $this->get_key_prefixes() as $prefix ) {
            if ( '' !== $prefix && 0 === strncmp( $key, $prefix, strlen( $prefix ) ) ) {
                return true;
            }
        }
        return false;
    }

    

























    public function sanitize( $key, $value ) {
        $fields = $this->get_fields();
        $field  = isset( $fields[ $key ] ) ? $fields[ $key ] : array();
        $spec   = isset( $field['sanitize'] ) ? $field['sanitize'] : 'text';

        
        
        if ( ! is_string( $spec ) || false !== strpos( $spec, '::' ) ) {
            if ( is_callable( $spec ) ) {
                return call_user_func( $spec, $value, $key );
            }
            $spec = 'text';
        }

        $spec = (string) $spec;
        if ( 0 === strncmp( $spec, 'flag:', 5 ) ) {
            $on = substr( $spec, 5 );
            return self::is_truthy( $value, $on ) ? $on : '';
        }

        switch ( $spec ) {
            case 'url':
                return esc_url_raw( (string) self::scalar( $value ) );
            case 'int':
                return (int) self::scalar( $value );
            case 'int_string':
                $n = absint( self::scalar( $value ) );
                return $n > 0 ? (string) $n : '';
            case 'enum':
                $v = (string) self::scalar( $value );
                return in_array( $v, isset( $field['enum'] ) ? (array) $field['enum'] : array(), true ) ? $v : '';
            case 'qubit':
                $n = (int) self::scalar( $value );
                return in_array( $n, array( -1, 0, 1 ), true ) ? $n : 0;
            case 'csv':
                $allowed = isset( $field['enum'] ) ? (array) $field['enum'] : array();
                $tokens  = is_array( $value ) ? $value : explode( ',', (string) self::scalar( $value ) );
                return implode( ',', self::clean_tokens( $tokens, $allowed ) );
            case 'string_list':
                $allowed = isset( $field['enum'] ) ? (array) $field['enum'] : array();
                $tokens  = is_array( $value ) ? $value : explode( ',', (string) self::scalar( $value ) );
                return self::clean_tokens( $tokens, $allowed );
            case 'object_keys':
                $out   = array();
                $props = isset( $field['properties'] ) ? (array) $field['properties'] : array();
                foreach ( (array) $value as $k => $v ) {
                    if ( ! isset( $props[ $k ] ) ) {
                        continue;
                    }
                    if ( false === $v || null === $v ) {
                        $out[ $k ] = false;
                        continue;
                    }
                    $type      = isset( $props[ $k ]['type'] ) ? (array) $props[ $k ]['type'] : array( 'string' );
                    $out[ $k ] = in_array( 'integer', $type, true ) && is_numeric( $v ) ? (int) $v : self::clean_text( $v );
                }
                return $out;
            case 'text':
            default:
                return self::clean_text( $value );
        }
    }

    







    public static function clean_text( $value ) {
        $value = (string) self::scalar( $value );
        if ( '' === $value ) {
            return '';
        }
        $value = wp_strip_all_tags( $value );
        $value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
        $value = preg_replace( '/[\r\n\t]+/', ' ', $value );
        $value = preg_replace( '/ {2,}/', ' ', $value );
        return trim( (string) $value );
    }

    



    private static function scalar( $value ) {
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }
        return $value;
    }

    




    private static function is_truthy( $value, $on ) {
        if ( is_bool( $value ) ) {
            return $value;
        }
        $v = strtolower( trim( (string) self::scalar( $value ) ) );
        return in_array( $v, array( '1', 'on', 'yes', 'true', strtolower( $on ) ), true );
    }

    




    private static function clean_tokens( array $tokens, array $allowed ) {
        $out = array();
        foreach ( $tokens as $token ) {
            $token = self::clean_text( $token );
            if ( '' === $token ) {
                continue;
            }
            if ( ! empty( $allowed ) && ! in_array( $token, $allowed, true ) ) {
                continue;
            }
            if ( ! in_array( $token, $out, true ) ) {
                $out[] = $token;
            }
        }
        return $out;
    }
}
