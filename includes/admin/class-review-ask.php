<?php

namespace Easy_MCP_AI\Admin;

use Easy_MCP_AI\Auth\Permission_Guard;
use Easy_MCP_AI\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Review_Ask {

    const CALLS_OPTION = 'easy_mcp_ai_review_ask_calls';

    const CLOSED_OPTION = 'easy_mcp_ai_review_ask_closed';

    const CALLS_NEEDED = 30;

    public static function register() {
        \add_action( 'easy_mcp_ai_after_tool_call', array( __CLASS__, 'count_call' ), 10, 3 );
    }

    public static function count_call( $tool_name, $arguments = array(), $context = array() ) {
        if ( ! is_array( $context ) || ! isset( $context['status'] ) || 'success' !== $context['status'] ) {
            return;
        }
        if ( ! empty( $context['parent'] ) ) {
            return;
        }
        if ( in_array( $tool_name, Permission_Guard::EVERY_CREDENTIAL, true ) ) {
            return;
        }
        $result = isset( $context['result'] ) ? $context['result'] : null;
        if ( is_array( $result ) && isset( $result['error'] ) ) {
            return;
        }
        $calls = self::calls();
        if ( $calls >= self::CALLS_NEEDED ) {
            return;
        }
        \update_option( self::CALLS_OPTION, $calls + 1, true );
    }

    public static function calls(): int {
        $stored = \get_option( self::CALLS_OPTION, 0 );
        if ( is_int( $stored ) || ( is_string( $stored ) && ctype_digit( $stored ) ) ) {
            return max( 0, (int) $stored );
        }
        return 0;
    }

    public static function is_closed(): bool {
        return (bool) \get_option( self::CLOSED_OPTION, false );
    }

    public static function close() {
        \update_option( self::CLOSED_OPTION, 1, false );
    }

    public static function due(): bool {
        return self::calls() >= self::CALLS_NEEDED
            && ! self::is_closed()
            && ! Config::is_white_labelled();
    }
}
