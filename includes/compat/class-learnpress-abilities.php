<?php

namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Learnpress_Abilities extends Vendor_Abilities {

    const GROUP = 'learnpress';

    const ABILITIES_CLASS = 'LearnPress\\MCP\\Abilities';

    const CATEGORY = 'learnpress';

    const PREFIX = 'learnpress/';

    const VENDOR = 'LearnPress';

    const ENABLE_PENDING_OPTION = 'easy_mcp_ai_learnpress_enable_pending';

    public static function register_category( string $class = self::ABILITIES_CLASS ): void {
        if ( ! self::wanted( $class, 'register_category' ) || ! function_exists( 'wp_has_ability_category' ) ) {
            return;
        }
        if ( wp_has_ability_category( self::CATEGORY ) ) {
            return;
        }
        self::call( $class, 'register_category' );
    }

    public static function register_abilities( string $class = self::ABILITIES_CLASS ): void {
        if ( ! self::wanted( $class, 'register_abilities' ) || ! function_exists( 'wp_has_ability_category' ) || ! function_exists( 'wp_get_abilities' ) ) {
            return;
        }
        if ( ! wp_has_ability_category( self::CATEGORY ) || self::any_registered() ) {
            return;
        }
        self::call( $class, 'register_abilities' );
    }

    private static function wanted( string $class, string $method ): bool {
        return self::active() && self::has_method( $class, $method, true );
    }

    private static function call( string $class, string $method ): void {
        self::run(
            static function () use ( $class, $method ) {
                call_user_func( array( $class, $method ) );
            },
            $class . '::' . $method . '()'
        );
    }
}
