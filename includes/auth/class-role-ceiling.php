<?php
namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Role_Ceiling {

    const ACCOUNT_CAPS = array( 'edit_user', 'promote_user', 'delete_user', 'remove_user' );

    private static $allowed = array();

    private static $record = array();

    public static function apply( int $user_id, array $roles ): void {
        $site_roles = Connect_Policy::site_roles();
        $allowed    = array( 'exist' => true );
        foreach ( $roles as $slug ) {
            foreach ( isset( $site_roles[ $slug ] ) ? $site_roles[ $slug ] : array() as $cap => $grant ) {
                if ( $grant ) {
                    $allowed[ (string) $cap ] = true;
                }
            }
        }
        self::$allowed[ $user_id ] = $allowed;
        self::$record[ $user_id ]  = array_fill_keys( $roles, true );
        if ( false === \has_filter( 'map_meta_cap', array( self::class, 'filter' ) ) ) {
            \add_filter( 'map_meta_cap', array( self::class, 'filter' ), PHP_INT_MAX, 4 );
        }
        self::refresh_user_setup( $user_id );
    }

    public static function lift( int $user_id ): void {
        unset( self::$allowed[ $user_id ], self::$record[ $user_id ] );
        self::refresh_user_setup( $user_id );
    }

    public static function while_limited( int $user_id, array $roles, callable $callback ) {
        self::apply( $user_id, $roles );
        try {
            return $callback();
        } finally {
            self::lift( $user_id );
        }
    }

    private static function refresh_user_setup( int $user_id ): void {
        if ( (int) \get_current_user_id() === $user_id && function_exists( 'kses_init' ) ) {
            \kses_init();
        }
    }

    public static function permits( int $user_id, string $cap ): bool {
        return ! isset( self::$allowed[ $user_id ] ) || isset( self::$allowed[ $user_id ][ $cap ] );
    }

    public static function filter( $caps, $cap, $user_id, $args = array() ) {
        $user_id = (int) $user_id;
        if ( ! isset( self::$allowed[ $user_id ] ) || ! is_array( $caps ) ) {
            return $caps;
        }
        if ( in_array( $cap, self::ACCOUNT_CAPS, true ) && isset( $args[0] ) && (int) $args[0] === $user_id ) {
            return array( 'do_not_allow' );
        }
        if ( \is_multisite() && \is_super_admin( $user_id ) && ! self::site_user_can( (string) $cap, $user_id, (array) $args ) ) {
            return array( 'do_not_allow' );
        }
        foreach ( $caps as $mapped ) {
            if ( ! isset( self::$allowed[ $user_id ][ (string) $mapped ] ) ) {
                return array( 'do_not_allow' );
            }
        }
        return $caps;
    }

    private static function site_user_can( string $cap, int $user_id, array $args ): bool {
        global $super_admins;
        $user    = \get_userdata( $user_id );
        $record  = self::record_for( $user_id, $user->cap_key );
        $holds   = static function ( $value, $object_id, $meta_key ) use ( $user_id, $user, $record ) {
            return (int) $object_id === $user_id && $user->cap_key === $meta_key ? array( $record ) : $value;
        };
        $current = \wp_get_current_user();
        $saved   = $current instanceof \WP_User && (int) $current->ID === $user_id ? array(
            'site_id' => (int) $current->get_site_id(),
            'cap_key' => $current->cap_key,
            'caps'    => $current->caps,
            'allcaps' => $current->allcaps,
            'roles'   => $current->roles,
        ) : null;
        $before  = $super_admins;

        $super_admins = array_values( array_diff( (array) \get_super_admins(), array( $user->user_login ) ) );
        \add_filter( 'get_user_metadata', $holds, PHP_INT_MAX, 3 );
        try {
            if ( null !== $saved ) {
                $current->for_site( $saved['site_id'] );
            }
            return \user_can( $user_id, $cap, ...array_values( $args ) );
        } finally {
            \remove_filter( 'get_user_metadata', $holds, PHP_INT_MAX );
            $super_admins = $before;
            if ( null !== $saved ) {
                self::put_back( $current, $saved );
            }
        }
    }

    private static function record_for( int $user_id, string $cap_key ): array {
        $own    = \get_user_meta( $user_id, $cap_key, true );
        $roles  = \wp_roles();
        $denied = array();
        foreach ( is_array( $own ) ? $own : array() as $key => $grant ) {
            if ( ! $grant && ! $roles->is_role( (string) $key ) ) {
                $denied[ (string) $key ] = false;
            }
        }
        return self::$record[ $user_id ] + $denied;
    }

    private static function put_back( \WP_User $current, array $saved ): void {
        if ( (int) $current->get_site_id() !== $saved['site_id'] ) {
            $current->for_site( $current->get_site_id() );
            return;
        }
        $current->cap_key = $saved['cap_key'];
        $current->caps    = $saved['caps'];
        $current->allcaps = $saved['allcaps'];
        $current->roles   = $saved['roles'];
    }
}
