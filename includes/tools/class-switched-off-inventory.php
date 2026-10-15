<?php






namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\Admin\Plugin_Integration_Registry;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\MCP\Tool_Status;
use Easy_MCP_AI\OAuth\Scope_Map;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once dirname( __DIR__ ) . '/mcp/class-tool-status.php';
require_once dirname( __DIR__ ) . '/admin/class-plugin-integration-registry.php';
require_once dirname( __DIR__ ) . '/oauth/class-scope-map.php';
require_once __DIR__ . '/class-base-tool.php';
require_once __DIR__ . '/class-tool-state.php';
require_once __DIR__ . '/class-dynamic-tool-registrar.php';

























final class Switched_Off_Inventory {

    const TOOL        = 'tool';
    const INTEGRATION = 'integration';
    const ABILITY     = 'ability';
    const PROVIDER    = 'provider';

    
    const OFF = 'off';

    
    const PATTERN = 'pattern';

    
    const GROUP_OFF = 'group_off';

    
    const NOT_ENABLED = 'not_enabled';

    
    const NOT_CONNECTED = 'not_connected';

    
    const SUMMARY_MAX = 160;

    




    const PROVIDERS = array(
        'ga'        => array( 'Google Analytics', 'Visitors, sessions, page views and conversions from Google Analytics 4.' ),
        'gsc'       => array( 'Google Search Console', 'Search queries, clicks, impressions, sitemaps and URL inspection from Google Search Console.' ),
        'dfs'       => array( 'DataForSEO', 'Keyword search volume, rankings, backlinks and search results data from DataForSEO.' ),
        'semrush'   => array( 'Semrush', 'Keyword, domain, competitor and backlink data from Semrush.' ),
        'seranking' => array( 'SE Ranking', 'Keyword, domain, backlink and AI search visibility data from SE Ranking.' ),
        'ahrefs'    => array( 'Ahrefs', 'Domain rating from Ahrefs.' ),
    );

    






    public static function all( ?object $registry ): array {
        return array_merge(
            self::integrations(),
            self::integration_tools(),
            self::abilities(),
            self::providers(),
            self::tools( $registry )
        );
    }

    




    public static function integrations(): array {
        $entries = array();
        foreach ( self::groups_off() as $group ) {
            $entries[] = array(
                'kind'        => self::INTEGRATION,
                'name'        => (string) $group['slug'],
                'label'       => (string) $group['name'],
                'summary'     => self::summary( (string) ( $group['description'] ?? '' ) ),
                'group'       => self::INTEGRATION . ':' . $group['slug'],
                'group_label' => (string) $group['name'],
                'access'      => 'write',
                'state'       => self::GROUP_OFF,
                'where'       => self::integration_place(),
                'tools'       => count( $group['tools'] ),
            );
        }
        return $entries;
    }

    





    public static function integration_tools(): array {
        $entries = array();
        foreach ( self::groups_off() as $group ) {
            foreach ( $group['tools'] as $tool ) {
                $name      = (string) $tool['name'];
                $entries[] = array(
                    'kind'        => self::TOOL,
                    'name'        => $name,
                    'label'       => $name,
                    'summary'     => self::summary( (string) $tool['description'] ),
                    'group'       => self::INTEGRATION . ':' . $group['slug'],
                    'group_label' => (string) $group['name'],
                    'access'      => 'read' === $tool['type'] ? 'read' : 'write',
                    'state'       => self::GROUP_OFF,
                    
                    'where'       => false === Tool_State::override_of( $name ) ? Tool_Status::CONFIGURATION_PLACE : self::integration_place(),
                );
            }
        }
        return $entries;
    }

    




    public static function abilities(): array {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }
        try {
            $all = (array) \wp_get_abilities();
        } catch ( \Throwable $e ) {
            return array();
        }
        $enabled = array_map( 'strval', array_filter( (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() ), 'is_scalar' ) );
        $entries = array();
        foreach ( $all as $key => $ability ) {
            if ( ! is_object( $ability ) ) {
                continue;
            }
            $slug = is_string( $key ) ? $key : self::read( $ability, 'get_name' );
            if ( '' === $slug || in_array( $slug, $enabled, true ) ) {
                continue;
            }
            $entries[] = self::ability_entry( $slug, $ability );
        }
        usort( $entries, static function ( array $a, array $b ): int {
            return strcmp( $a['group'] . ' ' . $a['slug'], $b['group'] . ' ' . $b['slug'] );
        } );
        return $entries;
    }

    




    public static function providers(): array {
        $entries = array();
        foreach ( self::PROVIDERS as $key => $spec ) {
            if ( Scope_Map::is_plugin_category_active( $key ) ) {
                continue;
            }
            $entries[] = array(
                'kind'        => self::PROVIDER,
                'name'        => $key,
                'label'       => $spec[0],
                'summary'     => $spec[1],
                'group'       => self::PROVIDER . ':' . $key,
                'group_label' => $spec[0],
                'access'      => 'read',
                'state'       => self::NOT_CONNECTED,
                'where'       => Tool_Status::place( Tool_Status::EXTERNAL_PATH ),
            );
        }
        return $entries;
    }

    






    public static function tools( ?object $registry ): array {
        if ( null === $registry || ! method_exists( $registry, 'get_all_tool_names' ) || ! method_exists( $registry, 'get_tool' ) ) {
            return array();
        }
        require_once dirname( __DIR__ ) . '/mcp/class-server.php';
        $patterns = (array) Config::get( 'allowed_tool_patterns' );
        $entries  = array();
        foreach ( (array) $registry->get_all_tool_names() as $name ) {
            $tool = $registry->get_tool( $name );
            if ( ! $tool instanceof Base_Tool || Server::is_app_only_tool( $tool ) ) {
                continue;
            }
            $name = (string) $name;
            if ( ! Tool_State::is_enabled( $tool ) ) {
                $status    = Tool_Status::off( $name, (bool) $tool->is_disabled_by_default() );
                $entries[] = self::tool_entry( $tool, $name, self::OFF, 'off' === $status['status'] ? Tool_Status::in_admin( $status['where'] ) : Tool_Status::CONFIGURATION_PLACE );
            } elseif ( ! Server::matches_tool_patterns( $name, $patterns ) ) {
                $entries[] = self::tool_entry( $tool, $name, self::PATTERN, Tool_Status::place( Tool_Status::PATTERNS_PATH, Config::is_locked( 'easy_mcp_ai_allowed_tool_patterns' ) ) );
            }
        }
        usort( $entries, static function ( array $a, array $b ): int {
            return strcmp( $a['group'] . ' ' . $a['name'], $b['group'] . ' ' . $b['name'] );
        } );
        return $entries;
    }

    




    private static function groups_off(): array {
        $enabled = array_filter( (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() ), 'is_string' );
        $groups  = array();
        foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
            if ( ! in_array( $group['slug'], $enabled, true ) && Plugin_Integration_Registry::is_installed( $group ) ) {
                $groups[] = $group;
            }
        }
        return $groups;
    }

    



    private static function integration_place(): string {
        return Tool_Status::place( Tool_Status::ABILITIES_PATH, Config::is_locked( 'easy_mcp_ai_disabled_tools' ) );
    }

    




    private static function ability_entry( string $slug, object $ability ): array {
        $name        = Dynamic_Tool_Registrar::build_tool_name( $slug );
        $namespace   = false !== strpos( $slug, '/' ) ? (string) strstr( $slug, '/', true ) : 'core';
        $label       = self::read( $ability, 'get_label' );
        $description = self::read( $ability, 'get_description' );
        $annotations = self::ability_annotations( $ability );
        $path        = 'core' === $namespace ? Tool_Status::CORE_ABILITIES_PATH : Tool_Status::ABILITIES_PATH;
        $entry       = array(
            'kind'        => self::ABILITY,
            'name'        => $name,
            'label'       => '' !== $label ? $label : $slug,
            'summary'     => self::summary( '' !== $description ? $description : $label ),
            'group'       => 'abilities:' . $namespace,
            'group_label' => 'core' === $namespace ? 'WordPress abilities' : ucfirst( $namespace ) . ' abilities',
            'access'      => ! empty( $annotations['readonly'] ) ? 'read' : 'write',
            'state'       => self::NOT_ENABLED,
            
            
            'where'       => Tool_Status::place( $path, null !== Tool_State::override_of( $name ) ),
            'slug'        => $slug,
        );
        if ( ! empty( $annotations['destructive'] ) ) {
            $entry['destructive'] = true;
        }
        return $entry;
    }

    





    private static function read( object $ability, string $getter ): string {
        if ( ! method_exists( $ability, $getter ) ) {
            return '';
        }
        try {
            $value = $ability->$getter();
        } catch ( \Throwable $e ) {
            return '';
        }
        return is_scalar( $value ) ? (string) $value : '';
    }

    




    private static function ability_annotations( object $ability ): array {
        try {
            if ( method_exists( $ability, 'get_meta_item' ) ) {
                return (array) $ability->get_meta_item( 'annotations' );
            }
            if ( method_exists( $ability, 'get_annotations' ) ) {
                return (array) $ability->get_annotations();
            }
        } catch ( \Throwable $e ) {
            return array();
        }
        return array();
    }

    






    private static function tool_entry( Base_Tool $tool, string $name, string $state, string $where ): array {
        $annotations         = (array) $tool->get_annotations();
        list( $key, $label ) = self::tool_group( $tool, $name );
        $read_only           = ! empty( $annotations['readOnlyHint'] );
        $entry               = array(
            'kind'        => self::TOOL,
            'name'        => $name,
            'label'       => (string) $tool->get_title(),
            'summary'     => self::summary( (string) $tool->get_description() ),
            'group'       => $key,
            'group_label' => $label,
            'access'      => $read_only ? 'read' : 'write',
            'state'       => $state,
            'where'       => $where,
        );
        $requires = self::required_capability( $tool, $name );
        if ( '' !== $requires ) {
            $entry['requires'] = $requires;
        }
        $covers = trim( $tool->get_covers() );
        if ( '' !== $covers ) {
            $entry['covers'] = $covers;
        }
        if ( ! $read_only && ! empty( $annotations['destructiveHint'] ) ) {
            $entry['destructive'] = true;
        }
        return $entry;
    }

    




    private static function required_capability( Base_Tool $tool, string $name ): string {
        if ( class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' ) && null !== \Easy_MCP_AI\Privileged\Privileged_Groups::group_of_tool( $name ) ) {
            return '';
        }
        return (string) Server::effective_required_capability( $tool->get_category(), $tool->get_required_capability() );
    }

    





    private static function tool_group( Base_Tool $tool, string $name ): array {
        $category = (string) $tool->get_category();
        if ( isset( self::PROVIDERS[ $category ] ) ) {
            return array( self::PROVIDER . ':' . $category, self::PROVIDERS[ $category ][0] );
        }
        $integrations = Plugin_Integration_Registry::get_tool_group_map();
        if ( isset( $integrations[ $name ] ) ) {
            foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
                if ( $group['slug'] === $integrations[ $name ] ) {
                    return array( self::INTEGRATION . ':' . $group['slug'], (string) $group['name'] );
                }
            }
        }
        return array( 'tools:' . $category, ucfirst( str_replace( '_', ' ', $category ) ) . ' tools' );
    }

    



    private static function summary( string $text ): string {
        $text = trim( (string) preg_replace( '/\s+/', ' ', \wp_strip_all_tags( $text ) ) );
        
        
        if ( preg_match( '/^(.{20,}?(?<!e\.g|i\.e)[.!?])\s+[A-Z]/su', $text, $first ) ) {
            $text = $first[1];
        }
        return mb_strlen( $text ) > self::SUMMARY_MAX ? rtrim( mb_substr( $text, 0, self::SUMMARY_MAX - 1 ) ) . '…' : $text;
    }
}
