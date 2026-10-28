<?php

namespace Easy_MCP_AI\Skills;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Skill_Instructions {

    const MAX_LENGTH = 2000;

    const DESCRIPTION_LENGTH = 100;

    public static function build( Skill_Set $served, bool $can_use_tool, string $base, int $reserve = 0 ): string {
        if ( $served->is_empty() ) {
            return $base;
        }
        $limit  = self::MAX_LENGTH - max( 0, (int) $reserve );
        $skills = $served->skills();
        if ( $can_use_tool ) {
            $how = ' For a multi-step job (reviewing, setting up or restyling the site), first call wp_get_skill with the skill\'s name and follow its steps.';
        } else {
            $how = ' For a multi-step job (reviewing, setting up or restyling the site), first read the matching skill file below (a resource) and follow its steps.';
        }
        $out    = $base . $how . ' Skills:';
        $listed = 0;
        foreach ( $skills as $skill ) {
            $label = $can_use_tool ? $skill['name'] : Skill_Set::uri( $skill );
            $line  = ' ' . $label . ' (' . self::shorten( Skill_Set::description( $skill ) ) . ');';
            if ( strlen( $out ) + strlen( $line ) > $limit ) {
                break;
            }
            $out .= $line;
            ++$listed;
        }
        return 0 === $listed ? $base : rtrim( $out, ';' ) . '.';
    }

    private static function shorten( string $text ): string {
        $text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
        if ( strlen( $text ) <= self::DESCRIPTION_LENGTH ) {
            return rtrim( $text, '.' );
        }
        $cut = substr( $text, 0, self::DESCRIPTION_LENGTH );
        $at  = strrpos( $cut, ' ' );
        return rtrim( false !== $at ? substr( $cut, 0, $at ) : $cut, ' ,.;-' ) . '…';
    }
}
