<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















class Get_Error_Log extends Base_Tool {

    const SOURCE_INI     = 'error_log ini';
    const SOURCE_CONTENT = 'wp-content/debug.log';

    






    const RESPONSE_LIMIT = 65536;

    
    const RESPONSE_JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    
    const LINE_OVERHEAD = 10;

    
    protected static $chunk_size = 65536;

    
    const MAX_LINE_BYTES = 4096;

    
    const MAX_SCAN_BYTES = 16777216;

    public function get_name() {
        return 'wp_get_error_log';
    }

    public function get_description() {
        return 'Get the last lines of the PHP error log, newest last. Reads ONLY the error_log path PHP is configured with (where WP_DEBUG_LOG writes) or, failing that, wp-content/debug.log — never any other file. Optional: `lines` (default 100, max 1000), `grep` (plain case-insensitive text match, applied after redaction). Secrets are redacted (API keys, Bearer/Basic tokens, password=… values, URL credentials, salts, JWTs). Returns { path_source ("error_log ini" or "wp-content/debug.log"), path (relative to the WordPress root, or the file name only), size_bytes, modified (ISO 8601 UTC), lines: [...], returned, truncated (true when the 64 KB response cap or the scan limit cut the result), scanned_bytes }, or { path_source: null, reason } when no readable log exists. Requires manage_options; on multisite only a network administrator, because the log is shared by every site on the server.';
    }

    public function get_category() {
        return 'site_health';
    }

    public function get_required_capability() {
        return 'manage_options';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'lines' => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 1000,
                    'default'     => 100,
                    'description' => 'How many of the most recent (matching) lines to return, 1-1000.',
                ),
                'grep'  => array(
                    'type'        => 'string',
                    'description' => 'Only lines containing this text (case-insensitive, not a regex).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        Site_Health_Guard::require_network_admin_on_multisite( 'read the PHP error log' );

        $lines = isset( $arguments['lines'] ) ? max( 1, min( 1000, (int) $arguments['lines'] ) ) : 100;
        $grep  = isset( $arguments['grep'] ) && is_scalar( $arguments['grep'] ) ? (string) $arguments['grep'] : '';

        $resolved = self::resolve_log_path( ini_get( 'error_log' ), WP_CONTENT_DIR );
        if ( null === $resolved['path'] ) {
            return array(
                'path_source' => null,
                'reason'      => $resolved['reason'],
                'lines'       => array(),
                'returned'    => 0,
                'truncated'   => false,
            );
        }

        $tail = self::tail( $resolved['path'], $lines, $grep );

        $result = array(
            'path_source'   => $resolved['source'],
            'path'          => Site_Health_Guard::display_path( $resolved['path'] ),
            'size_bytes'    => (int) filesize( $resolved['path'] ),
            'modified'      => Site_Health_Guard::iso_utc( filemtime( $resolved['path'] ) ),
            'lines'         => $tail['lines'],
            'returned'      => count( $tail['lines'] ),
            'truncated'     => $tail['truncated'],
            'scanned_bytes' => $tail['scanned_bytes'],
        );
        if ( '' !== $grep ) {
            $result['grep'] = $grep;
        }
        if ( '' !== $resolved['note'] ) {
            $result['note'] = $resolved['note'];
        }
        return self::fit_response( $result );
    }

    







    public static function resolve_log_path( $ini_value, string $content_dir ): array {
        $note = '';
        $ini  = is_string( $ini_value ) ? trim( $ini_value ) : '';

        if ( '' !== $ini ) {
            $refusal = self::refuse_ini_value( $ini );
            if ( null === $refusal ) {
                $real = self::usable_file( $ini );
                if ( null !== $real ) {
                    return array( 'path' => $real, 'source' => self::SOURCE_INI, 'reason' => '', 'note' => '' );
                }
                $refusal = 'it is not a readable regular file';
            }
            $note = 'PHP error_log is set but was not read (' . $refusal . '); fell back to wp-content/debug.log.';
        }

        $fallback = rtrim( (string) $content_dir, '/\\' ) . '/debug.log';
        $real     = self::usable_file( $fallback );
        if ( null !== $real ) {
            return array( 'path' => $real, 'source' => self::SOURCE_CONTENT, 'reason' => '', 'note' => $note );
        }

        $reason = '' === $ini
            ? 'No error log found: PHP error_log is not set and wp-content/debug.log does not exist. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php to create it.'
            : 'No readable error log: ' . $note . ' wp-content/debug.log does not exist or is not readable.';
        return array( 'path' => null, 'source' => null, 'reason' => $reason, 'note' => '' );
    }

    









    private static function refuse_ini_value( string $value ): ?string {
        if ( 0 === strcasecmp( $value, 'syslog' ) ) {
            return 'it is syslog, not a file';
        }
        $is_drive = (bool) preg_match( '#^[A-Za-z]:[\\\\/]#', $value );
        if ( ! $is_drive && preg_match( '#^[A-Za-z][A-Za-z0-9+.\-]*:#', $value ) ) {
            return 'it is a stream wrapper';
        }
        if ( ! $is_drive && '/' !== substr( $value, 0, 1 ) ) {
            return 'it is not an absolute path';
        }
        return null;
    }

    












    public static function tail( string $path, int $want, string $grep = '' ): array {
        $fh = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ( false === $fh ) {
            return array( 'lines' => array(), 'truncated' => false, 'scanned_bytes' => 0 );
        }

        fseek( $fh, 0, SEEK_END );
        $pos = (int) ftell( $fh );
        $s   = array(
            'want'      => (int) $want,
            'grep'      => (string) $grep,
            'out'       => array(), 
            'bytes'     => 0,
            'cut'       => false,
            'done'      => false,
            'carry'     => '',      
            'oversized' => false,   
            'first'     => true,    
        );
        $scanned = 0;

        while ( $pos > 0 && ! $s['done'] ) {
            if ( $scanned >= self::MAX_SCAN_BYTES ) {
                $s['cut'] = true;
                break;
            }
            $size = (int) min( static::$chunk_size, $pos );
            $pos -= $size;
            fseek( $fh, $pos );
            $scanned += $size;
            self::consume_chunk( (string) fread( $fh, $size ), $s ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
        }
        if ( ! $s['done'] && 0 === $pos && '' !== $s['carry'] ) {
            self::take_line( $s['carry'], $s['oversized'], $s ); 
        }
        fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

        return array( 'lines' => array_reverse( $s['out'] ), 'truncated' => $s['cut'], 'scanned_bytes' => $scanned );
    }

    







    private static function consume_chunk( string $chunk, array &$s ): void {
        $parts = explode( "\n", $chunk . $s['carry'] );
        $s['carry'] = (string) array_shift( $parts );
        $last       = count( $parts ) - 1;
        for ( $i = $last; $i >= 0 && ! $s['done']; $i-- ) {
            
            $was_oversized = $last === $i && $s['oversized'];
            if ( $last === $i ) {
                $s['oversized'] = false;
            }
            if ( $s['first'] ) {
                $s['first'] = false;
                if ( '' === $parts[ $i ] ) {
                    continue; 
                }
            }
            self::take_line( $parts[ $i ], $was_oversized, $s );
        }
        
        if ( strlen( $s['carry'] ) > self::MAX_LINE_BYTES ) {
            $s['carry']     = substr( $s['carry'], 0, self::MAX_LINE_BYTES );
            $s['oversized'] = true;
        }
    }

    







    private static function take_line( string $line, bool $oversized, array &$s ): void {
        $line = rtrim( $line, "\r" );
        if ( $oversized || strlen( $line ) > self::MAX_LINE_BYTES ) {
            $line = substr( $line, 0, self::MAX_LINE_BYTES ) . ' […line truncated]';
        }
        if ( function_exists( 'wp_check_invalid_utf8' ) ) {
            
            
            
            
            
            $line = \wp_check_invalid_utf8( $line, true );
        }
        $line = Text_Redactor::redact( $line );
        if ( '' !== $s['grep'] && false === stripos( $line, $s['grep'] ) ) {
            return;
        }
        $cost = self::encoded_line_cost( $line );
        if ( $s['bytes'] + $cost > self::RESPONSE_LIMIT ) {
            $s['cut']  = true; 
            $s['done'] = true;
            return;
        }
        $s['out'][]  = $line;
        $s['bytes'] += $cost;
        $s['done']   = count( $s['out'] ) >= $s['want'];
    }

    





    private static function encoded_line_cost( string $line ): int {
        $json = \wp_json_encode( $line, JSON_UNESCAPED_SLASHES );
        
        
        $size = false === $json ? 6 * strlen( $line ) + 2 : strlen( $json );
        return $size + self::LINE_OVERHEAD;
    }

    














    private static function fit_response( array $result ): array {
        $json = \wp_json_encode( $result, self::RESPONSE_JSON_FLAGS );
        while ( false !== $json && strlen( $json ) > self::RESPONSE_LIMIT && ! empty( $result['lines'] ) ) {
            $over = strlen( $json ) - self::RESPONSE_LIMIT;
            while ( $over > 0 && ! empty( $result['lines'] ) ) {
                $over -= self::encoded_line_cost( (string) array_shift( $result['lines'] ) );
            }
            $result['returned']  = count( $result['lines'] );
            $result['truncated'] = true;
            $json                = \wp_json_encode( $result, self::RESPONSE_JSON_FLAGS );
        }
        return $result;
    }

    





    private static function usable_file( string $path ): ?string {
        $real = @realpath( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
            return null;
        }
        return $real;
    }
}
