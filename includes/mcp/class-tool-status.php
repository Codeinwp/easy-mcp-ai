<?php

namespace Easy_MCP_AI\MCP;

use Easy_MCP_AI\Config;
use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Tool_Status {

    const TOOLS_PATH = 'Easy MCP AI › Tools';

    const EXTERNAL_PATH = 'Easy MCP AI › Tools › Connections';

    const API_TOKENS_PATH = 'Easy MCP AI › Connections › API tokens';

    const IN_CONFIGURATION = 'switched off in this site\'s configuration (wp-config.php or the server environment), where only the site owner can switch %s on';

    const CONFIGURATION_PLACE = 'in this site\'s configuration, where only the site owner can switch it on';

    public static function switch_locked( string $tool_name ): bool {
        return null !== Tool_State::override_of( $tool_name ) || Config::is_locked( 'easy_mcp_ai_disabled_tools' );
    }

    public static function switch_path(): string {
        return Config::brand( self::TOOLS_PATH );
    }

    public static function place( string $path, bool $locked = false ): string {
        return $locked ? self::CONFIGURATION_PLACE : self::in_admin( Config::brand( $path ) );
    }

    public static function in_admin( string $branded_path ): string {
        return 'wp-admin: ' . $branded_path;
    }

    public static function off( string $tool_name, bool $default_off ): array {
        if ( self::switch_locked( $tool_name ) ) {
            return array( 'status' => 'off_locked' );
        }
        return array(
            'status' => 'off',
            'where'  => self::switch_path(),
        );
    }

    public static function predicate( array $status ): string {
        switch ( $status['status'] ) {
            case 'paused':
                return 'is unavailable, like every tool, while this site has paused AI access; only the site owner can resume it.';
            case 'policy':
                return 'is unavailable, like every tool, while the tool policy set in this site\'s configuration (EASY_MCP_AI_DISABLED_TOOLS or EASY_MCP_AI_ALLOWED_TOOL_PATTERNS, in wp-config.php or the server environment) cannot be read; only the site owner can fix it.';
            case 'not_allowed':
                return 'is not included in this connection\'s allowed tools (the site owner can widen the API key or the OAuth grant).';
            case 'not_registered':
                return 'is not available on this site.';
            case 'capability':
                return sprintf( 'needs the %s capability, which your WordPress account does not have.', $status['capability'] );
            case 'blocked':
                return sprintf( 'needs the %s capability, which %s.', $status['capability'], $status['cause'] );
            case 'off':
                return sprintf( 'is switched off on this site. An administrator can switch it on in wp-admin under %s.', $status['where'] );
            case 'off_locked':
                return sprintf( 'is ' . self::IN_CONFIGURATION . '.', 'it' );
            case 'pattern':
                return Config::is_locked( 'easy_mcp_ai_allowed_tool_patterns' )
                    ? 'is excluded by the allowed tool patterns set in this site\'s configuration (wp-config.php or the server environment), where only the site owner can change them.'
                    : 'is excluded by this site\'s allowed tool patterns, which have no wp-admin screen: the site owner changes them with the easy_mcp_ai_allowed_tool_patterns option or EASY_MCP_AI_ALLOWED_TOOL_PATTERNS.';
            case 'app_only':
                return 'can only be called from an approval card, in a client that supports MCP Apps.';
            case 'privileged':
                return 'is refused right now: ' . rtrim( $status['message'], '.' ) . '.';
            default:
                return '';
        }
    }

    public static function who_switches_on( array $off, array $locked, string $path ): string {
        $parts = array();
        if ( array() !== $off ) {
            $parts[] = array() === $locked
                ? sprintf( 'an administrator can switch %s on in wp-admin under %s', count( $off ) > 1 ? 'these' : 'it', $path )
                : sprintf( 'an administrator can switch on %s in wp-admin under %s', self::names( $off ), $path );
        }
        if ( array() !== $locked ) {
            $parts[] = self::names( $locked ) . ( count( $locked ) > 1 ? ' are ' : ' is ' ) . sprintf( self::IN_CONFIGURATION, count( $locked ) > 1 ? 'them' : 'it' );
        }
        return implode( '; ', $parts );
    }

    public static function names( array $names ): string {
        $last = array_pop( $names );
        return array() === $names ? (string) $last : implode( ', ', $names ) . ' and ' . $last;
    }
}
