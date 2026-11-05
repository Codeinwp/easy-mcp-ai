<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi5_Rules {

    const SECTION = 'divi/section';

    const ROW = 'divi/row';

    const ROW_INNER = 'divi/row-inner';

    const COLUMN = 'divi/column';

    const COLUMN_INNER = 'divi/column-inner';

    const GROUP = 'divi/group';

    const GLOBAL_LAYOUT = 'divi/global-layout';

    const SHORTCODE_MODULE = 'divi/shortcode-module';

    const STRUCTURE = array( self::SECTION, self::ROW, self::ROW_INNER, self::COLUMN, self::COLUMN_INNER, self::GROUP );

    private $modules;

    public function __construct( array $modules ) {
        $this->modules = $modules;
    }

    public function insertable( string $name ): bool {
        return isset( $this->modules[ $name ] ) && in_array( $this->modules[ $name ]['category'], array( 'module', 'child-module', 'fullwidth-module' ), true );
    }

    public function role_key( string $name ): string {
        $d4 = $this->modules[ $name ]['d4'] ?? '';
        return '' !== $d4 ? $d4 : $name;
    }

    public function holds_elements( string $name ): bool {
        return in_array( $name, self::STRUCTURE, true ) || '' !== ( $this->modules[ $name ]['child'] ?? '' );
    }

    public function fixed_refusal( string $name ): ?string {
        if ( in_array( $name, array( self::COLUMN, self::COLUMN_INNER ), true ) ) {
            return 'A Divi column\'s width comes from its row\'s column structure, so columns are not added, removed, moved or copied on their own; change the row\'s structure in the Divi Builder, or move, copy or remove the whole row.';
        }
        return null;
    }

    public function placement_refusal( string $name, ?Divi5_Block $parent, ?Divi5_Block $holder ): ?string {
        if ( Divi5_Block::TEXT === $name ) {
            return 'The Divi Builder keeps no text between elements; text goes in a module.';
        }
        $fixed = $this->fixed_refusal( $name );
        if ( null !== $fixed ) {
            return $fixed;
        }
        if ( ! isset( $this->modules[ $name ] ) && ! in_array( $name, self::STRUCTURE, true ) ) {
            return sprintf( 'Divi does not register a %s module here, so where it may go is not known.', $name );
        }
        $accepted = $this->accepted( $parent, $holder );
        if ( in_array( $name, $accepted['takes'], true ) ) {
            return null;
        }
        return sprintf( '%s cannot hold a %s in the Divi Builder; it holds %s.', $accepted['what'], $name, array() === $accepted['takes'] ? 'no elements' : implode( ', ', $accepted['takes'] ) );
    }

    public static function section_kind( Divi5_Block $section ): string {
        $kind = $section->get( array( 'module', 'advanced', 'type', 'desktop', 'value' ) );
        return in_array( $kind, array( 'fullwidth', 'specialty' ), true ) ? (string) $kind : 'regular';
    }

    private function accepted( ?Divi5_Block $parent, ?Divi5_Block $holder ): array {
        if ( null === $parent ) {
            return array(
                'what'  => 'The page',
                'takes' => array( self::SECTION ),
            );
        }
        $name = $parent->name;
        if ( self::SECTION === $name ) {
            $kind = self::section_kind( $parent );
            return array(
                'what'  => sprintf( 'A %s section', $kind ),
                'takes' => 'fullwidth' === $kind ? $this->modules_in( 'fullwidth-module' ) : array( 'specialty' === $kind ? self::COLUMN : self::ROW ),
            );
        }
        if ( self::ROW === $name || self::ROW_INNER === $name ) {
            return array(
                'what'  => 'A row',
                'takes' => array( self::ROW === $name ? self::COLUMN : self::COLUMN_INNER ),
            );
        }
        if ( self::COLUMN === $name ) {
            $nested = null !== $holder && self::SECTION === $holder->name && 'specialty' === self::section_kind( $holder ) ? self::ROW_INNER : self::ROW;
            return array(
                'what'  => 'A column',
                'takes' => array_merge( $this->modules_in( 'module' ), array( $nested, self::GROUP ) ),
            );
        }
        if ( self::COLUMN_INNER === $name || self::GROUP === $name ) {
            return array(
                'what'  => self::GROUP === $name ? 'A module group' : 'A column',
                'takes' => $this->modules_in( 'module' ),
            );
        }
        $child = $this->modules[ $name ]['child'] ?? '';
        return array(
            'what'  => sprintf( 'A %s', $name ),
            'takes' => '' === $child ? array() : array( $child ),
        );
    }

    private function modules_in( string $category ): array {
        $names = array();
        foreach ( $this->modules as $name => $module ) {
            if ( $category === $module['category'] && self::SHORTCODE_MODULE !== $name ) {
                $names[] = (string) $name;
            }
        }
        return $names;
    }
}
