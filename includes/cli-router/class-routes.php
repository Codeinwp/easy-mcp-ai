<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




































































































final class Routes {

    
    private static $files = array();

    




    public static function get( string $command ): ?array {
        $top    = (string) strtok( $command, ' ' );
        $routes = self::family( $top );
        return isset( $routes[ $command ] ) ? $routes[ $command ] : null;
    }

    




    public static function family( string $top ): array {
        if ( ! isset( self::$files[ $top ] ) ) {
            self::$files[ $top ] = array();
            if ( preg_match( '/^[a-z0-9-]+\z/', $top ) ) {
                $file = __DIR__ . '/routes/' . $top . '.php';
                if ( is_readable( $file ) ) {
                    $routes = include $file;
                    if ( is_array( $routes ) ) {
                        self::$files[ $top ] = $routes;
                    }
                }
            }
        }
        return self::$files[ $top ];
    }

    




    public static function all(): array {
        $all   = array();
        $files = glob( __DIR__ . '/routes/*.php' );
        foreach ( $files ? $files : array() as $file ) {
            $all += self::family( basename( $file, '.php' ) );
        }
        ksort( $all );
        return $all;
    }

    
    public static function reset(): void {
        self::$files = array();
    }
}
