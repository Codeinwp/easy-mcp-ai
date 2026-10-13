<?php
namespace Easy_MCP_AI\Privileged\Search_Replace;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Database over $wpdb (GitHub #99).
 *
 * Table and column names come from SHOW TABLES / SHOW COLUMNS, never from the
 * caller unchecked, and are still backtick-quoted with backticks doubled.
 * Every value goes through $wpdb->prepare() as a string, primary keys
 * included: WP-CLI interpolates them, and WPVibe found that `pk = 0123`
 * matches `'123'` (docs/plans/2026-10-02-search-replace-tool.md §3).
 *
 * Matching is `LIKE BINARY`: byte-exact whatever the collation, as WP-CLI.
 *
 * Each statement that builds SQL from a table or column name carries its own
 * phpcs:ignore naming the rule it trips: a search-replace reads and writes
 * arbitrary tables by name, which prepare() has no placeholder for, so names
 * go through ident() and every value through prepare().
 */
final class Wpdb_Database implements Database {

    
    private $wpdb;

    public function __construct( $wpdb = null ) {
        if ( null === $wpdb ) {
            global $wpdb;
            $this->wpdb = $wpdb;
        } else {
            $this->wpdb = $wpdb;
        }
    }

    public static function ident( string $name ): string {
        return '`' . str_replace( '`', '``', $name ) . '`';
    }

    public function base_tables(): array {
        $rows = $this->wpdb->get_results( "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'", ARRAY_N );
        $out  = array();
        foreach ( (array) $rows as $row ) {
            if ( isset( $row[0] ) ) {
                $out[] = (string) $row[0];
            }
        }
        return $out;
    }

    public function primary_key( string $table ): array {
        $rows = $this->wpdb->get_results( 'SHOW KEYS FROM ' . self::ident( $table ) . " WHERE Key_name = 'PRIMARY'", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident(); there is no value.
        $keys = array();
        foreach ( (array) $rows as $row ) {
            $keys[ (int) $row['Seq_in_index'] ] = (string) $row['Column_name'];
        }
        ksort( $keys );
        return array_values( $keys );
    }

    public function text_columns( string $table ): array {
        $rows = $this->wpdb->get_results( 'SHOW COLUMNS FROM ' . self::ident( $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the table name is quoted by ident(); there is no value.
        $out  = array();
        foreach ( (array) $rows as $row ) {
            $column = self::text_column( (string) $row['Field'], (string) $row['Type'] );
            if ( null !== $column ) {
                $out[] = $column;
            }
        }
        return $out;
    }

    





    public static function text_column( string $name, string $type ): ?array {
        $type = strtolower( trim( $type ) );
        if ( preg_match( '/^(?:var)?char\((\d+)\)/', $type, $m ) ) {
            return array( 'name' => $name, 'type' => $type, 'max_chars' => (int) $m[1], 'max_bytes' => null );
        }
        $bytes = array(
            'tinytext'   => 255,
            'text'       => 65535,
            'mediumtext' => 16777215,
            'longtext'   => null,
        );
        $base = (string) preg_replace( '/\s.*$/', '', $type );
        if ( array_key_exists( $base, $bytes ) ) {
            return array( 'name' => $name, 'type' => $type, 'max_chars' => null, 'max_bytes' => $bytes[ $base ] );
        }
        return null;
    }

    public function fetch( string $table, string $column, array $pk, array $needles, ?array $after, int $limit, array $conditions ): array {
        $select = array();
        foreach ( $pk as $key ) {
            $select[] = self::ident( $key );
        }
        if ( ! in_array( $column, $pk, true ) ) {
            $select[] = self::ident( $column );
        }
        list( $where, $values ) = $this->where( $column, $needles, $conditions );
        if ( null !== $after ) {
            list( $keyset, $keyset_values ) = self::keyset( $pk, $after );
            $where                         .= ' AND ' . $keyset;
            $values                         = array_merge( $values, $keyset_values );
        }
        $order = implode( ', ', array_map( array( __CLASS__, 'ident' ), $pk ) );
        $sql   = 'SELECT ' . implode( ', ', $select ) . ' FROM ' . self::ident( $table ) . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . max( 1, $limit );
        $rows  = $this->wpdb->get_results( $this->wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- names quoted by ident(), every value a placeholder in $values.
        return is_array( $rows ) ? $rows : array();
    }

    public function count( string $table, string $column, array $needles, array $conditions ): int {
        list( $where, $values ) = $this->where( $column, $needles, $conditions );
        return (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::ident( $table ) . ' WHERE ' . $where, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- names quoted by ident(); where() builds the placeholders that $values fills.
    }

    public function column_values( string $table, string $select, string $column, array $needles, array $conditions, int $limit ): array {
        list( $where, $values ) = $this->where( $column, $needles, $conditions );
        $sql = 'SELECT ' . self::ident( $select ) . ' FROM ' . self::ident( $table ) . ' WHERE ' . $where . ' LIMIT ' . max( 1, $limit );
        return array_map( 'strval', (array) $this->wpdb->get_col( $this->wpdb->prepare( $sql, $values ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- names quoted by ident(), every value a placeholder in $values.
    }

    public function update( string $table, string $column, array $key, string $value ): bool {
        $formats = array_fill( 0, count( $key ), '%s' );
        $result  = $this->wpdb->update( $table, array( $column => $value ), $key, array( '%s' ), $formats );
        return false !== $result;
    }

    




    private function where( string $column, array $needles, array $conditions ): array {
        $likes  = array();
        $values = array();
        foreach ( $needles as $needle ) {
            $likes[]  = self::ident( $column ) . ' LIKE BINARY %s';
            $values[] = '%' . $this->wpdb->esc_like( (string) $needle ) . '%';
        }
        $where = '(' . implode( ' OR ', $likes ) . ')';
        foreach ( $conditions as $condition ) {
            list( $col, $op, $value ) = $condition;
            if ( 'not_like' === $op || 'like' === $op ) {
                $where   .= ' AND ' . self::ident( $col ) . ( 'like' === $op ? ' LIKE %s' : ' NOT LIKE %s' );
                $values[] = (string) $value;
            } elseif ( 'in' === $op || 'not_in' === $op ) {
                $list   = array_values( array_map( 'strval', (array) $value ) );
                $where .= ' AND ' . self::ident( $col ) . ( 'in' === $op ? ' IN (' : ' NOT IN (' ) . implode( ', ', array_fill( 0, count( $list ), '%s' ) ) . ')';
                $values = array_merge( $values, $list );
            }
        }
        return array( $where, $values );
    }

    







    public static function keyset( array $pk, array $after ): array {
        $ors    = array();
        $values = array();
        $count  = count( $pk );
        for ( $i = 0; $i < $count; $i++ ) {
            $ands = array();
            for ( $j = 0; $j < $i; $j++ ) {
                $ands[]   = self::ident( $pk[ $j ] ) . ' = %s';
                $values[] = (string) $after[ $j ];
            }
            $ands[]   = self::ident( $pk[ $i ] ) . ' > %s';
            $values[] = (string) $after[ $i ];
            $ors[]    = '(' . implode( ' AND ', $ands ) . ')';
        }
        return array( '(' . implode( ' OR ', $ors ) . ')', $values );
    }
}
