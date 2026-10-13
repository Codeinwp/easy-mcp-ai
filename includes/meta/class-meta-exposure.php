<?php







namespace Easy_MCP_AI\Meta;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../class-class-map-loader.php';

































































final class Meta_Exposure {

    const OPTION_ENABLED_GROUPS = 'easy_mcp_ai_enabled_plugin_groups';

    



    const PROVIDERS = array(
        'rank-math'         => 'Easy_MCP_AI\\Meta\\Providers\\Rank_Math_Meta_Provider',
        'yoast-seo'         => 'Easy_MCP_AI\\Meta\\Providers\\Yoast_Meta_Provider',
        'seopress'          => 'Easy_MCP_AI\\Meta\\Providers\\Seopress_Meta_Provider',
        'the-seo-framework' => 'Easy_MCP_AI\\Meta\\Providers\\Tsf_Meta_Provider',
    );

    
    private static $registered = false;

    
    private static $report = array();

    
    private static $providers = null;

    
    private static $exposed = array();

    
    private static $hooked_types = array();

    




    public static function all_providers() {
        if ( null === self::$providers ) {
            self::$providers = \Easy_MCP_AI\Class_Map_Loader::load( self::PROVIDERS, 'includes/meta/providers', Meta_Field_Provider::class );
        }
        return self::$providers;
    }

    





    public static function is_group_enabled( $slug ) {
        $enabled = get_option( self::OPTION_ENABLED_GROUPS, array() );
        return is_array( $enabled ) && in_array( $slug, $enabled, true );
    }

    





    public static function enabled_providers() {
        $out = array();
        foreach ( self::all_providers() as $slug => $provider ) {
            if ( self::is_group_enabled( $slug ) && $provider->is_active() ) {
                $out[ $slug ] = $provider;
            }
        }
        return $out;
    }

    







    public static function register_for_request( $providers = null ) {
        if ( null === $providers ) {
            if ( self::$registered ) {
                return self::$report;
            }
            self::$registered = true;
            $providers        = self::enabled_providers();
        }
        $providers = array_filter( (array) $providers, static function ( $p ) {
            return $p instanceof Meta_Field_Provider;
        } );
        if ( ! function_exists( 'register_post_meta' ) || ! function_exists( 'get_registered_meta_keys' ) ) {
            return self::$report;
        }
        foreach ( $providers as $provider ) {
            self::$report = array_merge( self::$report, self::register_provider( $provider ) );
        }
        self::hook_rest_filters();
        return self::$report;
    }

    


    private static function hook_rest_filters() {
        foreach ( array_keys( self::$exposed ) as $post_type ) {
            if ( isset( self::$hooked_types[ $post_type ] ) ) {
                continue;
            }
            self::$hooked_types[ $post_type ] = true;
            add_filter(
                "rest_pre_insert_{$post_type}",
                static function ( $prepared_post, $request ) use ( $post_type ) {
                    return self::guard_meta_write( $prepared_post, $request, $post_type );
                },
                10,
                2
            );
            add_filter( "rest_prepare_{$post_type}", array( __CLASS__, 'hide_meta_from_readers' ), 10, 2 );
        }
    }

    









    public static function guard_meta_write( $prepared_post, $request, $post_type ) {
        if ( is_wp_error( $prepared_post ) || empty( self::$exposed[ $post_type ] ) ) {
            return $prepared_post;
        }
        $meta = is_object( $request ) && method_exists( $request, 'get_param' ) ? $request->get_param( 'meta' ) : null;
        if ( ! is_array( $meta ) || empty( $meta ) ) {
            return $prepared_post;
        }
        $user_id = get_current_user_id();
        $post_id = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
        $refused = array();
        foreach ( array_keys( $meta ) as $key ) {
            $key = (string) $key;
            if ( ! isset( self::$exposed[ $post_type ][ $key ] ) ) {
                continue;
            }
            $provider = self::$exposed[ $post_type ][ $key ];
            if ( ! $provider->can_edit_field( $key, $user_id, $post_id ) ) {
                $refused[ $key ] = $provider->get_label();
            }
        }
        if ( empty( $refused ) ) {
            return $prepared_post;
        }
        $parts = array();
        foreach ( $refused as $key => $label ) {
            $parts[] = sprintf( '"%s" (%s restricts this field for your role)', $key, $label );
        }
        return new \WP_Error(
            'easy_mcp_ai_meta_forbidden',
            sprintf( 'You are not allowed to set %s. Nothing was saved.', implode( ', ', $parts ) ),
            array( 'status' => 403 )
        );
    }

    








    public static function hide_meta_from_readers( $response, $post ) {
        if ( ! is_object( $post ) || empty( $post->post_type ) || empty( self::$exposed[ $post->post_type ] ) ) {
            return $response;
        }
        if ( ! is_object( $response ) || ! method_exists( $response, 'get_data' ) || ! method_exists( $response, 'set_data' ) ) {
            return $response;
        }
        if ( current_user_can( 'edit_post', (int) $post->ID ) ) {
            return $response;
        }
        $data = $response->get_data();
        if ( ! is_array( $data ) || empty( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
            return $response;
        }
        $changed = false;
        foreach ( array_keys( self::$exposed[ $post->post_type ] ) as $key ) {
            if ( array_key_exists( $key, $data['meta'] ) ) {
                unset( $data['meta'][ $key ] );
                $changed = true;
            }
        }
        if ( $changed ) {
            $response->set_data( $data );
        }
        return $response;
    }

    




    public static function exposed_keys() {
        $out = array();
        foreach ( self::$exposed as $type => $keys ) {
            $out[ $type ] = array_keys( $keys );
        }
        return $out;
    }

    





    public static function set_providers( array $providers ) {
        self::$providers = array();
        foreach ( $providers as $slug => $provider ) {
            if ( $provider instanceof Meta_Field_Provider ) {
                self::$providers[ (string) $slug ] = $provider;
            }
        }
    }

    


    public static function reset() {
        self::$registered   = false;
        self::$report       = array();
        self::$providers    = null;
        self::$exposed      = array();
        self::$hooked_types = array();
    }

    






    public static function register_provider( Meta_Field_Provider $provider ) {
        $report = array();
        $global = get_registered_meta_keys( 'post', '' );
        $fields = $provider->get_fields();

        foreach ( $provider->get_post_types() as $post_type ) {
            $post_type = (string) $post_type;
            if ( '' === $post_type ) {
                continue;
            }
            $subtype = get_registered_meta_keys( 'post', $post_type );

            foreach ( $fields as $key => $field ) {
                $slot = $post_type . ':' . $key;

                
                if ( isset( $subtype[ $key ] ) ) {
                    $report[ $slot ] = 'vendor';
                    continue;
                }
                
                if ( isset( $global[ $key ] ) && ! empty( $global[ $key ]['show_in_rest'] ) ) {
                    $report[ $slot ] = 'vendor';
                    continue;
                }

                $vendor_sanitize = isset( $global[ $key ]['sanitize_callback'] ) && is_callable( $global[ $key ]['sanitize_callback'] )
                    ? $global[ $key ]['sanitize_callback']
                    : null;
                
                
                $vendor_auth = null;
                if ( isset( $global[ $key ]['auth_callback'] ) && is_callable( $global[ $key ]['auth_callback'] )
                    && ! in_array( $global[ $key ]['auth_callback'], array( '__return_true', '__return_false' ), true ) ) {
                    $vendor_auth = $global[ $key ]['auth_callback'];
                }
                
                
                
                $subtype_filter_exists = (bool) has_filter( "auth_post_meta_{$key}_for_{$post_type}" );

                $registered = register_post_meta( $post_type, $key, self::registration_args( $provider, $key, $field, $vendor_sanitize, $vendor_auth, $subtype_filter_exists ) );

                $report[ $slot ] = $registered ? 'registered' : 'failed';
                if ( $registered ) {
                    self::$exposed[ $post_type ][ $key ] = $provider;
                }
            }
        }
        return $report;
    }

    










    public static function registration_args( Meta_Field_Provider $provider, $key, array $field, $vendor_sanitize = null, $vendor_auth = null, $respect_allowed = false ) {
        $type   = isset( $field['type'] ) ? (string) $field['type'] : 'string';
        $schema = array();
        if ( 'array' === $type ) {
            $schema['items'] = isset( $field['items'] ) ? $field['items'] : array( 'type' => 'string' );
        } elseif ( 'object' === $type ) {
            $schema['properties']           = isset( $field['properties'] ) ? $field['properties'] : array();
            $schema['additionalProperties'] = false;
        } elseif ( 'string' === $type && ! empty( $field['enum'] ) && empty( $field['enum_is_hint'] ) ) {
            $schema['enum'] = array_values( (array) $field['enum'] );
        }

        return array(
            'type'              => $type,
            'single'            => true,
            'description'       => isset( $field['description'] ) ? (string) $field['description'] : '',
            'show_in_rest'      => empty( $schema ) ? true : array( 'schema' => $schema ),
            'sanitize_callback' => static function ( $value, $meta_key, $object_type = 'post', $object_subtype = '' ) use ( $provider, $key, $vendor_sanitize ) {
                $value = $provider->sanitize( $key, $value );
                if ( null !== $vendor_sanitize ) {
                    $value = call_user_func( $vendor_sanitize, $value, $meta_key, $object_type, $object_subtype );
                }
                return $value;
            },
            
            
            
            'auth_callback'     => static function ( $allowed, $meta_key, $object_id, $user_id = 0, $cap = '', $caps = array() ) use ( $provider, $key, $vendor_auth, $respect_allowed ) {
                $user_id   = (int) $user_id;
                $object_id = (int) $object_id;
                if ( ! user_can( $user_id, 'edit_post', $object_id ) ) {
                    return false;
                }
                if ( ! $provider->can_edit_field( $key, $user_id, $object_id ) ) {
                    return false;
                }
                if ( $respect_allowed && ! $allowed ) {
                    return false;
                }
                if ( null !== $vendor_auth && ! call_user_func( $vendor_auth, $allowed, $meta_key, $object_id, $user_id, $cap, $caps ) ) {
                    return false;
                }
                return true;
            },
        );
    }

    




    public static function writable_keys() {
        $out = array();
        foreach ( self::enabled_providers() as $provider ) {
            foreach ( array_keys( $provider->get_fields() ) as $key ) {
                $out[ $key ] = $provider->get_label();
            }
        }
        return $out;
    }

    





    public static function describe_writable_keys() {
        $parts = array();
        foreach ( self::enabled_providers() as $provider ) {
            $parts[] = $provider->get_label() . ' (' . implode( ', ', array_keys( $provider->get_fields() ) ) . ')';
        }
        if ( empty( $parts ) ) {
            return '';
        }
        return ' Plugin integration keys writable on this site: ' . implode( '; ', $parts ) . '.';
    }

    





    public static function provider_for_key( $key ) {
        $providers = self::all_providers();
        foreach ( $providers as $provider ) {
            if ( array_key_exists( (string) $key, $provider->get_fields() ) ) {
                return $provider;
            }
        }
        foreach ( $providers as $provider ) {
            if ( $provider->owns_key( $key ) ) {
                return $provider;
            }
        }
        return null;
    }

    






    public static function explain_ignored_keys( array $keys ) {
        $out = array();
        foreach ( $keys as $key ) {
            $key      = (string) $key;
            $provider = self::provider_for_key( $key );
            if ( null === $provider ) {
                continue;
            }
            $label = $provider->get_label();
            $slug  = $provider->get_group_slug();
            $tool  = $provider->get_dedicated_tool();

            if ( ! $provider->is_active() ) {
                $out[ $key ] = sprintf( '"%s" is a %s meta key, but %s is not active on this site.', $key, $label, $label );
                continue;
            }
            if ( ! self::is_group_enabled( $slug ) ) {
                $out[ $key ] = sprintf(
                    '"%s" is a %s meta key. %s is installed but its integration is not enabled under Easy MCP AI → Tools → Plugins; once an administrator enables it, this key can be written here%s.',
                    $key,
                    $label,
                    $label,
                    '' !== $tool ? ' and ' . $tool . ' becomes available' : ''
                );
                continue;
            }
            if ( ! array_key_exists( $key, $provider->get_fields() ) ) {
                $out[ $key ] = sprintf(
                    '"%s" is a %s meta key that Easy MCP AI does not expose. Writable %s keys: %s.',
                    $key,
                    $label,
                    $label,
                    implode( ', ', array_keys( $provider->get_fields() ) )
                );
                continue;
            }
            $out[ $key ] = sprintf(
                '"%s" is exposed for %s on public, REST-enabled post types; %s may have registered it for this post type itself without REST exposure, in which case Easy MCP AI defers to it%s.',
                $key,
                $label,
                $label,
                '' !== $tool ? ' — use ' . $tool : ''
            );
        }
        return $out;
    }
}
