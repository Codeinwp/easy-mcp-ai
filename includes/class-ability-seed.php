<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















class Ability_Seed {

    
    const PENDING_OPTION = 'easy_mcp_ai_abilities_seed_pending';

    






    const EXCLUDED_PREFIXES = array( 'woocommerce/' );

    



    public static function mark_pending(): void {
        \add_option( self::PENDING_OPTION, 1 );
    }

    



    public static function maybe_run(): void {
        
        
        
        
        
        
        
        
        
        if ( ! \delete_option( self::PENDING_OPTION ) ) {
            return;
        }
        if ( ! \function_exists( 'wp_get_abilities' ) ) {
            return;
        }
        
        
        $current = \get_option( 'easy_mcp_ai_enabled_abilities', array() );
        if ( ! empty( $current ) ) {
            return;
        }
        $seed = self::select( self::annotations_by_name( \wp_get_abilities() ) );
        if ( empty( $seed ) ) {
            return;
        }
        $write = static function () use ( $seed ) {
            \update_option( 'easy_mcp_ai_enabled_abilities', $seed );
        };
        
        
        
        if ( \class_exists( '\\Easy_MCP_AI\\MCP\\Option_Guard' ) ) {
            \Easy_MCP_AI\MCP\Option_Guard::while_suspended( $write );
            return;
        }
        $write();
    }

    





    public static function select( array $annotations_by_name ): array {
        $seed = array();
        foreach ( $annotations_by_name as $name => $annotations ) {
            if ( self::is_seedable( (string) $name, $annotations ) ) {
                $seed[] = (string) $name;
            }
        }
        return $seed;
    }

    








    public static function is_seedable( string $name, $annotations ): bool {
        if ( '' === $name ) {
            return false;
        }
        foreach ( self::EXCLUDED_PREFIXES as $prefix ) {
            if ( 0 === strpos( $name, $prefix ) ) {
                return false;
            }
        }
        
        
        
        if ( Tools\Ability_Input_Policy::applies_to( $name ) ) {
            return false;
        }
        return is_array( $annotations )
            && array_key_exists( 'destructive', $annotations )
            && false === $annotations['destructive'];
    }

    





    private static function annotations_by_name( array $abilities ): array {
        $out = array();
        foreach ( $abilities as $key => $ability ) {
            if ( ! is_object( $ability ) ) {
                continue;
            }
            $name = method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : (string) $key;
            if ( method_exists( $ability, 'get_meta_item' ) ) {
                $annotations = $ability->get_meta_item( 'annotations' );
            } elseif ( method_exists( $ability, 'get_annotations' ) ) {
                $annotations = $ability->get_annotations();
            } else {
                $annotations = null;
            }
            $out[ $name ] = is_array( $annotations ) ? $annotations : array();
        }
        return $out;
    }
}
