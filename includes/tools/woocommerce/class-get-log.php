<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Site_Health\Get_Error_Log;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}














class Get_Log extends Base_Tool {

    public function get_name() {
        return 'wp_wc_get_log';
    }

    public function get_description() {
        return 'Get the newest lines of one WooCommerce log (WooCommerce › Status › Logs), newest last: fatal errors, a payment gateway, webhook deliveries. Pass `source` (e.g. "fatal-errors": its newest day, or the day in `date`) or a `file_id` from wp_wc_list_logs, not both. Optional: `date` (YYYY-MM-DD, UTC), `level` (minimum: emergency, alert, critical, error, warning, notice, info, debug), `lines` (default 100, max 1000), `grep` (case-insensitive text, matched after redaction). Secrets are redacted; customer names, emails and addresses are not. Returns { storage, source, date, lines: [...], returned, truncated (a size, scan or time limit cut the result) }, plus file_id, rotation, size_bytes, modified, scanned_bytes for file storage or scanned_entries for database storage. A source with no log that day returns `reason`, not an error. PHP errors outside WooCommerce: wp_get_error_log. Requires manage_woocommerce.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
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
                'source'  => array(
                    'type'        => 'string',
                    'description' => 'Exact log source, e.g. "fatal-errors", "stripe", "webhooks-delivery". Reads the newest day of that source, or the day in `date`.',
                ),
                'file_id' => array(
                    'type'        => 'string',
                    'description' => 'A file_id from wp_wc_list_logs, e.g. "fatal-errors-2026-10-09". File storage only; use instead of `source`.',
                ),
                'date'    => array(
                    'type'        => 'string',
                    'description' => 'Day to read, YYYY-MM-DD in UTC (WooCommerce starts a new file each UTC day). With `source` only.',
                ),
                'level'   => array(
                    'type'        => 'string',
                    'enum'        => array_keys( WC_Logs::SEVERITY ),
                    'description' => 'Only entries at this level or more severe.',
                ),
                'lines'   => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 1000,
                    'default'     => 100,
                    'description' => 'How many of the most recent (matching) lines to return, 1-1000.',
                ),
                'grep'    => array(
                    'type'        => 'string',
                    'description' => 'Only lines containing this text (case-insensitive, not a regex).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        WC_Logs::require_woocommerce();

        $source  = self::text( $arguments, 'source' );
        $file_id = self::text( $arguments, 'file_id' );
        $date    = self::text( $arguments, 'date' );
        $level   = strtolower( self::text( $arguments, 'level' ) );
        $grep    = isset( $arguments['grep'] ) && is_scalar( $arguments['grep'] ) ? (string) $arguments['grep'] : '';
        $lines   = isset( $arguments['lines'] ) ? max( 1, min( 1000, (int) $arguments['lines'] ) ) : 100;

        if ( '' !== $source && '' !== $file_id ) {
            throw new \RuntimeException( 'Pass `source` or `file_id`, not both.' );
        }
        if ( '' === $source && '' === $file_id ) {
            throw new \RuntimeException( 'Pass `source` (for example "fatal-errors") or a `file_id` from wp_wc_list_logs.' );
        }
        if ( '' !== $date && '' !== $file_id ) {
            throw new \RuntimeException( '`date` goes with `source`; a `file_id` already names its day.' );
        }
        if ( '' !== $date && ! self::is_date( $date ) ) {
            throw new \RuntimeException( '`date` must be a calendar day as YYYY-MM-DD, for example 2026-10-09.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( '' !== $level && ! isset( WC_Logs::SEVERITY[ $level ] ) ) {
            throw new \RuntimeException( '`level` must be one of: ' . implode( ', ', array_keys( WC_Logs::SEVERITY ) ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $storage = WC_Logs::storage();
        if ( WC_Logs::STORAGE_OTHER === $storage['storage'] ) {
            throw new \RuntimeException( 'WooCommerce writes its logs with ' . $storage['handler'] . ', a custom log handler; these tools read only WooCommerce\'s file and database handlers.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $filters = array();
        if ( '' !== $grep ) {
            $filters['grep'] = $grep;
        }
        if ( '' !== $level ) {
            $filters['level'] = $level;
        }

        if ( WC_Logs::STORAGE_DATABASE === $storage['storage'] ) {
            if ( '' !== $file_id ) {
                throw new \RuntimeException( 'This store keeps its WooCommerce logs in the database, which has no files: pass `source` instead of `file_id`.' );
            }
            return Get_Error_Log::fit_response( array_merge( self::read_database( $source, '' === $date ? null : $date, $level, $lines, $grep ), $filters ) );
        }

        return Get_Error_Log::fit_response( array_merge( self::read_file( $source, $file_id, $date, $level, $lines, $grep ), $filters ) );
    }

    











    private static function read_file( string $source, string $file_id, string $date, string $level, int $lines, string $grep ): array {
        $dir = WC_Logs::directory();
        if ( null === $dir && '' !== $file_id ) {
            self::by_id( array(), $file_id ); 
        }
        if ( null === $dir ) {
            return self::nothing( $source, $date, 'WooCommerce\'s log folder does not exist: nothing has been logged to a file on this site yet.' );
        }
        WC_Logs::require_site_scope( $dir );

        $file = '' === $file_id ? self::newest_of_source( WC_Logs::files( $dir ), $source, $date ) : self::by_id( WC_Logs::files( $dir ), $file_id );
        if ( null === $file ) {
            return self::nothing(
                $source,
                $date,
                sprintf( 'WooCommerce has no "%s" log file%s. wp_wc_list_logs lists the files that exist.', $source, '' === $date ? '' : ' for ' . $date )
            );
        }

        $keep = null;
        if ( '' !== $level ) {
            $min  = WC_Logs::SEVERITY[ $level ];
            $keep = static function ( string $line ) use ( $min ): ?bool {
                $severity = WC_Logs::line_severity( $line );
                return null === $severity ? null : $severity >= $min;
            };
        }
        $tail = Get_Error_Log::tail( $file['path'], $lines, $grep, $keep, array( WC_Logs::class, 'redact_line' ), WC_Logs::seconds() );

        return array_merge(
            array( 'storage' => WC_Logs::STORAGE_FILE ),
            WC_Logs::public_file( $file ),
            array(
                'lines'         => $tail['lines'],
                'returned'      => count( $tail['lines'] ),
                'truncated'     => $tail['truncated'],
                'scanned_bytes' => $tail['scanned_bytes'],
            )
        );
    }

    









    private static function read_database( string $source, ?string $date, string $level, int $lines, string $grep ): array {
        $scan = array( 'rows' => 0, 'cut' => false );
        $min  = '' === $level ? 0 : WC_Logs::SEVERITY[ $level ];
        $read = Get_Error_Log::collect( WC_Logs::db_lines( $source, $date, $min, $scan ), $lines, $grep, array( WC_Logs::class, 'redact_line' ), WC_Logs::seconds() );

        $result = array(
            'storage'         => WC_Logs::STORAGE_DATABASE,
            'source'          => $source,
            'date'            => $date,
            'lines'           => $read['lines'],
            'returned'        => count( $read['lines'] ),
            'truncated'       => $read['truncated'] || $scan['cut'],
            'scanned_entries' => $scan['rows'],
        );
        if ( 0 === $scan['rows'] ) {
            $result['reason'] = sprintf(
                'The WooCommerce log table has no "%s" entries%s%s.',
                $source,
                null === $date ? '' : ' for ' . $date,
                '' === $level ? '' : ' at level ' . $level . ' or above'
            );
        }
        return $result;
    }

    








    private static function newest_of_source( array $files, string $source, string $date ): ?array {
        $matches = array_filter( $files, static function ( array $file ) use ( $source, $date ): bool {
            return $file['source'] === $source && ( '' === $date || $file['date'] === $date );
        } );
        usort( $matches, static function ( array $a, array $b ): int {
            
            return array( (string) $b['date'], null === $a['rotation'] ? 0 : 1, $b['mtime'] )
                <=> array( (string) $a['date'], null === $b['rotation'] ? 0 : 1, $a['mtime'] );
        } );
        return empty( $matches ) ? null : $matches[0];
    }

    








    private static function by_id( array $files, string $file_id ): array {
        $matches = array_values( array_filter( $files, static function ( array $file ) use ( $file_id ): bool {
            return $file['file_id'] === $file_id;
        } ) );
        if ( empty( $matches ) ) {
            throw new \RuntimeException( sprintf( 'No WooCommerce log file has the file_id "%s". wp_wc_list_logs lists the files that exist.', $file_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( count( $matches ) > 1 ) {
            throw new \RuntimeException( sprintf( 'Several WooCommerce log files have the file_id "%s"; read them by `source` and `date` instead.', $file_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $matches[0];
    }

    







    private static function nothing( string $source, string $date, string $reason ): array {
        return array(
            'storage'   => WC_Logs::STORAGE_FILE,
            'source'    => '' === $source ? null : $source,
            'file_id'   => null,
            'date'      => '' === $date ? null : $date,
            'lines'     => array(),
            'returned'  => 0,
            'truncated' => false,
            'reason'    => $reason,
        );
    }

    






    private static function text( array $arguments, string $name ): string {
        return isset( $arguments[ $name ] ) && is_scalar( $arguments[ $name ] ) ? trim( (string) $arguments[ $name ] ) : '';
    }

    





    private static function is_date( string $value ): bool {
        return (bool) preg_match( '/^(\d{4})-(\d{2})-(\d{2})\z/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
    }
}
