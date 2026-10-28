<?php

namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Vendor_Calls {

    public function function_exists( string $name ): bool {
        return function_exists( $name );
    }

    public function class_exists( string $name, bool $autoload = true ): bool {
        return class_exists( $name, $autoload );
    }

    public function method_exists( $object_or_class, string $method ): bool {
        return ( is_object( $object_or_class ) || ( is_string( $object_or_class ) && class_exists( $object_or_class ) ) ) && method_exists( $object_or_class, $method );
    }

    public function defined( string $name ): bool {
        return defined( $name );
    }

    public function constant( string $name ) {
        return defined( $name ) ? constant( $name ) : null;
    }

    public function call( $callback, array $args = array() ) {
        return call_user_func_array( $callback, $args );
    }

    public function instantiate( string $class_name, array $args = array() ) {
        return new $class_name( ...$args );
    }

    public function global_value( string $name ) {
        return isset( $GLOBALS[ $name ] ) ? $GLOBALS[ $name ] : null;
    }

    public function do_action( string $hook, ...$args ): void {
        \do_action( $hook, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- third-party hooks owned by the cache vendors.
    }

    public function has_action( string $hook, $callback = false ) {
        return \has_action( $hook, $callback );
    }

    public function did_action( string $hook ): int {
        return (int) \did_action( $hook );
    }

    public function apply_filters( string $hook, $value, ...$args ) {
        return \apply_filters( $hook, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- third-party hooks owned by the cache vendors.
    }

    public function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): void {
        \add_filter( $hook, $callback, $priority, $args );
    }

    public function remove_filter( string $hook, $callback, int $priority = 10 ): void {
        \remove_filter( $hook, $callback, $priority );
    }

    public function get_option( string $name, $default = false ) {
        return \get_option( $name, $default );
    }

    public function current_user_can( string $capability, ...$args ): bool {
        return (bool) \current_user_can( $capability, ...$args );
    }

    public function current_user_has_role( string $role ): bool {
        $user = \wp_get_current_user();
        return is_object( $user ) && isset( $user->roles ) && in_array( $role, (array) $user->roles, true );
    }

    public function current_user_roles(): array {
        $user = \wp_get_current_user();
        return is_object( $user ) && isset( $user->roles ) ? array_values( array_map( 'strval', (array) $user->roles ) ) : array();
    }

    public function is_multisite(): bool {
        return \is_multisite();
    }

    public function is_super_admin(): bool {
        return \is_super_admin();
    }

    public function using_ext_object_cache(): bool {
        return (bool) \wp_using_ext_object_cache();
    }

    public function flush_object_cache(): bool {
        return (bool) \wp_cache_flush();
    }

    public function server( string $key ): string {
        return isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) ? \wp_unslash( $_SERVER[ $key ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared, never output or stored.
    }

    public function headers_list(): array {
        return array_values( headers_list() );
    }

    public function headers_sent(): bool {
        return headers_sent();
    }

    public function file_exists( string $path ): bool {
        return file_exists( $path );
    }

    public function is_dir( string $path ): bool {
        return is_dir( $path );
    }

    public function glob( string $pattern ): array {
        $found = glob( $pattern );
        return is_array( $found ) ? array_values( $found ) : array();
    }

    public function find_files( string $dir, array $patterns ): array {
        if ( '' === $dir || ! is_dir( $dir ) ) {
            return array();
        }
        $found    = array();
        $iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
        foreach ( $iterator as $file ) {
            if ( ! $file->isFile() ) {
                continue;
            }
            foreach ( $patterns as $pattern ) {
                if ( fnmatch( $pattern, $file->getFilename() ) ) {
                    $found[] = $file->getPathname();
                    break;
                }
            }
        }
        return $found;
    }

    public function now(): float {
        return microtime( true );
    }
}
