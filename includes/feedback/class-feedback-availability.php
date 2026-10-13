<?php
namespace Easy_MCP_AI\Feedback;

use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}











class Feedback_Availability {

    const OFF           = 'off';
    const NO_ACCESS     = 'no_access';
    const INTEGRATION   = 'integration_off';
    const NOT_CONNECTED = 'not_connected';
    const ABILITY       = 'ability_off';

    




    const MAX_GAP = 2;

    





    const INTEGRATIONS = array(
        'woocommerce'         => array( 'woocommerce', 'WooCommerce', array( 'woocommerce', 'woo commerce' ) ),
        'acf'                 => array( 'acf', 'Advanced Custom Fields', array( 'acf', 'advanced custom fields', 'secure custom fields' ) ),
        'the-events-calendar' => array( 'events', 'The Events Calendar', array( 'events calendar', 'tribe events' ) ),
        'redirection'         => array( 'redirection', 'Redirection', array( 'redirection plugin', 'redirection by john godley' ) ),
        'buddypress'          => array( 'buddypress', 'BuddyPress', array( 'buddypress' ) ),
        'yoast-seo'           => array( 'yoast', 'Yoast SEO', array( 'yoast' ) ),
        'rank-math'           => array( 'rankmath', 'Rank Math', array( 'rank math', 'rankmath' ) ),
        'aioseo'              => array( 'aioseo', 'All in One SEO', array( 'aioseo', 'all in one seo' ) ),
        'seopress'            => array( 'seopress', 'SEOPress', array( 'seopress' ) ),
        'slim-seo'            => array( 'slimseo', 'Slim SEO', array( 'slim seo' ) ),
        'the-seo-framework'   => array( 'tsf', 'The SEO Framework', array( 'seo framework' ) ),
    );

    



    const PROVIDERS = array(
        'gsc'       => array( 'Google Search Console', array( 'search console', 'gsc' ) ),
        'ga'        => array( 'Google Analytics', array( 'google analytics', 'ga4' ) ),
        'dfs'       => array( 'DataForSEO', array( 'dataforseo', 'data for seo' ) ),
        'semrush'   => array( 'Semrush', array( 'semrush' ) ),
        'ahrefs'    => array( 'Ahrefs', array( 'ahrefs' ) ),
        'seranking' => array( 'SE Ranking', array( 'se ranking', 'seranking' ) ),
    );

    















    public static function find( string $summary, string $details, ?object $registry, ?callable $may_use = null, string $self = '' ): array {
        $text      = strtolower( $summary . "\n" . $details );
        $words     = self::words( $summary );
        $scope_map = self::scope_map_loaded();
        return array_merge(
            self::tool_matches( $text, $words, $registry, $may_use, $self ),
            $scope_map ? self::integration_matches( $text ) : array(),
            $scope_map ? self::provider_matches( $text ) : array(),
            self::ability_matches( $text, $words )
        );
    }

    







    private static function tool_matches( string $text, array $words, ?object $registry, ?callable $may_use, string $self ): array {
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
            if ( in_array( $name, $off, true ) ) {
                $found[] = array( 'case' => self::OFF, 'name' => $name, 'label' => $name );
            } elseif ( null !== $may_use && false === $may_use( $name ) ) {
                $found[] = array( 'case' => self::NO_ACCESS, 'name' => $name, 'label' => $name );
            }
        }
        return $found;
    }

    





    private static function integration_matches( string $text ): array {
        $groups = (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        $found  = array();
        foreach ( self::INTEGRATIONS as $group => $spec ) {
            list( $category, $label, $phrases ) = $spec;
            if ( ! in_array( $group, $groups, true ) && self::mentions( $text, $phrases ) && \Easy_MCP_AI\OAuth\Scope_Map::is_plugin_category_active( $category ) ) {
                $found[] = array( 'case' => self::INTEGRATION, 'name' => $group, 'label' => $label );
            }
        }
        return $found;
    }

    




    private static function provider_matches( string $text ): array {
        $found = array();
        foreach ( self::PROVIDERS as $category => $spec ) {
            list( $label, $phrases ) = $spec;
            if ( self::mentions( $text, $phrases ) && ! \Easy_MCP_AI\OAuth\Scope_Map::is_plugin_category_active( $category ) ) {
                $found[] = array( 'case' => self::NOT_CONNECTED, 'name' => $category, 'label' => $label );
            }
        }
        return $found;
    }

    





    private static function ability_matches( string $text, array $words ): array {
        $found = array();
        foreach ( self::disabled_abilities() as $slug => $label ) {
            $slug          = (string) $slug;
            $ability_words = self::words( str_replace( array( '/', '-' ), ' ', substr( $slug, (int) strpos( $slug, '/' ) ) ) );
            $exact         = false !== strpos( $text, strtolower( $slug ) );
            if ( $exact || ( count( $ability_words ) >= 2 && self::in_order( $ability_words, $words ) ) ) {
                $found[] = array( 'case' => self::ABILITY, 'name' => $slug, 'label' => '' !== $label ? $label . ' (' . $slug . ')' : $slug );
            }
        }
        return $found;
    }

    





    public static function refusal( array $found ): string {
        $items = array();
        foreach ( array_slice( $found, 0, 5 ) as $match ) {
            switch ( $match['case'] ) {
                case self::OFF:
                    $items[] = sprintf( '%s exists but is switched off (Tools screen)', $match['label'] );
                    break;
                case self::NO_ACCESS:
                    $items[] = sprintf( '%s exists and is on, but this connection or the user\'s WordPress role cannot use it', $match['label'] );
                    break;
                case self::INTEGRATION:
                    $items[] = sprintf( 'the %s integration is available but switched off (Tools screen, Abilities)', $match['label'] );
                    break;
                case self::NOT_CONNECTED:
                    $items[] = sprintf( '%s is available but not connected (Tools screen, External data)', $match['label'] );
                    break;
                default:
                    $items[] = sprintf( 'the ability %s is installed but not enabled (Tools screen, Abilities)', $match['label'] );
            }
        }
        return 'Not queued: this site already has what the report asks for, but it is not switched on for you, so nothing was stored or sent. '
            . implode( '; ', $items ) . '. '
            . 'Tell the user to ask a site administrator to switch it on or grant access, instead of reporting it to the plugin\'s authors.';
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

    




    private static function disabled_abilities(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }
        try {
            $all = \wp_get_abilities();
        } catch ( \Throwable $e ) {
            return array();
        }
        $enabled = array_map( 'strval', (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() ) );
        $out     = array();
        foreach ( (array) $all as $slug => $ability ) {
            $slug = is_string( $slug ) ? $slug : ( is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '' );
            if ( '' === $slug || in_array( $slug, $enabled, true ) ) {
                continue;
            }
            $label        = is_object( $ability ) && method_exists( $ability, 'get_label' ) ? (string) $ability->get_label() : '';
            $out[ $slug ] = $label;
        }
        return $out;
    }

    private static function scope_map_loaded(): bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\OAuth\\Scope_Map' ) && defined( 'EASY_MCP_AI_PLUGIN_DIR' ) ) {
            $file = EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-scope-map.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }
        return class_exists( '\\Easy_MCP_AI\\OAuth\\Scope_Map' );
    }
}
