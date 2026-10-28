<?php

namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Admin_Page;
use Easy_MCP_AI\Admin\History_Settings_Page;
use Easy_MCP_AI\Admin\Settings_Validator;
use Easy_MCP_AI\Admin\Tool_Catalogue;
use Easy_MCP_AI\Auth\Connect_Policy;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings_Controller extends Admin_Rest_Controller {

    const SECTIONS = array( 'access', 'logging', 'usage', 'advanced', 'capture' );

    const LOGGER_FLAG_OPTION = 'easy_mcp_ai_logger_flag';

    const ASKED_OPTION = 'easy_mcp_ai_usage_consent_asked';

    const FIELDS = array(
        'access'   => array(
            'oauthMinCapability'  => 'oauth_min_capability',
            'connectRoles'        => 'connect_roles',
            'forceDraftOnCreate'  => 'force_draft_on_create',
            'selfServiceKeys'     => 'self_service_keys',
            'approvalRequired'    => 'approval_required',
            'approvalAlways'      => 'approval_always',
            'approvalNever'       => 'approval_never',
        ),
        'logging'  => array(
            'auditLogEnabled'    => 'audit_log_enabled',
            'auditLogRetention'  => 'audit_log_retention',
            'changeLogEnabled'   => 'change_log_enabled',
            'changeLogRetention' => 'change_log_retention',
        ),
        'usage'    => array(
            'shareUsageData'  => 'logger_flag',
            'dismissUsageAsk' => 'usage_consent_asked',
        ),
        'advanced' => array(
            'rateLimitPerMinute'        => 'rate_limit_per_minute',
            'externalDataMinCapability' => 'external_data_min_capability',
            'maxTitleLength'            => 'max_title_length',
            'disabledTools'             => 'disabled_tools',
            'allowedToolPatterns'       => 'allowed_tool_patterns',
            'ipWhitelist'               => 'ip_whitelist',
        ),
        'capture'  => array(
            'optionMode'     => 'change_log_option_mode',
            'captureMeta'    => 'change_log_capture_meta',
            'captureDb'      => 'change_log_capture_db',
            'dbRetention'    => 'change_log_db_retention',
            'externalIntent' => 'change_log_external_intent',
        ),
    );

    private $tool_registry;

    public function __construct( $tool_registry = null ) {
        $this->tool_registry = $tool_registry;
    }

    public function register_routes() {
        $this->register_route(
            '/settings/paused',
            self::UPDATABLE,
            array( $this, 'patch_paused' ),
            array(
                'paused' => array(
                    'required' => true,
                    'type'     => 'boolean',
                ),
            )
        );
        $this->register_route( '/settings', \WP_REST_Server::READABLE, array( $this, 'get_settings' ) );
        $this->register_route(
            '/settings/(?P<section>' . implode( '|', self::SECTIONS ) . ')',
            self::UPDATABLE,
            array( $this, 'patch_section' ),
            array(
                'section' => array(
                    'required' => true,
                    'type'     => 'string',
                    'enum'     => self::SECTIONS,
                ),
            )
        );
    }

    public function patch_paused( $request ) {
        $raw = $request->get_param( 'paused' );
        if ( ! is_bool( $raw ) && ! in_array( $raw, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
            return $this->fail(
                'easy_mcp_ai_invalid_param',
                \__( 'Expected true or false.', 'easy-mcp-ai' ),
                400
            );
        }
        if ( Config::is_locked( 'paused' ) ) {
            return $this->fail(
                'easy_mcp_ai_setting_locked',
                \__( 'This setting is set in wp-config.php or the environment and cannot be changed here.', 'easy-mcp-ai' ),
                409
            );
        }
        Config::update( 'easy_mcp_ai_paused', \rest_sanitize_boolean( $raw ) );
        return $this->ok( array( 'paused' => true === Config::get( 'paused' ) ) );
    }

    public function get_settings( $request ) {
        return $this->ok( $this->payload() );
    }

    public function payload() {
        $patterns = (array) Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() );
        $grid     = $this->grid();
        $defaults = Tool_Catalogue::defaults_of( $this->tool_registry, $grid );

        $sections = array(
            'access'   => array(
                'oauthMinCapability'  => Connect_Policy::stored_floor(),
                'connectRoles'        => Connect_Policy::ticked_roles(),
                'forceDraftOnCreate'  => (bool) Config::get( 'easy_mcp_ai_force_draft_on_create', false ),
                'selfServiceKeys'     => true === Config::get( 'self_service_keys' ),
                'approvalRequired'    => true === Config::get( 'approval_required' ),
                'approvalAlways'      => array_values( (array) Config::get( 'approval_always' ) ),
                'approvalNever'       => array_values( (array) Config::get( 'approval_never' ) ),
            ),
            'logging'  => array(
                'auditLogEnabled'    => (bool) Config::get( 'easy_mcp_ai_audit_log_enabled', true ),
                'auditLogRetention'  => self::audit_retention(),
                'changeLogEnabled'   => (bool) Config::get( 'easy_mcp_ai_change_log_enabled', true ),
                'changeLogRetention' => \Easy_MCP_AI\Plugin::change_log_retention_days(),
            ),
            'usage'    => array(
                'shareUsageData' => 'yes' === \get_option( self::LOGGER_FLAG_OPTION, 'no' ),
            ),
            'advanced' => array(
                'rateLimitPerMinute'        => self::rate_limit(),
                'externalDataMinCapability' => Admin_Page::sanitize_external_data_min_capability( Config::get( 'easy_mcp_ai_external_data_min_capability', 'manage_options' ) ),
                'maxTitleLength'            => max( 0, (int) Config::get( 'easy_mcp_ai_max_title_length', 0 ) ),
                'disabledTools'             => array_values( array_filter( $grid, static function ( $name ) use ( $defaults ) {
                    return Tool_State::stored_off( $name, $defaults[ $name ] );
                } ) ),
                'allowedToolPatterns'       => array_values( array_filter( $patterns, 'is_string' ) ),
                'ipWhitelist'               => (string) Config::get( 'easy_mcp_ai_ip_whitelist', '' ),
            ),
            'capture'  => array(
                'optionMode'     => History_Settings_Page::sanitize_mode( Config::get( 'easy_mcp_ai_change_log_option_mode', 'all_except_denylist' ) ),
                'captureMeta'    => (bool) Config::get( 'easy_mcp_ai_change_log_capture_meta', true ),
                'captureDb'      => (bool) Config::get( 'easy_mcp_ai_change_log_capture_db', false ),
                'dbRetention'    => History_Settings_Page::sanitize_db_retention( Config::get( 'easy_mcp_ai_change_log_db_retention', 7 ) ),
                'externalIntent' => (bool) Config::get( 'easy_mcp_ai_change_log_external_intent', true ),
            ),
        );

        $locked = array();
        foreach ( self::FIELDS as $fields ) {
            foreach ( $fields as $field => $suffix ) {
                if ( ! self::is_sdk_field( $suffix ) && Config::is_locked( $suffix ) ) {
                    $locked[ $field ] = Config::constant_name( $suffix );
                }
            }
        }
        if ( ! isset( $locked['connectRoles'] ) && isset( $locked['oauthMinCapability'] ) ) {
            $locked['connectRoles'] = $locked['oauthMinCapability'];
        }

        return array(
            'sections'   => $sections,
            'locked'     => $locked,
            'choices'    => array(
                'oauthCapabilities'        => self::capability_choices( Admin_Page::oauth_min_capability_choices() ),
                'connectRoles'             => Connect_Policy::role_choices(),
                'connectRolesFiltered'     => Connect_Policy::is_filtered_for( Connect_Policy::CHANNEL_OAUTH ),
                'externalDataCapabilities' => self::capability_choices( Admin_Page::external_data_min_capability_choices() ),
                'optionModes'              => array(
                    array( 'value' => 'all_except_denylist', 'label' => \__( 'All options except churn (recommended)', 'easy-mcp-ai' ) ),
                    array( 'value' => 'allowlist', 'label' => \__( 'Allowlist only (legacy)', 'easy-mcp-ai' ) ),
                ),
                'destructiveTools'         => $grid,
                'toolOverrides'            => (object) array_intersect_key( Tool_State::overrides(), array_flip( $grid ) ),
                'toolOverridesSource'      => Tool_State::override_source(),
                'toolOverridesStrict'      => Tool_State::is_strict(),
                'approvalDefaultTools'     => $this->approval_default_tools(),
            ),
            'tools'      => $this->tool_counts( $patterns ),
            'controlled' => self::controlled(),
        );
    }

    private static function controlled() {
        $invalid = \Easy_MCP_AI\Config_Admin::invalid_settings();
        $list    = array();
        foreach ( Config::controlled() as $constant => $source ) {
            $list[] = array(
                'constant' => $constant,
                'source'   => $source,
                'invalid'  => in_array( $constant, $invalid, true ),
            );
        }
        return $list;
    }

    private function approval_default_tools() {
        if ( ! $this->tool_registry instanceof \Easy_MCP_AI\Tools\Tool_Registry ) {
            return array();
        }
        return \Easy_MCP_AI\Approvals\Approval_Gate::default_pause_names(
            $this->tool_registry,
            \Easy_MCP_AI\MCP\Server::available_tools( $this->tool_registry )
        );
    }

    private static function audit_retention() {
        $stored = Config::get( 'easy_mcp_ai_audit_log_retention', 30 );
        return is_numeric( $stored ) ? max( 1, (int) $stored ) : 30;
    }

    private static function rate_limit() {
        $stored = (int) Config::get( 'easy_mcp_ai_rate_limit_per_minute', 60 );
        return $stored < 1 ? 60 : $stored;
    }

    private static function capability_choices( array $choices ) {
        $list = array();
        foreach ( $choices as $cap => $label ) {
            $list[] = array( 'value' => $cap, 'label' => $label . ' (' . $cap . ')' );
        }
        return $list;
    }

    private function tool_counts( array $patterns ) {
        $names = array();
        if ( $this->tool_registry && method_exists( $this->tool_registry, 'get_all_tool_names' ) ) {
            $names = array_values( array_diff( (array) $this->tool_registry->get_all_tool_names(), Tool_State::off_names( $this->tool_registry ) ) );
        }
        return array(
            'total'    => count( $names ),
            'matching' => empty( $patterns ) ? null : count( self::matching_tools( $names, $patterns ) ),
        );
    }

    public static function matching_tools( array $names, array $patterns ) {
        return array_values( array_filter( $names, static function ( $name ) use ( $patterns ) {
            return \Easy_MCP_AI\MCP\Server::matches_tool_patterns( $name, $patterns );
        } ) );
    }

    public function patch_section( $request ) {
        $section = (string) $request->get_param( 'section' );
        if ( ! isset( self::FIELDS[ $section ] ) ) {
            return $this->fail( 'easy_mcp_ai_invalid_param', \__( 'Unknown settings section.', 'easy-mcp-ai' ), 400, array( 'field' => 'section' ) );
        }
        $body = $request->get_json_params();
        if ( ! is_array( $body ) || empty( $body ) ) {
            $body = (array) $request->get_body_params();
        }

        $writes = array();
        foreach ( self::FIELDS[ $section ] as $field => $suffix ) {
            if ( ! array_key_exists( $field, $body ) ) {
                continue;
            }
            if ( ! self::is_sdk_field( $suffix ) && Config::is_locked( $suffix ) ) {
                return $this->deployed( $field, $suffix );
            }
            $value = $this->validate_field( $field, $body[ $field ] );
            if ( \is_wp_error( $value ) ) {
                return $value;
            }
            $writes[ $suffix ] = $value;
        }

        foreach ( $writes as $suffix => $value ) {
            if ( 'logger_flag' === $suffix ) {
                \update_option( self::LOGGER_FLAG_OPTION, $value ? 'yes' : 'no' );
                \update_option( self::ASKED_OPTION, 1 );
            } elseif ( 'usage_consent_asked' === $suffix ) {
                if ( $value ) {
                    \update_option( self::ASKED_OPTION, 1 );
                }
            } elseif ( 'disabled_tools' === $suffix ) {
                $this->write_disabled_tools( $value );
            } elseif ( 'connect_roles' === $suffix ) {
                Config::update( Connect_Policy::FLOOR_OPTION, $value[0] );
                Config::update( 'easy_mcp_ai_connect_roles', $value[1] );
            } else {
                Config::update( 'easy_mcp_ai_' . $suffix, $value );
            }
        }
        return $this->ok( $this->payload() );
    }

    private static function is_sdk_field( $suffix ) {
        return in_array( $suffix, array( 'logger_flag', 'usage_consent_asked' ), true );
    }

    public static function usage_ask(): bool {
        return 'yes' !== \get_option( self::LOGGER_FLAG_OPTION, 'no' )
            && ! \get_option( self::ASKED_OPTION, false )
            && ! Config::is_white_labelled();
    }

    private function connect_roles( $raw ) {
        $field = 'connectRoles';
        if ( Config::is_locked( 'oauth_min_capability' ) ) {
            return $this->deployed( $field, 'oauth_min_capability' );
        }
        if ( Connect_Policy::is_filtered_for( Connect_Policy::CHANNEL_OAUTH ) ) {
            return $this->locked( $field, self::filter_decides() );
        }
        $choices = Connect_Policy::role_choices();
        $known   = array_column( $choices, 'value' );
        if ( ! is_array( $raw ) || array_filter( $raw, static function ( $role ) use ( $known ) {
            return ! is_string( $role ) || ! in_array( $role, $known, true );
        } ) ) {
            return $this->invalid( $field, \__( 'Pick roles from the list.', 'easy-mcp-ai' ) );
        }
        foreach ( $choices as $choice ) {
            if ( $choice['always'] && ! in_array( $choice['value'], $raw, true ) ) {
                return $this->invalid( $field, \__( 'Administrators can always connect.', 'easy-mcp-ai' ) );
            }
        }
        $split = Connect_Policy::split_ticks( array_values( array_unique( $raw ) ) );
        if ( Connect_Policy::filter_overrides( $split[0] ) ) {
            return $this->locked( $field, self::filter_decides() );
        }
        return $split;
    }

    private function locked( string $field, string $message ): \WP_Error {
        return $this->fail( 'easy_mcp_ai_setting_locked', $message, 409, array( 'field' => $field ) );
    }

    private function deployed( string $field, string $suffix ): \WP_Error {
        return $this->locked(
            $field,
            sprintf(
                /* translators: %s: the wp-config constant name */
                \__( 'This setting is set by %s in wp-config.php or the environment and cannot be changed here.', 'easy-mcp-ai' ),
                Config::constant_name( $suffix )
            )
        );
    }

    private static function filter_decides(): string {
        return \__( 'Who can connect is set in code by the easy_mcp_ai_oauth_min_capability filter and cannot be changed here.', 'easy-mcp-ai' );
    }

    private function validate_field( $field, $raw ) {
        switch ( $field ) {
            case 'forceDraftOnCreate':
            case 'selfServiceKeys':
            case 'approvalRequired':
            case 'auditLogEnabled':
            case 'changeLogEnabled':
            case 'shareUsageData':
            case 'dismissUsageAsk':
            case 'captureMeta':
            case 'captureDb':
            case 'externalIntent':
                if ( ! is_bool( $raw ) && ! in_array( $raw, array( 0, 1, '0', '1', 'true', 'false' ), true ) ) {
                    return $this->invalid( $field, \__( 'Must be on or off.', 'easy-mcp-ai' ) );
                }
                return \rest_sanitize_boolean( $raw );

            case 'oauthMinCapability':
                if ( ! is_string( $raw ) || ! array_key_exists( $raw, Admin_Page::oauth_min_capability_choices() ) ) {
                    return $this->invalid( $field, \__( 'Pick one of the listed capabilities. The floor is Author.', 'easy-mcp-ai' ) );
                }
                return $raw;

            case 'connectRoles':
                return $this->connect_roles( $raw );

            case 'externalDataMinCapability':
                if ( ! is_string( $raw ) || ! array_key_exists( $raw, Admin_Page::external_data_min_capability_choices() ) ) {
                    return $this->invalid( $field, \__( 'Pick one of the listed capabilities.', 'easy-mcp-ai' ) );
                }
                return $raw;

            case 'auditLogRetention':
                return $this->integer( $field, $raw, 1, 365 );
            case 'changeLogRetention':
                return $this->integer( $field, $raw, 1, 3650 );
            case 'rateLimitPerMinute':
                return $this->integer( $field, $raw, 1, 1000 );
            case 'maxTitleLength':
                return $this->integer( $field, $raw, 0, 2000 );
            case 'dbRetention':
                return $this->integer( $field, $raw, 0, 3650 );

            case 'optionMode':
                if ( ! is_string( $raw ) || ! in_array( $raw, History_Settings_Page::VALID_MODES, true ) ) {
                    return $this->invalid( $field, \__( 'Pick one of the recording modes.', 'easy-mcp-ai' ) );
                }
                return $raw;

            case 'disabledTools':
                if ( ! is_array( $raw ) ) {
                    return $this->invalid( $field, \__( 'Expected a list of tool names.', 'easy-mcp-ai' ) );
                }
                $grid = $this->grid();
                foreach ( $raw as $name ) {
                    if ( ! is_string( $name ) || ! in_array( $name, $grid, true ) ) {
                        return $this->invalid(
                            $field,
                            sprintf(
                                /* translators: %s: tool name */
                                \__( '%s is not one of the tools this list can disable.', 'easy-mcp-ai' ),
                                is_string( $name ) ? $name : \wp_json_encode( $name )
                            )
                        );
                    }
                }
                return array_values( array_unique( $raw ) );

            case 'allowedToolPatterns':
            case 'approvalAlways':
            case 'approvalNever':
                if ( ! is_string( $raw ) && ! is_array( $raw ) ) {
                    return $this->invalid( $field, \__( 'Expected comma-separated glob patterns.', 'easy-mcp-ai' ) );
                }
                $patterns = Settings_Validator::parse_patterns( $raw );
                foreach ( $patterns as $pattern ) {
                    if ( 'allowedToolPatterns' === $field && ! Settings_Validator::is_valid_pattern( $pattern ) ) {
                        return $this->invalid(
                            $field,
                            sprintf(
                                /* translators: %s: the rejected pattern */
                                \__( '%s is not a tool-name pattern. Use letters, digits, _ and the wildcards * and ?.', 'easy-mcp-ai' ),
                                $pattern
                            )
                        );
                    }
                    if ( 'allowedToolPatterns' !== $field && ! Settings_Validator::is_valid_approval_entry( $pattern ) ) {
                        return $this->invalid(
                            $field,
                            sprintf(
                                /* translators: %s: the rejected entry */
                                \__( '%s is not a tool-name pattern, or one followed by a REST route such as "wp_rest_write /redirection/v1/*". Use letters, digits, _ and the wildcards * and ?.', 'easy-mcp-ai' ),
                                $pattern
                            )
                        );
                    }
                }
                return 'allowedToolPatterns' === $field ? $patterns : array_values( array_unique( $patterns ) );

            case 'ipWhitelist':
                if ( ! is_string( $raw ) ) {
                    return $this->invalid( $field, \__( 'Expected one IP address or CIDR range per line.', 'easy-mcp-ai' ) );
                }
                $parsed = Settings_Validator::sanitize_ip_whitelist( \sanitize_textarea_field( $raw ) );
                if ( ! empty( $parsed['invalid'] ) ) {
                    return $this->invalid(
                        $field,
                        sprintf(
                            /* translators: %s: the rejected lines, comma-separated */
                            \__( 'Not an IP address or CIDR range: %s', 'easy-mcp-ai' ),
                            implode( ', ', array_map( 'trim', $parsed['invalid'] ) )
                        )
                    );
                }
                return $parsed['value'];
        }
        return $this->invalid( $field, \__( 'Unknown field.', 'easy-mcp-ai' ) );
    }

    private function integer( $field, $raw, $min, $max ) {
        $value = Settings_Validator::integer_in_range( $raw, $min, $max );
        if ( null === $value ) {
            return $this->invalid(
                $field,
                sprintf(
                    /* translators: 1: minimum, 2: maximum */
                    \__( 'Enter a whole number from %1$d to %2$d.', 'easy-mcp-ai' ),
                    $min,
                    $max
                )
            );
        }
        return $value;
    }

    private function invalid( $field, $message ) {
        return $this->fail( 'easy_mcp_ai_invalid_setting', $message, 400, array( 'field' => $field ) );
    }

    private function write_disabled_tools( array $submitted ) {
        $grid = $this->grid();
        Tool_Catalogue::store_choices( $grid, $submitted, Tool_Catalogue::defaults_of( $this->tool_registry, $grid ) );
    }

    private function grid(): array {
        $grid = Settings_Validator::destructive_tools();
        if ( ! $this->tool_registry || ! method_exists( $this->tool_registry, 'get_all_tool_names' ) || ! method_exists( $this->tool_registry, 'get_tool' ) ) {
            return $grid;
        }
        $default_off = array();
        foreach ( (array) $this->tool_registry->get_all_tool_names() as $name ) {
            $tool = $this->tool_registry->get_tool( $name );
            if ( $tool instanceof \Easy_MCP_AI\Tools\Base_Tool && $tool->is_disabled_by_default() ) {
                $default_off[] = (string) $name;
            }
        }
        sort( $default_off );
        return array_values( array_unique( array_merge( $grid, $default_off ) ) );
    }
}
