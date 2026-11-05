<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi4_Rules implements Structure_Rules {

    const SECTION = 'et_pb_section';

    const ROW = 'et_pb_row';

    const ROW_INNER = 'et_pb_row_inner';

    const COLUMN = 'et_pb_column';

    const COLUMN_INNER = 'et_pb_column_inner';

    const STRUCTURE = array( self::SECTION, self::ROW, self::ROW_INNER, self::COLUMN, self::COLUMN_INNER );

    private $modules;

    private $version;

    public function __construct( array $modules, string $version ) {
        $this->modules = $modules;
        $this->version = $version;
    }

    public function name(): string {
        return 'the Divi Builder';
    }

    public function base_tag( string $tag ): string {
        if ( self::ROW_INNER === $tag ) {
            return self::ROW;
        }
        return self::COLUMN_INNER === $tag ? self::COLUMN : $tag;
    }

    public function insertable( string $tag ): bool {
        return isset( $this->modules[ $tag ] ) && ! in_array( $tag, self::STRUCTURE, true );
    }

    public function holds_elements( string $tag ): bool {
        return in_array( $tag, self::STRUCTURE, true ) || '' !== ( $this->modules[ $tag ]['child_slug'] ?? '' );
    }

    public function placement_refusal( string $child, array $chain, array $descendants ): ?string {
        unset( $descendants );
        if ( Shortcode_Tree::TEXT === $child || 'text' === $child ) {
            return 'The Divi Builder keeps no text between elements; text goes in a module.';
        }
        $child = $this->base_tag( $child );
        if ( self::COLUMN === $child ) {
            return self::fixed_column();
        }
        if ( ! isset( $this->modules[ $child ] ) && ! in_array( $child, self::STRUCTURE, true ) ) {
            return sprintf( 'Divi does not register a %s module here, so where it may go is not known.', $child );
        }
        $accepted = $this->accepted( $chain );
        if ( in_array( $child, $accepted['takes'], true ) ) {
            return null;
        }
        return sprintf( '%s cannot hold a %s in the Divi Builder; it holds %s.', $accepted['what'], $child, array() === $accepted['takes'] ? 'no elements' : implode( ', ', $accepted['takes'] ) );
    }

    public function tag_at( string $tag, array $ancestors ): string {
        $base   = $this->base_tag( $tag );
        $parent = array() === $ancestors ? '' : $this->base_tag( (string) $ancestors[ count( $ancestors ) - 1 ]['tag'] );
        if ( self::ROW === $base ) {
            return self::COLUMN === $parent ? self::ROW_INNER : self::ROW;
        }
        if ( self::COLUMN === $base ) {
            return array() !== $ancestors && self::ROW_INNER === $ancestors[ count( $ancestors ) - 1 ]['tag'] ? self::COLUMN_INNER : self::COLUMN;
        }
        return $tag;
    }

    public function markup( string $tag, string $atts ): string {
        if ( $this->holds_elements( $tag ) || ! empty( $this->modules[ $tag ]['content'] ) ) {
            return '[' . $tag . $atts . '][/' . $tag . ']';
        }
        return '[' . $tag . $atts . ' /]';
    }

    public function trailing_breaks( string $tag ): int {
        unset( $tag );
        return 0;
    }

    public function always_saved( string $tag, string $parent ): array {
        unset( $tag, $parent );
        return array( '_builder_version' => $this->version );
    }

    public function copy_drops(): array {
        return array();
    }

    public function fixed_refusal( string $tag ): ?string {
        return self::COLUMN === $this->base_tag( $tag ) ? self::fixed_column() : null;
    }

    private function accepted( array $chain ): array {
        if ( array() === $chain ) {
            return array(
                'what'  => 'The page',
                'takes' => array( self::SECTION ),
            );
        }
        $parent = $chain[ count( $chain ) - 1 ];
        $tag    = (string) $parent['tag'];
        $base   = $this->base_tag( $tag );
        if ( self::SECTION === $base ) {
            $kind = self::section_kind( $parent );
            return array(
                'what'  => sprintf( 'A %s section', $kind ),
                'takes' => 'fullwidth' === $kind ? $this->modules_where( true ) : array( 'specialty' === $kind ? self::COLUMN : self::ROW ),
            );
        }
        if ( self::ROW === $base ) {
            return array(
                'what'  => 'A row',
                'takes' => array( self::COLUMN ),
            );
        }
        if ( self::COLUMN === $base ) {
            $takes = $this->modules_where( false );
            if ( self::COLUMN === $tag && count( $chain ) >= 2 && 'specialty' === self::section_kind( $chain[ count( $chain ) - 2 ] ) ) {
                $takes[] = self::ROW;
            }
            return array(
                'what'  => 'A column',
                'takes' => $takes,
            );
        }
        $child_slug = $this->modules[ $tag ]['child_slug'] ?? '';
        return array(
            'what'  => sprintf( 'A %s', $tag ),
            'takes' => '' === $child_slug ? array() : array( $child_slug ),
        );
    }

    private function modules_where( bool $fullwidth ): array {
        $slugs = array();
        foreach ( $this->modules as $slug => $module ) {
            if ( ! in_array( $slug, self::STRUCTURE, true ) && ! $module['child'] && $fullwidth === $module['fullwidth'] ) {
                $slugs[] = (string) $slug;
            }
        }
        return $slugs;
    }

    public static function section_kind( array $section ): string {
        $atts = isset( $section['atts'] ) && is_array( $section['atts'] ) ? $section['atts'] : array();
        if ( 'on' === ( $atts['fullwidth'] ?? '' ) ) {
            return 'fullwidth';
        }
        return 'on' === ( $atts['specialty'] ?? '' ) ? 'specialty' : 'regular';
    }

    private static function fixed_column(): string {
        return 'A Divi column\'s width comes from its row\'s column structure, so columns are not added, removed, moved or copied on their own; change the row\'s structure in the Divi Builder, or move, copy or remove the whole row.';
    }
}
