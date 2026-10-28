<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Htaccess_Plain_Routes {

    const MARKER = Htaccess_Auth_Rule::PLAIN_ROUTES_MARKER;

    const OFFER_BUTTON = 'button';

    const OFFER_PASTE = 'paste';

    const OFFER_ALREADY_PRESENT = 'already_present';

    const OFFER_NOT_APPLICABLE = 'not_applicable';

    const OFFER_NONE = 'none';

    public static function rule_lines( string $home_root ): array {
        $index = '/' . trim( $home_root, '/' );
        $index = ( '/' === $index ? '' : $index ) . '/index.php';
        return array(
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteRule ^\.well-known/(oauth-protected-resource|oauth-authorization-server|openid-configuration)(/.*)?$ ' . $index . ' [L]',
            'RewriteCond %{REQUEST_FILENAME} !-f',
            'RewriteCond %{REQUEST_FILENAME} !-d',
            'RewriteCond %{REQUEST_METHOD} =GET',
            'RewriteCond %{QUERY_STRING} (^|&)response_type=code(&|$)',
            'RewriteRule ^authorize/?$ ' . $index . ' [L]',
            'RewriteCond %{REQUEST_FILENAME} !-f',
            'RewriteCond %{REQUEST_FILENAME} !-d',
            'RewriteCond %{REQUEST_METHOD} =POST',
            'RewriteRule ^(register|token)/?$ ' . $index . ' [L]',
            '</IfModule>',
        );
    }

    public static function block_text( string $home_root ): string {
        return implode(
            "\n",
            array_merge(
                array( '# BEGIN ' . self::MARKER ),
                self::rule_lines( $home_root ),
                array( '# END ' . self::MARKER )
            )
        );
    }

    public static function has_block( $contents ): bool {
        return Htaccess_Auth_Rule::has_marker_block( $contents, self::MARKER );
    }

    public static function offer( bool $plain, bool $main_site, string $server_software, bool $file_exists, bool $file_writable, bool $dir_writable, $contents ): string {
        if ( ! $plain || ! $main_site ) {
            return self::OFFER_NONE;
        }
        if ( ! Htaccess_Auth_Rule::server_applies( $server_software ) ) {
            return self::OFFER_NOT_APPLICABLE;
        }
        if ( self::has_block( $contents ) ) {
            return self::OFFER_ALREADY_PRESENT;
        }
        if ( $file_exists ? $file_writable : $dir_writable ) {
            return self::OFFER_BUTTON;
        }
        return self::OFFER_PASTE;
    }

    public static function place( string $contents, string $home_root ): string {
        $contents = self::strip( $contents );
        $eol      = ( false !== strpos( $contents, "\r\n" ) ) ? "\r\n" : "\n";
        $block    = str_replace( "\n", $eol, self::block_text( $home_root ) ) . $eol;

        $auth_end = '/^[ \t]*# END ' . preg_quote( Htaccess_Auth_Rule::MARKER, '/' ) . '[ \t]*(?:\R|$)/m';
        if ( preg_match( $auth_end, $contents, $m, PREG_OFFSET_CAPTURE ) ) {
            $at = (int) $m[0][1] + strlen( $m[0][0] );
            $sep = "\n" === substr( $m[0][0], -1 ) ? '' : $eol;
            return substr( $contents, 0, $at ) . $sep . $block . substr( $contents, $at );
        }

        $wp_marker = '/^[ \t]*# BEGIN ' . preg_quote( Htaccess_Auth_Rule::WORDPRESS_MARKER, '/' ) . '[ \t]*(?:\R|$)/m';
        if ( preg_match( $wp_marker, $contents, $m, PREG_OFFSET_CAPTURE ) ) {
            $at = (int) $m[0][1];
            return substr( $contents, 0, $at ) . $block . substr( $contents, $at );
        }

        return $block . $contents;
    }

    public static function strip( string $contents ): string {
        return Htaccess_Auth_Rule::strip_marker_block( $contents, self::MARKER );
    }

    public static function home_root(): string {
        $path = \wp_parse_url( \home_url(), PHP_URL_PATH );
        return is_string( $path ) && '' !== trim( $path, '/' ) ? '/' . trim( $path, '/' ) . '/' : '/';
    }

    public static function offer_now(): array {
        $path   = Htaccess_Auth_Rule::htaccess_path();
        $exists = file_exists( $path );
        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';

        return array(
            'offer' => self::offer(
                '' === (string) \get_option( 'permalink_structure', '' ),
                ! function_exists( 'is_main_site' ) || \is_main_site(),
                $server,
                $exists,
                $exists && is_writable( $path ),
                ! $exists && is_writable( dirname( $path ) ),
                Htaccess_Auth_Rule::read( $path )
            ),
            'path'  => $path,
        );
    }

    public static function is_in_place(): bool {
        return self::has_block( Htaccess_Auth_Rule::read( Htaccess_Auth_Rule::htaccess_path() ) );
    }

    public static function front_page_status(): int {
        $response = \wp_remote_get(
            \home_url( '/' ),
            array(
                'timeout'     => 10,
                'redirection' => 0,
                'sslverify'   => (bool) \apply_filters( 'https_local_ssl_verify', false ),
            )
        );
        return \is_wp_error( $response ) ? 0 : (int) \wp_remote_retrieve_response_code( $response );
    }

    public static function breaks_site( int $before, int $after ): bool {
        return $after >= 500 && $before < 500;
    }

    public static function write( string $path, string $home_root ): bool {
        $exists = file_exists( $path );
        if ( $exists ? ! is_writable( $path ) : ! is_writable( dirname( $path ) ) ) {
            return false;
        }
        return Htaccess_Auth_Rule::mutate(
            $path,
            static function ( $contents ) use ( $home_root ) {
                return self::place( (string) $contents, $home_root );
            },
            ! $exists
        );
    }

    public static function remove( string $path ): bool {
        if ( ! file_exists( $path ) ) {
            return true;
        }
        if ( ! is_writable( $path ) ) {
            return ! self::has_block( Htaccess_Auth_Rule::read( $path ) );
        }
        return Htaccess_Auth_Rule::mutate(
            $path,
            static function ( $contents ) {
                return self::strip( (string) $contents );
            }
        );
    }
}
