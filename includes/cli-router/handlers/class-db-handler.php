<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Db_Handler extends Command_Handler {

    const NOT_CAPTURED_MAINTENANCE = 'Table maintenance: OPTIMIZE TABLE and REPAIR TABLE rebuild storage without changing rows, so change history has nothing to record.';

    const EXTRA_ARGUMENT = "Parameter errors:\n --%s parameter is not available through wp_cli_run: WP-CLI passes it to the mysqlcheck binary, which wp_cli_run does not run; it runs %s TABLE on each table through WordPress's own database connection.";

    const COLUMNS_WILDCARD = "wp_cli_run refuses '%s' for 'db columns': WP-CLI would send the pattern itself to SHOW COLUMNS, which fails, and print an empty table. Name one table, as `db tables` prints it.";

    const ROW_CAP = 1000;

    const MAINTENANCE_CAP = 200;

    const OVER_ROW_CAP_TABLES = "wp_cli_run lists at most %1\$d tables in one 'db tables', and %2\$d match. Narrow it with <table> patterns (wildcards * and ?) or --scope, or leave out --network, --all-tables and --all-tables-with-prefix.";

    const OVER_ROW_CAP_SIZE = "wp_cli_run sizes at most %1\$d tables one by one in one 'db size', and %2\$d match. Narrow it with <table> patterns (wildcards * and ?) or --scope, or ask for the whole database's size without --tables.";

    const OVER_MAINTENANCE_CAP = "wp_cli_run runs %1\$s TABLE on at most %2\$d tables in one 'db %3\$s', and this database has %4\$d; every statement runs in this one web request. Run 'wp db %3\$s' on the server instead.";

    const DIVISORS = array(
        'TB'  => 1000 ** 4,
        'GB'  => 1000 ** 3,
        'MB'  => 1000 ** 2,
        'KB'  => 1000,
        'tb'  => 1024 ** 4,
        'TiB' => 1024 ** 4,
        'gb'  => 1024 ** 3,
        'GiB' => 1024 ** 3,
        'mb'  => 1024 ** 2,
        'MiB' => 1024 ** 2,
        'kb'  => 1024,
        'KiB' => 1024,
    );

    const MAINTENANCE = array(
        'check'    => array( 'CHECK', 'Database checked.' ),
        'optimize' => array( 'OPTIMIZE', 'Database optimized.' ),
        'repair'   => array( 'REPAIR', 'Database repaired.' ),
    );

    public function preflight_maintenance( Invocation $inv, Context $ctx ): void {
        $verb  = $this->verb( $inv );
        $op    = self::MAINTENANCE[ $verb ][0];
        $known = array( 'dbuser', 'dbpass', 'defaults' );
        foreach ( array_keys( $inv->assoc ) as $key ) {
            if ( ! in_array( (string) $key, $known, true ) ) {
                $ctx->out->error( sprintf( self::EXTRA_ARGUMENT, (string) $key, $op ) );
            }
        }
        $count = count( $this->base_tables() );
        if ( $count > self::MAINTENANCE_CAP ) {
            $ctx->out->error( sprintf( self::OVER_MAINTENANCE_CAP, $op, self::MAINTENANCE_CAP, $verb, $count ) );
        }
    }

    public function preflight_tables( Invocation $inv, Context $ctx ): void {
        if ( $this->network_form( $inv->assoc, false ) ) {
            $ctx->require_network( 'manage_network_options' );
        }
    }

    public function preflight_size( Invocation $inv, Context $ctx ): void {
        if ( $this->network_form( $inv->assoc, true ) ) {
            $ctx->require_network( 'manage_network_options' );
        }
    }

    public function preflight_columns( Invocation $inv, Context $ctx ): void {
        $table = (string) ( $inv->args[0] ?? '' );
        if ( false !== strpos( $table, '*' ) || false !== strpos( $table, '?' ) ) {
            $ctx->out->error( sprintf( self::COLUMNS_WILDCARD, $table ) );
        }
        if ( $this->multisite() && ! $this->is_own_table( $table ) ) {
            $ctx->require_network( 'manage_network_options' );
        }
    }

    public function prefix( Invocation $inv, Context $ctx ): void {
        $ctx->out->log( (string) $this->wpdb_value( 'prefix' ) );
    }

    public function tables( Invocation $inv, Context $ctx ): void {
        $args       = array_values( $inv->args );
        $assoc_args = $inv->assoc;

        $format = Read_Value::flag( $assoc_args, 'format' );
        unset( $assoc_args['format'] );

        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args['scope'] = 'all';
        }

        $tables = $this->table_names( $args, $assoc_args, $ctx->out );

        if ( count( $tables ) > self::ROW_CAP ) {
            $ctx->out->error( sprintf( self::OVER_ROW_CAP_TABLES, self::ROW_CAP, count( $tables ) ) );
        }

        if ( 'csv' === $format ) {
            $ctx->out->line( implode( ',', $tables ) );
        } else {
            foreach ( $tables as $table ) {
                $ctx->out->line( $table );
            }
        }
    }

    public function size( Invocation $inv, Context $ctx ): void {
        $args       = array_values( $inv->args );
        $assoc_args = $inv->assoc;

        $format         = Read_Value::flag( $assoc_args, 'format' );
        $size_format    = Read_Value::flag( $assoc_args, 'size_format' );
        $human_readable = Read_Value::flag( $assoc_args, 'human-readable', false );
        $tables         = ! empty( Read_Value::flag( $assoc_args, 'tables' ) );

        $assoc_args = self::size_scope( $args, $assoc_args, $size_format, $human_readable, $ctx );

        $default_unit = ( empty( $size_format ) && ! $human_readable ) ? ' B' : '';
        $rows         = $this->size_rows( $args, $assoc_args, $tables, $default_unit, $ctx );

        $size_format_display = '';
        if ( ! empty( $size_format ) || $human_readable ) {
            $size_format_display = self::format_sizes( $rows, $size_format, (bool) $human_readable, Read_Value::flag( $assoc_args, 'decimals', 0 ) );
        }

        if ( self::prints_bare_size( $size_format, $tables, $format, $human_readable, $assoc_args ) ) {
            $ctx->out->line( str_replace( " {$size_format_display}", '', (string) $rows[0]['Size'] ) );
            return;
        }

        self::sort_size_rows( $rows, Read_Value::flag( $assoc_args, 'order', 'asc' ), Read_Value::flag( $assoc_args, 'orderby', null ) );

        $formatter_args = array( 'format' => $format );
        $this->formatter( $ctx, $formatter_args, array( 'Name', 'Size' ) )->display_items( $rows );
    }

    private static function size_scope( array $args, array $assoc_args, $size_format, $human_readable, Context $ctx ): array {
        if ( ! is_null( $size_format ) && $human_readable ) {
            $ctx->out->error( 'Cannot use --size_format and --human-readable arguments at the same time.' );
        }

        unset( $assoc_args['format'], $assoc_args['size_format'], $assoc_args['human-readable'], $assoc_args['tables'] );

        if ( empty( $args ) && empty( $assoc_args ) ) {
            $assoc_args['scope'] = 'all';
        }
        return $assoc_args;
    }

    private function size_rows( array $args, array $assoc_args, bool $tables, string $default_unit, Context $ctx ): array {
        $db_name = $this->db_name();

        if ( $tables || Read_Value::flag( $assoc_args, 'all-tables' ) || Read_Value::flag( $assoc_args, 'all-tables-with-prefix' ) ) {
            $table_names = $this->table_names( $args, $assoc_args, $ctx->out );
            if ( count( $table_names ) > self::ROW_CAP ) {
                $ctx->out->error( sprintf( self::OVER_ROW_CAP_SIZE, self::ROW_CAP, count( $table_names ) ) );
            }
            $rows = array();
            foreach ( $table_names as $table_name ) {
                $table_bytes = $this->q_var(
                    'SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = %s and Table_Name = %s GROUP BY Table_Name LIMIT 1',
                    array( $db_name, $table_name )
                );
                $rows[]      = array(
                    'Name'  => $table_name,
                    'Size'  => strtoupper( (string) $table_bytes ) . $default_unit,
                    'Bytes' => strtoupper( (string) $table_bytes ),
                );
            }
            return $rows;
        }

        $db_bytes = $this->q_var(
            'SELECT SUM(data_length + index_length) FROM information_schema.TABLES where table_schema = %s GROUP BY table_schema;',
            array( $db_name )
        );
        return array(
            array(
                'Name'  => $db_name,
                'Size'  => strtoupper( (string) $db_bytes ) . $default_unit,
                'Bytes' => strtoupper( (string) $db_bytes ),
            ),
        );
    }

    private static function format_sizes( array &$rows, $size_format, bool $human_readable, $decimals ): string {
        $size_format_display = '';
        foreach ( $rows as $index => $row ) {
            if ( $human_readable ) {
                $bytes       = (float) $row['Size'];
                $size_key    = $bytes > 0 ? (int) floor( log( $bytes ) / log( 1000 ) ) : 0;
                $sizes       = array( 'B', 'KB', 'MB', 'GB', 'TB' );
                $size_format = isset( $sizes[ $size_key ] ) ? $sizes[ $size_key ] : $sizes[0];
            }
            $divisor                = self::divisor( (string) $size_format );
            $size_format_display    = (string) preg_replace( '/IB$/u', 'iB', strtoupper( (string) $size_format ) );
            $rows[ $index ]['Size'] = round( (int) $row['Bytes'] / $divisor, (int) $decimals ) . ' ' . $size_format_display;
        }
        return $size_format_display;
    }

    private static function prints_bare_size( $size_format, bool $tables, $format, $human_readable, array $assoc_args ): bool {
        return ! empty( $size_format ) && ! $tables && ! $format && ! $human_readable
            && true !== Read_Value::flag( $assoc_args, 'all-tables' ) && true !== Read_Value::flag( $assoc_args, 'all-tables-with-prefix' );
    }

    private static function sort_size_rows( array &$rows, $order, $orderby ): void {
        if ( ! $orderby ) {
            return;
        }
        usort(
            $rows,
            static function ( array $a, array $b ) use ( $order, $orderby ): int {
                list( $first, $second ) = 'asc' === $order ? array( $a, $b ) : array( $b, $a );
                if ( 'size' === $orderby ) {
                    return (int) ( $first['Bytes'] > $second['Bytes'] );
                }
                return strcmp( $first['Name'], $second['Name'] );
            }
        );
    }

    public function columns( Invocation $inv, Context $ctx ): void {
        $format = Read_Value::flag( $inv->assoc, 'format' );
        $table  = (string) $inv->args[0];

        $this->table_names( array( $table ), array( 'all-tables' => true ), $ctx->out );

        $columns = $this->q_results( 'SHOW COLUMNS FROM ' . self::ident( $table ) );

        $formatter_args = array( 'format' => $format );
        $this->formatter( $ctx, $formatter_args, array( 'Field', 'Type', 'Null', 'Key', 'Default', 'Extra' ) )->display_items( $columns );
    }

    public function check( Invocation $inv, Context $ctx ): void {
        $this->maintenance( 'check', $ctx );
    }

    public function optimize( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED_MAINTENANCE );
        $this->maintenance( 'optimize', $ctx );
    }

    public function repair( Invocation $inv, Context $ctx ): void {
        $ctx->not_captured( self::NOT_CAPTURED_MAINTENANCE );
        $this->maintenance( 'repair', $ctx );
    }

    public function describe_maintenance( Invocation $inv ): array {
        $op    = self::MAINTENANCE[ $this->verb( $inv ) ][0];
        $lines = array();
        $rows  = $this->q_results(
            "SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
            array( $this->db_name() )
        );
        foreach ( $rows as $row ) {
            $lines[] = sprintf( '%s (%s) → %s TABLE; its rows do not change', (string) $row['TABLE_NAME'], (string) $row['ENGINE'], $op );
        }
        if ( ! $lines ) {
            $lines[] = 'No base table in the database.';
        }
        return $lines;
    }

    public function table_names( array $args, array $assoc_args, Output $out ): array {
        if ( Read_Value::flag( $assoc_args, 'all-tables' ) ) {
            $tables = $this->q_col( 'SHOW TABLES' );
        } elseif ( Read_Value::flag( $assoc_args, 'all-tables-with-prefix' ) ) {
            $tables = $this->q_col( 'SHOW TABLES LIKE %s', array( $this->esc_like( $this->blog_prefix() ) . '%' ) );
        } else {
            $tables = $this->registered_tables( $assoc_args );
        }

        $tables = array_values( array_map( 'strval', $tables ) );

        if ( $args ) {
            $tables = self::matching_tables( $tables, $args, $out );
        }

        return $tables;
    }

    private function registered_tables( array $assoc_args ): array {
        $scope = Read_Value::flag( $assoc_args, 'scope', 'all' );

        if ( Read_Value::flag( $assoc_args, 'network' ) && $this->multisite() ) {
            $wp_tables = $this->network_tables( $scope );
        } else {
            $wp_tables = array_values( $this->wpdb_tables( is_string( $scope ) ? $scope : 'all' ) );
        }

        if ( version_compare( str_replace( '-src', '', $this->wp_version() ), '6.1', '>=' ) || ! $this->global_terms_enabled() ) {
            $wp_tables = array_values( array_diff( $wp_tables, array( (string) $this->wpdb_value( 'sitecategories' ) ) ) );
        }

        if ( ! $wp_tables ) {
            return array();
        }
        $placeholders = implode( ', ', array_fill( 0, count( $wp_tables ), '%s' ) );
        return $this->q_col( 'SHOW TABLES WHERE ' . self::ident( 'Tables_in_' . (string) $this->wpdb_value( 'dbname' ) ) . " IN ({$placeholders})", array_values( $wp_tables ) );
    }

    private function network_tables( $scope ): array {
        $network_global_scope = in_array( $scope, array( 'all', 'global', 'ms_global' ), true ) ? ( 'all' === $scope ? 'global' : $scope ) : '';
        $wp_tables            = array_values( $this->wpdb_tables( (string) $network_global_scope ) );
        if ( in_array( $scope, array( 'all', 'blog' ), true ) ) {
            $blog_ids = $this->q_col( 'SELECT blog_id FROM ' . (string) $this->wpdb_value( 'blogs' ) . ' WHERE site_id = ' . (int) $this->wpdb_value( 'siteid' ) );
            foreach ( $blog_ids as $blog_id ) {
                $wp_tables = array_merge( $wp_tables, array_values( $this->wpdb_tables( 'blog', (int) $blog_id ) ) );
            }
        }
        return $wp_tables;
    }

    private static function matching_tables( array $tables, array $args, Output $out ): array {
        $args_tables = array();
        foreach ( $args as $arg ) {
            $arg = (string) $arg;
            if ( false !== strpos( $arg, '*' ) || false !== strpos( $arg, '?' ) ) {
                $args_tables = array_merge(
                    $args_tables,
                    array_filter(
                        $tables,
                        static function ( string $v ) use ( $arg ): bool {
                            return fnmatch( $arg, $v );
                        }
                    )
                );
            } else {
                $args_tables[] = $arg;
            }
        }
        $args_tables = array_values( array_unique( $args_tables ) );
        $tables      = array_values( array_intersect( $tables, $args_tables ) );
        if ( empty( $tables ) ) {
            $out->error( sprintf( "Couldn't find any tables matching: %s", implode( ' ', $args ) ) );
        }
        return $tables;
    }

    public function view_names(): array {
        return array_values( array_map( 'strval', $this->q_col( "SHOW FULL TABLES WHERE Table_type = 'VIEW'" ) ) );
    }

    public function is_own_table( string $table ): bool {
        $prefix  = (string) $this->blog_prefix();
        $base    = (string) $this->wpdb_value( 'base_prefix' );
        $globals = array_values( array_map( 'strval', $this->wpdb_tables( 'global' ) ) );
        if ( in_array( $table, $globals, true ) ) {
            return true;
        }
        if ( 0 !== strpos( $table, $prefix ) ) {
            return false;
        }
        return ! ( $prefix === $base && preg_match( '/^' . preg_quote( $base, '/' ) . '\d+_/', $table ) );
    }

    private function maintenance( string $verb, Context $ctx ): void {
        list( $op, $success ) = self::MAINTENANCE[ $verb ];
        foreach ( $this->base_tables() as $name ) {
            $result = $this->maintain( $op, $name );
            if ( null === $result ) {
                $ctx->out->error( sprintf( "Couldn't execute '%s TABLE %s' on the database.", $op, self::ident( $name ) ) );
            }
            self::print_result( $result, $ctx->out );
        }
        $ctx->out->success( $success );
    }

    public static function print_result( array $rows, Output $out ): void {
        $prev = '';
        foreach ( $rows as $row ) {
            $table   = (string) $row[0];
            $type    = (string) $row[2];
            $text    = (string) $row[3];
            $changed = 0 !== strcmp( $prev, $table );
            $status  = 'status' === $type;
            if ( $status && $changed ) {
                $out->out( sprintf( "%-50s %s\n", $table, $text ) );
            } elseif ( ! $status && $changed ) {
                if ( 'error' === $type && false !== strpos( $text, 'REPAIR ' ) ) {
                    $out->out( sprintf( "%-50s %s\n", $table, 'Needs upgrade with REPAIR' ) );
                } elseif ( 'error' === $type && false !== strpos( $text, 'ALTER TABLE' ) ) {
                    $out->out( sprintf( "%-50s %s\n", $table, 'Needs upgrade with ALTER TABLE FORCE' ) );
                } else {
                    $out->out( sprintf( "%s\n%-9s: %s\n", $table, $type, $text ) );
                }
            } else {
                $out->out( sprintf( "%-9s: %s\n", $type, $text ) );
            }
            $prev = $table;
        }
    }

    private function base_tables(): array {
        $names = array();
        foreach ( $this->q_results( 'SHOW FULL TABLES', array(), 'ARRAY_N' ) as $row ) {
            if ( isset( $row[1] ) && 'VIEW' === $row[1] ) {
                continue;
            }
            $names[] = (string) $row[0];
        }
        return $names;
    }

    private function verb( Invocation $inv ): string {
        $verb = (string) substr( $inv->command, 3 );
        return isset( self::MAINTENANCE[ $verb ] ) ? $verb : 'check';
    }

    private function network_form( array $assoc, bool $size ): bool {
        if ( ! $this->multisite() ) {
            return false;
        }
        if ( Read_Value::flag( $assoc, 'network' ) || Read_Value::flag( $assoc, 'all-tables' ) ) {
            return true;
        }
        if ( Read_Value::flag( $assoc, 'all-tables-with-prefix' ) ) {
            return (string) $this->blog_prefix() === (string) $this->wpdb_value( 'base_prefix' );
        }
        return $size && empty( $assoc['tables'] );
    }

    private static function divisor( string $size_format ): float {
        return (float) ( self::DIVISORS[ $size_format ] ?? 1 );
    }

    public static function ident( string $name ): string {
        return '`' . str_replace( '`', '``', $name ) . '`';
    }

    private function esc_like( string $text ): string {
        return addcslashes( $text, '_%\\' );
    }

    protected function call( string $name, array $args, callable $real ) {
        return $real();
    }

    protected function q_col( string $sql, array $params = array() ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $sql, $params ),
            static function () use ( $sql, $params ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are quoted by ident(), values prepared.
                return (array) $wpdb->get_col( $params ? $wpdb->prepare( $sql, $params ) : $sql );
            }
        );
    }

    protected function q_var( string $sql, array $params = array() ): ?string {
        return $this->call(
            __FUNCTION__,
            array( $sql, $params ),
            static function () use ( $sql, $params ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are quoted by ident(), values prepared.
                return $wpdb->get_var( $params ? $wpdb->prepare( $sql, $params ) : $sql );
            }
        );
    }

    protected function q_results( string $sql, array $params = array(), string $output = 'ARRAY_A' ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $sql, $params, $output ),
            static function () use ( $sql, $params, $output ) {
                global $wpdb;
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- identifiers are quoted by ident(), values prepared.
                return (array) $wpdb->get_results( $params ? $wpdb->prepare( $sql, $params ) : $sql, $output );
            }
        );
    }

    protected function maintain( string $op, string $table ): ?array {
        $result = $this->call(
            __FUNCTION__,
            array( $op, $table ),
            static function () use ( $op, $table ) {
                global $wpdb;
                $wpdb->last_error = '';
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident(); there is no value.
                $rows = $wpdb->get_results( $op . ' TABLE ' . self::ident( $table ), 'ARRAY_N' );
                if ( '' !== (string) $wpdb->last_error ) {
                    return null;
                }
                $out = array();
                foreach ( (array) $rows as $row ) {
                    $out[] = array_map( 'strval', array_values( (array) $row ) );
                }
                return $out;
            }
        );
        return is_array( $result ) ? $result : null;
    }

    protected function wpdb_tables( string $scope, int $blog_id = 0 ): array {
        return (array) $this->call(
            __FUNCTION__,
            array( $scope, $blog_id ),
            static function () use ( $scope, $blog_id ) {
                global $wpdb;
                return (array) $wpdb->tables( $scope, true, $blog_id );
            }
        );
    }

    protected function wpdb_value( string $property ) {
        return $this->call(
            __FUNCTION__,
            array( $property ),
            static function () use ( $property ) {
                global $wpdb;
                return isset( $wpdb->$property ) ? $wpdb->$property : null;
            }
        );
    }

    protected function blog_prefix(): string {
        return (string) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                global $wpdb;
                return $wpdb->get_blog_prefix();
            }
        );
    }

    protected function multisite(): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return \is_multisite();
            }
        );
    }

    protected function db_name(): string {
        return (string) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return defined( 'DB_NAME' ) ? (string) constant( 'DB_NAME' ) : '';
            }
        );
    }

    protected function wp_version(): string {
        return (string) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return isset( $GLOBALS['wp_version'] ) ? (string) $GLOBALS['wp_version'] : '';
            }
        );
    }

    protected function global_terms_enabled(): bool {
        return (bool) $this->call(
            __FUNCTION__,
            array(),
            static function () {
                return function_exists( 'global_terms_enabled' ) && \global_terms_enabled(); // phpcs:ignore WordPress.WP.DeprecatedFunctions.global_terms_enabledFound -- WP-CLI's own branch for WordPress before 6.1.
            }
        );
    }
}
