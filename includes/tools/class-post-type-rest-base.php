<?php
namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\Rest_Routes\Route_Gate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Post_Type_Rest_Base {

    const BUILTIN_TYPES = array( 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face' );

    const RESERVED_REST_BASES = array( 'posts', 'pages', 'media', 'blocks', 'templates', 'template-parts', 'global-styles', 'navigation', 'menu-items', 'menus', 'menu-locations', 'font-families', 'font-collections', 'users', 'comments', 'taxonomies', 'categories', 'tags', 'types', 'statuses', 'settings', 'themes', 'plugins', 'block-types', 'block-renderer', 'block-directory', 'block-patterns', 'pattern-directory', 'search', 'sidebars', 'widgets', 'widget-types', 'application-passwords', 'revisions', 'autosaves' );

    const OK = 'ok';

    const NOT_REST = 'not_rest';

    const FOREIGN_NAMESPACE = 'namespace';

    const UNSAFE_BASE = 'base';

    const SHARED_BASE = 'shared';

    public static function resolve( string $value ): string {
        $served = self::served_type( $value );
        if ( null !== $served ) {
            return self::text( $served[1], 'rest_base', $served[0] );
        }
        $object = \get_post_type_object( $value );
        if ( ! is_object( $object ) ) {
            return $value;
        }
        $found = self::inspect( $value, $object );
        if ( self::OK !== $found['reason'] ) {
            throw new \InvalidArgumentException( self::refusal( $value, $found ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $found['base'];
    }

    public static function inspect( string $post_type, object $object ): array {
        $found = array(
            'reason'    => self::OK,
            'base'      => '',
            'namespace' => '',
            'owner'     => '',
        );
        if ( empty( $object->show_in_rest ) ) {
            $found['reason'] = self::NOT_REST;
            return $found;
        }
        $found['namespace'] = self::text( $object, 'rest_namespace', 'wp/v2' );
        if ( 'wp/v2' !== $found['namespace'] ) {
            $found['reason'] = self::FOREIGN_NAMESPACE;
            return $found;
        }
        $found['base'] = self::text( $object, 'rest_base', $post_type );
        $custom        = ! self::is_builtin( $post_type );
        if ( ! preg_match( '/^[A-Za-z0-9_-]+\z/', $found['base'] ) || ( $custom && in_array( strtolower( $found['base'] ), self::RESERVED_REST_BASES, true ) ) ) {
            $found['reason'] = self::UNSAFE_BASE;
            return $found;
        }
        $owner = $custom ? self::owner( $found['base'] ) : null;
        if ( null !== $owner && $owner !== $post_type ) {
            $found['reason'] = self::SHARED_BASE;
            $found['owner']  = $owner;
        }
        return $found;
    }

    public static function is_builtin( string $post_type ): bool {
        return 'post' === $post_type || 'page' === $post_type || in_array( $post_type, self::BUILTIN_TYPES, true );
    }

    public static function served_type( string $base ): ?array {
        foreach ( self::wp_v2_types() as $name => $object ) {
            if ( 0 === strcasecmp( self::text( $object, 'rest_base', $name ), $base ) ) {
                return array( $name, $object );
            }
        }
        return null;
    }

    public static function require_served_type( string $base ): array {
        $served = self::served_type( $base );
        if ( null === $served ) {
            throw new \InvalidArgumentException( Post_Type_Tool_Hint::not_a_post_type( $base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $served;
    }

    public static function require_own_route( string $method, string $base, string $path ): void {
        $server = \function_exists( 'rest_get_server' ) ? \rest_get_server() : null;
        if ( ! is_object( $server ) ) {
            return;
        }
        try {
            $handler = Route_Gate::resolve( $server, $method, $path )['handler'];
        } catch ( \RuntimeException $e ) {
            throw new \InvalidArgumentException( Post_Type_Tool_Hint::no_route( $base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $served     = self::require_served_type( $base );
        $controller = method_exists( $served[1], 'get_rest_controller' ) ? $served[1]->get_rest_controller() : null;
        if ( ! is_object( $controller ) ) {
            return;
        }
        $answers = isset( $handler['callback'] ) && is_array( $handler['callback'] ) && isset( $handler['callback'][0] ) ? $handler['callback'][0] : null;
        if ( ! is_object( $answers ) || ! is_a( $answers, get_class( $controller ) ) ) {
            throw new \InvalidArgumentException( Post_Type_Tool_Hint::other_controller( $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function wp_v2_types(): array {
        $types = array();
        foreach ( (array) \get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $key => $object ) {
            if ( ! is_object( $object ) || empty( $object->show_in_rest ) || 'wp/v2' !== self::text( $object, 'rest_namespace', 'wp/v2' ) || ! self::registers_routes( $object ) ) {
                continue;
            }
            $types[ isset( $object->name ) ? (string) $object->name : (string) $key ] = $object;
        }
        return $types;
    }

    private static function registers_routes( object $object ): bool {
        return ! method_exists( $object, 'get_rest_controller' ) || is_object( $object->get_rest_controller() );
    }

    private static function owner( string $base ): ?string {
        foreach ( self::wp_v2_types() as $name => $other ) {
            if ( ! self::is_builtin( $name ) && 0 === strcasecmp( self::text( $other, 'rest_base', $name ), $base ) ) {
                return $name;
            }
        }
        return null;
    }

    private static function text( object $object, string $property, string $fallback ): string {
        return isset( $object->$property ) && is_string( $object->$property ) && ! empty( $object->$property ) ? $object->$property : $fallback;
    }

    private static function refusal( string $post_type, array $found ): string {
        switch ( $found['reason'] ) {
            case self::NOT_REST:
                return sprintf( 'Post type "%s" is not shown in the REST API (show_in_rest is false), and this tool reaches posts only through it.', $post_type );
            case self::FOREIGN_NAMESPACE:
                return sprintf( 'Post type "%1$s" is not served under /wp/v2, the only namespace this tool addresses, but under %2$s.', $post_type, $found['namespace'] );
            case self::SHARED_BASE:
                return sprintf( 'Post type "%1$s" cannot be reached: post type "%2$s" has the same REST base and WordPress serves it first (rest_base "%3$s").', $post_type, $found['owner'], $found['base'] );
            default:
                return sprintf( 'Post type "%1$s" has a REST base this tool cannot address safely: "%2$s".', $post_type, $found['base'] );
        }
    }
}
