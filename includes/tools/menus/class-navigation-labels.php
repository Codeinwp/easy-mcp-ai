<?php
namespace Easy_MCP_AI\Tools\Menus;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Navigation_Labels {

    const BLOCKS = array( 'navigation-link', 'navigation-submenu' );

    const DELIMITER = '/<!--[ \t\f\r\n]+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)[ \t\f\r\n]+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}[ \t\f\r\n]+\/?-->).)*+)?}[ \t\f\r\n]+)?(?P<void>\/)?-->/s';

    const LOST_ESCAPES = array(
        'u005c' => '\\',
        'u002d' => '-',
        'u003c' => '<',
        'u003e' => '>',
        'u0026' => '&',
        'u0022' => '"',
    );

    public static function titles( object $post ): array {
        return array_values(
            array_unique(
                array(
                    self::comparable( (string) $post->post_title ),
                    self::comparable( (string) apply_filters( 'the_title', (string) $post->post_title, (int) $post->ID ) ),
                )
            )
        );
    }

    public static function comparable( string $text ): string {
        $text = str_ireplace( array_keys( self::LOST_ESCAPES ), array_values( self::LOST_ESCAPES ), $text );
        return trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
    }

    public static function holders(): array {
        $common = array(
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        );
        $posts  = array_merge(
            get_posts(
                array(
                    'post_type'   => 'wp_navigation',
                    'post_status' => 'any',
                ) + $common
            ),
            get_posts(
                array(
                    'post_type'   => array( 'wp_template_part', 'wp_template' ),
                    'post_status' => 'publish',
                    'tax_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- How core finds a theme's templates; a few rows.
                        array(
                            'taxonomy' => 'wp_theme',
                            'field'    => 'name',
                            'terms'    => get_stylesheet(),
                        ),
                    ),
                ) + $common
            )
        );
        return array_values(
            array_filter(
                $posts,
                static function ( $post ): bool {
                    $content = is_object( $post ) ? (string) $post->post_content : '';
                    return false !== strpos( $content, 'navigation-link' ) || false !== strpos( $content, 'navigation-submenu' );
                }
            )
        );
    }

    public static function relabel( string $content, int $post_id, array $old_titles, string $new_title ): array {
        $updated = array();
        $kept    = array();
        $current = array();
        $new     = self::comparable( $new_title );
        $result  = preg_replace_callback(
            self::DELIMITER,
            static function ( array $match ) use ( $post_id, $old_titles, $new_title, $new, &$updated, &$kept, &$current ): string {
                $attrs = self::link_attributes( $match, $post_id );
                if ( null === $attrs ) {
                    return $match[0];
                }
                $label = isset( $attrs->label ) && is_string( $attrs->label ) ? $attrs->label : '';
                $read  = self::comparable( $label );
                if ( $read === $new ) {
                    $current[] = $label;
                    return $match[0];
                }
                if ( ! in_array( $read, $old_titles, true ) ) {
                    $kept[] = $label;
                    return $match[0];
                }
                $updated[]    = $label;
                $attrs->label = $new_title;
                $json         = rtrim( (string) $match['attrs'] );
                return substr_replace( $match[0], serialize_block_attributes( $attrs ), (int) strpos( $match[0], '{' ), strlen( $json ) );
            },
            $content
        );
        return array(
            'content' => is_string( $result ) ? $result : $content,
            'updated' => is_string( $result ) ? $updated : array(),
            'kept'    => is_string( $result ) ? $kept : array(),
            'current' => is_string( $result ) ? $current : array(),
        );
    }

    public static function route( object $holder ): string {
        $items = rest_get_route_for_post_type_items( $holder->post_type );
        if ( '' === $items ) {
            return '';
        }
        if ( 'wp_navigation' === $holder->post_type ) {
            return $items . '/' . (int) $holder->ID;
        }
        $themes = get_the_terms( $holder, 'wp_theme' );
        if ( ! is_array( $themes ) || array() === $themes ) {
            return '';
        }
        return $items . '/' . $themes[0]->name . '//' . $holder->post_name;
    }

    private static function link_attributes( array $match, int $post_id ): ?\stdClass {
        $attrs = $match['attrs'] ?? '';
        if ( '' === $attrs || ! in_array( $match['namespace'], array( '', 'core/' ), true ) || ! in_array( $match['name'], self::BLOCKS, true ) ) {
            return null;
        }
        $attrs = json_decode( $attrs );
        if ( ! $attrs instanceof \stdClass || ! isset( $attrs->kind, $attrs->id ) || 'post-type' !== $attrs->kind ) {
            return null;
        }
        return is_scalar( $attrs->id ) && (string) $attrs->id === (string) $post_id ? $attrs : null;
    }
}
