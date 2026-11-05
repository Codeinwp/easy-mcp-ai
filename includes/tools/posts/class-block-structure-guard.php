<?php

namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Block_Structure_Guard {

    const MAX_LISTED = 20;

    const DELIMITER = '/<!--[ \t\f\r\n]+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)[ \t\f\r\n]+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}[ \t\f\r\n]+\/?-->).)*+)?}[ \t\f\r\n]+)?(?P<void>\/)?-->/s';

    const MAX_OPEN_COPY = 16777216;

    const CHANGES_BLOCKS = 'The edit would change the post\'s blocks, so nothing was changed. Search for text inside one block, or use wp_update_post_blocks to add, remove or move blocks.';

    public static function inspect( string $before, string $after ): array {
        $delimiters = self::compare_delimiters( $before, $after );
        $refusal    = $delimiters['refusal'];
        if ( null === $refusal && 0 < $delimiters['open'] ) {
            $copy = $delimiters['open'] * max( strlen( $before ), strlen( $after ) );
            if ( $copy > self::MAX_OPEN_COPY ) {
                $refusal = new Detailed_Tool_Error(
                    'The post leaves too many blocks open (a block delimiter with no closer) to check this edit, so nothing was changed. Close them in the block editor, or send the whole content with wp_update_post.',
                    sprintf(
                        '%1$d blocks are open, and WordPress copies the rest of the post for each one when it reads the blocks: checking this edit would copy about %2$d MB.',
                        $delimiters['open'],
                        (int) ceil( $copy / 1048576 )
                    )
                );
            }
        }
        if ( null !== $refusal || 0 === $delimiters['count'] ) {
            return array(
                'refusal' => $refusal,
                'changed' => array(),
            );
        }
        $old = self::flatten( parse_blocks( $before ) );
        $new = self::flatten( parse_blocks( $after ) );

        $refusal = self::structure_refusal( $old, $new );
        $changed = array();
        foreach ( self::pairs( $old, $new ) as $i => $j ) {
            $block         = $old[ $i ];
            $now           = $new[ $j ];
            $same_settings = self::same_settings( $block['attrs'], $now['attrs'] );
            if ( $same_settings && $block['html'] === $now['html'] ) {
                continue;
            }
            if ( null === $refusal && $block['html'] === $now['html'] && '' !== trim( $block['html'] ) ) {
                $refusal = new Detailed_Tool_Error(
                    'The edit changes a block\'s settings but not its HTML, so the block editor would report the block as invalid. Nothing was changed. Change the HTML in the same edit, or use wp_update_post_blocks.',
                    sprintf( 'The block: %s.', self::label( $new, $j ) )
                );
            }
            if ( count( $changed ) < self::MAX_LISTED ) {
                $changed[] = self::label( $new, $j );
            }
        }
        return array(
            'refusal' => $refusal,
            'changed' => $changed,
        );
    }

    private static function pairs( array $old, array $new ): array {
        $keys  = static function ( array $blocks ): array {
            $keyed = array();
            $named = 0;
            $top   = 0;
            foreach ( $blocks as $i => $block ) {
                if ( null === $block['name'] ) {
                    $keyed[ 'html ' . $top ] = $i;
                    continue;
                }
                $keyed[ 'block ' . $named++ ] = $i;
                if ( -1 === $block['parent'] ) {
                    ++$top;
                }
            }
            return $keyed;
        };
        $is    = $keys( $new );
        $pairs = array();
        foreach ( $keys( $old ) as $key => $i ) {
            if ( isset( $is[ $key ] ) ) {
                $pairs[ $i ] = $is[ $key ];
            }
        }
        return $pairs;
    }

    private static function compare_delimiters( string $before, string $after ): array {
        $at_before = 0;
        $at_after  = 0;
        $open      = 0;
        $stopped   = false;
        for ( $number = 1; ; $number++ ) {
            $was = self::next_delimiter( $before, $at_before );
            $is  = self::next_delimiter( $after, $at_after );
            if ( $was === $is ) {
                if ( null === $was ) {
                    return array(
                        'refusal' => null,
                        'count'   => $number - 1,
                        'open'    => $open,
                    );
                }
                if ( $stopped || '/-->' === substr( $was, -4 ) ) {
                    continue;
                }
                if ( '<!-- /' === substr( $was, 0, 6 ) ) {
                    $stopped = 0 === $open;
                    $open    = max( 0, $open - 1 );
                } else {
                    ++$open;
                }
                continue;
            }
            return array(
                'refusal' => new Detailed_Tool_Error(
                    self::CHANGES_BLOCKS,
                    sprintf( 'Block delimiter %1$d is %2$s before and %3$s after.', $number, $was ?? 'missing', $is ?? 'missing' )
                ),
                'count'   => $number,
                'open'    => 0,
            );
        }
    }

    private static function next_delimiter( string $content, int &$offset ): ?string {
        if ( 1 !== preg_match( self::DELIMITER, $content, $match, PREG_OFFSET_CAPTURE, $offset ) ) {
            return null;
        }
        $offset    = $match[0][1] + strlen( $match[0][0] );
        $namespace = 'core/' === $match['namespace'][0] ? '' : $match['namespace'][0];
        return sprintf(
            '<!-- %swp:%s%s %s-->',
            '' === $match['closer'][0] ? '' : '/',
            $namespace,
            $match['name'][0],
            isset( $match['void'] ) && '' !== $match['void'][0] ? '/' : ''
        );
    }

    private static function flatten( array $blocks, int $parent = -1, array &$out = array() ): array {
        $place = 0;
        foreach ( $blocks as $block ) {
            $name = isset( $block['blockName'] ) && is_string( $block['blockName'] ) ? $block['blockName'] : null;
            $html = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
            if ( null === $name && '' === trim( $html ) ) {
                continue;
            }
            $out[] = array(
                'parent' => $parent,
                'place'  => $place,
                'name'   => $name,
                'attrs'  => isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array(),
                'html'   => $html,
            );
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                self::flatten( $block['innerBlocks'], count( $out ) - 1, $out );
            }
            ++$place;
        }
        return $out;
    }

    private static function same_settings( $a, $b ): bool {
        if ( $a === $b ) {
            return true;
        }
        if ( ! is_array( $a ) || ! is_array( $b ) || count( $a ) !== count( $b ) ) {
            return false;
        }
        foreach ( $a as $key => $value ) {
            if ( ! array_key_exists( $key, $b ) || ! self::same_settings( $value, $b[ $key ] ) ) {
                return false;
            }
        }
        return true;
    }

    private static function structure_refusal( array $old, array $new ): ?Detailed_Tool_Error {
        $count = max( count( $old ), count( $new ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $was = $old[ $i ] ?? null;
            $is  = $new[ $i ] ?? null;
            if ( null !== $was && null !== $is && $was['name'] === $is['name'] ) {
                continue;
            }
            return new Detailed_Tool_Error(
                self::CHANGES_BLOCKS,
                sprintf(
                    '%1$d blocks before, %2$d after; first difference: %3$s before, %4$s after.',
                    count( $old ),
                    count( $new ),
                    null === $was ? 'no block' : self::label( $old, $i ),
                    null === $is ? 'no block' : self::label( $new, $i )
                )
            );
        }
        return null;
    }

    private static function label( array $blocks, int $index ): string {
        $places = array();
        for ( $i = $index; $i >= 0; $i = $blocks[ $i ]['parent'] ) {
            $places[] = $blocks[ $i ]['place'];
        }
        return sprintf( '%s at %s', $blocks[ $index ]['name'] ?? 'HTML outside any block', implode( '.', array_reverse( $places ) ) );
    }
}
