<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Hidden_Post_Types {

    const POST_TYPE_NAMESPACE = 'easy-mcp-ai-post-types/v1';

    const TAXONOMY_NAMESPACE = 'easy-mcp-ai-taxonomies/v1';

    const FILTER = 'easy_mcp_ai_open_hidden_post_type';

    const NAMESPACES = array(
        'post_type' => self::POST_TYPE_NAMESPACE,
        'taxonomy'  => self::TAXONOMY_NAMESPACE,
    );

    const PROPERTIES = array( 'show_in_rest', 'rest_base', 'rest_namespace', 'rest_controller' );

    const RESERVED_FIELDS = array( '_fields', 'author', 'author_exclude', 'class_list', 'comment_status', 'content', 'excerpt', 'featured_media', 'format', 'generated_slug', 'ignore_sticky', 'menu_order', 'meta', 'parent', 'parent_exclude', 'permalink_template', 'ping_status', 'search_semantics', 'sticky', 'title' );

    private static array $open = array();

    private static array $routed = array();

    private static array $left_out = array();

    public static function reaches( string $post_type, object $object ): bool {
        if ( ! self::may_open( $post_type, $object ) ) {
            return false;
        }
        return true === \apply_filters( self::FILTER, self::opens_by_default( $post_type, $object ), $post_type, $object );
    }

    public static function closed_reason( string $post_type, object $object ): string {
        if ( ! empty( $object->show_in_rest ) || Post_Type_Rest_Base::is_builtin( $post_type ) || self::reaches( $post_type, $object ) ) {
            return '';
        }
        $served = Post_Type_Rest_Base::served_type( $post_type );
        if ( null !== $served ) {
            return sprintf( 'its slug is the REST base of post type "%s", which WordPress serves first', $served[0] );
        }
        if ( ! self::may_open( $post_type, $object ) ) {
            return 'it names a REST controller class of its own';
        }
        if ( self::opens_by_default( $post_type, $object ) ) {
            return sprintf( 'the %s filter keeps it closed', self::FILTER );
        }
        return sprintf( '%1$s (the %2$s filter can open it)', empty( $object->show_ui ) ? 'it has no admin screen' : 'it is not public', self::FILTER );
    }

    public static function reachable_types(): array {
        $types = array();
        foreach ( (array) \get_post_types( array(), 'objects' ) as $slug => $object ) {
            if ( is_object( $object ) && self::reaches( (string) $slug, $object ) ) {
                $types[ (string) $slug ] = $object;
            }
        }
        return $types;
    }

    public static function reaches_taxonomy( string $taxonomy, object $object ): bool {
        if ( ! empty( $object->show_in_rest ) || ! empty( $object->_builtin ) || empty( $object->show_ui )
            || ! self::uses_own_controller( $object, 'WP_REST_Terms_Controller' ) || ! \is_taxonomy_viewable( $taxonomy ) ) {
            return false;
        }
        foreach ( (array) $object->object_type as $post_type ) {
            $type = \get_post_type_object( (string) $post_type );
            if ( is_object( $type ) && self::reaches( (string) $post_type, $type ) ) {
                return true;
            }
        }
        return false;
    }

    public static function reachable_taxonomies(): array {
        $taxonomies = array();
        foreach ( array_keys( self::reachable_types() ) as $post_type ) {
            $taxonomies += self::hidden_taxonomies_of( $post_type );
        }
        return $taxonomies;
    }

    public static function taxonomy_field( string $taxonomy, object $object ): string {
        return empty( $object->rest_base ) ? $taxonomy : (string) $object->rest_base;
    }

    public static function run_for( $value, callable $call ) {
        $object = is_string( $value ) ? \get_post_type_object( $value ) : null;
        if ( ! is_object( $object ) || ! self::reaches( $value, $object ) ) {
            return $call();
        }
        $taxonomies = self::hidden_taxonomies_of( $value );
        return self::run(
            array( array( 'post_type', $value, $object, false ) ),
            static function () use ( $value, $object, $taxonomies, $call ) {
                $taken    = self::rest_taxonomy_fields( $value ) + array_fill_keys( array_merge( self::RESERVED_FIELDS, self::post_fields( $object ) ), '' );
                $openings = array();
                $left_out = array();
                foreach ( $taxonomies as $name => $taxonomy ) {
                    $field = self::taxonomy_field( $name, $taxonomy );
                    if ( array_key_exists( $field, $taken ) ) {
                        $left_out[ $name ] = array(
                            'field' => $field,
                            'by'    => $taken[ $field ],
                        );
                        continue;
                    }
                    $taken[ $field ] = (string) $name;
                    $openings[]      = array( 'taxonomy', (string) $name, $taxonomy, false );
                }
                $outer          = self::$left_out;
                self::$left_out = $left_out;
                try {
                    return self::run(
                        $openings,
                        static function () use ( $value, $object, $call ) {
                            self::register_routes( 'post_type', $value, $object );
                            return $call();
                        }
                    );
                } finally {
                    self::$left_out = $outer;
                }
            }
        );
    }

    public static function left_out( string $taxonomy ): ?array {
        return self::$left_out[ $taxonomy ] ?? null;
    }

    public static function run_for_taxonomy( $value, callable $call ) {
        $object = is_string( $value ) ? \get_taxonomy( $value ) : null;
        if ( ! is_object( $object ) || ! self::reaches_taxonomy( $value, $object ) ) {
            return $call();
        }
        return self::run( array( array( 'taxonomy', $value, $object, true ) ), $call );
    }

    public static function open_type( string $base ): ?array {
        $key = 'post_type:' . $base;
        return isset( self::$open[ $key ] ) ? array( $base, self::$open[ $key ]['object'] ) : null;
    }

    public static function refuse_unreadable( string $base ): void {
        $open = self::open_type( $base );
        if ( null === $open || \is_post_type_viewable( $base ) ) {
            return;
        }
        $cap = (string) $open[1]->cap->edit_posts;
        if ( ! \current_user_can( $cap ) ) {
            throw new \RuntimeException( sprintf( 'Reading post type "%1$s" needs the %2$s capability: the type is not public, so its items are shown only to users who can edit them.', $base, $cap ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    public static function drop_closed_routes( $endpoints ) {
        if ( ! is_array( $endpoints ) ) {
            return $endpoints;
        }
        foreach ( array_keys( $endpoints ) as $route ) {
            $key = self::route_key( (string) $route );
            if ( null !== $key && ! isset( self::$open[ $key ] ) ) {
                unset( $endpoints[ $route ] );
            }
        }
        return $endpoints;
    }

    private static function hidden_taxonomies_of( string $post_type ): array {
        $taxonomies = array();
        foreach ( (array) \get_object_taxonomies( $post_type, 'objects' ) as $name => $taxonomy ) {
            if ( is_object( $taxonomy ) && self::reaches_taxonomy( (string) $name, $taxonomy ) ) {
                $taxonomies[ (string) $name ] = $taxonomy;
            }
        }
        return $taxonomies;
    }

    private static function rest_taxonomy_fields( string $post_type ): array {
        $fields = array();
        foreach ( (array) \get_object_taxonomies( $post_type, 'objects' ) as $name => $taxonomy ) {
            if ( is_object( $taxonomy ) && ! empty( $taxonomy->show_in_rest ) ) {
                $field                         = self::taxonomy_field( (string) $name, $taxonomy );
                $fields[ $field ]              = (string) $name;
                $fields[ $field . '_exclude' ] = (string) $name;
            }
        }
        return $fields;
    }

    private static function post_fields( object $object ): array {
        $controller              = method_exists( $object, 'get_rest_controller' ) ? $object->get_rest_controller() : null;
        $object->rest_controller = null;
        if ( ! is_object( $controller ) ) {
            return array();
        }
        $schema = $controller->get_item_schema();
        return array_map( 'strval', array_merge( array_keys( (array) ( $schema['properties'] ?? array() ) ), array_keys( (array) $controller->get_collection_params() ) ) );
    }

    private static function may_open( string $post_type, object $object ): bool {
        return empty( $object->show_in_rest ) && ! Post_Type_Rest_Base::is_builtin( $post_type )
            && self::uses_own_controller( $object, 'WP_REST_Posts_Controller' ) && null === Post_Type_Rest_Base::served_type( $post_type );
    }

    private static function opens_by_default( string $post_type, object $object ): bool {
        return ! empty( $object->show_ui ) && \is_post_type_viewable( $post_type );
    }

    private static function uses_own_controller( object $object, string $class ): bool {
        $named = isset( $object->rest_controller_class ) && is_string( $object->rest_controller_class ) ? ltrim( $object->rest_controller_class, '\\' ) : '';
        return '' === $named || $class === $named;
    }

    private static function run( array $openings, callable $call ) {
        $opened = array();
        try {
            foreach ( $openings as $opening ) {
                list( $kind, $name, $object, $routes ) = $opening;
                $opened[] = self::open( $kind, $name, $object, $routes );
                if ( $routes ) {
                    self::register_routes( $kind, $name, $object );
                }
            }
            return $call();
        } finally {
            foreach ( array_reverse( $opened ) as $key ) {
                self::close( $key );
            }
        }
    }

    private static function open( string $kind, string $name, object $object, bool $routes ): string {
        $key   = $kind . ':' . $name;
        $saved = array();
        foreach ( self::PROPERTIES as $property ) {
            $saved[ $property ] = $object->$property ?? null;
        }
        self::$open[ $key ] = array(
            'object' => $object,
            'saved'  => $saved,
        );
        $object->show_in_rest   = true;
        $object->rest_namespace = self::NAMESPACES[ $kind ];
        if ( 'post_type' === $kind || $routes ) {
            $object->rest_base = $name;
        }
        return $key;
    }

    private static function close( string $key ): void {
        $object = self::$open[ $key ]['object'];
        foreach ( self::$open[ $key ]['saved'] as $property => $value ) {
            $object->$property = $value;
        }
        unset( self::$open[ $key ] );
    }

    private static function register_routes( string $kind, string $name, object $object ): void {
        $key    = $kind . ':' . $name;
        $server = \rest_get_server();
        if ( isset( self::$routed[ $key ] ) && self::$routed[ $key ] === $server ) {
            return;
        }
        \add_filter( 'rest_endpoints', array( __CLASS__, 'drop_closed_routes' ), PHP_INT_MAX );
        $controller = method_exists( $object, 'get_rest_controller' ) ? $object->get_rest_controller() : null;
        if ( ! is_object( $controller ) || ! method_exists( $controller, 'register_routes' ) ) {
            throw new \RuntimeException( sprintf( 'WordPress built no REST controller for %1$s "%2$s", so the tools cannot reach it.', 'taxonomy' === $kind ? 'taxonomy' : 'post type', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $controller->register_routes();
        self::$routed[ $key ] = $server;
    }

    private static function route_key( string $route ): ?string {
        foreach ( self::NAMESPACES as $kind => $namespace ) {
            $prefix = '/' . $namespace;
            if ( $route === $prefix ) {
                return '';
            }
            if ( 0 === strpos( $route, $prefix . '/' ) ) {
                return $kind . ':' . explode( '/', substr( $route, strlen( $prefix ) + 1 ), 2 )[0];
            }
        }
        return null;
    }
}
