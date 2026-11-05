<?php

namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Option_Lock {

    public static function acquire( string $name, int $ttl ): string {
        global $wpdb;
        $now   = time();
        $token = $now . ':' . bin2hex( random_bytes( 8 ) );
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            return add_option( $name, $token, '', false ) ? $token : '';
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic lock cannot go through the options cache.
        $won = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, $token ) );
        if ( 1 !== $won ) {
            if ( self::failed() ) {
                return '';
            }
            $held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
            if ( self::failed() ) {
                return '';
            }
            $time = (int) $held;
            if ( $time > 0 && $now - $time < $ttl ) {
                return '';
            }
            $won = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, $name, $held ) );
        }
        // phpcs:enable
        wp_cache_delete( $name, 'options' );
        return 1 === $won ? $token : '';
    }

    public static function acquire_waiting( string $name, int $ttl, int $wait, string $holder ): string {
        global $wpdb;
        $deadline = microtime( true ) + $wait;
        do {
            $lock = self::acquire( $name, $ttl );
            if ( '' !== $lock ) {
                return $lock;
            }
            if ( self::failed() ) {
                throw new \RuntimeException( sprintf( 'The database refused the lock this call takes before saving (%s), so nothing was changed.', $wpdb->last_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            usleep( 100000 );
        } while ( microtime( true ) < $deadline );
        throw new \RuntimeException( sprintf( 'Another %s call has been saving for more than %d seconds, so nothing was changed. Call again.', $holder, $wait ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    public static function lost_cause( string $holder, int $ttl ): string {
        global $wpdb;
        return self::failed()
            ? sprintf( 'The database refused the renewal of this call\'s lock (%s)', $wpdb->last_error )
            : sprintf( 'Another %s call took this call\'s lock over (possible only after this call held it for %d seconds)', $holder, $ttl );
    }

    private static function failed(): bool {
        global $wpdb;
        return '' !== (string) ( $wpdb->last_error ?? '' );
    }

    public static function renew( string $name, string $token ): string {
        global $wpdb;
        $now  = time();
        $next = $now . ':' . bin2hex( random_bytes( 8 ) );
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            wp_cache_delete( $name, 'options' );
            return get_option( $name ) === $token && update_option( $name, $next, false ) ? $next : '';
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic check-and-renew cannot go through the options cache.
        $won = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $next, $name, $token ) );
        wp_cache_delete( $name, 'options' );
        return 1 === $won ? $next : '';
    }

    public static function holds( string $name, string $token ): bool {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) {
            wp_cache_delete( $name, 'options' );
            return get_option( $name ) === $token;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the lock row as stored now, not as cached.
        return (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) ) === $token;
    }

    public static function release( string $name, string $token ): void {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            if ( get_option( $name ) === $token ) {
                delete_option( $name );
            }
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- releases only the row this request wrote.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, $token ) );
        wp_cache_delete( $name, 'options' );
        wp_cache_delete( 'notoptions', 'options' );
    }
}
