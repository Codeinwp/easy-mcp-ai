<?php






namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






final class Class_Map_Loader {

    














    public static function load( array $map, string $dir, string $base ): array {
        $instances = array();
        foreach ( $map as $key => $class ) {
            if ( ! class_exists( $class ) ) {
                $file = EASY_MCP_AI_PLUGIN_DIR . rtrim( $dir, '/' ) . '/class-' . str_replace( '_', '-', strtolower( substr( strrchr( $class, '\\' ), 1 ) ) ) . '.php';
                if ( is_readable( $file ) ) {
                    require_once $file;
                }
            }
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
