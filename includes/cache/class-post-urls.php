<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-url-guard.php';









final class Post_Urls {

    





    public static function expand( array $post_ids ): array {
        $rows    = array();
        $dropped = array();
        foreach ( $post_ids as $post_id ) {
            $post = \get_post( (int) $post_id );
            if ( ! $post ) {
                continue;
            }
            foreach ( self::candidates( $post ) as $row ) {
                self::add( $rows, $dropped, $row );
            }
        }
        self::add( $rows, $dropped, array( 'url' => (string) \home_url( '/' ), 'source' => 'home' ) );
        return array(
            'rows'    => array_values( $rows ),
            'dropped' => $dropped,
        );
    }

    







    private static function add( array &$rows, array &$dropped, array $row ): void {
        if ( '' === $row['url'] ) {
            return;
        }
        try {
            $row['url'] = Url_Guard::check( $row['url'] );
        } catch ( \InvalidArgumentException $e ) {
            
            
            $dropped[] = array(
                'url'    => $row['url'],
                'reason' => 'WordPress gives the post this address and the URL guard refused it, so caches that take only URLs are not sent it; a cache plugin\'s own post purge is not affected. Guard: ' . $e->getMessage(),
            );
            return;
        }
        if ( ! isset( $rows[ $row['url'] ] ) ) {
            $rows[ $row['url'] ] = $row;
        }
    }

    





    private static function candidates( object $post ): array {
        $id   = (int) $post->ID;
        $list = array(
            array(
                'url'     => self::permalink( $post ),
                'source'  => 'permalink',
                'post_id' => $id,
            ),
        );
        foreach ( self::translations( $post ) as $translation_id ) {
            $translation = \get_post( $translation_id );
            if ( $translation && \current_user_can( 'read_post', $translation_id ) ) {
                $list[] = array(
                    'url'     => self::permalink( $translation ),
                    'source'  => 'translation',
                    'post_id' => $translation_id,
                );
            }
        }
        foreach ( self::language_variants( $list[0]['url'] ) as $variant ) {
            $list[] = array(
                'url'     => $variant,
                'source'  => 'translation',
                'post_id' => $id,
            );
        }
        return array_merge( $list, self::archives( $post ) );
    }

    







    private static function language_variants( string $url ): array {
        if ( '' === $url ) {
            return array();
        }
        $urls = array();
        if ( class_exists( 'TRP_Translate_Press' ) && method_exists( 'TRP_Translate_Press', 'get_trp_instance' ) ) {
            $trp       = \TRP_Translate_Press::get_trp_instance();
            $converter = $trp->get_component( 'url_converter' );
            $settings  = $trp->get_component( 'settings' );
            $languages = is_object( $settings ) && method_exists( $settings, 'get_settings' ) ? (array) ( $settings->get_settings()['translation-languages'] ?? array() ) : array();
            foreach ( $languages as $language ) {
                if ( is_object( $converter ) && method_exists( $converter, 'get_url_for_language' ) ) {
                    $urls[] = (string) $converter->get_url_for_language( (string) $language, $url, '' );
                }
            }
        }
        if ( function_exists( 'weglot_create_url_object' ) ) {
            foreach ( (array) \weglot_create_url_object( $url )->getAllUrls() as $row ) {
                if ( is_array( $row ) && isset( $row['url'] ) && empty( $row['excluded'] ) ) {
                    $urls[] = (string) $row['url'];
                }
            }
        }
        return array_values( array_diff( array_unique( $urls ), array( $url, '' ) ) );
    }

    





    private static function permalink( object $post ): string {
        if ( in_array( $post->post_status, array( 'publish', 'private' ), true ) ) {
            return (string) \get_permalink( $post );
        }
        $name = preg_replace( '/__trashed(-\d+)?$/', '', (string) $post->post_name );
        if ( '' === (string) $name ) {
            return '';
        }
        $former              = clone $post;
        $former->post_status = 'publish';
        $former->post_name   = (string) $name;
        return (string) \get_permalink( $former );
    }

    





    private static function translations( object $post ): array {
        $ids = array();
        if ( function_exists( 'pll_get_post_translations' ) ) {
            $ids = array_merge( $ids, array_values( (array) \pll_get_post_translations( (int) $post->ID ) ) );
        }
        $type = \apply_filters( 'wpml_element_type', $post->post_type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
        $trid = \apply_filters( 'wpml_element_trid', null, (int) $post->ID, $type ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
        if ( $trid ) {
            $elements = \apply_filters( 'wpml_get_element_translations', null, $trid, $type, true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own filter.
            foreach ( (array) $elements as $element ) {
                if ( is_object( $element ) && isset( $element->element_id ) ) {
                    $ids[] = (int) $element->element_id;
                }
            }
        }
        $ids = array_map( 'intval', $ids );
        return array_values( array_diff( array_unique( $ids ), array( (int) $post->ID, 0 ) ) );
    }

    





    private static function archives( object $post ): array {
        $list       = array();
        $posts_page = (int) \get_option( 'page_for_posts' );
        if ( 'post' === $post->post_type && $posts_page > 0 && 'page' === \get_option( 'show_on_front' ) ) {
            $list[] = array( 'url' => (string) \get_permalink( $posts_page ), 'source' => 'posts_page' );
        }
        $archive = \get_post_type_archive_link( $post->post_type );
        if ( is_string( $archive ) && '' !== $archive ) {
            $list[] = array( 'url' => $archive, 'source' => 'post_type_archive' );
        }
        foreach ( \get_object_taxonomies( $post->post_type ) as $taxonomy ) {
            $tax = \get_taxonomy( $taxonomy );
            
            if ( ! $tax || empty( $tax->publicly_queryable ) ) {
                continue;
            }
            $terms = \get_the_terms( $post, $taxonomy );
            foreach ( is_array( $terms ) ? $terms : array() as $term ) {
                $link = \get_term_link( $term );
                if ( is_string( $link ) ) {
                    $list[] = array( 'url' => $link, 'source' => 'term_archive' );
                }
            }
        }
        return $list;
    }
}
