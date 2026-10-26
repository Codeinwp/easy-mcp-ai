<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




















final class Protected_Sources {

    




    const OVERRIDE = 'allow_protected_source';

    





    const MCP_ROUTE = 'easy-mcp-ai/v1/mcp';

    











    public static function refuse( string $subject, array $arguments, ?string $host, string $stored, bool $regex, string $query ): void {
        if ( isset( $arguments[ self::OVERRIDE ] ) && rest_sanitize_boolean( $arguments[ self::OVERRIDE ] ) ) {
            return;
        }
        if ( null !== $host && ! self::is_own_host( $host ) ) {
            return;
        }
        $taken = self::taken_over( $stored, $regex, $query );
        if ( null !== $taken ) {
            throw new \InvalidArgumentException( self::refusal( $subject, $taken ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    




    public static function host_of( string $source ): ?string {
        if ( ! preg_match( '#^https?://#i', $source ) ) {
            return null;
        }
        $host = wp_parse_url( $source, PHP_URL_HOST );
        return is_string( $host ) && '' !== $host ? strtolower( $host ) : null;
    }

    







    public static function is_own_host( string $host ): bool {
        $own = array( wp_parse_url( home_url(), PHP_URL_HOST ), wp_parse_url( site_url(), PHP_URL_HOST ) );
        if ( class_exists( '\\Redirection_Request' ) && is_callable( array( '\\Redirection_Request', 'get_server_name' ) ) ) {
            $own[] = \Redirection_Request::get_server_name();
        }
        foreach ( $own as $candidate ) {
            if ( is_string( $candidate ) && '' !== $candidate && strtolower( $candidate ) === strtolower( $host ) ) {
                return true;
            }
        }
        return false;
    }

    







    public static function taken_over( string $stored, bool $regex, string $query_handling ): ?array {
        return $regex ? self::regex_takes_over( $stored ) : self::path_takes_over( $stored, $query_handling );
    }

    







    public static function refusal( string $subject, array $taken ): string {
        $why     = array(
            'the home page'    => 'every visitor would be sent away',
            'the sign-in page' => 'nobody could sign in',
            'the REST API'     => 'the editor and this connection could break',
        );
        $tail    = sprintf( ': %1$s. If the user asked for exactly this, resend with %2$s: true.', $why[ $taken['page'] ], self::OVERRIDE );
        $address = '/' === $taken['address'] ? '/' : ltrim( $taken['address'], '/' );
        $message = sprintf( '%1$s would redirect %2$s (%3$s)', $subject, $taken['page'], $address ) . $tail;
        
        
        return strlen( $message ) <= Detailed_Tool_Error::MAX_MESSAGE ? $message : sprintf( '%1$s would redirect %2$s', $subject, $taken['page'] ) . $tail;
    }

    









    private static function path_takes_over( string $stored, string $query_handling ): ?array {
        $parts = explode( '?', $stored, 2 );
        $path  = self::normalise( $parts[0] );
        $query = $parts[1] ?? '';
        $pages = self::pages();

        foreach ( $pages['sign_in'] as $address ) {
            if ( self::normalise( $address ) === $path ) {
                return array( 'page' => 'the sign-in page', 'address' => $address );
            }
        }
        $rest = self::normalise( $pages['rest'] );
        if ( $path === $rest || 0 === strpos( $path, $rest . '/' ) ) {
            return array( 'page' => 'the REST API', 'address' => $pages['rest'] );
        }
        $params = array();
        parse_str( $query, $params );
        if ( array_key_exists( 'rest_route', $params ) ) {
            return array( 'page' => 'the REST API', 'address' => $pages['home'] . '?rest_route=' );
        }
        if ( '' !== $query ) {
            return null;
        }
        if ( self::normalise( $pages['home'] ) === $path ) {
            return array( 'page' => 'the home page', 'address' => $pages['home'] );
        }
        
        
        
        if ( 'exact' !== $query_handling && self::normalise( $pages['home'] . 'index.php' ) === $path ) {
            return array( 'page' => 'the REST API', 'address' => $pages['home'] . 'index.php?rest_route=' );
        }
        return null;
    }

    









    private static function regex_takes_over( string $stored ): ?array {
        $regex = '@' . str_replace( '@', '\\@', rawurldecode( $stored ) ) . '@si';
        foreach ( self::samples() as $sample ) {
            if ( 1 === @preg_match( $regex, $sample['address'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid pattern warns; it matches nothing, as in Redirection.
                return $sample;
            }
        }
        return null;
    }

    





    private static function samples(): array {
        $pages   = self::pages();
        $samples = array(
            array( 'page' => 'the home page', 'address' => $pages['home'] ),
        );
        foreach ( $pages['sign_in'] as $address ) {
            $samples[] = array( 'page' => 'the sign-in page', 'address' => $address );
        }
        foreach ( array( $pages['rest'], $pages['rest'] . self::MCP_ROUTE, $pages['home'] . '?rest_route=/', $pages['home'] . 'index.php?rest_route=/' ) as $address ) {
            $samples[] = array( 'page' => 'the REST API', 'address' => $address );
        }
        return $samples;
    }

    






    private static function pages(): array {
        return array(
            'home'    => trailingslashit( self::path_of( home_url( '/' ) ) ),
            'rest'    => trailingslashit( self::path_of( home_url( '/' . rest_get_url_prefix() . '/' ) ) ),
            'sign_in' => array_values( array_unique( array( self::path_of( site_url( '/wp-login.php' ) ), self::path_of( wp_login_url() ) ) ) ),
        );
    }

    




    private static function path_of( string $url ): string {
        $path = wp_parse_url( $url, PHP_URL_PATH );
        return is_string( $path ) && '' !== $path ? $path : '/';
    }

    




    private static function normalise( string $path ): string {
        $path = '/' . trim( $path, '/' );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $path, 'UTF-8' ) : strtolower( $path );
    }
}
