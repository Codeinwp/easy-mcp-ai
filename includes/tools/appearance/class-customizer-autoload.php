<?php







namespace Easy_MCP_AI\Tools\Appearance;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}














































final class Customizer_Autoload {

    
    const ROUTE = '/easy-mcp-ai/v1/mcp';

    
    const NOT_AUTOLOADED = array( 'wp_customize_manager' );

    



    const BASE_FILES = array(
        'class-wp-customize-setting.php',
        'class-wp-customize-panel.php',
        'class-wp-customize-section.php',
        'class-wp-customize-control.php',
    );

    






    public static function is_mcp_post( array $server, array $get ): bool {
        $method = isset( $server['REQUEST_METHOD'] ) && is_string( $server['REQUEST_METHOD'] ) ? strtoupper( $server['REQUEST_METHOD'] ) : '';
        if ( 'POST' !== $method ) {
            return false;
        }
        $route = isset( $get['rest_route'] ) && is_string( $get['rest_route'] ) ? $get['rest_route'] : '';
        $uri   = isset( $server['REQUEST_URI'] ) && is_string( $server['REQUEST_URI'] ) ? $server['REQUEST_URI'] : '';
        $path  = rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
        return false !== strpos( $route, self::ROUTE ) || false !== strpos( $path, self::ROUTE );
    }

    





    public static function register( string $dir = '' ): \Closure {
        $dir      = '' === $dir ? ABSPATH . WPINC : $dir;
        $autoload = static function ( $class ) use ( $dir ): void {
            self::load( (string) $class, $dir );
        };
        spl_autoload_register( $autoload );
        return $autoload;
    }

    






    public static function file_for( string $class ): string {
        $lower = strtolower( $class );
        if ( 1 !== preg_match( '/^wp_\w*customize\w*\z/', $lower ) || in_array( $lower, self::NOT_AUTOLOADED, true ) ) {
            return '';
        }
        return 'class-' . str_replace( '_', '-', $lower ) . '.php';
    }

    







    public static function load( string $class, string $dir ): void {
        $file = self::file_for( $class );
        if ( '' === $file ) {
            return;
        }
        foreach ( self::BASE_FILES as $base ) {
            if ( is_readable( $dir . '/' . $base ) ) {
                require_once $dir . '/' . $base;
            }
        }
        
        foreach ( array( '/', '/customize/' ) as $subdir ) {
            if ( is_readable( $dir . $subdir . $file ) ) {
                require_once $dir . $subdir . $file;
                return;
            }
        }
    }
}
