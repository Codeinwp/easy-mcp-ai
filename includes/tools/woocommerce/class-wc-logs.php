<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Easy_MCP_AI\Text_Redactor;
use Easy_MCP_AI\Tools\Site_Health\Site_Health_Guard;

class WC_Logs {

    const SEVERITY = array(
        'emergency' => 800,
        'alert'     => 700,
        'critical'  => 600,
        'error'     => 500,
        'warning'   => 400,
        'notice'    => 300,
        'info'      => 200,
        'debug'     => 100,
    );

    const STORAGE_FILE     = 'file';
    const STORAGE_DATABASE = 'database';
    const STORAGE_OTHER    = 'other';

    protected static int $db_batch = 500;

    protected static int $db_max_rows = 5000;

    protected static float $seconds = 10.0;

    public static function seconds(): float {
        return static::$seconds;
    }

    const DB_FIELD_BYTES = 8192;

    public static function require_woocommerce(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }
    }

    public static function storage(): array {
        if ( self::util_has( 'get_default_handler' ) ) {
            $handler = \Automattic\WooCommerce\Utilities\LoggingUtil::get_default_handler();
        } else {
            $handler = defined( 'WC_LOG_HANDLER' ) ? (string) constant( 'WC_LOG_HANDLER' ) : 'WC_Log_Handler_File';
        }
        return array(
            'storage' => self::storage_of( $handler ),
            'handler' => $handler,
        );
    }

    public static function util_has( string $method ): bool {
        return class_exists( '\Automattic\WooCommerce\Utilities\LoggingUtil' )
            && method_exists( '\Automattic\WooCommerce\Utilities\LoggingUtil', $method );
    }

    public static function storage_of( string $handler ): string {
        $handler = ltrim( $handler, '\\' );
        if ( 'WC_Log_Handler_DB' === $handler || is_a( $handler, 'WC_Log_Handler_DB', true ) ) {
            return self::STORAGE_DATABASE;
        }
        if ( in_array( $handler, array( 'Automattic\WooCommerce\Internal\Admin\Logging\LogHandlerFileV2', 'WC_Log_Handler_File' ), true )
            || is_a( $handler, 'Automattic\WooCommerce\Internal\Admin\Logging\LogHandlerFileV2', true )
            || is_a( $handler, 'WC_Log_Handler_File', true ) ) {
            return self::STORAGE_FILE;
        }
        return self::STORAGE_OTHER;
    }

    public static function settings(): array {
        $util = '\Automattic\WooCommerce\Utilities\LoggingUtil';
        return array(
            'logging_enabled' => self::util_has( 'logging_is_enabled' ) ? $util::logging_is_enabled() : null,
            'level_threshold' => self::util_has( 'get_level_threshold' ) ? $util::get_level_threshold() : null,
            'retention_days'  => self::util_has( 'get_retention_period' ) ? $util::get_retention_period() : null,
        );
    }

    public static function directory(): ?string {
        if ( self::util_has( 'get_log_directory' ) ) {
            $takes_flag = ( new \ReflectionMethod( '\Automattic\WooCommerce\Utilities\LoggingUtil', 'get_log_directory' ) )->getNumberOfParameters() > 0;
            $dir        = $takes_flag ? \Automattic\WooCommerce\Utilities\LoggingUtil::get_log_directory( false ) : self::settings_directory();
        } elseif ( defined( 'WC_LOG_DIR' ) ) {
            $dir = (string) constant( 'WC_LOG_DIR' );
        } else {
            return null;
        }
        $real = '' === $dir ? false : @realpath( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false !== $real && is_dir( $real ) ? rtrim( $real, '/\\' ) : null;
    }

    private static function settings_directory(): string {
        if ( defined( 'WC_LOG_DIR_CUSTOM' ) && true === constant( 'WC_LOG_DIR_CUSTOM' ) && defined( 'WC_LOG_DIR' ) ) {
            return (string) constant( 'WC_LOG_DIR' );
        }
        $uploads = function_exists( 'wp_upload_dir' ) ? \wp_upload_dir( null, false ) : array();
        if ( empty( $uploads['basedir'] ) ) {
            return '';
        }
        return (string) \apply_filters( 'woocommerce_log_directory', $uploads['basedir'] . '/wc-logs/' ); // phpcs:ignore WooCommerce.Commenting.CommentHooks.MissingHookComment, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, applied as WooCommerce applies it.
    }

    public static function require_site_scope( string $dir ): void {
        if ( ! function_exists( 'is_multisite' ) || ! \is_multisite() ) {
            return;
        }
        $uploads = function_exists( 'wp_upload_dir' ) ? \wp_upload_dir( null, false ) : array();
        $basedir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
        $moved   = ! self::util_has( 'get_log_directory' )
            || ( defined( 'WC_LOG_DIR_CUSTOM' ) && true === constant( 'WC_LOG_DIR_CUSTOM' ) )
            || ( function_exists( 'has_filter' ) && false !== \has_filter( 'woocommerce_log_directory' ) )
            || self::uploads_moved( $basedir );
        if ( ! self::is_shared_directory( $dir, $basedir, $moved ) ) {
            return;
        }
        if ( \is_super_admin() && \current_user_can( 'manage_network_options' ) ) {
            return;
        }
        throw new \RuntimeException( 'This site\'s WooCommerce log folder is not its own default folder (WC_LOG_DIR or the woocommerce_log_directory filter moved it), so on this multisite network it can hold every site\'s logs. Only a network administrator (super admin) can read it.' );
    }

    private static function uploads_moved( string $basedir ): bool {
        if ( ! function_exists( '_wp_upload_dir' ) ) {
            return true;
        }
        $core = \_wp_upload_dir();
        $own  = isset( $core['basedir'] ) ? @realpath( (string) $core['basedir'] ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        $now  = '' === $basedir ? false : @realpath( $basedir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false === $own || false === $now || $own !== $now;
    }

    public static function is_shared_directory( string $dir, string $basedir, bool $moved ): bool {
        if ( $moved || '' === $basedir ) {
            return true;
        }
        $default = @realpath( rtrim( $basedir, '/\\' ) . '/wc-logs' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        return false === $default || rtrim( str_replace( '\\', '/', $default ), '/' ) !== rtrim( str_replace( '\\', '/', $dir ), '/' );
    }

    public static function files( string $dir ): array {
        $names = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( false === $names ) {
            return array();
        }
        $files = array();
        foreach ( $names as $name ) {
            if ( '.log' !== strtolower( substr( $name, -4 ) ) || strlen( $name ) <= 4 ) {
                continue;
            }
            $path = $dir . '/' . $name;
            if ( is_link( $path ) || ! is_file( $path ) ) {
                continue;
            }
            $mtime   = (int) @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
            $files[] = array_merge(
                self::parse_name( substr( $name, 0, -4 ) ),
                array(
                    'path'       => $path,
                    'size_bytes' => (int) @filesize( $path ), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
                    'modified'   => Site_Health_Guard::iso_utc( $mtime ?: false ),
                    'mtime'      => $mtime,
                )
            );
        }
        usort( $files, static function ( array $a, array $b ): int {
            return array( $b['mtime'], $a['file_id'] ) <=> array( $a['mtime'], $b['file_id'] );
        } );
        return $files;
    }

    public static function parse_name( string $filename ): array {
        $segments = explode( '-', $filename );
        $date     = null;
        if ( count( $segments ) >= 5 ) {
            list( $y, $m, $d ) = array_slice( $segments, -4, 3 );
            if ( preg_match( '/^\d{4}\z/', $y ) && preg_match( '/^\d{1,2}\z/', $m ) && preg_match( '/^\d{1,2}\z/', $d )
                && checkdate( (int) $m, (int) $d, (int) $y ) ) {
                $date     = sprintf( '%04d-%02d-%02d', (int) $y, (int) $m, (int) $d );
                $segments = array_slice( $segments, 0, -4 );
            }
        }
        if ( null === $date && count( $segments ) >= 2 && preg_match( '/^[0-9a-f]{32}\z/', (string) end( $segments ) ) ) {
            array_pop( $segments );
        }
        $source   = implode( '-', $segments );
        $rotation = null;
        $dot      = strrpos( $source, '.' );
        if ( false !== $dot && ctype_digit( substr( $source, -1 ) ) ) {
            $rotation = (int) substr( $source, -1 );
            $source   = substr( $source, 0, $dot );
        }
        $file_id = $source . ( null === $rotation ? '' : '.' . $rotation ) . ( null === $date ? '' : '-' . $date );
        return array(
            'file_id'  => $file_id,
            'source'   => $source,
            'rotation' => $rotation,
            'date'     => $date,
        );
    }

    public static function public_file( array $file ): array {
        return array(
            'file_id'    => $file['file_id'],
            'source'     => $file['source'],
            'rotation'   => $file['rotation'],
            'date'       => $file['date'],
            'size_bytes' => $file['size_bytes'],
            'modified'   => $file['modified'],
        );
    }

    public static function line_severity( string $line ): ?int {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\S+ ([A-Z]+) /', $line, $m ) ) {
            return null;
        }
        $level = strtolower( $m[1] );
        return isset( self::SEVERITY[ $level ] ) ? self::SEVERITY[ $level ] : null;
    }

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'woocommerce_log';
    }

    public static function db_sources( string $prefix, int $limit ): array {
        global $wpdb;
        $table = self::table();
        $where = '' === $prefix ? '' : $wpdb->prepare( ' WHERE source LIKE %s', $wpdb->esc_like( $prefix ) . '%' );
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the table name is ours; $where is prepared above.
        $total = $wpdb->get_var( "SELECT COUNT(DISTINCT source) FROM {$table}{$where}" );
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT source, COUNT(*) AS entries, MIN(timestamp) AS oldest, MAX(timestamp) AS newest FROM {$table}{$where} GROUP BY source ORDER BY newest DESC, source ASC LIMIT %d", $limit ),
            ARRAY_A
        );
        // phpcs:enable
        self::require_db_read( $rows );
        $sources = array();
        foreach ( (array) $rows as $row ) {
            $sources[] = array(
                'source'  => (string) $row['source'],
                'entries' => (int) $row['entries'],
                'oldest'  => Site_Health_Guard::iso_utc( self::db_timestamp( isset( $row['oldest'] ) ? (string) $row['oldest'] : null ) ),
                'newest'  => Site_Health_Guard::iso_utc( self::db_timestamp( isset( $row['newest'] ) ? (string) $row['newest'] : null ) ),
            );
        }
        return array( 'sources' => $sources, 'total' => (int) $total );
    }

    public static function db_lines( string $source, ?string $date, int $min_severity, array &$scan ): \Generator {
        global $wpdb;
        $table = self::table();
        $where = $wpdb->prepare( 'source = %s AND level >= %d', $source, $min_severity );
        if ( null !== $date ) {
            $next   = gmdate( 'Y-m-d', (int) strtotime( $date . ' 00:00:00 UTC' ) + DAY_IN_SECONDS );
            $where .= $wpdb->prepare( ' AND timestamp >= %s AND timestamp < %s', $date . ' 00:00:00', $next . ' 00:00:00' );
        }
        $before = null;
        while ( $scan['rows'] < static::$db_max_rows ) {
            $cursor = null === $before ? '' : $wpdb->prepare( ' AND log_id < %d', $before );
            $limit  = min( static::$db_batch, static::$db_max_rows - $scan['rows'] );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- the table name is ours; $where and $cursor are prepared above.
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT log_id, timestamp, level, SUBSTRING(message, 1, %d) AS message, SUBSTRING(context, 1, %d) AS context FROM {$table} WHERE {$where}{$cursor} ORDER BY log_id DESC LIMIT %d",
                    self::DB_FIELD_BYTES,
                    self::DB_FIELD_BYTES,
                    $limit
                ),
                ARRAY_A
            );
            // phpcs:enable
            self::require_db_read( $rows );
            foreach ( $rows as $row ) {
                $scan['rows']++;
                $before = (int) $row['log_id'];
                yield self::db_line( $row );
            }
            if ( count( $rows ) < $limit ) {
                return;
            }
        }
        $scan['cut'] = true;
    }

    private static function db_line( array $row ): string {
        $level   = array_search( (int) $row['level'], self::SEVERITY, true );
        $ts      = self::db_timestamp( (string) $row['timestamp'] );
        $line    = false === $ts ? (string) $row['timestamp'] : gmdate( 'c', $ts );
        $line   .= ' ' . strtoupper( false === $level ? (string) $row['level'] : $level );
        $line   .= ' ' . self::one_line( (string) $row['message'] );
        $context = isset( $row['context'] ) ? (string) $row['context'] : '';
        if ( '' !== $context ) {
            $line .= ' CONTEXT: ' . self::row_context( $context );
        }
        return $line;
    }

    public static function redact_line( string $line ): string {
        $at = strpos( $line, ' CONTEXT: ' );
        while ( false !== $at ) {
            $context = self::structured( substr( $line, $at + 10 ) );
            if ( null !== $context ) {
                $line = substr( $line, 0, $at ) . ' CONTEXT: ' . $context;
                break;
            }
            $at = strpos( $line, ' CONTEXT: ', $at + 1 );
        }
        return self::redact_text( $line );
    }

    const MAX_DEPTH = 8;

    const SERIALIZED_OMITTED = '[serialized context omitted: it could not be decoded]';

    public static function row_context( string $context ): string {
        $json = self::structured( $context );
        if ( null !== $json ) {
            return self::redact_text( $json );
        }
        if ( ! preg_match( '/^\s*(?:a:\d+:\{|[sOC]:\d+:")/', $context ) ) {
            return self::redact_text( self::one_line( $context ) );
        }
        $decoded = self::unserialized( $context );
        if ( is_array( $decoded ) ) {
            $json = \wp_json_encode( self::redact_structure( $decoded ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
            return false === $json ? self::SERIALIZED_OMITTED : self::redact_text( $json );
        }
        return is_string( $decoded ) ? self::redact_text( self::one_line( $decoded ) ) : self::SERIALIZED_OMITTED;
    }

    private static function unserialized( string $data ) {
        if ( ! function_exists( 'is_serialized' ) || ! \is_serialized( $data ) || preg_match( '/[;{}][Rr]:\d+;/', $data ) ) {
            return null;
        }
        $decoded = @unserialize( $data, array( 'allowed_classes' => false, 'max_depth' => self::MAX_DEPTH ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
        return is_array( $decoded ) || is_string( $decoded ) ? $decoded : null;
    }

    private static function structured( string $json ): ?string {
        $decoded = json_decode( $json, true );
        if ( ! is_array( $decoded ) ) {
            return null;
        }
        $encoded = \wp_json_encode( self::redact_structure( $decoded ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        return false === $encoded ? null : $encoded;
    }

    private static function redact_structure( $value, $key = '', int $depth = 0 ) {
        if ( '' !== (string) $key && ! is_int( $key ) && preg_match( Text_Redactor::SENSITIVE_KEY_PATTERN, (string) $key ) ) {
            return Text_Redactor::MASK;
        }
        if ( $depth >= self::MAX_DEPTH ) {
            return Text_Redactor::MASK;
        }
        if ( is_array( $value ) ) {
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[ $k ] = self::redact_structure( $v, $k, $depth + 1 );
            }
            return $out;
        }
        if ( is_string( $value ) ) {
            $inner = json_decode( $value, true );
            if ( is_array( $inner ) ) {
                $encoded = \wp_json_encode( self::redact_structure( $inner, '', $depth + 1 ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
                return false === $encoded ? Text_Redactor::MASK : $encoded;
            }
            return self::redact_text( $value );
        }
        return Text_Redactor::redact_value( $value );
    }

    public static function redact_text( string $text ): string {
        $best  = self::redact_layer( $text );
        $probe = $best;
        for ( $i = 0; $i < 3; $i++ ) {
            $next = preg_replace( '/\\\\(["\\\\\/])/', '$1', $probe );
            if ( null === $next || $next === $probe ) {
                break;
            }
            $probe = self::redact_layer( $next );
            if ( $probe !== $next ) {
                $best = $probe;
            }
        }
        return $best;
    }

    private static function redact_layer( string $text ): string {
        $netted = self::net( $text );
        return Text_Redactor::MASK === $netted ? $netted : (string) Text_Redactor::redact( $netted );
    }

    private static function net( string $text ): string {
        $keys  = (string) preg_replace( '~^/(.*)/[a-z]*$~s', '$1', Text_Redactor::SENSITIVE_KEY_PATTERN );
        $value = '(?:\[[^\]]*(?:\]|$)|\{[^}]*(?:\}|$)|"(?:[^"\\\\]|\\\\.)*(?:"|$)|[^,}\]\s"]+)';
        $out = preg_replace( '/((?<!\\\\)"(?=[^"\\\\]*(?:' . $keys . '))[^"\\\\]*+"\s*+:\s*+)' . $value . '/i', '$1"' . Text_Redactor::MASK . '"', $text );
        return null === $out ? Text_Redactor::MASK : $out;
    }

    private static function one_line( string $text ): string {
        if ( false === strpbrk( $text, "\r\n" ) ) {
            return trim( $text );
        }
        $parts = array_filter( array_map( 'trim', (array) preg_split( '/[\r\n]+/', $text ) ), 'strlen' );
        return implode( ' ', $parts );
    }

    private static function db_timestamp( ?string $value ) {
        return null !== $value && '' !== $value ? strtotime( $value . ' UTC' ) : false;
    }

    private static function require_db_read( $rows ): void {
        global $wpdb;
        if ( ! is_array( $rows ) || ( isset( $wpdb->last_error ) && '' !== (string) $wpdb->last_error ) ) {
            throw new \RuntimeException( 'Could not read the WooCommerce log table (' . self::table() . '). WooCommerce creates it on install; it may be missing on this site.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
