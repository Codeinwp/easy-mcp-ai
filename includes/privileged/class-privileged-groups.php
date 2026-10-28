<?php
namespace Easy_MCP_AI\Privileged;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Privileged_Groups {

    const SCOPE_PREFIX        = 'mcp:privileged:';
    const CATEGORY            = 'privileged';
    const TOOL_PREFIX         = 'wp_privileged_';

    const TIMEOUT_SECONDS = 30;
    const MEMORY_HEADROOM_BYTES = 134217728;
    const OUTPUT_MAX_BYTES = 65536;
    const RATE_LIMIT_PER_MINUTE = 60;

    const GROUPS = array(
        'files_read'     => array(
            'capability' => 'easy_mcp_ai_read_files',
            'floor'      => 'files',
            'read_only'  => true,
        ),
        'wp_cli'         => array(
            'capability' => 'easy_mcp_ai_run_wp_cli',
            'floor'      => 'code',
        ),
        'execute_php'    => array(
            'capability' => 'easy_mcp_ai_execute_php',
            'floor'      => 'code',
        ),
        'search_replace' => array(
            'capability' => 'easy_mcp_ai_search_replace',
            'floor'      => 'site',
        ),
    );

    const TOOL_CLASSES = array(
        '\\Easy_MCP_AI\\Tools\\Privileged\\Search_Replace_Run',
        '\\Easy_MCP_AI\\Tools\\Privileged\\Files_Read_Query',
    );

    const SITE_CAPABILITY_FILTER = 'easy_mcp_ai_search_replace_capability';

    private static $tools = array();

    public static function group_of_name( $name ): ?string {
        if ( ! is_string( $name ) || 0 !== strpos( $name, self::TOOL_PREFIX ) ) {
            return null;
        }
        $rest = substr( $name, strlen( self::TOOL_PREFIX ) );
        foreach ( array_keys( self::GROUPS ) as $group ) {
            if ( 0 === strpos( $rest, $group . '_' ) && preg_match( '/^[a-z0-9_]+\z/', substr( $rest, strlen( $group ) + 1 ) ) ) {
                return $group;
            }
        }
        return null;
    }

    public static function switch_source(): string {
        return \Easy_MCP_AI\Tools\Tool_State::override_source();
    }

    public static function switched_on_names(): array {
        $names = array_merge( array_keys( \Easy_MCP_AI\Tools\Tool_State::overrides() ), \Easy_MCP_AI\Tools\Tool_State::enabled_list() );
        $out   = array();
        foreach ( array_unique( array_map( 'strval', $names ) ) as $name ) {
            $group = self::group_of_name( $name );
            if ( null !== $group && \Easy_MCP_AI\Tools\Tool_State::resolve( $name, true )[0] ) {
                $out[ $group ][] = $name;
            }
        }
        $ordered = array();
        foreach ( array_keys( self::GROUPS ) as $group ) {
            if ( isset( $out[ $group ] ) ) {
                sort( $out[ $group ] );
                $ordered[ $group ] = $out[ $group ];
            }
        }
        return $ordered;
    }

    public static function switched_on_groups(): array {
        return array_keys( self::switched_on_names() );
    }

    public static function is_enabled( $group ): bool {
        return is_string( $group ) && in_array( $group, self::switched_on_groups(), true );
    }

    public static function enabled_groups(): array {
        return self::switched_on_groups();
    }

    public static function capability( $group ): string {
        return isset( self::GROUPS[ $group ] ) ? self::GROUPS[ $group ]['capability'] : '';
    }

    public static function group_for_capability( $cap ): ?string {
        foreach ( self::GROUPS as $group => $spec ) {
            if ( $spec['capability'] === $cap ) {
                return $group;
            }
        }
        return null;
    }

    public static function floor_passes( $group, $user_id ): bool {
        $user_id = (int) $user_id;
        if ( $user_id <= 0 || ! isset( self::GROUPS[ $group ] ) ) {
            return false;
        }
        switch ( self::GROUPS[ $group ]['floor'] ) {
            case 'code':
                return (bool) \user_can( $user_id, 'edit_plugins' );
            case 'files':
                if ( function_exists( 'is_multisite' ) && \is_multisite() ) {
                    return \is_super_admin( $user_id ) && (bool) \user_can( $user_id, 'manage_network_options' );
                }
                return (bool) \user_can( $user_id, 'manage_options' );
        }
        return (bool) \user_can( $user_id, self::site_capability() );
    }

    public static function is_read_only( $group ): bool {
        return is_string( $group ) && isset( self::GROUPS[ $group ] ) && ! empty( self::GROUPS[ $group ]['read_only'] );
    }

    public static function site_capability(): string {
        $cap = function_exists( 'apply_filters' ) ? \apply_filters( self::SITE_CAPABILITY_FILTER, 'manage_options' ) : 'manage_options';
        if ( ! is_string( $cap ) || '' === trim( $cap ) || null !== self::group_for_capability( $cap ) ) {
            return 'manage_options';
        }
        return $cap;
    }

    public static function user_holds( $group, $user_id ): bool {
        return self::is_enabled( $group ) && self::floor_passes( $group, $user_id );
    }

    public static function map_meta_cap( $caps, $cap, $user_id ) {
        $group = self::group_for_capability( $cap );
        if ( null === $group ) {
            return $caps;
        }
        return self::user_holds( $group, (int) $user_id ) ? array( 'exist' ) : array( 'do_not_allow' );
    }

    public static function admit_tool( $name, $group ): bool {
        if ( ! is_string( $group ) || self::group_of_name( $name ) !== $group ) {
            return false;
        }
        self::$tools[ $name ] = $group;
        return true;
    }

    public static function is_privileged_tool( $name ): bool {
        return is_string( $name ) && isset( self::$tools[ $name ] );
    }

    public static function group_of_tool( $name ): ?string {
        return is_string( $name ) && isset( self::$tools[ $name ] ) ? self::$tools[ $name ] : null;
    }

    public static function tool_names( ?array $groups = null ): array {
        $names = array();
        foreach ( self::$tools as $name => $group ) {
            if ( null === $groups || in_array( $group, $groups, true ) ) {
                $names[] = $name;
            }
        }
        sort( $names );
        return $names;
    }

    public static function reset_tools(): void {
        self::$tools = array();
    }

    public static function scope_for_group( string $group ): string {
        return self::SCOPE_PREFIX . $group;
    }

    public static function group_label( $group ): string {
        switch ( $group ) {
            case 'files_read':
                return \__( 'Read files on the server', 'easy-mcp-ai' );
            case 'wp_cli':
                return \__( 'Run WP-CLI commands', 'easy-mcp-ai' );
            case 'execute_php':
                return \__( 'Run PHP code', 'easy-mcp-ai' );
            case 'search_replace':
                return \__( 'Search and replace across the database', 'easy-mcp-ai' );
        }
        return '';
    }

    public static function is_privileged_scope( $scope ): bool {
        return is_string( $scope ) && 0 === strpos( $scope, self::SCOPE_PREFIX );
    }

    public static function group_for_scope( $scope ): ?string {
        if ( ! self::is_privileged_scope( $scope ) ) {
            return null;
        }
        $group = substr( $scope, strlen( self::SCOPE_PREFIX ) );
        return isset( self::GROUPS[ $group ] ) ? $group : null;
    }

    public static function enabled_scopes(): array {
        return array_map( array( __CLASS__, 'scope_for_group' ), self::enabled_groups() );
    }

    public static function tools_for_scopes( array $scopes ): array {
        $groups = array();
        foreach ( $scopes as $scope ) {
            $group = self::group_for_scope( $scope );
            if ( null !== $group && self::is_enabled( $group ) ) {
                $groups[] = $group;
            }
        }
        return empty( $groups ) ? array() : self::tool_names( $groups );
    }

    public static function split_scopes( array $scopes ): array {
        $privileged = array();
        $ordinary   = array();
        foreach ( $scopes as $scope ) {
            if ( self::is_privileged_scope( $scope ) ) {
                $privileged[] = $scope;
            } else {
                $ordinary[] = $scope;
            }
        }
        return array( $privileged, $ordinary );
    }

    public static function apply_soft_limits(): array {
        $previous = array(
            'time'   => ini_get( 'max_execution_time' ),
            'memory' => ini_get( 'memory_limit' ),
        );
        if ( function_exists( 'set_time_limit' ) ) {
            set_time_limit( self::TIMEOUT_SECONDS );
        }
        $target  = memory_get_usage( true ) + self::MEMORY_HEADROOM_BYTES;
        $current = self::bytes( (string) $previous['memory'] );
        if ( $current < 0 || $current > $target ) {
            ini_set( 'memory_limit', (string) $target ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- a documented guard around one privileged call, restored in restore_soft_limits().
        }
        return $previous;
    }

    public static function restore_soft_limits( array $previous ): void {
        if ( isset( $previous['memory'] ) && false !== $previous['memory'] ) {
            ini_set( 'memory_limit', (string) $previous['memory'] ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed -- restoring the request's own value.
        }
        if ( isset( $previous['time'] ) && false !== $previous['time'] && function_exists( 'set_time_limit' ) ) {
            set_time_limit( (int) $previous['time'] );
        }
    }

    private static function bytes( string $value ): int {
        $value = trim( $value );
        if ( '' === $value || '-1' === $value ) {
            return -1;
        }
        $number = (int) $value;
        switch ( strtolower( substr( $value, -1 ) ) ) {
            case 'g':
                $number *= 1024;
            case 'm':
                $number *= 1024;
            case 'k':
                $number *= 1024;
        }
        return $number;
    }

    public static function cap_output( $result ) {
        $text = is_string( $result ) ? $result : \wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $text ) || strlen( $text ) <= self::OUTPUT_MAX_BYTES ) {
            return $result;
        }
        $cut = function_exists( 'mb_strcut' ) ? mb_strcut( $text, 0, self::OUTPUT_MAX_BYTES, 'UTF-8' ) : substr( $text, 0, self::OUTPUT_MAX_BYTES );
        return $cut . "\n\n" . sprintf( '[Output truncated: %1$d of %2$d bytes shown.]', strlen( $cut ), strlen( $text ) );
    }

    public static function status(): array {
        $switched = self::switched_on_names();
        $groups   = array();
        foreach ( self::GROUPS as $group => $spec ) {
            $on    = isset( $switched[ $group ] ) ? $switched[ $group ] : array();
            $names = array_values( array_unique( array_merge( self::tool_names( array( $group ) ), $on ) ) );
            sort( $names );
            $tools = array();
            foreach ( $names as $name ) {
                $tools[] = array(
                    'name'      => $name,
                    'on'        => in_array( $name, $on, true ),
                    'installed' => isset( self::$tools[ $name ] ),
                );
            }
            $groups[ $group ] = array(
                'enabled'    => ! empty( $on ),
                'capability' => $spec['capability'],
                'scope'      => self::scope_for_group( $group ),
                'tools'      => $tools,
            );
        }
        return array(
            'source' => self::switch_source(),
            'strict' => \Easy_MCP_AI\Tools\Tool_State::is_strict(),
            'groups' => $groups,
        );
    }
}
