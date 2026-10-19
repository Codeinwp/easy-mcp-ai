<?php






namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






final class Class_Map_Loader {

    












    public static function load( array $map, string $base ): array {
        $instances = array();
        foreach ( $map as $key => $class ) {
            if ( class_exists( $class ) ) {
                $instance = new $class();
                if ( $instance instanceof $base ) {
                    $instances[ $key ] = $instance;
                }
            }
        }
        return $instances;
    }
}
