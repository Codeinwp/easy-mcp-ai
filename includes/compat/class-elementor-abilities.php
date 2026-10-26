<?php







namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}































final class Elementor_Abilities extends Vendor_Abilities {

    
    const GROUP = 'elementor';

    
    const MODULE_CLASS = 'Elementor\\Modules\\Mcp\\Module';

    
    const CATEGORY = 'elementor';

    
    const PREFIX = 'elementor/';

    
    const VENDOR = 'Elementor';

    
    const ENABLE_PENDING_OPTION = 'easy_mcp_ai_elementor_enable_pending';

    





    public static function register_abilities( string $class = self::MODULE_CLASS ): void {
        if ( ! self::active() || ! function_exists( 'wp_has_ability_category' ) || ! function_exists( 'wp_get_abilities' ) ) {
            return;
        }
        if ( ! self::has_method( $class, 'instance', true ) || ! self::has_method( $class, 'is_active', true ) || ! self::has_method( $class, 'register_abilities', false ) ) {
            return;
        }
        if ( ! wp_has_ability_category( self::CATEGORY ) || self::any_registered() ) {
            return;
        }
        self::run(
            static function () use ( $class ) {
                if ( true === call_user_func( array( $class, 'is_active' ) ) ) {
                    call_user_func( array( $class, 'instance' ) )->register_abilities();
                }
            },
            $class . '::instance()->register_abilities()'
        );
    }
}
