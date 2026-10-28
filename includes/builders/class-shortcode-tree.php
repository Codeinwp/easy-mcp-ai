<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Shortcode_Tree {

    const TEXT = '#text';

    const MAX_DEPTH = 32;

    public static function parse( string $content, array $tags, int $depth = 0, int $base = 0 ): array {
        $tags = array_values( array_filter( $tags, 'is_string' ) );
        if ( array() === $tags || '' === $content ) {
            return self::text_item( $content, $base );
        }
        $pattern = '/' . get_shortcode_regex( $tags ) . '/s';
        if ( ! preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
            return self::text_item( $content, $base );
        }
        $items  = array();
        $cursor = 0;
        $text   = '';
        $from   = 0;
        foreach ( $matches as $match ) {
            $start  = $match[0][1];
            $text  .= substr( $content, $cursor, $start - $cursor );
            $cursor = $start + strlen( $match[0][0] );
            if ( '[' === $match[1][0] && ']' === $match[6][0] ) {
                $text .= $match[0][0];
                continue;
            }
            $items = array_merge( $items, self::text_item( $text, $base + $from ) );
            $text  = '';
            $from  = $cursor;
            $enclosed = isset( $match[5] ) && -1 !== $match[5][1] && '' !== $match[5][0] ? $match[5][0] : null;
            $closer   = '[/' . $match[2][0] . ']';
            $closed   = substr( $match[0][0], -strlen( $closer ) ) === $closer;
            $atts     = shortcode_parse_atts( $match[3][0] );
            $content_at = $closed ? $match[5][1] : -1;
            $items[]    = array(
                'tag'        => $match[2][0],
                'atts'       => is_array( $atts ) ? $atts : array(),
                'content'    => $closed ? (string) $enclosed : null,
                'children'   => ( null !== $enclosed && $depth < self::MAX_DEPTH ) ? self::parse( $enclosed, $tags, $depth + 1, $base + $match[5][1] ) : array(),
                'start'      => $base + $start,
                'end'        => $base + $cursor,
                'atts_at'    => $base + $match[3][1],
                'atts_raw'   => $match[3][0],
                'content_at' => -1 === $content_at ? -1 : $base + $content_at,
            );
        }
        return array_merge( $items, self::text_item( $text . substr( $content, $cursor ), $base + $from ) );
    }

    private static function text_item( string $text, int $at = 0 ): array {
        if ( '' === trim( $text ) ) {
            return array();
        }
        return array(
            array(
                'tag'        => self::TEXT,
                'atts'       => array(),
                'content'    => $text,
                'children'   => array(),
                'start'      => $at,
                'end'        => $at + strlen( $text ),
                'atts_at'    => $at,
                'atts_raw'   => '',
                'content_at' => $at,
            ),
        );
    }
}
