<?php







namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




























final class Learnpress_Abilities {

    
    const GROUP = 'learnpress';

    
    const ABILITIES_CLASS = 'LearnPress\\MCP\\Abilities';

    
    const CATEGORY = 'learnpress';

    
    const PREFIX = 'learnpress/';

    




    const ENABLE_PENDING_OPTION = 'easy_mcp_ai_learnpress_enable_pending';

    
    const ENABLED_ABILITIES_OPTION = 'easy_mcp_ai_enabled_abilities';

    
    private static $failed = false;

    
    public static function group_enabled(): bool {
        return \Easy_MCP_AI\Meta\Meta_Exposure::is_group_enabled( self::GROUP );
    }

    





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

    








    public static function group_changed( $old, $new ): void {
        $was = is_array( $old ) && in_array( self::GROUP, $old, true );
        $is  = is_array( $new ) && in_array( self::GROUP, $new, true );
        if ( ! $was && $is ) {
            update_option( self::ENABLE_PENDING_OPTION, 1 );
        } elseif ( $was && ! $is ) {
            delete_option( self::ENABLE_PENDING_OPTION );
        }
    }

    






    public static function enable_pending(): void {
        if ( ! get_option( self::ENABLE_PENDING_OPTION ) || ! self::group_enabled() || ! function_exists( 'wp_get_abilities' ) ) {
            return;
        }
        $names = self::registered_names();
        if ( empty( $names ) || ! delete_option( self::ENABLE_PENDING_OPTION ) ) {
            return;
        }
        $enabled = get_option( self::ENABLED_ABILITIES_OPTION, array() );
        $enabled = is_array( $enabled ) ? $enabled : array();
        $write   = static function () use ( $enabled, $names ) {
            update_option( self::ENABLED_ABILITIES_OPTION, array_values( array_unique( array_merge( $enabled, $names ) ) ) );
        };
        
        
        if ( class_exists( '\\Easy_MCP_AI\\MCP\\Option_Guard' ) ) {
            \Easy_MCP_AI\MCP\Option_Guard::while_suspended( $write );
            return;
        }
        $write();
    }

    
    public static function reset(): void {
        self::$failed = false;
    }

    
    private static function wanted( string $class, string $method ): bool {
        return ! self::$failed && self::group_enabled() && self::callable_without_arguments( $class, $method );
    }

    
    private static function callable_without_arguments( string $class, string $method ): bool {
        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            return false;
        }
        $reflection = new \ReflectionMethod( $class, $method );
        return $reflection->isPublic() && $reflection->isStatic() && 0 === $reflection->getNumberOfRequiredParameters();
    }

    
    private static function any_registered(): bool {
        return array() !== self::registered_names();
    }

    
    private static function registered_names(): array {
        $names = array();
        foreach ( (array) wp_get_abilities() as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
            if ( 0 === strpos( $name, self::PREFIX ) ) {
                $names[] = $name;
            }
        }
        return $names;
    }

    
    private static function call( string $class, string $method ): void {
        try {
            call_user_func( array( $class, $method ) );
        } catch ( \Throwable $e ) {
            self::$failed = true;
            error_log( sprintf( 'Easy MCP AI: LearnPress %s::%s() failed, LearnPress abilities not registered: %s', $class, $method, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: a vendor change must be visible
        }
    }
}
