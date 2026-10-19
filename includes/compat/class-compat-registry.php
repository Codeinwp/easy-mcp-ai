<?php






namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















final class Compat_Registry {

    



    const SHIMS = array(
        'buddypress-loggedin-user' => 'Easy_MCP_AI\\Compat\\Shims\\Buddypress_Loggedin_User',
        'tec-event-update-fill'    => 'Easy_MCP_AI\\Compat\\Shims\\Tec_Event_Update_Fill',
        'tec-no-duplicate-reuse'   => 'Easy_MCP_AI\\Compat\\Shims\\Tec_No_Duplicate_Reuse',
        'learnpress-mcp-auth'      => 'Easy_MCP_AI\\Compat\\Shims\\Learnpress_Mcp_Auth',
        'astra-customizer-loader'  => 'Easy_MCP_AI\\Compat\\Shims\\Astra_Customizer_Loader',
    );

    
    private static $shims = null;

    
    private static $armed = array();

    




    public static function all_shims() {
        if ( null === self::$shims ) {
            self::$shims = \Easy_MCP_AI\Class_Map_Loader::load( self::SHIMS, Compat_Shim::class );
        }
        return self::$shims;
    }

    




    public static function mark() {
        return count( self::$armed );
    }

    









    public static function arm_for( $tool, array $arguments = array() ) {
        $armed = array();
        foreach ( self::all_shims() as $id => $shim ) {
            if ( ! $shim->applies_to( $tool ) ) {
                continue;
            }
            











            if ( ! $shim->is_required() && false === apply_filters( 'easy_mcp_ai_compat_shim_enabled', true, $id, $tool ) ) {
                continue;
            }
            if ( ! $shim->is_needed() ) {
                continue;
            }
            self::$armed[] = array(
                'id'        => $id,
                'shim'      => $shim,
                'tool'      => $tool,
                'arguments' => $arguments,
                'state'     => $shim->enter( $arguments, $tool ),
            );
            $armed[]       = $id;
        }
        return $armed;
    }

    













    public static function is_armed( string $id, ?object $tool = null, ?array $arguments = null ): bool {
        for ( $i = count( self::$armed ) - 1; $i >= 0; $i-- ) {
            $arming = self::$armed[ $i ];
            if ( $arming['id'] !== $id ) {
                continue;
            }
            return null === $tool || ( $arming['tool'] === $tool && $arming['arguments'] === $arguments );
        }
        return false;
    }

    








    public static function release( $mark ): void {
        $mark = max( 0, (int) $mark );
        while ( count( self::$armed ) > $mark ) {
            $arming = array_pop( self::$armed );
            try {
                $arming['shim']->leave( $arming['state'] );
            } catch ( \Throwable $e ) {
                error_log( sprintf( 'Easy MCP AI: compatibility shim "%s" failed to unwind: %s', $arming['id'], $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
        }
    }

    


    public static function reset(): void {
        self::release( 0 );
        self::$shims = null;
    }
}
