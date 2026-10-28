<?php

namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_Catalogue {

    const PLUGIN_REST_APIS = array( 'plugin_rest' );

    private $tool_registry;

    public function __construct( $tool_registry ) {
        $this->tool_registry = $tool_registry;
    }

    private $external_service;

    public function set_external_service( External_Data_Service $service ) {
        $this->external_service = $service;
    }

    private function external_service() {
        if ( ! $this->external_service ) {
            $this->external_service = new External_Data_Service();
        }
        return $this->external_service;
    }

    public function sections( array $groups ) {
        return array(
            'locked'             => \Easy_MCP_AI\Config::is_locked( 'easy_mcp_ai_disabled_tools' ),
            'abilitiesSupported' => function_exists( 'wp_get_abilities' ),
            'wpVersion'          => (string) \get_bloginfo( 'version' ),
            'core'               => $this->core_rows(),
            'plugins'            => $this->plugin_rows(),
            'external'           => $this->external_rows( $groups ),
            'abilities'          => $this->ability_rows(),
        );
    }

    private $off_names;

    private function disabled_tools() {
        if ( null === $this->off_names ) {
            $this->off_names = \Easy_MCP_AI\Tools\Tool_State::off_names( $this->tool_registry );
        }
        return $this->off_names;
    }

    private static function override_of( $name ) {
        return \Easy_MCP_AI\Tools\Tool_State::override_of( $name );
    }

    private static function not_overridden( array $names ) {
        return array_values( array_filter( $names, static function ( $name ) {
            return null === self::override_of( $name );
        } ) );
    }

    private function allowed_patterns() {
        return (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() );
    }

    private static function is_active( $name, array $disabled, array $patterns ) {
        if ( in_array( $name, $disabled, true ) ) {
            return false;
        }
        return \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns );
    }

    private function by_category() {
        return (array) $this->tool_registry->get_tools_by_category();
    }

    private function capability_of( $name ) {
        if ( ! method_exists( $this->tool_registry, 'get_tool' ) ) {
            return '';
        }
        $tool = $this->tool_registry->get_tool( $name );
        return $tool && method_exists( $tool, 'get_required_capability' ) ? (string) $tool->get_required_capability() : '';
    }

    private static function access_of( array $definition ) {
        return ! empty( $definition['annotations']['readOnlyHint'] ) ? 'read' : 'write';
    }

    private function detail( array $definition, $provenance, $enabled ) {
        $name = (string) ( $definition['name'] ?? '' );
        return array(
            'name'               => $name,
            'description'        => (string) ( $definition['description'] ?? '' ),
            'access'             => self::access_of( $definition ),
            'requiredCapability' => $this->capability_of( $name ),
            'provenance'         => $provenance,
            'enabled'            => (bool) $enabled,
            'pluginRestApi'      => false,
            'override'           => self::override_of( $name ),
        );
    }

    private static function counts( array $items ) {
        $counts = array( 'enabled' => 0, 'total' => count( $items ), 'read' => 0, 'write' => 0 );
        foreach ( $items as $item ) {
            if ( ! empty( $item['enabled'] ) ) {
                $counts['enabled']++;
            }
            $counts[ 'read' === $item['access'] ? 'read' : 'write' ]++;
        }
        return $counts;
    }

    private static function active_names( array $items, array $disabled, array $patterns ) {
        $names = array();
        foreach ( $items as $item ) {
            if ( ! empty( $item['enabled'] ) && self::is_active( $item['name'], $disabled, $patterns ) ) {
                $names[] = $item['name'];
            }
        }
        return $names;
    }

    private function core_tool_names() {
        $names = array();
        foreach ( $this->by_category() as $category => $definitions ) {
            if ( ! isset( Tool_Groups::CORE_CATEGORY_LABELS[ $category ] ) ) {
                continue;
            }
            foreach ( $definitions as $definition ) {
                $names[] = (string) $definition['name'];
            }
        }
        return $names;
    }

    private function core_rows() {
        $disabled    = $this->disabled_tools();
        $patterns    = $this->allowed_patterns();
        $by_category = $this->by_category();
        $rows        = array();
        foreach ( Tool_Groups::CORE_CATEGORY_LABELS as $category => $label ) {
            if ( empty( $by_category[ $category ] ) ) {
                continue;
            }
            $items = array();
            foreach ( $by_category[ $category ] as $definition ) {
                $items[] = $this->detail( $definition, 'core', ! in_array( $definition['name'], $disabled, true ) );
            }
            $row = array(
                'key'    => $category,
                'label'  => $label,
                'counts' => self::counts( $items ),
                'tools'  => self::active_names( $items, $disabled, $patterns ),
                'items'  => $items,
            );
            $covers = self::covers( $category );
            if ( array() !== $covers ) {
                $row['covers'] = $covers;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function covers( string $category ): array {
        if ( 'builders' !== $category ) {
            return array();
        }
        return array_map(
            static function ( \Easy_MCP_AI\Builders\Layout_Adapter $adapter ): string {
                return $adapter->label();
            },
            \Easy_MCP_AI\Builders\Builder_Registry::present()
        );
    }

    private function plugin_rows() {
        $disabled        = $this->disabled_tools();
        $patterns        = $this->allowed_patterns();
        $enabled_groups  = (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        $disabled_plugin = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_plugin_tools', array() );
        $registered      = array();
        foreach ( $this->by_category() as $definitions ) {
            $registered = array_merge( $registered, array_column( $definitions, 'name' ) );
        }
        $rows = array();
        foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
            $installed = Plugin_Integration_Registry::is_installed( $group );
            $enabled   = $installed && in_array( $group['slug'], $enabled_groups, true );
            $items     = array();
            foreach ( $group['tools'] as $tool ) {
                $override = self::override_of( (string) $tool['name'] );
                $items[]  = array(
                    'name'               => (string) $tool['name'],
                    'description'        => (string) $tool['description'],
                    'access'             => 'read' === $tool['type'] ? 'read' : 'write',
                    'requiredCapability' => $this->capability_of( $tool['name'] ),
                    'provenance'         => 'plugin',
                    'enabled'            => $enabled && ( null !== $override ? $override : ! in_array( $tool['name'], $disabled_plugin, true ) ),
                    'pluginRestApi'      => in_array( $tool['api'], self::PLUGIN_REST_APIS, true ),
                    'api'                => (string) $tool['api'],
                    'override'           => $override,
                );
            }
            $tools = array_values( array_intersect( self::active_names( $items, $disabled, $patterns ), $registered ) );
            if ( ! $installed ) {
                $status = 'not_installed';
            } elseif ( empty( $group['tools'] ) ) {
                $status = $enabled ? 'active' : 'no_tools';
            } elseif ( empty( $tools ) ) {
                $status = 'no_tools';
            } else {
                $status = 'active';
            }
            $rows[] = array(
                'key'         => (string) $group['slug'],
                'label'       => (string) $group['name'],
                'description' => (string) ( $group['description'] ?? '' ),
                'requires'    => (string) ( $group['requires'] ?? '' ),
                'maturity'    => (string) ( $group['status'] ?? 'stable' ),
                'paid'        => ! empty( $group['paid'] ),
                'installed'   => $installed,
                'enabled'     => $enabled,
                'status'      => $status,
                'counts'      => self::counts( $items ),
                'tools'       => $tools,
                'items'       => $items,
            );
            if ( ! empty( $group['abilities'] ) && is_string( $group['abilities'] ) ) {
                $rows[ count( $rows ) - 1 ]['abilities'] = $group['abilities'];
            }
        }
        return $rows;
    }

    private function external_rows( array $groups ) {
        $disabled    = $this->disabled_tools();
        $patterns    = $this->allowed_patterns();
        $by_category = $this->by_category();
        $rows        = array();
        foreach ( (array) ( $groups['external'] ?? array() ) as $label => $row ) {
            if ( 'not_configured' === ( $row['status'] ?? '' ) ) {
                continue;
            }
            $key   = (string) ( $row['key'] ?? '' );
            $items = array();
            foreach ( (array) ( $by_category[ $key ] ?? array() ) as $definition ) {
                $items[] = $this->detail( $definition, 'external', ! in_array( $definition['name'], $disabled, true ) );
            }
            $tools  = self::active_names( $items, $disabled, $patterns );
            $rows[] = array(
                'key'    => $key,
                'label'  => \Easy_MCP_AI\Admin\Rest\Tools_Controller::EXTERNAL_LABELS[ $key ] ?? (string) $label,
                'status' => empty( $tools ) ? 'no_tools' : 'active',
                'counts' => self::counts( $items ),
                'tools'  => $tools,
                'items'  => $items,
            );
        }
        return $rows;
    }

    private function ability_rows() {
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return array();
        }
        $disabled = $this->disabled_tools();
        $patterns = $this->allowed_patterns();
        $enabled  = (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        $grouped  = array();
        foreach ( (array) \wp_get_abilities() as $ability ) {
            $slug        = (string) $ability->get_name();
            $parts       = explode( '/', $slug, 2 );
            $prefix      = count( $parts ) > 1 ? $parts[0] : 'core';
            $annotations = Abilities_Page::ability_annotations( $ability );
            $name        = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::build_tool_name( $slug );
            $override    = self::override_of( $name );
            $grouped[ $prefix ][] = array(
                'name'               => $name,
                'slug'               => $slug,
                'label'              => (string) ( $ability->get_label() ?: $slug ),
                'description'        => (string) $ability->get_description(),
                'access'             => ! empty( $annotations['readonly'] ) ? 'read' : 'write',
                'requiredCapability' => $this->capability_of( $name ),
                'provenance'         => 'ability',
                'enabled'            => in_array( $slug, $enabled, true ) && false !== $override,
                'pluginRestApi'      => false,
                'override'           => $override,
            );
        }
        $rows = array();
        foreach ( $grouped as $prefix => $items ) {
            $tools  = self::active_names( $items, $disabled, $patterns );
            $rows[] = array(
                'key'    => (string) $prefix,
                'label'  => ucfirst( (string) $prefix ),
                'status' => empty( $tools ) ? 'not_enabled' : 'active',
                'counts' => self::counts( $items ),
                'tools'  => $tools,
                'items'  => $items,
            );
        }
        return $rows;
    }

    public function apply( array $body ) {
        $writes = array();
        foreach ( array( 'plugins', 'abilities', 'external', 'core' ) as $section ) {
            if ( ! array_key_exists( $section, $body ) ) {
                continue;
            }
            if ( ! is_array( $body[ $section ] ) ) {
                return self::invalid( $section, \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
            }
            $planned = $this->{"plan_$section"}( $body[ $section ] );
            if ( \is_wp_error( $planned ) ) {
                return $planned;
            }
            if ( null !== $planned ) {
                $writes[] = $planned;
            }
        }
        foreach ( $writes as $write ) {
            $write();
        }
        return true;
    }

    private static function invalid( $field, $message ) {
        return new \WP_Error( 'easy_mcp_ai_invalid_param', $message, array( 'status' => 400, 'field' => $field ) );
    }

    private static function locked_error() {
        if ( ! \Easy_MCP_AI\Config::is_locked( 'easy_mcp_ai_disabled_tools' ) ) {
            return null;
        }
        return new \WP_Error(
            'easy_mcp_ai_locked',
            \__( 'The disabled-tool list is set by a constant or environment variable on this site, so it cannot be changed here.', 'easy-mcp-ai' ),
            array( 'status' => 409, 'field' => 'core.disabled' )
        );
    }

    private function plan_plugins( array $spec ) {
        if ( empty( $spec ) ) {
            return null;
        }
        $locked = self::locked_error();
        if ( $locked ) {
            return $locked;
        }
        $groups = array();
        foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
            $groups[ $group['slug'] ] = $group;
        }
        $enabled_groups  = (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
        $disabled_plugin = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_plugin_tools', array() );

        $group_on = array();
        $checked  = array();
        foreach ( $groups as $slug => $group ) {
            $installed         = Plugin_Integration_Registry::is_installed( $group );
            $group_on[ $slug ] = $installed && in_array( $slug, $enabled_groups, true );
            foreach ( $group['tools'] as $tool ) {
                $checked[ $tool['name'] ] = $group_on[ $slug ] && ! in_array( $tool['name'], $disabled_plugin, true );
            }
        }

        foreach ( $spec as $slug => $changes ) {
            $field = 'plugins.' . $slug;
            if ( ! isset( $groups[ $slug ] ) ) {
                return self::invalid( $field, \__( 'Unknown plugin integration.', 'easy-mcp-ai' ) );
            }
            if ( ! is_array( $changes ) ) {
                return self::invalid( $field, \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
            }
            if ( ! Plugin_Integration_Registry::is_installed( $groups[ $slug ] ) ) {
                return self::invalid( $field, \__( 'This plugin is not installed or not active.', 'easy-mcp-ai' ) );
            }
            $names = array_column( $groups[ $slug ]['tools'], 'name' );
            if ( array_key_exists( 'enabled', $changes ) ) {
                if ( ! is_bool( $changes['enabled'] ) ) {
                    return self::invalid( $field . '.enabled', \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                }
                $group_on[ $slug ] = $changes['enabled'];
                foreach ( self::not_overridden( $names ) as $name ) {
                    $checked[ $name ] = $changes['enabled'];
                }
            }
            if ( array_key_exists( 'tools', $changes ) ) {
                if ( ! is_array( $changes['tools'] ) ) {
                    return self::invalid( $field . '.tools', \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
                }
                foreach ( $changes['tools'] as $name => $on ) {
                    if ( ! in_array( $name, $names, true ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Unknown tool.', 'easy-mcp-ai' ) );
                    }
                    if ( ! is_bool( $on ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                    }
                    if ( null !== self::override_of( $name ) ) {
                        continue;
                    }
                    $checked[ $name ] = $on;
                    if ( $on ) {
                        $group_on[ $slug ] = true;
                    }
                }
            }
        }

        $submitted_groups = array_keys( array_filter( $group_on ) );
        $submitted_tools  = array_keys( array_filter( $checked ) );
        return static function () use ( $submitted_groups, $submitted_tools ) {
            $state = Plugin_Integrations_Page::compute_state( $submitted_groups, $submitted_tools );
            Plugin_Integrations_Page::persist( $state['enabled_groups'], $state['disabled_plugin_tools'] );
        };
    }

    private function plan_abilities( array $spec ) {
        if ( empty( $spec ) ) {
            return null;
        }
        if ( ! function_exists( 'wp_get_abilities' ) ) {
            return self::invalid( 'abilities', \__( 'WordPress Abilities need WordPress 6.9 or later.', 'easy-mcp-ai' ) );
        }
        $groups = array();
        foreach ( $this->ability_rows() as $row ) {
            $groups[ $row['key'] ] = $row['items'];
        }
        $current  = (array) \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        $rendered = array();
        $checked  = array();

        foreach ( $spec as $key => $changes ) {
            $field = 'abilities.' . $key;
            if ( ! isset( $groups[ $key ] ) ) {
                return self::invalid( $field, \__( 'Unknown ability group.', 'easy-mcp-ai' ) );
            }
            if ( ! is_array( $changes ) ) {
                return self::invalid( $field, \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
            }
            $slug_by_name = array();
            foreach ( $groups[ $key ] as $item ) {
                $slug_by_name[ $item['name'] ] = $item['slug'];
                $rendered[]                    = $item['slug'];
                $checked[ $item['slug'] ]      = in_array( $item['slug'], $current, true );
            }
            if ( array_key_exists( 'enabled', $changes ) ) {
                if ( ! is_bool( $changes['enabled'] ) ) {
                    return self::invalid( $field . '.enabled', \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                }
                foreach ( self::not_overridden( array_keys( $slug_by_name ) ) as $name ) {
                    $checked[ $slug_by_name[ $name ] ] = $changes['enabled'];
                }
            }
            if ( array_key_exists( 'tools', $changes ) ) {
                if ( ! is_array( $changes['tools'] ) ) {
                    return self::invalid( $field . '.tools', \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
                }
                foreach ( $changes['tools'] as $name => $on ) {
                    if ( ! isset( $slug_by_name[ $name ] ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Unknown tool.', 'easy-mcp-ai' ) );
                    }
                    if ( ! is_bool( $on ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                    }
                    if ( null === self::override_of( $name ) ) {
                        $checked[ $slug_by_name[ $name ] ] = $on;
                    }
                }
            }
        }

        $checked_slugs = array_keys( array_filter( $checked ) );
        return static function () use ( $current, $rendered, $checked_slugs ) {
            \update_option( 'easy_mcp_ai_enabled_abilities', Abilities_Page::compute_enabled( $current, $rendered, $checked_slugs ) );
        };
    }

    private function plan_external( array $spec ) {
        if ( empty( $spec ) ) {
            return null;
        }
        $locked = self::locked_error();
        if ( $locked ) {
            return $locked;
        }
        $service = $this->external_service();
        $plans   = array();
        foreach ( $spec as $key => $changes ) {
            $field = 'external.' . $key;
            if ( ! External_Data_Service::is_provider( $key ) ) {
                return self::invalid( $field, \__( 'Unknown provider.', 'easy-mcp-ai' ) );
            }
            if ( ! is_array( $changes ) ) {
                return self::invalid( $field, \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
            }
            if ( ! $service->is_configured( $key ) ) {
                return self::invalid( $field, \__( 'Save credentials for this provider first.', 'easy-mcp-ai' ) );
            }
            $names   = $service->tool_names( $key );
            $bucket  = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_' . $key . '_tools', array() );
            $checked = array();
            foreach ( $names as $name ) {
                $checked[ $name ] = ! in_array( $name, $bucket, true );
            }
            if ( array_key_exists( 'enabled', $changes ) ) {
                if ( ! is_bool( $changes['enabled'] ) ) {
                    return self::invalid( $field . '.enabled', \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                }
                foreach ( self::not_overridden( $names ) as $name ) {
                    $checked[ $name ] = $changes['enabled'];
                }
            }
            if ( array_key_exists( 'tools', $changes ) ) {
                if ( ! is_array( $changes['tools'] ) ) {
                    return self::invalid( $field . '.tools', \__( 'Expected a JSON object.', 'easy-mcp-ai' ) );
                }
                foreach ( $changes['tools'] as $name => $on ) {
                    if ( ! in_array( $name, $names, true ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Unknown tool.', 'easy-mcp-ai' ) );
                    }
                    if ( ! is_bool( $on ) ) {
                        return self::invalid( $field . '.tools.' . $name, \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                    }
                    if ( null === self::override_of( $name ) ) {
                        $checked[ $name ] = $on;
                    }
                }
            }
            $plans[ $key ] = array_keys( array_filter( $checked ) );
        }
        return static function () use ( $service, $plans ) {
            foreach ( $plans as $key => $checked ) {
                $service->apply_enabled( $key, $checked );
            }
        };
    }

    private function plan_core( array $spec ) {
        if ( ! array_key_exists( 'disabled', $spec ) ) {
            return null;
        }
        $locked = self::locked_error();
        if ( $locked ) {
            return $locked;
        }
        if ( ! is_array( $spec['disabled'] ) ) {
            return self::invalid( 'core.disabled', \__( 'Expected a list of tool names.', 'easy-mcp-ai' ) );
        }
        $core_names = $this->core_tool_names();
        $disabled   = array();
        foreach ( $spec['disabled'] as $name ) {
            if ( ! is_string( $name ) || ! in_array( $name, $core_names, true ) ) {
                return self::invalid(
                    'core.disabled',
                    sprintf(
                        /* translators: %s: tool name */
                        \__( '%s is not a core tool.', 'easy-mcp-ai' ),
                        is_string( $name ) ? $name : \wp_json_encode( $name )
                    )
                );
            }
            $disabled[] = $name;
        }
        $defaults = self::defaults_of( $this->tool_registry, $core_names );
        return static function () use ( $core_names, $disabled, $defaults ) {
            self::store_choices( $core_names, $disabled, $defaults );
        };
    }

    public static function defaults_of( $registry, array $names ) {
        if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Base_Tool' ) ) {
            return array_fill_keys( $names, false );
        }
        $defaults = array();
        foreach ( $names as $name ) {
            $tool              = is_object( $registry ) && method_exists( $registry, 'get_tool' ) ? $registry->get_tool( $name ) : null;
            $defaults[ $name ] = $tool instanceof \Easy_MCP_AI\Tools\Base_Tool && $tool->is_disabled_by_default();
        }
        return $defaults;
    }

    public static function store_choices( array $names, array $off, array $defaults ) {
        $global  = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() );
        $current = \Easy_MCP_AI\Tools\Tool_State::stored_enabled_list();
        list( $disabled, $enabled ) = \Easy_MCP_AI\Tools\Tool_State::apply_choices( $names, $off, $defaults, $global, $current );
        \Easy_MCP_AI\Config::update(
            'easy_mcp_ai_disabled_tools',
            External_Data_Admin::merge_disabled_tool_buckets( $disabled )
        );
        if ( $enabled !== $current ) {
            \update_option( \Easy_MCP_AI\Tools\Tool_State::ENABLED_OPTION, $enabled, false );
        }
    }
}
