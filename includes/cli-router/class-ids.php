<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




























final class Ids {

    
    const BAD_ID = "wp_cli_run refuses '%s' as %s: it is not a whole number in digits, and WP-CLI would convert it to a different ID rather than refuse it. Pass the ID as digits only.";

    




    public static function is_digits( $value ): bool {
        if ( is_int( $value ) ) {
            return $value >= 0;
        }
        return is_string( $value ) && 1 === preg_match( '/^[0-9]+\z/', $value );
    }

    






    public static function require_digits( Context $ctx, $value, string $what ): void {
        if ( ! self::is_digits( $value ) ) {
            $ctx->out->error( sprintf( self::BAD_ID, is_scalar( $value ) ? (string) $value : '', $what ) );
        }
    }

    





    public static function require_all( Context $ctx, array $values, string $what ): void {
        foreach ( $values as $value ) {
            self::require_digits( $ctx, $value, $what );
        }
    }
}
