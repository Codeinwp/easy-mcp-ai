<?php
namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class History_Settings_Page {

    const VALID_MODES = array( 'allowlist', 'all_except_denylist' );

    





    public static function sanitize_mode( $raw ) {
        return ( is_string( $raw ) && in_array( $raw, self::VALID_MODES, true ) ) ? $raw : 'all_except_denylist';
    }

    



    public static function sanitize_db_retention( $raw ) {
        if ( ! is_numeric( $raw ) ) {
            return 7;
        }
        $days = (int) $raw;
        if ( $days < 0 ) {
            return 7;
        }
        return min( $days, 3650 );
    }
}
