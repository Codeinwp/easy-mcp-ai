<?php
namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Permission_Guard {
    






    const EVERY_CREDENTIAL = array( 'wp_send_feedback' );

    private $token_manager;

    public function __construct( Token_Manager $token_manager ) {
        $this->token_manager = $token_manager;
    }

    




    public function can_use_tool( $token_id, $tool_name ) {
        return self::allowlist_permits( $this->get_allowed_tools( $token_id ), $tool_name, false );
    }

    










    public function can_use_tool_with_scope( array $allowed_tools, $tool_name ) {
        return self::allowlist_permits( $allowed_tools, $tool_name );
    }

    





























    public static function allowlist_permits( array $allowed, $tool_name, $globs = true ) {
        if ( in_array( (string) $tool_name, self::EVERY_CREDENTIAL, true ) ) {
            return true;
        }
        if ( self::is_privileged( $tool_name ) ) {
            return in_array( '*', $allowed, true ) || in_array( $tool_name, $allowed, true );
        }
        if ( in_array( '*', $allowed, true ) || in_array( $tool_name, $allowed, true ) ) {
            return true;
        }
        if ( ! $globs ) {
            return false;
        }
        foreach ( $allowed as $pattern ) {
            if ( is_string( $pattern ) && false !== strpos( $pattern, '*' ) && fnmatch( $pattern, (string) $tool_name ) ) {
                return true;
            }
        }
        return false;
    }

    private static function is_privileged( $tool_name ) {
        return class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' )
            && \Easy_MCP_AI\Privileged\Privileged_Groups::is_privileged_tool( $tool_name );
    }

    public function get_allowed_tools( $token_id ) {
        return $this->token_manager->get_allowed_tools( $token_id );
    }
}
