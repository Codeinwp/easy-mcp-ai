<?php
namespace Easy_MCP_AI\Tools\Site_Health;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










class Site_Health_Guard {

    


















    public static function require_network_admin_on_multisite( $what ) {
        if ( ! function_exists( 'is_multisite' ) || ! \is_multisite() ) {
            return;
        }
        if ( \is_super_admin() && \current_user_can( 'manage_network_options' ) ) {
            return;
        }
        throw new \RuntimeException( sprintf( 'On a multisite network only a network administrator (super admin) can %s: it affects every site on the network.', $what ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    








    public static function flag( array $arguments, $name, $default ) {
        if ( ! array_key_exists( $name, $arguments ) || null === $arguments[ $name ] ) {
            return (bool) $default;
        }
        $v = $arguments[ $name ];
        if ( is_string( $v ) ) {
            return ! in_array( strtolower( trim( $v ) ), array( '', '0', 'false', 'no', 'off' ), true );
        }
        return (bool) $v;
    }

    








    public static function cron_events() {
        $crons = function_exists( '_get_cron_array' ) ? \_get_cron_array() : array();
        if ( ! is_array( $crons ) ) {
            return array();
        }
        $rows = array();
        foreach ( $crons as $timestamp => $hooks ) {
            if ( ! is_numeric( $timestamp ) || ! is_array( $hooks ) ) {
                continue; 
            }
            foreach ( $hooks as $hook => $instances ) {
                if ( ! is_array( $instances ) ) {
                    continue;
                }
                foreach ( $instances as $key => $event ) {
                    if ( ! is_array( $event ) ) {
                        continue;
                    }
                    $rows[] = array(
                        'timestamp' => (int) $timestamp,
                        'hook'      => (string) $hook,
                        'key'       => (string) $key,
                        
                        
                        'schedule'  => ! empty( $event['schedule'] ) && is_string( $event['schedule'] ) ? $event['schedule'] : false,
                        'interval'  => isset( $event['interval'] ) ? (int) $event['interval'] : 0,
                        'args'      => isset( $event['args'] ) && is_array( $event['args'] ) ? $event['args'] : array(),
                    );
                }
            }
        }
        usort( $rows, static function ( $a, $b ) {
            return $a['timestamp'] <=> $b['timestamp'];
        } );
        return $rows;
    }

    







    public static function describe_schedule( $schedule, $interval ) {
        if ( false === $schedule ) {
            return null;
        }
        $all     = function_exists( 'wp_get_schedules' ) ? \wp_get_schedules() : array();
        $known   = isset( $all[ $schedule ] ) && is_array( $all[ $schedule ] );
        $seconds = $known && isset( $all[ $schedule ]['interval'] ) ? (int) $all[ $schedule ]['interval'] : (int) $interval;
        return array(
            'name'             => (string) $schedule,
            'interval_seconds' => $seconds,
            'display'          => $known && isset( $all[ $schedule ]['display'] ) ? (string) $all[ $schedule ]['display'] : null,
            'registered'       => $known,
        );
    }

    





    public static function iso_utc( $ts ) {
        if ( null === $ts || false === $ts || ! is_numeric( $ts ) ) {
            return null;
        }
        return gmdate( 'Y-m-d\TH:i:s\Z', (int) $ts );
    }

    







    public static function display_path( $path ) {
        $path = str_replace( '\\', '/', (string) $path );
        $root = rtrim( str_replace( '\\', '/', (string) ABSPATH ), '/' ) . '/';
        if ( '' !== $root && '/' !== $root && 0 === strpos( $path, $root ) ) {
            return substr( $path, strlen( $root ) );
        }
        return basename( $path );
    }
}
