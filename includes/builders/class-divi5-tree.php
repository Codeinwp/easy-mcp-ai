<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi5_Tree {

    const PLACEHOLDER = 'divi/placeholder';

    const TOKEN = '/<!--\s+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)\s+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?}\s+)?(?P<void>\/)?-->/s';

    public $top = array();

    public $end = '';

    public static function parse( string $content ): ?self {
        $tree  = new self();
        $stack = array();
        $list  = array();
        $at    = 0;
        if ( false === preg_match_all( self::TOKEN, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
            return null;
        }
        foreach ( $matches as $match ) {
            $start = (int) $match[0][1];
            $gap   = substr( $content, $at, $start - $at );
            $at    = $start + strlen( $match[0][0] );
            $name  = ( '' !== ( $match['namespace'][0] ?? '' ) ? $match['namespace'][0] : 'core/' ) . $match['name'][0];
            if ( '' !== ( $match['closer'][0] ?? '' ) ) {
                $open = array_pop( $stack );
                if ( null === $open || $open['block']->name !== $name ) {
                    return null;
                }
                $block           = $open['block'];
                $block->children = self::with_gap( $list, $gap, $block->tail );
                $block->closer   = $match[0][0];
                $list            = $open['list'];
                $list[]          = $block;
                continue;
            }
            $block         = new Divi5_Block();
            $block->name   = $name;
            $block->opener = $match[0][0];
            $block->void   = '' !== ( $match['void'][0] ?? '' );
            $json          = isset( $match['attrs'][0] ) ? trim( (string) $match['attrs'][0] ) : '';
            if ( '' !== $json ) {
                $attrs = json_decode( $json );
                if ( ! $attrs instanceof \stdClass ) {
                    return null;
                }
                $block->attrs = $attrs;
            }
            $list = self::with_gap( $list, $gap, $block->lead );
            if ( $block->void ) {
                $list[] = $block;
                continue;
            }
            $stack[] = array(
                'block' => $block,
                'list'  => $list,
            );
            $list    = array();
        }
        if ( array() !== $stack ) {
            return null;
        }
        $tree->top = self::with_gap( $list, substr( $content, $at ), $tree->end );
        return $tree;
    }

    public function &items(): array {
        $placeholder = $this->placeholder();
        if ( null !== $placeholder ) {
            return $placeholder->children;
        }
        return $this->top;
    }

    public function placeholder(): ?Divi5_Block {
        $blocks = array_values(
            array_filter(
                $this->top,
                static function ( Divi5_Block $block ): bool {
                    return ! $block->is_text();
                }
            )
        );
        return 1 === count( $blocks ) && self::PLACEHOLDER === $blocks[0]->name ? $blocks[0] : null;
    }

    public function serialize(): string {
        return self::serialize_list( $this->top ) . $this->end;
    }

    public static function serialize_list( array $blocks ): string {
        $out = '';
        foreach ( $blocks as $block ) {
            $out .= self::serialize_block( $block );
        }
        return $out;
    }

    public static function serialize_block( Divi5_Block $block ): string {
        if ( $block->is_text() ) {
            return $block->lead . $block->text;
        }
        $void   = $block->void && array() === $block->children;
        $opener = $block->opener;
        if ( $block->dirty || '' === $opener ) {
            $json   = array() === get_object_vars( $block->attrs ) ? '' : serialize_block_attributes( $block->attrs ) . ' ';
            $opener = '<!-- wp:' . $block->name . ' ' . $json . ( $void ? '/-->' : '-->' );
        } elseif ( $block->void && ! $void ) {
            $opener = substr( $opener, 0, -4 ) . '-->';
        }
        if ( $void ) {
            return $block->lead . $opener;
        }
        $closer = '' !== $block->closer ? $block->closer : '<!-- /wp:' . $block->name . ' -->';
        return $block->lead . $opener . self::serialize_list( $block->children ) . $block->tail . $closer;
    }

    public static function addresses( array $blocks, string $prefix = '' ): array {
        $map = array();
        foreach ( array_values( $blocks ) as $index => $block ) {
            $address         = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            $map[ $address ] = $block;
            $map            += self::addresses( $block->children, $address );
        }
        return $map;
    }

    private static function with_gap( array $list, string $gap, string &$lead ): array {
        if ( '' === trim( $gap ) ) {
            $lead = $gap;
            return $list;
        }
        $text       = new Divi5_Block();
        $text->name = Divi5_Block::TEXT;
        $text->text = $gap;
        $list[]     = $text;
        $lead       = '';
        return $list;
    }
}
