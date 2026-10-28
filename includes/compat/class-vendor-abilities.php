<?php

namespace Easy_MCP_AI\Compat;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class Vendor_Abilities {

    const GROUP = '';

    const PREFIX = '';

    const VENDOR = '';

    const ENABLE_PENDING_OPTION = '';

    const ENABLED_ABILITIES_OPTION = 'easy_mcp_ai_enabled_abilities';

    const TICK_LOCK = 'easy_mcp_ai_abilities_tick_lock';

    const TICK_LOCK_TTL = 30;

    private static $failed = array();

    public static function group_enabled(): bool {
        return \Easy_MCP_AI\Meta\Meta_Exposure::is_group_enabled( static::GROUP );
    }

    public static function group_changed( $old, $new ): void {
        $was = is_array( $old ) && in_array( static::GROUP, $old, true );
        $is  = is_array( $new ) && in_array( static::GROUP, $new, true );
        if ( ! $was && $is ) {
            update_option( static::ENABLE_PENDING_OPTION, 1 );
        } elseif ( $was && ! $is ) {
            delete_option( static::ENABLE_PENDING_OPTION );
        }
    }

    public static function enable_pending(): void {
        if ( ! get_option( static::ENABLE_PENDING_OPTION ) || ! static::group_enabled() || ! function_exists( 'wp_get_abilities' ) ) {
            return;
        }
        $names = static::registered_names();
        if ( empty( $names ) ) {
            return;
        }
        $lock = self::lock();
        if ( '' === $lock ) {
            return;
        }
        try {
            if ( ! delete_option( static::ENABLE_PENDING_OPTION ) ) {
                return;
            }
            $write = static function () use ( $names ) {
                $enabled = self::stored_enabled();
                update_option( self::ENABLED_ABILITIES_OPTION, array_values( array_unique( array_merge( $enabled, $names ) ) ) );
            };
            if ( class_exists( '\\Easy_MCP_AI\\MCP\\Option_Guard' ) ) {
                \Easy_MCP_AI\MCP\Option_Guard::while_suspended( $write );
                return;
            }
            $write();
        } finally {
            self::unlock( $lock );
        }
    }

    private static function stored_enabled(): array {
        \wp_cache_delete( self::ENABLED_ABILITIES_OPTION, 'options' );
        \wp_cache_delete( 'notoptions', 'options' );
        \wp_cache_delete( 'alloptions', 'options' );
        $enabled = get_option( self::ENABLED_ABILITIES_OPTION, array() );
        return is_array( $enabled ) ? $enabled : array();
    }

    private static function lock(): string {
        global $wpdb;
        $now   = time();
        $token = $now . ':' . bin2hex( random_bytes( 8 ) );
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            return \add_option( self::TICK_LOCK, $token, '', false ) ? $token : '';
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- an atomic lock cannot go through the options cache.
        $won = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", self::TICK_LOCK, $token ) );
        if ( 1 !== $won ) {
            $held = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::TICK_LOCK ) );
            $time = (int) $held;
            if ( $time > 0 && $now - $time < self::TICK_LOCK_TTL ) {
                return '';
            }
            $won = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $token, self::TICK_LOCK, $held ) );
        }
        // phpcs:enable
        \wp_cache_delete( self::TICK_LOCK, 'options' );
        return 1 === $won ? $token : '';
    }

    private static function unlock( string $token ): void {
        global $wpdb;
        if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'query' ) ) {
            if ( get_option( self::TICK_LOCK ) === $token ) {
                delete_option( self::TICK_LOCK );
            }
            return;
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- releases only the row this request wrote.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", self::TICK_LOCK, $token ) );
        \wp_cache_delete( self::TICK_LOCK, 'options' );
        \wp_cache_delete( 'notoptions', 'options' );
    }

    public static function reset(): void {
        unset( self::$failed[ static::class ] );
    }

    protected static function active(): bool {
        return ! isset( self::$failed[ static::class ] ) && static::group_enabled();
    }

    protected static function any_registered(): bool {
        return array() !== static::registered_names();
    }

    protected static function registered_names(): array {
        $names = array();
        foreach ( (array) wp_get_abilities() as $ability ) {
            $name = is_object( $ability ) && method_exists( $ability, 'get_name' ) ? (string) $ability->get_name() : '';
            if ( 0 === strpos( $name, static::PREFIX ) ) {
                $names[] = $name;
            }
        }
        return $names;
    }

    protected static function run( callable $registration, string $what ): void {
        try {
            $registration();
        } catch ( \Throwable $e ) {
            self::$failed[ static::class ] = true;
            error_log( sprintf( 'Easy MCP AI: %s %s failed, %s abilities not registered: %s', static::VENDOR, $what, static::VENDOR, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional: a vendor change must be visible
        }
    }

    protected static function has_method( string $class, string $method, bool $static ): bool {
        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            return false;
        }
        $reflection = new \ReflectionMethod( $class, $method );
        return $reflection->isPublic() && $static === $reflection->isStatic() && 0 === $reflection->getNumberOfRequiredParameters();
    }
}
