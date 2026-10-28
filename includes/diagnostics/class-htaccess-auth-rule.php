<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Htaccess_Auth_Rule {

    const MARKER = 'Easy MCP AI';

    const WORDPRESS_MARKER = 'WordPress';

    const PLAIN_ROUTES_MARKER = 'Easy MCP AI Plain Permalinks';

    const RULE_MARKER = 'HTTP_AUTHORIZATION';

    const OFFER_BUTTON = 'button';

    const OFFER_PASTE = 'paste';

    const OFFER_ALREADY_PRESENT = 'already_present';

    const OFFER_NOT_APPLICABLE = 'not_applicable';

    const OFFER_NONE = 'none';

    public static function rule_lines() {
        return array(
            '<IfModule mod_rewrite.c>',
            'RewriteEngine On',
            'RewriteCond %{HTTP:Authorization} .',
            'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]',
            '</IfModule>',
        );
    }

    public static function block_text() {
        return implode(
            "\n",
            array_merge(
                array( '# BEGIN ' . self::MARKER ),
                self::rule_lines(),
                array( '# END ' . self::MARKER )
            )
        );
    }

    public static function server_applies( $server_software ) {
        $s = strtolower( (string) $server_software );
        return false !== strpos( $s, 'apache' ) || false !== strpos( $s, 'litespeed' );
    }

    public static function has_active_rule( $contents ) {
        if ( null === $contents || '' === (string) $contents ) {
            return false;
        }
        return 1 === preg_match( self::active_rule_pattern(), (string) $contents );
    }

    public static function has_effective_rule( $contents ) {
        if ( null === $contents || '' === (string) $contents ) {
            return false;
        }
        $contents = (string) $contents;
        if ( ! preg_match( self::active_rule_pattern(), $contents, $rule, PREG_OFFSET_CAPTURE ) ) {
            return false;
        }
        $stop = self::first_last_flag_offset( $contents );
        return null === $stop || $rule[0][1] < $stop;
    }

    public static function first_last_flag_offset( $contents ) {
        $pattern = '/^[ \t]*RewriteRule\b[^\r\n]*\[(?:[^\]\r\n]*,)?[ \t]*L[ \t]*(?:,[^\]\r\n]*)?\][ \t]*\r?$/mi';
        if ( preg_match( $pattern, (string) $contents, $m, PREG_OFFSET_CAPTURE ) ) {
            return (int) $m[0][1];
        }
        return null;
    }

    private static function active_rule_pattern() {
        return '/^[ \t]*(?:'
            . 'RewriteRule\b[^\r\n]*\bE=HTTP_AUTHORIZATION\b'
            . '|SetEnvIf(?:NoCase)?\b[^\r\n]*\bHTTP_AUTHORIZATION\b'
            . '|CGIPassAuth[ \t]+On\b'
            . ')/mi';
    }

    public static function has_block( $contents ) {
        return self::has_marker_block( $contents, self::MARKER );
    }

    public static function has_marker_block( ?string $contents, string $marker ): bool {
        return 1 === preg_match( '/^[ \t]*# BEGIN ' . preg_quote( $marker, '/' ) . '[ \t]*\r?$/m', (string) $contents );
    }

    public static function strip_marker_block( ?string $contents, string $marker ): string {
        $contents = (string) $contents;
        if ( ! self::has_marker_block( $contents, $marker ) ) {
            return $contents;
        }
        $pattern = '/^[ \t]*# BEGIN ' . preg_quote( $marker, '/' ) . '[ \t]*\R.*?^[ \t]*# END ' . preg_quote( $marker, '/' ) . '[ \t]*(?:\R|$)/ms';
        $updated = preg_replace( $pattern, '', $contents );
        return null === $updated ? $contents : $updated;
    }

    public static function offer( $a1_status, $server_software, $file_exists, $file_writable, $contents ) {
        if ( Diagnostic_Result::STATUS_WARN !== $a1_status ) {
            return self::OFFER_NONE;
        }
        if ( ! self::server_applies( $server_software ) ) {
            return self::OFFER_NOT_APPLICABLE;
        }
        if ( self::has_effective_rule( $contents ) ) {
            return self::OFFER_ALREADY_PRESENT;
        }
        if ( $file_exists && $file_writable ) {
            return self::OFFER_BUTTON;
        }
        return self::OFFER_PASTE;
    }

    public static function may_write( $has_manage_options, $is_multisite, $is_super_admin, $has_network_cap ) {
        if ( ! $has_manage_options ) {
            return false;
        }
        if ( $is_multisite ) {
            return $is_super_admin && $has_network_cap;
        }
        return true;
    }

    public static function place( $contents ) {
        $contents = self::strip( (string) $contents );
        $eol      = ( false !== strpos( $contents, "\r\n" ) ) ? "\r\n" : "\n";
        $block    = str_replace( "\n", $eol, self::block_text() ) . $eol;

        foreach ( array( self::PLAIN_ROUTES_MARKER, self::WORDPRESS_MARKER ) as $marker ) {
            if ( preg_match( '/^[ \t]*' . preg_quote( '# BEGIN ' . $marker, '/' ) . '[ \t]*(?:\R|$)/m', $contents, $m, PREG_OFFSET_CAPTURE ) ) {
                $at = (int) $m[0][1];
                return substr( $contents, 0, $at ) . $block . substr( $contents, $at );
            }
        }

        return $block . $contents;
    }

    public static function offer_now( $a1 ) {
        $status = ( $a1 instanceof Diagnostic_Result ) ? $a1->status() : Diagnostic_Result::STATUS_UNKNOWN;
        $server = isset( $_SERVER['SERVER_SOFTWARE'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : '';
        $path   = self::htaccess_path();
        $exists = file_exists( $path );

        return array(
            'offer' => self::offer(
                $status,
                $server,
                $exists,
                $exists && is_writable( $path ),
                self::read( $path )
            ),
            'path'  => $path,
        );
    }

    public static function htaccess_path() {
        if ( ! function_exists( 'get_home_path' ) ) {
            $file = ABSPATH . 'wp-admin/includes/file.php';
            if ( is_readable( $file ) ) {
                require_once $file;
            }
        }

        $home = ABSPATH;
        if ( function_exists( 'get_home_path' ) ) {
            $resolved = \get_home_path();
            if ( is_string( $resolved ) && '' !== $resolved ) {
                $home = $resolved;
            }
        }

        return rtrim( (string) $home, '/\\' ) . '/.htaccess';
    }

    public static function read( $path ) {
        if ( ! is_readable( $path ) ) {
            return null;
        }
        $contents = @file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local config read; WP_Filesystem is not initialised here.
        return false === $contents ? null : $contents;
    }

    public static function write( $path ) {
        if ( ! file_exists( $path ) || ! is_writable( $path ) ) {
            return false;
        }
        return self::mutate( $path, array( __CLASS__, 'place' ) );
    }

    public static function remove( $path ) {
        if ( ! file_exists( $path ) ) {
            return true;
        }
        if ( ! is_writable( $path ) ) {
            $contents = self::read( $path );
            return null !== $contents && ! self::has_block( $contents );
        }
        return self::mutate( $path, array( __CLASS__, 'strip' ) );
    }

    public static function strip( $contents ) {
        return self::strip_marker_block( $contents, self::MARKER );
    }

    public static function mutate( $path, $transform, $create = false ) {
        $fp = @fopen( $path, $create ? 'c+' : 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Our own block in .htaccess under flock(); WP_Filesystem is not initialised on uninstall.
        if ( ! $fp ) {
            return false;
        }
        if ( ! flock( $fp, LOCK_EX ) ) {
            fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            return false;
        }

        $ok       = false;
        $contents = stream_get_contents( $fp );
        if ( false !== $contents ) {
            $updated = (string) call_user_func( $transform, $contents );
            if ( $updated === $contents ) {
                $ok = true;
            } elseif ( rewind( $fp ) && ftruncate( $fp, 0 ) ) {
                $written = fwrite( $fp, $updated ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
                $ok      = ( false !== $written && strlen( $updated ) === $written );
                fflush( $fp );
            }
        }

        flock( $fp, LOCK_UN );
        fclose( $fp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        return $ok;
    }
}
