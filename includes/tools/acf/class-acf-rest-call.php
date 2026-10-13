<?php







namespace Easy_MCP_AI\Tools\ACF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}














































final class Acf_Rest_Call {

    
    const EDIT_CAPS = array(
        'post' => 'edit_post',
        'term' => 'edit_term',
        'user' => 'edit_user',
    );

    









    public static function run( callable $call, $type, $id, $route, $write ) {
        $id     = (int) $id;
        $route  = (string) $route;
        $own    = self::object_route( $type, $id );
        $opened = '' !== $own && $own === $route
            && isset( self::EDIT_CAPS[ $type ] ) && current_user_can( self::EDIT_CAPS[ $type ], $id );
        $acf_id = 'post' === $type ? (string) $id : $type . '_' . $id;
        $saved  = array();

        $record = static function ( $check, $value = null, $post_id = null, $field = null ) use ( $acf_id, &$saved ) {
            if ( (string) $post_id === $acf_id && is_array( $field ) && ! self::is_sub_field( $field ) ) {
                foreach ( array( 'key', 'name' ) as $k ) {
                    if ( isset( $field[ $k ] ) && '' !== (string) $field[ $k ] ) {
                        $saved[ (string) $field[ $k ] ] = true;
                    }
                }
            }
            return $check;
        };

        
        $hooks = array( array( 'acf/pre_update_value', $record, 10, 4 ) );
        if ( $write ) {
            $hooks[] = array(
                'rest_endpoints',
                static function ( $endpoints ) use ( $route ) {
                    return self::add_acf_argument( $endpoints, $route );
                },
                10,
                1,
            );
        }
        if ( $opened ) {
            $hooks[] = array( 'acf/settings/rest_api_enabled', array( __CLASS__, 'rest_on' ), PHP_INT_MAX, 1 );
            $hooks[] = array( 'acf/load_field_groups', array( __CLASS__, 'expose_groups' ), 30, 1 );
        }

        $set_aside = self::set_aside_acf_fields();
        foreach ( $hooks as $hook ) {
            add_filter( $hook[0], $hook[1], $hook[2], $hook[3] );
        }
        try {
            $data = $call();
        } finally {
            foreach ( $hooks as $hook ) {
                remove_filter( $hook[0], $hook[1], $hook[2] );
            }
            self::restore_acf_fields( $set_aside );
        }
        return array(
            'data'   => $data,
            'saved'  => $saved,
            'opened' => $opened,
        );
    }

    





    private static function set_aside_acf_fields() {
        global $wp_rest_additional_fields;
        $taken = array(
            'entries' => array(),
            'bases'   => is_array( $wp_rest_additional_fields ) ? array_map( 'strval', array_keys( $wp_rest_additional_fields ) ) : array(),
        );
        if ( ! is_array( $wp_rest_additional_fields ) ) {
            return $taken;
        }
        foreach ( $wp_rest_additional_fields as $base => $fields ) {
            if ( is_array( $fields ) && array_key_exists( 'acf', $fields ) ) {
                $taken['entries'][ (string) $base ] = $fields['acf'];
                unset( $wp_rest_additional_fields[ $base ]['acf'] );
            }
        }
        return $taken;
    }

    





    private static function restore_acf_fields( array $taken ) {
        global $wp_rest_additional_fields;
        if ( is_array( $wp_rest_additional_fields ) ) {
            foreach ( array_keys( $wp_rest_additional_fields ) as $base ) {
                if ( ! is_array( $wp_rest_additional_fields[ $base ] ) ) {
                    continue;
                }
                unset( $wp_rest_additional_fields[ $base ]['acf'] );
                if ( empty( $wp_rest_additional_fields[ $base ] ) && ! in_array( (string) $base, $taken['bases'], true ) ) {
                    unset( $wp_rest_additional_fields[ $base ] );
                }
            }
        }
        foreach ( $taken['entries'] as $base => $entry ) {
            $wp_rest_additional_fields[ $base ]['acf'] = $entry;
        }
    }

    









    public static function add_acf_argument( $endpoints, $route ) {
        if ( ! is_array( $endpoints ) || ! class_exists( 'WP_REST_Server' ) ) {
            return $endpoints;
        }
        foreach ( $endpoints as $pattern => $handlers ) {
            if ( ! is_array( $handlers ) || ! preg_match( '@^' . $pattern . '$@i', $route ) ) {
                continue;
            }
            $schema = isset( $handlers['schema'] ) ? $handlers['schema'] : null;
            if ( ! is_array( $schema ) || ! isset( $schema[0] ) || ! is_object( $schema[0] ) || ! method_exists( $schema[0], 'get_endpoint_args_for_item_schema' ) ) {
                continue;
            }
            $args = $schema[0]->get_endpoint_args_for_item_schema( \WP_REST_Server::EDITABLE );
            if ( empty( $args['acf'] ) ) {
                continue;
            }
            foreach ( $handlers as $key => $handler ) {
                if ( ! is_numeric( $key ) || ! is_array( $handler ) || isset( $handler['args']['acf'] ) ) {
                    continue;
                }
                $methods = isset( $handler['methods'] ) ? $handler['methods'] : '';
                $methods = is_array( $methods ) ? implode( ',', array_merge( array_keys( $methods ), array_values( array_filter( $methods, 'is_string' ) ) ) ) : (string) $methods;
                if ( false === stripos( $methods, 'POST' ) && false === stripos( $methods, 'PUT' ) && false === stripos( $methods, 'PATCH' ) ) {
                    continue;
                }
                $endpoints[ $pattern ][ $key ]['args']['acf'] = $args['acf'];
            }
        }
        return $endpoints;
    }

    






    private static function object_route( $type, $id ) {
        if ( 'post' === $type && function_exists( 'rest_get_route_for_post' ) ) {
            return (string) rest_get_route_for_post( $id );
        }
        if ( 'term' === $type && function_exists( 'rest_get_route_for_term' ) ) {
            return (string) rest_get_route_for_term( $id );
        }
        if ( 'user' === $type ) {
            return '/wp/v2/users/' . $id;
        }
        return '';
    }

    









    private static function is_sub_field( array $field ) {
        $parent = isset( $field['parent'] ) ? $field['parent'] : 0;
        if ( is_numeric( $parent ) ) {
            return (int) $parent > 0 && 'acf-field' === get_post_type( (int) $parent );
        }
        return 0 === strpos( (string) $parent, 'field_' );
    }

    




    public static function rest_on() {
        return true;
    }

    







    public static function expose_groups( $groups ) {
        if ( ! is_array( $groups ) ) {
            return $groups;
        }
        foreach ( $groups as $i => $group ) {
            if ( is_array( $group ) ) {
                $groups[ $i ]['show_in_rest'] = 1;
            }
        }
        return $groups;
    }

    








    public static function read_report( $response, $opened, $object ) {
        if ( is_array( $response ) && array_key_exists( 'acf', $response ) ) {
            return array();
        }
        return array( 'notice' => sprintf( 'ACF did not add its "acf" field to the REST response for this %s, so no ACF value could be read. %s', $object, self::missing_acf_cause( $opened, $object ) ) );
    }

    










    public static function write_report( array $sent, array $saved, $response, $opened, $object ) {
        
        
        $key_map = ( isset( $sent['_acf_field_key_map'] ) && is_array( $sent['_acf_field_key_map'] ) ) ? $sent['_acf_field_key_map'] : array();

        $ignored = array();
        $unknown = array();
        $nested  = array();
        foreach ( array_keys( $sent ) as $submitted ) {
            $submitted = (string) $submitted;
            if ( 0 === strpos( $submitted, '_acf_' ) ) {
                continue;
            }
            $lookup = isset( $key_map[ $submitted ] ) ? (string) $key_map[ $submitted ] : $submitted;
            if ( isset( $saved[ $lookup ] ) ) {
                continue;
            }
            $ignored[] = $submitted;
            $field     = function_exists( 'acf_get_field' ) ? acf_get_field( $lookup ) : null;
            if ( function_exists( 'acf_get_field' ) && ! is_array( $field ) ) {
                $unknown[] = $submitted;
            } elseif ( is_array( $field ) && self::is_sub_field( $field ) ) {
                $nested[] = $submitted;
            }
        }
        if ( empty( $ignored ) ) {
            return array();
        }

        $parts = array( sprintf( 'These ACF fields were sent but not saved: %s.', implode( ', ', $ignored ) ) );
        if ( ! is_array( $response ) || ! array_key_exists( 'acf', $response ) ) {
            $parts[] = sprintf( 'ACF did not add its "acf" field to the REST response for this %s, so it did not process the fields. %s', $object, self::missing_acf_cause( $opened, $object ) );
        } else {
            if ( ! empty( $unknown ) ) {
                $parts[] = sprintf( 'No ACF field has the name or key %s — check the spelling with wp_acf_list_field_groups.', implode( ', ', $unknown ) );
            }
            if ( ! empty( $nested ) ) {
                $parts[] = sprintf( '%s belong%s to a Group, Repeater or Flexible Content field — send %s inside that field\'s value instead.', implode( ', ', $nested ), count( $nested ) > 1 ? '' : 's', count( $nested ) > 1 ? 'them' : 'it' );
            }
            if ( count( $unknown ) + count( $nested ) < count( $ignored ) ) {
                $parts[] = sprintf( 'ACF saves a field only when its field group is active and assigned to this %s by its location rules, and its field type is exposed to the REST API.', $object );
            }
        }

        return array(
            'fields_ignored' => $ignored,
            'notice'         => implode( ' ', $parts ),
        );
    }

    




    private static function missing_acf_cause( $opened, $object ) {
        if ( $opened ) {
            return 'Easy MCP AI turns ACF\'s REST support on for this call, so the likely causes are code that turns it off again after that (a late acf/settings/rest_api_enabled filter), or an endpoint ACF does not recognise.';
        }
        return sprintf( 'Most likely this site has ACF\'s REST support turned off; Easy MCP AI turns it on only for users who can edit the %s.', $object );
    }
}
