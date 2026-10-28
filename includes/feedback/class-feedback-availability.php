<?php
namespace Easy_MCP_AI\Feedback;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Switched_Off_Inventory;
use Easy_MCP_AI\MCP\Tool_Status;
use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Feedback_Availability {

    const OFF           = 'off';
    const NO_ACCESS     = 'no_access';
    const PATTERN       = 'pattern';
    const INTEGRATION   = 'integration_off';
    const NOT_CONNECTED = 'not_connected';
    const ABILITY       = 'ability_off';

    const NOT_QUEUED = 'Not queued: this site already has what the report asks for, but it is not switched on for you, so nothing was stored or sent.';

    const LIST_TOOL_POINTER = 'wp_list_disabled_tools lists everything else that is switched off on this site.';

    const IN_CONFIGURATION = Tool_Status::CONFIGURATION_PLACE;

    const MAX_GAP = 2;

    const INTEGRATION_PHRASES = array(
        'woocommerce'         => array( 'woocommerce', 'woo commerce' ),
        'acf'                 => array( 'acf', 'advanced custom fields', 'secure custom fields' ),
        'the-events-calendar' => array( 'events calendar', 'tribe events' ),
        'redirection'         => array( 'redirection plugin', 'redirection by john godley' ),
        'site-kit'            => array( 'site kit', 'sitekit' ),
        'contact-form-7'      => array( 'contact form 7', 'cf7' ),
        'buddypress'          => array( 'buddypress' ),
        'yoast-seo'           => array( 'yoast' ),
        'rank-math'           => array( 'rank math', 'rankmath' ),
        'aioseo'              => array( 'aioseo', 'all in one seo' ),
        'seopress'            => array( 'seopress' ),
        'slim-seo'            => array( 'slim seo' ),
        'the-seo-framework'   => array( 'seo framework' ),
    );

    const PROVIDER_PHRASES = array(
        'gsc'       => array( 'search console', 'gsc' ),
        'ga'        => array( 'google analytics', 'ga4' ),
        'dfs'       => array( 'dataforseo', 'data for seo' ),
        'semrush'   => array( 'semrush' ),
        'ahrefs'    => array( 'ahrefs' ),
        'seranking' => array( 'se ranking', 'seranking' ),
    );

    public static function find( string $summary, string $details, ?object $registry, ?callable $status_of = null, string $self = '' ): array {
        $text  = strtolower( $summary . "\n" . $details );
        $words = self::words( $summary );
        return array_merge(
            self::tool_matches( $text, $words, $registry, $status_of, $self ),
            self::named_matches( self::INTEGRATION, Switched_Off_Inventory::integrations(), self::INTEGRATION_PHRASES, $text ),
            self::named_matches( self::NOT_CONNECTED, Switched_Off_Inventory::providers(), self::PROVIDER_PHRASES, $text ),
            self::ability_matches( $text, $words )
        );
    }

    private static function tool_matches( string $text, array $words, ?object $registry, ?callable $status_of, string $self ): array {
        if ( null === $registry || ! method_exists( $registry, 'get_all_tool_names' ) || ! method_exists( $registry, 'get_tool' ) ) {
            return array();
        }
        $off   = Tool_State::off_names( $registry );
        $found = array();
        foreach ( (array) $registry->get_all_tool_names() as $name ) {
            $name = (string) $name;
            if ( $name === $self || ! self::name_matches( $name, $text, $words ) ) {
                continue;
            }
            $status = null !== $status_of ? $status_of( $name ) : null;
            if ( null === $status ) {
                if ( ! in_array( $name, $off, true ) ) {
                    continue;
                }
                $tool   = $registry->get_tool( $name );
                $status = Tool_Status::off( $name, $tool instanceof \Easy_MCP_AI\Tools\Base_Tool && $tool->is_disabled_by_default() );
            }
            if ( 'available' === $status['status'] ) {
                continue;
            }
            $found[] = in_array( $status['status'], array( 'off', 'off_locked' ), true )
                ? array(
                    'case'  => self::OFF,
                    'name'  => $name,
                    'label' => $name,
                    'where' => 'off' === $status['status'] ? Tool_Status::in_admin( $status['where'] ) : self::IN_CONFIGURATION,
                )
                : array(
                    'case'  => 'pattern' === $status['status'] ? self::PATTERN : self::NO_ACCESS,
                    'name'  => $name,
                    'label' => $name,
                    'why'   => rtrim( Tool_Status::predicate( $status ), '.' ),
                );
        }
        return $found;
    }

    private static function named_matches( string $case, array $entries, array $phrases, string $text ): array {
        $found = array();
        foreach ( $entries as $entry ) {
            if ( isset( $phrases[ $entry['name'] ] ) && self::mentions( $text, $phrases[ $entry['name'] ] ) ) {
                $found[] = array( 'case' => $case, 'name' => $entry['name'], 'label' => $entry['label'], 'where' => $entry['where'] );
            }
        }
        return $found;
    }

    private static function ability_matches( string $text, array $words ): array {
        $found = array();
        foreach ( Switched_Off_Inventory::abilities() as $entry ) {
            $slug          = (string) $entry['slug'];
            $ability_words = self::words( str_replace( array( '/', '-' ), ' ', substr( $slug, (int) strpos( $slug, '/' ) ) ) );
            $exact         = false !== strpos( $text, strtolower( $slug ) );
            if ( $exact || ( count( $ability_words ) >= 2 && self::in_order( $ability_words, $words ) ) ) {
                $found[] = array( 'case' => self::ABILITY, 'name' => $slug, 'label' => $entry['label'] !== $slug ? $entry['label'] . ' (' . $slug . ')' : $slug, 'where' => $entry['where'] );
            }
        }
        return $found;
    }

    public static function refusal( array $found, bool $list_tool = false ): Detailed_Tool_Error {
        $items        = array();
        $switched_off = false;
        foreach ( array_slice( $found, 0, 5 ) as $match ) {
            $switched_off = $switched_off || self::NO_ACCESS !== $match['case'];
            switch ( $match['case'] ) {
                case self::OFF:
                    $items[] = sprintf( '%s exists but is switched off (%s)', $match['label'], isset( $match['where'] ) ? $match['where'] : 'Tools screen' );
                    break;
                case self::NO_ACCESS:
                case self::PATTERN:
                    $items[] = isset( $match['why'] )
                        ? $match['label'] . ' ' . $match['why']
                        : sprintf( '%s exists, but this connection or the user\'s WordPress role cannot use it', $match['label'] );
                    break;
                case self::INTEGRATION:
                    $items[] = sprintf( 'the %s integration is available but switched off (%s)', $match['label'], isset( $match['where'] ) ? $match['where'] : 'Tools screen, Abilities' );
                    break;
                case self::NOT_CONNECTED:
                    $items[] = sprintf( '%s is available but not connected (%s)', $match['label'], isset( $match['where'] ) ? $match['where'] : 'Tools screen, External data' );
                    break;
                default:
                    $items[] = sprintf( 'the ability %s is installed but not enabled (%s)', $match['label'], isset( $match['where'] ) ? $match['where'] : 'Tools screen, Abilities' );
            }
        }
        return new Detailed_Tool_Error(
            self::NOT_QUEUED,
            implode( '; ', $items ) . '. '
            . 'Tell the user to ask a site administrator to switch it on or grant access, instead of reporting it to the plugin\'s authors.'
            . ( $list_tool && $switched_off ? ' ' . self::LIST_TOOL_POINTER : '' )
        );
    }

    public static function name_matches( string $name, string $text, array $words ): bool {
        $name = strtolower( $name );
        if ( '' === $name ) {
            return false;
        }
        if ( preg_match( '/(?<![a-z0-9_])' . preg_quote( $name, '/' ) . '(?![a-z0-9_])/', $text ) ) {
            return true;
        }
        $parts = self::words( str_replace( '_', ' ', (string) preg_replace( '/^wp_/', '', $name ) ) );
        return count( $parts ) >= 2 && self::in_order( $parts, $words );
    }

    private static function in_order( array $parts, array $words ): bool {
        foreach ( array_keys( $words, $parts[0], true ) as $start ) {
            $at = $start;
            foreach ( array_slice( $parts, 1 ) as $part ) {
                $next = array_search( $part, array_slice( $words, $at + 1, self::MAX_GAP + 1 ), true );
                if ( false === $next ) {
                    continue 2;
                }
                $at += 1 + (int) $next;
            }
            return true;
        }
        return false;
    }

    public static function words( string $text ): array {
        $out = array();
        foreach ( preg_split( '/[^a-z0-9]+/', strtolower( $text ), -1, PREG_SPLIT_NO_EMPTY ) as $word ) {
            $out[] = self::singular( $word );
        }
        return $out;
    }

    private static function singular( string $word ): string {
        $length = strlen( $word );
        if ( $length > 4 && 'ies' === substr( $word, -3 ) ) {
            return substr( $word, 0, -3 ) . 'y';
        }
        if ( $length > 3 && 's' === substr( $word, -1 ) && 'ss' !== substr( $word, -2 ) ) {
            return substr( $word, 0, -1 );
        }
        return $word;
    }

    private static function mentions( string $text, array $phrases ): bool {
        foreach ( $phrases as $phrase ) {
            if ( preg_match( '/(?<![a-z0-9])' . preg_quote( (string) $phrase, '/' ) . '(?![a-z0-9])/', (string) $text ) ) {
                return true;
            }
        }
        return false;
    }
}
