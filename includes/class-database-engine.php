<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Database_Engine {

    public static function describe( object $db ): string {
        if ( defined( 'DB_ENGINE' ) && 'sqlite' === strtolower( (string) DB_ENGINE ) ) {
            return defined( 'SQLITE_DRIVER_VERSION' )
                ? 'SQLite (SQLite Database Integration ' . SQLITE_DRIVER_VERSION . ')'
                : 'SQLite';
        }

        $server  = method_exists( $db, 'db_server_info' ) ? (string) $db->db_server_info() : '';
        $version = method_exists( $db, 'db_version' ) ? (string) $db->db_version() : '';
        if ( '' === $version ) {
            return $server;
        }
        $engine = false !== stripos( $server, 'mariadb' ) ? 'MariaDB' : 'MySQL';
        return $engine . ' ' . $version;
    }
}
