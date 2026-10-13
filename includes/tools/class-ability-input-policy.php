<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















































class Ability_Input_Policy {

    







    const REFUSED_PATHS = array(
        
        
        'scf/create-post-type'     => array( 'register_meta_box_cb', 'rest_controller_class' ),
        'scf/update-post-type'     => array( 'register_meta_box_cb', 'rest_controller_class' ),
        'scf/import-post-type'     => array( 'register_meta_box_cb', 'rest_controller_class' ),
        
        'scf/create-taxonomy'      => array( 'meta_box_cb', 'meta_box_sanitize_cb', 'rest_controller_class' ),
        'scf/update-taxonomy'      => array( 'meta_box_cb', 'meta_box_sanitize_cb', 'rest_controller_class' ),
        'scf/import-taxonomy'      => array( 'meta_box_cb', 'meta_box_sanitize_cb', 'rest_controller_class' ),
        
        'meta-box/create-post-type' => array( 'settings.register_meta_box_cb', 'settings.rest_controller_class' ),
        'meta-box/update-post-type' => array( 'settings.register_meta_box_cb', 'settings.rest_controller_class' ),
        
        
        
        
        
        'meta-box/create-taxonomy'  => array( 'settings.meta_box_cb', 'settings.meta_box_sanitize_cb', 'settings.update_count_callback', 'settings.rest_controller_class' ),
        'meta-box/update-taxonomy'  => array( 'settings.meta_box_cb', 'settings.meta_box_sanitize_cb', 'settings.update_count_callback', 'settings.rest_controller_class' ),
    );

    



    const MAX_SLUG_LENGTH = array(
        'post_type' => 20,
        'taxonomy'  => 32,
    );

    





    const SLUG_GUARDED = array(
        'meta-box/create-post-type' => 'post_type',
        'meta-box/update-post-type' => 'post_type',
        'meta-box/create-taxonomy'  => 'taxonomy',
        'meta-box/update-taxonomy'  => 'taxonomy',
    );

    







    const RESERVED_POST_TYPES = array(
        'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css',
        'customize_changeset', 'oembed_cache', 'user_request', 'wp_block',
        'wp_global_styles', 'wp_navigation', 'wp_template', 'wp_template_part',
        'action', 'author', 'order', 'theme',
    );

    







    const RESERVED_TAXONOMIES = array(
        'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat',
        'category', 'category__and', 'category__in', 'category__not_in',
        'category_name', 'comments_per_page', 'comments_popup', 'custom',
        'customize_messenger_channel', 'customized', 'cpage', 'day', 'debug',
        'embed', 'error', 'exact', 'feed', 'fields', 'hour', 'link_category', 'm',
        'minute', 'monthnum', 'more', 'name', 'nav_menu', 'nonce', 'nopaging',
        'offset', 'order', 'orderby', 'p', 'page', 'page_id', 'paged', 'pagename',
        'pb', 'perm', 'post', 'post__in', 'post__not_in', 'post_format',
        'post_mime_type', 'post_status', 'post_tag', 'post_type', 'posts',
        'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's',
        'search', 'second', 'sentence', 'showposts', 'static', 'status', 'subpost',
        'subpost_id', 'tag', 'tag__and', 'tag__in', 'tag__not_in', 'tag_id',
        'tag_slug__and', 'tag_slug__in', 'taxonomy', 'tb', 'term', 'terms', 'theme',
        'title', 'type', 'types', 'w', 'withcomments', 'withoutcomments', 'year',
    );

    





    public static function applies_to( $slug ) {
        return isset( self::REFUSED_PATHS[ $slug ] ) || isset( self::SLUG_GUARDED[ $slug ] );
    }

    





    public static function refused_paths( $slug ) {
        return isset( self::REFUSED_PATHS[ $slug ] ) ? self::REFUSED_PATHS[ $slug ] : array();
    }

    











    public static function strip_schema( $slug, $schema ) {
        $paths = self::refused_paths( $slug );
        if ( empty( $paths ) || ! is_array( $schema ) ) {
            return $schema;
        }
        foreach ( $paths as $path ) {
            $schema = self::remove_path_from_schema( $schema, explode( '.', $path ) );
        }
        return $schema;
    }

    






    public static function describe( $slug ) {
        $parts = array();
        $paths = self::refused_paths( $slug );
        if ( ! empty( $paths ) ) {
            $keys    = array();
            foreach ( $paths as $path ) {
                $segments = explode( '.', $path );
                $keys[]   = end( $segments );
            }
            $parts[] = 'Easy MCP AI refuses PHP callback and REST controller settings (' . implode( ', ', array_unique( $keys ) ) . ').';
        }
        if ( isset( self::SLUG_GUARDED[ $slug ] ) ) {
            $kind    = self::SLUG_GUARDED[ $slug ];
            $parts[] = sprintf(
                'settings.slug must be a lowercase key (a-z, 0-9, _ -, at most %d characters) that is not an existing or reserved %s name.',
                self::MAX_SLUG_LENGTH[ $kind ],
                'taxonomy' === $kind ? 'taxonomy' : 'post type'
            );
        }
        return implode( ' ', $parts );
    }

    







    public static function violation( $slug, $arguments ) {
        if ( ! is_array( $arguments ) ) {
            return null;
        }

        foreach ( self::refused_paths( $slug ) as $path ) {
            if ( self::path_is_present( $arguments, explode( '.', $path ) ) ) {
                return sprintf(
                    "Refused: '%s' names a PHP callback or REST controller class. Easy MCP AI does not accept callback or controller-class settings through MCP; set them in the plugin's own admin screen if they are genuinely needed.",
                    $path
                );
            }
        }

        if ( isset( self::SLUG_GUARDED[ $slug ] ) ) {
            return self::slug_violation( $slug, self::SLUG_GUARDED[ $slug ], $arguments );
        }

        return null;
    }

    
    
    

    





    private static function slug_violation( $slug, $kind, array $arguments ) {
        $label     = 'taxonomy' === $kind ? 'taxonomy' : 'post type';
        $is_create = 0 === strpos( $slug, 'meta-box/create-' );
        $settings  = isset( $arguments['settings'] ) && is_array( $arguments['settings'] ) ? $arguments['settings'] : array();

        if ( ! array_key_exists( 'slug', $settings ) ) {
            
            
            return $is_create
                ? sprintf( "Refused: 'settings.slug' is required — a %s definition without a slug cannot be registered.", $label )
                : null;
        }

        $candidate = $settings['slug'];

        
        
        
        
        if ( ! is_string( $candidate ) || '' === $candidate ) {
            return sprintf( "Refused: 'settings.slug' must be a non-empty string; a %s slug of any other type breaks registration on every request.", $label );
        }

        
        
        
        
        
        
        
        if ( ! preg_match( '/^[a-z0-9_\-]+\z/', $candidate ) || strlen( $candidate ) > self::MAX_SLUG_LENGTH[ $kind ] ) {
            return sprintf(
                "Refused: '%s' is not a valid %s slug — use lowercase letters, digits, underscores or hyphens, at most %d characters.",
                $candidate,
                $label,
                self::MAX_SLUG_LENGTH[ $kind ]
            );
        }

        if ( self::is_reserved( $kind, $candidate ) ) {
            return sprintf( "Refused: '%s' is a name WordPress reserves; it cannot be used as a %s slug.", $candidate, $label );
        }

        
        
        if ( 0 === strpos( $slug, 'meta-box/update-' ) && self::is_current_slug_of_definition( $arguments, $candidate ) ) {
            return null;
        }

        $exists = 'taxonomy' === $kind
            ? ( \function_exists( 'taxonomy_exists' ) && \taxonomy_exists( $candidate ) )
            : ( \function_exists( 'post_type_exists' ) && \post_type_exists( $candidate ) );
        if ( $exists ) {
            return sprintf( "Refused: a %s named '%s' is already registered on this site.", $label, $candidate );
        }

        return null;
    }

    




    private static function is_reserved( $kind, $candidate ) {
        $reserved = 'taxonomy' === $kind ? self::RESERVED_TAXONOMIES : self::RESERVED_POST_TYPES;
        if ( in_array( $candidate, $reserved, true ) ) {
            return true;
        }
        
        if ( isset( $GLOBALS['wp'] ) && is_object( $GLOBALS['wp'] ) ) {
            foreach ( array( 'public_query_vars', 'private_query_vars' ) as $prop ) {
                if ( isset( $GLOBALS['wp']->{$prop} ) && is_array( $GLOBALS['wp']->{$prop} ) && in_array( $candidate, $GLOBALS['wp']->{$prop}, true ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    









    private static function is_current_slug_of_definition( array $arguments, $candidate ) {
        if ( empty( $arguments['id'] ) || ! \function_exists( 'get_post' ) ) {
            return false;
        }
        $post = \get_post( (int) $arguments['id'] );
        if ( ! $post || ! isset( $post->post_content ) || ! is_string( $post->post_content ) ) {
            return false;
        }
        $stored = json_decode( $post->post_content, true );
        return is_array( $stored ) && isset( $stored['slug'] ) && $stored['slug'] === $candidate;
    }

    
    
    

    




    private static function path_is_present( array $arguments, array $segments ) {
        $node = $arguments;
        foreach ( $segments as $segment ) {
            if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
                return false;
            }
            $node = $node[ $segment ];
        }
        return true;
    }

    




    private static function remove_path_from_schema( array $schema, array $segments ) {
        $key = array_shift( $segments );
        if ( ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) || ! array_key_exists( $key, $schema['properties'] ) ) {
            return $schema;
        }
        if ( empty( $segments ) ) {
            unset( $schema['properties'][ $key ] );
            if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
                $schema['required'] = array_values( array_diff( $schema['required'], array( $key ) ) );
                if ( empty( $schema['required'] ) ) {
                    unset( $schema['required'] );
                }
            }
            return $schema;
        }
        if ( is_array( $schema['properties'][ $key ] ) ) {
            $schema['properties'][ $key ] = self::remove_path_from_schema( $schema['properties'][ $key ], $segments );
        }
        return $schema;
    }
}
