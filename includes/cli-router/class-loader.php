<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















final class Loader {

    const PREFIX = 'Easy_MCP_AI\\Cli_Router\\';

    
    private static $registered = false;

    
    public static function register(): void {
        if ( self::$registered ) {
            return;
        }
        self::$registered = true;
        spl_autoload_register( array( __CLASS__, 'load' ) );
    }

    




    public static function load( string $class ): void {
        if ( 0 !== strpos( $class, self::PREFIX ) ) {
            return;
        }
        $file = self::file_for( $class );
        if ( null !== $file && is_readable( $file ) ) {
            require_once $file;
        }
    }

    




    public static function file_for( string $class ): ?string {
        if ( 0 !== strpos( $class, self::PREFIX ) ) {
            return null;
        }
        $parts = explode( '\\', substr( $class, strlen( self::PREFIX ) ) );
        $short = array_pop( $parts );
        if ( null === $short || '' === $short || ! preg_match( '/^[A-Za-z0-9_]+\z/', $short ) ) {
            return null;
        }
        $dir = __DIR__;
        foreach ( $parts as $part ) {
            if ( ! preg_match( '/^[A-Za-z0-9_]+\z/', $part ) ) {
                return null;
            }
            $dir .= '/' . str_replace( '_', '-', strtolower( $part ) );
        }
        return $dir . '/class-' . str_replace( '_', '-', strtolower( $short ) ) . '.php';
    }
}
