<?php
namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\Rest_Routes\Route_Gate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( Post_Type_Rest_Base::class, false ) ) {
    require_once __DIR__ . '/class-post-type-rest-base.php';
}













final class Post_Type_Tool_Hint {

    
    const TOOLS = array(
        'post'              => array(
            'get'    => 'wp_get_post',
            'update' => 'wp_update_post',
            'delete' => 'wp_delete_post',
        ),
        'page'              => array(
            'get'    => 'wp_get_page',
            'update' => 'wp_update_page',
            'delete' => 'wp_delete_page',
        ),
        'attachment'        => array(
            'get'    => 'wp_get_media',
            'update' => 'wp_update_media',
            'delete' => 'wp_delete_media',
        ),
        'revision'          => array(
            'get'    => 'wp_get_revision',
            'delete' => 'wp_delete_revision',
        ),
        'product'           => array(
            'get'    => 'wp_wc_get_product',
            'update' => 'wp_wc_update_product',
            'delete' => 'wp_wc_delete_product',
        ),
        'product_variation' => array(
            'get'    => 'wp_wc_get_product_variation',
            'update' => 'wp_wc_update_product_variation',
            'delete' => 'wp_wc_delete_product_variation',
        ),
    );

    
    const ARGUMENTS = array(
        'post'              => 'post_id %1$d',
        'page'              => 'page_id %1$d',
        'attachment'        => 'media_id %1$d',
        'revision'          => 'post_id %2$d, revision_id %1$d',
        'product'           => 'id %1$d',
        'product_variation' => 'product_id %2$d, id %1$d',
    );

    
    const CPT_TOOLS = array(
        'get'    => 'wp_get_cpt_item',
        'update' => 'wp_update_cpt_item',
        'delete' => 'wp_delete_cpt_item',
    );

    
    const VERBS = array(
        'get'    => 'read',
        'update' => 'update',
        'delete' => 'delete',
    );

    










    public static function other_type( object $post, string $expected_type, string $verb, string $tool ): string {
        $id   = (int) $post->ID;
        $type = (string) $post->post_type;
        foreach ( self::candidates( $post, $verb ) as $candidate ) {
            if ( self::may_use( $candidate[0] ) ) {
                return sprintf( 'Use %1$s for that ID, which has post type "%2$s", not "%3$s": %4$s.', $candidate[0], $type, $expected_type, $candidate[1] );
            }
        }
        return sprintf( 'ID %1$d has post type "%2$s", not "%3$s", so %4$s cannot %5$s it.', $id, $type, $expected_type, $tool, self::VERBS[ $verb ] );
    }

    






    public static function no_route( string $value ): string {
        if ( self::may_use( 'wp_get_post_types' ) ) {
            return sprintf( 'Pass a rest_base or post type slug from wp_get_post_types: no route answers this request at /wp/v2/%s', $value );
        }
        return sprintf( 'Pass the rest_base or slug of a post type shown in the REST API: no route answers this request at /wp/v2/%s', $value );
    }

    








    public static function not_a_post_type( string $value ): string {
        if ( self::may_use( 'wp_get_post_types' ) ) {
            return sprintf( 'Pass a rest_base or post type slug from wp_get_post_types: this tool reaches posts only, and no post type is served at /wp/v2/%s', $value );
        }
        return sprintf( 'Pass the rest_base or slug of a post type shown in the REST API: this tool reaches posts only, and no post type is served at /wp/v2/%s', $value );
    }

    







    public static function other_controller( string $path ): string {
        return sprintf( 'This tool reaches posts only, and on this site another REST controller, not the post type\'s, answers this request: %s', $path );
    }

    






    public static function may_use( string $tool ): bool {
        return class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) && true === \Easy_MCP_AI\MCP\Server::current_call_may_use( $tool );
    }

    







    private static function candidates( object $post, string $verb ): array {
        $type   = (string) $post->post_type;
        $id     = (int) $post->ID;
        $parent = isset( $post->post_parent ) ? (int) $post->post_parent : 0;
        $found  = array();
        if ( isset( self::TOOLS[ $type ][ $verb ] ) && ( 'revision' !== $type || self::revision_route_exists( $post, $verb ) ) ) {
            $found[] = array( self::TOOLS[ $type ][ $verb ], sprintf( self::ARGUMENTS[ $type ], $id, $parent ) );
        }
        $object = Post_Type_Rest_Base::is_builtin( $type ) ? null : \get_post_type_object( $type );
        if ( is_object( $object ) ) {
            $reach = Post_Type_Rest_Base::inspect( $type, $object );
            if ( Post_Type_Rest_Base::OK === $reach['reason'] ) {
                $found[] = array( self::CPT_TOOLS[ $verb ], sprintf( 'item_id %1$d, rest_base "%2$s"', $id, $reach['base'] ) );
            }
        }
        return $found;
    }

    












    private static function revision_route_exists( object $revision, string $verb ): bool {
        $parent = isset( $revision->post_parent ) ? \get_post( (int) $revision->post_parent ) : null;
        $object = is_object( $parent ) ? \get_post_type_object( (string) $parent->post_type ) : null;
        $server = \function_exists( 'rest_get_server' ) ? \rest_get_server() : null;
        if ( empty( $object->show_in_rest ) || ! is_object( $server ) ) {
            return false;
        }
        if ( ! class_exists( Route_Gate::class, false ) ) {
            require_once dirname( __DIR__ ) . '/rest-routes/class-route-gate.php';
        }
        
        $base = ! empty( $object->rest_base ) ? (string) $object->rest_base : (string) $parent->post_type;
        try {
            Route_Gate::resolve( $server, 'delete' === $verb ? 'DELETE' : 'GET', sprintf( '/wp/v2/%s/%d/revisions/%d', $base, (int) $parent->ID, (int) $revision->ID ) );
        } catch ( \RuntimeException $e ) {
            return false;
        }
        return true;
    }
}
