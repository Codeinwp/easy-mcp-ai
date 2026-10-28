<?php
namespace Easy_MCP_AI\Tools;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Row_Extension {

    const USER = 'easy_mcp_ai_user_row';

    const WC_CUSTOMER = 'easy_mcp_ai_wc_customer_row';

    const WC_ORDER = 'easy_mcp_ai_wc_order_row';

    const RESERVED = array(
        self::USER => array( 'capabilities', 'extra_capabilities', 'cap_key', 'spam', 'deleted' ),
    );

    public static function apply( string $hook, array $row, array $source, string $tool, bool $list ): array {
        if ( ! has_filter( $hook ) ) {
            return $row;
        }
        $plain_row    = self::plain( $row );
        $plain_source = self::plain( $source );
        if ( null === $plain_row || null === $plain_source ) {
            return $row;
        }
        $context  = array(
            'tool' => $tool,
            'mode' => $list ? 'list' : 'single',
        );
        $filtered = apply_filters( $hook, $plain_row, $plain_source, $context );
        if ( ! is_array( $filtered ) ) {
            return $row;
        }
        $reserved = isset( self::RESERVED[ $hook ] ) ? array_flip( self::RESERVED[ $hook ] ) : array();
        return $row + array_diff_key( $filtered, $reserved );
    }

    private static function plain( array $value ): ?array {
        $json = wp_json_encode( $value );
        return false === $json ? null : json_decode( $json, true );
    }
}
