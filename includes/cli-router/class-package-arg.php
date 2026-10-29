<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Package_Arg {

    const WPORG_PAGE = '~^https?://(?:www\.)?wordpress\.org/(plugins|themes)/([^/?#]+)/?\z~i';

    public static function is_url( string $arg ): bool {
        return false !== strpos( $arg, '://' );
    }

    public static function wporg_slug( string $arg, string $kind ): ?string {
        if ( ! preg_match( self::WPORG_PAGE, $arg, $m ) || strtolower( $m[1] ) !== $kind . 's' ) {
            return null;
        }
        return $m[2];
    }

    public static function problem( string $arg ): ?string {
        if ( self::is_url( $arg ) ) {
            return 0 === stripos( $arg, 'https://' ) || preg_match( self::WPORG_PAGE, $arg ) ? null : 'a URL that is not https://';
        }
        if ( '.zip' === strtolower( substr( $arg, -4 ) ) ) {
            return 'a zip file';
        }
        if ( false !== strpos( $arg, '/' ) || false !== strpos( $arg, '\\' ) ) {
            return 'a path';
        }
        return null;
    }

    public static function source( string $arg ): array {
        return self::is_url( $arg ) ? array( 'url' => $arg ) : array( 'slug' => $arg );
    }

    public static function arguments( string $arg, array $assoc ): array {
        $arguments = self::source( $arg );
        if ( isset( $assoc['version'] ) ) {
            $arguments['version'] = is_string( $assoc['version'] ) ? $assoc['version'] : (string) (int) $assoc['version'];
        }
        if ( Read_Value::flag( $assoc, 'force', false ) ) {
            $arguments['force'] = true;
        }
        return $arguments;
    }

    public static function shown( string $arg ): string {
        if ( ! self::is_url( $arg ) ) {
            return $arg;
        }
        $scheme = (string) wp_parse_url( $arg, PHP_URL_SCHEME );
        $host   = (string) wp_parse_url( $arg, PHP_URL_HOST );
        $path   = (string) wp_parse_url( $arg, PHP_URL_PATH );
        if ( '' === $host ) {
            return '[a URL]';
        }
        $text = strtolower( $scheme ) . '://' . $host . $path;
        return strlen( $text ) > 200 ? substr( $text, 0, 197 ) . '...' : $text;
    }
}
