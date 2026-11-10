<?php

namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tool_Catalogue {

    const PLUGIN_REST_APIS = array( 'plugin_rest' );

    private $tool_registry;

    private $next_registry;

    public function __construct( $tool_registry, ?callable $next_registry = null ) {
        $this->tool_registry = $tool_registry;
        $this->next_registry = $next_registry;
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

    public function sections( array $groups, bool $persist = true ) {
        $this->off_names = null;
        if ( $persist && function_exists( 'wp_get_abilities' ) ) {
            $persist = \Easy_MCP_AI\Tools\Tool_Policy::observe_abilities( \Easy_MCP_AI\Tools\Excluded_Abilities::without( (array) \wp_get_abilities() ) );
        }
        $sections = array(
            'locked'             => \Easy_MCP_AI\Config::is_locked( 'easy_mcp_ai_disabled_tools' ),
            'abilitiesSupported' => function_exists( 'wp_get_abilities' ),
            'wpVersion'          => (string) \get_bloginfo( 'version' ),
            'core'               => $this->core_rows(),
            'plugins'            => $this->plugin_rows(),
            'external'           => $this->external_rows( $groups ),
            'abilities'          => $this->ability_rows(),
        );
        $sections['policy'] = $this->policy_catalogue( $sections, $persist );
        return $sections;
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
        foreach ( \Easy_MCP_AI\Tools\Excluded_Abilities::without( (array) \wp_get_abilities() ) as $ability ) {
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
                'label'  => \Easy_MCP_AI\Compat\Vendor_Abilities::vendor_of( (string) $prefix ) ?? ucfirst( (string) $prefix ),
                'status' => empty( $tools ) ? 'not_enabled' : 'active',
                'counts' => self::counts( $items ),
                'tools'  => $tools,
                'items'  => $items,
            );
        }
        return $rows;
    }

    private function metadata_registry() {
        $registry = new \Easy_MCP_AI\Tools\Tool_Registry();
        $registry->auto_discover( true, array_merge(
            \Easy_MCP_AI\Tools\Tool_Directories::ALWAYS_ON,
            array_values( \Easy_MCP_AI\Tools\Tool_Directories::PLUGIN_GROUPS ),
            \Easy_MCP_AI\Tools\Tool_Directories::EXTERNAL_DATA,
            array( \Easy_MCP_AI\Tools\Tool_Directories::BUILDERS )
        ) );
        return $registry;
    }

    private function ability_facts(): array {
        $hints    = array();
        $defaults = array();
        if ( function_exists( 'wp_get_abilities' ) ) {
            foreach ( (array) \wp_get_abilities() as $ability ) {
                $tool_name              = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::build_tool_name( (string) $ability->get_name() );
                $hints[ $tool_name ]    = \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar::annotations_of( $ability );
                $defaults[ $tool_name ] = \Easy_MCP_AI\Ability_Seed::seeds( $ability );
            }
        }
        return array( $hints, $defaults );
    }

    private static function ability_category( $hints, $access ): string {
        return $hints ? \Easy_MCP_AI\Tools\Tool_Policy::category( $hints, 'abilities' ) : ( 'read' === $access ? 'read' : 'write' );
    }

    private function policy_catalogue( array $sections, bool $persist ) {
        $metadata = $this->metadata_registry();
        $rows = array();
        foreach ( array( 'core', 'plugins', 'external', 'abilities' ) as $section ) {
            foreach ( $sections[ $section ] as $group ) {
                foreach ( $group['items'] as $item ) {
                    $item['section'] = $section;
                    $item['group'] = $group['key'];
                    $item['groupLabel'] = $group['label'];
                    $item['available'] = 'plugins' !== $section || ! empty( $group['installed'] );
                    $item['status'] = $group['status'] ?? 'active';
                    $rows[ $item['name'] ] = $item;
                }
            }
        }
        foreach ( $metadata->get_all_definitions() as $definition ) {
            $name = $definition['name'];
            if ( isset( $rows[ $name ] ) ) {
                continue;
            }
            $tool = $metadata->get_tool( $name );
            $group = $tool->get_category();
            $external = in_array( $group, External_Data_Service::PROVIDERS, true );
            $rows[ $name ] = array_merge( $this->detail( $definition, $external ? 'external' : 'core', (bool) $this->tool_registry->get_tool( $name ) ), array(
                'section' => $external ? 'external' : 'core',
                'group' => $group,
                'groupLabel' => External_Data_Service::LABELS[ $group ] ?? ( Tool_Groups::CORE_CATEGORY_LABELS[ $group ] ?? ucfirst( $group ) ),
                'available' => $external ? $this->external_service()->is_configured( $group ) : (bool) $this->tool_registry->get_tool( $name ),
                'status' => $external ? 'not_configured' : 'not_active',
            ) );
        }
        foreach ( $this->tool_registry->get_tools_by_category() as $group => $definitions ) {
            foreach ( $definitions as $definition ) {
                $name = $definition['name'];
                if ( isset( $rows[ $name ] ) ) {
                    continue;
                }
                $rows[ $name ] = array_merge( $this->detail( $definition, 'core', true ), array(
                    'section' => 'core', 'group' => $group, 'groupLabel' => ucfirst( $group ), 'available' => true, 'status' => 'active',
                ) );
            }
        }
        list( $ability_hints, $ability_defaults ) = $this->ability_facts();
        $categories = array();
        foreach ( $rows as $name => &$item ) {
            $tool = $this->tool_registry->get_tool( $name ) ?: $metadata->get_tool( $name );
            if ( $tool ) {
                $annotations = $tool->get_annotations();
                $item['requiredCapability'] = (string) $tool->get_required_capability();
                $item['category'] = \Easy_MCP_AI\Tools\Tool_Policy::category( $annotations, $tool->get_category() );
                $item['access'] = ! empty( $annotations['readOnlyHint'] ) ? 'read' : 'write';
                $item['recommended'] = array( 'enabled' => $ability_defaults[ $name ] ?? ! $tool->is_disabled_by_default(), 'requireApproval' => 'read' !== $item['category'] && \Easy_MCP_AI\Approvals\Approval_Gate::is_destructive_by_annotation( $tool ) );
            } else {
                $hints = $ability_hints[ $name ] ?? null;
                $item['category'] = self::ability_category( $hints, $item['access'] );
                $item['recommended'] = array( 'enabled' => $ability_defaults[ $name ] ?? false, 'requireApproval' => $hints && 'read' !== $item['category'] && \Easy_MCP_AI\Approvals\Approval_Gate::annotations_destructive( $hints ) );
                $item['requireApproval'] = \Easy_MCP_AI\Tools\Tool_Policy::approval_choice( $name ) ?? false;
            }
            $item['source'] = in_array( $item['section'], array( 'plugins', 'abilities' ), true ) ? 'abilities' : $item['section'];
            $categories[ $name ] = $item['category'];
        }
        unset( $item );
        $state = \Easy_MCP_AI\Tools\Tool_Policy::synchronize( $categories, $persist );
        $patterns = $this->allowed_patterns();
        $approval_lock = self::approval_lock();
        foreach ( $rows as $name => &$item ) {
            $tool = $this->tool_registry->get_tool( $name ) ?: $metadata->get_tool( $name );
            $choice = \Easy_MCP_AI\Tools\Tool_Policy::enabled_choice( $name );
            $enabled = $tool ? \Easy_MCP_AI\Tools\Tool_State::is_enabled( $tool ) : $item['enabled'];
            if ( $tool && ! $this->tool_registry->get_tool( $name ) ) {
                $enabled = false;
            }
            if ( null === $choice && ! $item['enabled'] ) {
                $enabled = false;
            }
            if ( ! empty( $state['known'][ $name ]['held'] ) && ! isset( $state['choices'][ $name ]['enabled'] ) && null === self::override_of( $name ) && ! \Easy_MCP_AI\Config::is_locked( 'disabled_tools' ) ) {
                $enabled = false;
            }
            $item['enabled'] = $item['available'] && $enabled && \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns );
            $approval_choice = \Easy_MCP_AI\Tools\Tool_Policy::approval_choice( $name );
            if ( $tool && null !== $approval_choice && ! \Easy_MCP_AI\Approvals\Approval_Gate::settings_deployed() ) {
                $item['requireApproval'] = (bool) \apply_filters( 'easy_mcp_ai_requires_approval', $approval_choice, $name, array(), 'explicit' );
            } elseif ( $tool ) {
                $item['requireApproval'] = \Easy_MCP_AI\Approvals\Approval_Gate::requires_approval( $tool, array() );
            } elseif ( isset( $state['choices'][ $name ]['requireApproval'] ) && ! \Easy_MCP_AI\Approvals\Approval_Gate::settings_deployed() ) {
                $item['requireApproval'] = (bool) \apply_filters( 'easy_mcp_ai_requires_approval', $state['choices'][ $name ]['requireApproval'], $name, array(), 'explicit' );
            }
            $item['lock'] = self::policy_lock( $name, $patterns );
            $item['locked'] = null !== $item['lock'];
            $item['approvalLock'] = $approval_lock;
            $item['approvalLocked'] = null !== $approval_lock;
        }
        unset( $item );
        return array(
            'tools'      => array_values( $rows ),
        );
    }

    private function plan_policy_external( array $spec ) {
        $write = $this->plan_external( $spec );
        if ( \is_wp_error( $write ) || ! $write ) {
            return $write;
        }
        $inactive = array();
        foreach ( $spec as $key => $changes ) {
            $inactive[ $key ] = array_diff(
                (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_' . $key . '_tools', array() ),
                $this->external_service()->tool_names( $key )
            );
        }
        return static function () use ( $write, $inactive ) {
            $write();
            foreach ( $inactive as $key => $names ) {
                if ( $names ) {
                    $option = 'easy_mcp_ai_disabled_' . $key . '_tools';
                    \Easy_MCP_AI\Config::update( $option, array_values( array_unique( array_merge( (array) \get_option( $option, array() ), $names ) ) ) );
                }
            }
            \Easy_MCP_AI\Config::update( 'easy_mcp_ai_disabled_tools', External_Data_Admin::merge_disabled_tool_buckets( (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() ) ) );
        };
    }

    private function plan_policy_plugins( array $spec ) {
        return static function () use ( $spec ) {
            $groups = (array) \get_option( 'easy_mcp_ai_enabled_plugin_groups', array() );
            $disabled = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_plugin_tools', array() );
            $global = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_disabled_tools', array() );
            $disabled = array_unique( array_merge( $disabled, array_intersect( $global, Plugin_Integration_Registry::get_all_tool_names() ) ) );
            foreach ( $spec as $slug => $changes ) {
                foreach ( $changes['tools'] as $name => $on ) {
                    $disabled = array_values( array_diff( $disabled, array( $name ) ) );
                    if ( $on ) {
                        if ( ! in_array( $slug, $groups, true ) ) {
                            $groups[] = $slug;
                            foreach ( Plugin_Integration_Registry::get_groups() as $group ) {
                                if ( $slug !== $group['slug'] ) {
                                    continue;
                                }
                                foreach ( array_diff( array_column( $group['tools'], 'name' ), array_keys( $changes['tools'] ) ) as $sibling ) {
                                    if ( true !== ( \Easy_MCP_AI\Tools\Tool_Policy::state()['choices'][ $sibling ]['enabled'] ?? null ) ) {
                                        $disabled[] = $sibling;
                                    }
                                }
                            }
                        }
                    } else {
                        $disabled[] = $name;
                    }
                }
            }
            Plugin_Integrations_Page::persist( $groups, $disabled );
        };
    }

    private static function policy_lock( $name, array $patterns ) {
        if ( $patterns && ! \Easy_MCP_AI\Config::is_locked( 'allowed_tool_patterns' ) && ! \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns ) ) {
            return array( 'setting' => 'easy_mcp_ai_allowed_tool_patterns', 'source' => 'option', 'reason' => \__( 'Left out by the allowed tool patterns saved on this site, which a tool must match to be callable.', 'easy-mcp-ai' ) );
        }
        if ( null !== self::override_of( $name ) ) {
            return array( 'setting' => 'EASY_MCP_AI_TOOLS', 'source' => \Easy_MCP_AI\Tools\Tool_State::override_source(), 'reason' => \__( 'Tool enablement is fixed by the deployment tool map.', 'easy-mcp-ai' ) );
        }
        if ( \Easy_MCP_AI\Config::is_locked( 'disabled_tools' ) && false === \Easy_MCP_AI\Tools\Tool_Policy::enabled_choice( $name ) && ! in_array( $name, \Easy_MCP_AI\Tools\Tool_State::disabled_list(), true ) ) {
            return array(
                'setting' => \Easy_MCP_AI\Tools\Tool_Policy::OPTION,
                'source'  => 'option',
                'reason'  => sprintf(
                    /* translators: %s: the deployed setting, EASY_MCP_AI_DISABLED_TOOLS. */
                    \__( 'Switched off by a choice saved on this site, which stays in force while %s is deployed.', 'easy-mcp-ai' ),
                    \Easy_MCP_AI\Config::constant_name( 'disabled_tools' )
                ),
            );
        }
        foreach ( array( 'disabled_tools', 'allowed_tool_patterns' ) as $setting ) {
            if ( ! \Easy_MCP_AI\Config::is_locked( $setting ) ) {
                continue;
            }
            if ( 'allowed_tool_patterns' === $setting && \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, (array) \Easy_MCP_AI\Config::get( $setting ) ) ) {
                continue;
            }
            return array( 'setting' => \Easy_MCP_AI\Config::constant_name( $setting ), 'source' => \Easy_MCP_AI\Config::source( $setting ), 'reason' => \__( 'Tool policy is fixed by this deployment setting.', 'easy-mcp-ai' ) );
        }
        return null;
    }

    public static function approval_lock() {
        if ( ! \Easy_MCP_AI\Approvals\Approval_Gate::settings_deployed() ) {
            return null;
        }
        foreach ( array( 'approval_required', 'approval_always', 'approval_never' ) as $setting ) {
            if ( \Easy_MCP_AI\Config::is_locked( $setting ) ) {
                return array( 'setting' => \Easy_MCP_AI\Config::constant_name( $setting ), 'source' => \Easy_MCP_AI\Config::source( $setting ), 'reason' => \__( 'Approval is fixed by this deployment setting.', 'easy-mcp-ai' ) );
            }
        }
        return null;
    }

    public function apply_policy( array $body ) {
        foreach ( $body as $field => $value ) {
            if ( ! in_array( $field, array( 'tools', 'namespaces' ), true ) ) {
                return self::invalid( $field, \__( 'Unknown field.', 'easy-mcp-ai' ) );
            }
        }
        $namespaces = array( 'off' => array(), 'on' => array() );
        if ( array_key_exists( 'namespaces', $body ) ) {
            if ( ! is_array( $body['namespaces'] ) || array_diff( array_keys( $body['namespaces'] ), array( 'off', 'on' ) ) ) {
                return self::invalid( 'namespaces', \__( 'Expected off and on namespace lists.', 'easy-mcp-ai' ) );
            }
            foreach ( array( 'off', 'on' ) as $field ) {
                $list = $body['namespaces'][ $field ] ?? array();
                if ( ! is_array( $list ) || array_values( $list ) !== $list ) {
                    return self::invalid( 'namespaces.' . $field, \__( 'Expected a list of namespaces.', 'easy-mcp-ai' ) );
                }
                foreach ( $list as $namespace ) {
                    if ( ! is_string( $namespace ) || 1 !== preg_match( \Easy_MCP_AI\Admin\Rest\Rest_Namespaces_Controller::NAMESPACE_PATTERN, trim( $namespace, ' /' ) ) ) {
                        return self::invalid( 'namespaces.' . $field, \__( 'Invalid namespace.', 'easy-mcp-ai' ) );
                    }
                }
                $namespaces[ $field ] = $list;
            }
            if ( array_intersect( array_map( array( '\Easy_MCP_AI\Rest_Routes\Namespace_State', 'normalize' ), $namespaces['off'] ), array_map( array( '\Easy_MCP_AI\Rest_Routes\Namespace_State', 'normalize' ), $namespaces['on'] ) ) ) {
                return self::invalid( 'namespaces', \__( 'A namespace cannot be both on and off.', 'easy-mcp-ai' ) );
            }
        }
        if ( array_key_exists( 'tools', $body ) && ! is_array( $body['tools'] ) ) {
            return self::invalid( 'tools', \__( 'Expected a tool-choice object.', 'easy-mcp-ai' ) );
        }
        $sections = $this->sections( array(), false );
        $rows = array_column( $sections['policy']['tools'], null, 'name' );
        $choices = array();
        $legacy = array();
        $core_off = array();
        $core_touched = array();
        foreach ( (array) ( $body['tools'] ?? array() ) as $name => $spec ) {
            $field = 'tools.' . $name;
            if ( ! isset( $rows[ $name ] ) || ! is_array( $spec ) || ! $spec || array_diff( array_keys( $spec ), array( 'enabled', 'requireApproval' ) ) ) {
                return self::invalid( $field, \__( 'Unknown tool or invalid choice.', 'easy-mcp-ai' ) );
            }
            foreach ( $spec as $key => $value ) {
                if ( ! is_bool( $value ) ) {
                    return self::invalid( $field . '.' . $key, \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                }
            }
            $row = $rows[ $name ];
            if ( $row['locked'] ) {
                continue;
            }
            if ( $row['approvalLocked'] ) {
                unset( $spec['requireApproval'] );
                if ( ! $spec ) {
                    continue;
                }
            }
            if ( ! $row['available'] ) {
                return self::invalid( $field, \__( 'This tool is not available; activate its plugin or configure its connection first.', 'easy-mcp-ai' ) );
            }
            $changed = array();
            foreach ( $spec as $key => $value ) {
                if ( $value !== $row[ $key ] ) {
                    $changed[ $key ] = $value;
                }
            }
            if ( 'read' === $row['category'] && ! empty( $changed['requireApproval'] ) ) {
                return self::invalid( $field . '.requireApproval', \__( 'Read tools do not offer approval.', 'easy-mcp-ai' ) );
            }
            if ( ! $changed ) {
                continue;
            }
            $choices[ $name ] = $changed;
            if ( ! array_key_exists( 'enabled', $changed ) ) {
                continue;
            }
            if ( 'core' === $row['section'] ) {
                $core_touched[] = $name;
                if ( ! $changed['enabled'] ) {
                    $core_off[] = $name;
                }
            } else {
                $legacy[ $row['section'] ][ $row['group'] ]['tools'][ $name ] = $changed['enabled'];
            }
        }
        $writes = array();
        foreach ( $legacy as $section => $spec ) {
            $write = 'plugins' === $section ? $this->plan_policy_plugins( $spec ) : ( 'external' === $section ? $this->plan_policy_external( $spec ) : $this->plan_abilities( $spec ) );
            if ( \is_wp_error( $write ) ) {
                return $write;
            }
            if ( $write ) {
                $writes[] = $write;
            }
        }
        $this->sections( array() );
        $result = new \WP_Error( 'easy_mcp_ai_policy_not_saved', \__( 'Another save of the tool policy is still running. Nothing was changed; try again.', 'easy-mcp-ai' ), array( 'status' => 500 ) );
        \Easy_MCP_AI\Tools\Tool_Policy::locked( function () use ( $choices, $writes, $core_touched, $core_off, $namespaces, $body, &$result ) {
            $result = $this->write_policy( $choices, $writes, $core_touched, $core_off, $namespaces, isset( $body['namespaces'] ) );
        } );
        if ( true === $result ) {
            $this->list_tools_again();
        }
        return $result;
    }

    private function list_tools_again(): void {
        if ( null !== $this->next_registry ) {
            $this->tool_registry = ( $this->next_registry )();
        }
    }

    private function write_policy( array $choices, array $writes, array $core_touched, array $core_off, array $namespaces, bool $namespaces_sent ) {
        $snapshot = self::snapshot_options();
        $saved = \Easy_MCP_AI\Tools\Tool_Policy::save( $choices );
        if ( \is_wp_error( $saved ) ) {
            return $saved;
        }
        $intended = array();
        $record   = static function ( $value, $old, $option ) use ( &$intended ) {
            $intended[ $option ] = $value;
            return $value;
        };
        $watched = array_diff( array_keys( $snapshot ), array( \Easy_MCP_AI\Tools\Tool_Policy::OPTION ) );
        foreach ( $watched as $option ) {
            \add_filter( 'pre_update_option_' . $option, $record, PHP_INT_MIN, 3 );
        }
        try {
            foreach ( $writes as $write ) {
                $write();
            }
            if ( $core_touched ) {
                self::store_choices( $core_touched, $core_off, self::defaults_of( $this->tool_registry, $core_touched ) );
            }
            if ( $namespaces_sent ) {
                \Easy_MCP_AI\Rest_Routes\Namespace_State::apply_choices( $namespaces['off'], $namespaces['on'] );
            }
        } finally {
            foreach ( $watched as $option ) {
                \remove_filter( 'pre_update_option_' . $option, $record, PHP_INT_MIN );
            }
        }
        $this->off_names = null;
        $failed = $this->unapplied( $choices, $namespaces, $namespaces_sent );
        foreach ( $failed ? array() : $intended as $option => $value ) {
            if ( ! self::stored_as( $value, \Easy_MCP_AI\Config::read_uncached( $option ) ) ) {
                $failed[] = $option;
            }
        }
        if ( $failed ) {
            $restored = self::restore_options( $snapshot );
            $message  = sprintf(
                /* translators: %s: comma-separated tool names and settings that did not apply. */
                \__( 'These changes did not apply: %s.', 'easy-mcp-ai' ),
                implode( ', ', $failed )
            );
            $message .= ' ' . ( $restored
                ? \__( 'Nothing was changed; try again.', 'easy-mcp-ai' )
                : \__( 'Restoring the previous state also failed; review the Tools screen.', 'easy-mcp-ai' ) );
            return new \WP_Error(
                'easy_mcp_ai_policy_not_applied',
                $message,
                array( 'status' => 500, 'failed' => $failed )
            );
        }
        return true;
    }

    private static function snapshot_options() {
        $options = array_merge(
            array(
                \Easy_MCP_AI\Tools\Tool_Policy::OPTION,
                'easy_mcp_ai_enabled_plugin_groups',
                'easy_mcp_ai_disabled_plugin_tools',
                'easy_mcp_ai_disabled_tools',
                'easy_mcp_ai_enabled_abilities',
                \Easy_MCP_AI\Tools\Tool_State::ENABLED_OPTION,
                \Easy_MCP_AI\Rest_Routes\Namespace_State::OPTION,
                'easy_mcp_ai_ahrefs_enabled',
            ),
            array_map(
                static function ( $key ) {
                    return 'easy_mcp_ai_disabled_' . $key . '_tools';
                },
                External_Data_Service::PROVIDERS
            )
        );
        $snapshot = array();
        foreach ( $options as $option ) {
            $snapshot[ $option ] = \Easy_MCP_AI\Config::read_uncached( $option );
        }
        return $snapshot;
    }

    private static function stored_as( $written, $read ): bool {
        if ( is_scalar( $written ) && is_scalar( $read ) ) {
            return (string) $written === (string) $read;
        }
        return $written === $read;
    }

    private static function restore_options( array $snapshot ) {
        $ok = true;
        foreach ( $snapshot as $option => $value ) {
            if ( \Easy_MCP_AI\Config::read_uncached( $option ) === $value ) {
                continue;
            }
            if ( null === $value ) {
                \delete_option( $option );
            } else {
                \update_option( $option, $value );
            }
            $ok = $ok && \Easy_MCP_AI\Config::read_uncached( $option ) === $value;
        }
        return $ok;
    }

    private function unapplied( array $choices, array $namespaces, $namespaces_sent ) {
        $failed = array();
        $patterns = $this->allowed_patterns();
        $fresh = null;
        foreach ( $choices as $name => $changed ) {
            if ( ! array_key_exists( 'enabled', $changed ) ) {
                continue;
            }
            $tool = $this->tool_registry->get_tool( $name );
            if ( ! $tool && $changed['enabled'] ) {
                if ( null === $fresh ) {
                    $fresh = new \Easy_MCP_AI\Tools\Tool_Registry();
                    $fresh->auto_discover();
                    ( new \Easy_MCP_AI\Tools\Dynamic_Tool_Registrar() )->register_to( $fresh );
                }
                $tool = $fresh->get_tool( $name );
            }
            $state_on = $tool && \Easy_MCP_AI\Tools\Tool_State::is_enabled( $tool );
            $on = $state_on && \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns );
            if ( $on !== $changed['enabled'] && ( $tool || $changed['enabled'] ) ) {
                $failed[] = $name;
            }
        }
        if ( $namespaces_sent ) {
            $off = \Easy_MCP_AI\Rest_Routes\Namespace_State::stored_off() ?? array();
            foreach ( array( 'off' => true, 'on' => false ) as $field => $expect_off ) {
                foreach ( $namespaces[ $field ] as $namespace ) {
                    $key = \Easy_MCP_AI\Rest_Routes\Namespace_State::normalize( (string) $namespace );
                    if ( '' !== $key && null === \Easy_MCP_AI\Rest_Routes\Namespace_State::override_of( $key ) && in_array( $key, $off, true ) !== $expect_off ) {
                        $failed[] = $key;
                    }
                }
            }
        }
        return $failed;
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
        $rendered = array();
        $explicit = array();

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
            }
            if ( array_key_exists( 'enabled', $changes ) ) {
                if ( ! is_bool( $changes['enabled'] ) ) {
                    return self::invalid( $field . '.enabled', \__( 'Expected true or false.', 'easy-mcp-ai' ) );
                }
                foreach ( self::not_overridden( array_keys( $slug_by_name ) ) as $name ) {
                    $explicit[ $slug_by_name[ $name ] ] = $changes['enabled'];
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
                        $explicit[ $slug_by_name[ $name ] ] = $on;
                    }
                }
            }
        }

        return static function () use ( $rendered, $explicit ) {
            $current = (array) ( \Easy_MCP_AI\Config::read_uncached( 'easy_mcp_ai_enabled_abilities' ) ?? array() );
            $checked = array();
            foreach ( $rendered as $slug ) {
                $checked[ $slug ] = array_key_exists( $slug, $explicit ) ? $explicit[ $slug ] : in_array( $slug, $current, true );
            }
            \update_option( 'easy_mcp_ai_enabled_abilities', Abilities_Page::compute_enabled( $current, $rendered, array_keys( array_filter( $checked ) ) ) );
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
