<?php








































namespace Easy_MCP_AI\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Connect_Policy {

    const CHANNEL_OAUTH     = 'oauth';
    const CHANNEL_DEVICE    = 'device';
    const CHANNEL_SELF_KEY  = 'self_service_key';
    const CHANNEL_ADMIN_KEY = 'admin_key';

    
    const FLOOR_OPTION = 'easy_mcp_ai_oauth_min_capability';

    const DEFAULT_FLOOR = 'publish_posts';

    





    const FLOOR_CAPABILITIES = array( 'publish_posts', 'edit_others_posts', 'manage_options' );

    
    const ALWAYS_CAPABILITY = 'manage_options';

    

    





    public static function current_user_can_connect( string $channel ): bool {
        if ( ! \get_current_user_id() ) {
            return false;
        }
        $floor = self::channel_floor( $channel );
        return self::decide( \current_user_can( $floor['capability'] ), \current_user_can( self::ALWAYS_CAPABILITY ), $floor['filtered'], \wp_get_current_user(), $channel );
    }

    






    public static function user_can_connect( $user, string $channel ): bool {
        if ( is_numeric( $user ) ) {
            $user = (int) $user > 0 ? \get_userdata( (int) $user ) : false;
        }
        if ( ! is_object( $user ) || empty( $user->ID ) ) {
            return false;
        }
        $floor = self::channel_floor( $channel );
        return self::decide( (bool) \user_can( $user, $floor['capability'] ), (bool) \user_can( $user, self::ALWAYS_CAPABILITY ), $floor['filtered'], $user, $channel );
    }

    





    private static function channel_floor( string $channel ): array {
        $stored   = self::CHANNEL_ADMIN_KEY === $channel ? self::DEFAULT_FLOOR : self::stored_floor();
        $filtered = \apply_filters( self::FLOOR_OPTION, $stored );
        return array(
            
            
            'capability' => is_string( $filtered ) && '' !== $filtered ? $filtered : 'do_not_allow',
            'filtered'   => $filtered !== $stored,
        );
    }

    







    private static function decide( bool $passes_floor, bool $holds_always, bool $filtered, $user, string $channel ): bool {
        $allowed = $passes_floor || ( $holds_always && ! $filtered );
        if ( ! $allowed && ! $filtered ) {
            $roles   = isset( $user->roles ) && is_array( $user->roles ) ? $user->roles : array();
            $allowed = ! empty( array_intersect( $roles, self::extra_roles() ) );
        }
        






        return (bool) \apply_filters( 'easy_mcp_ai_user_can_connect', $allowed, $user, $channel );
    }

    

    







    public static function stored_floor(): string {
        $stored = \Easy_MCP_AI\Config::get( self::FLOOR_OPTION, self::DEFAULT_FLOOR );
        return is_string( $stored ) && in_array( $stored, self::FLOOR_CAPABILITIES, true ) ? $stored : self::DEFAULT_FLOOR;
    }

    





    public static function floor_capability(): string {
        return self::channel_floor( self::CHANNEL_OAUTH )['capability'];
    }

    





    public static function admin_key_capability(): string {
        return self::channel_floor( self::CHANNEL_ADMIN_KEY )['capability'];
    }

    








    public static function filter_overrides( string $floor ): bool {
        return \apply_filters( self::FLOOR_OPTION, $floor ) !== $floor;
    }

    






    public static function is_filtered_for( string $channel ): bool {
        return self::channel_floor( $channel )['filtered'];
    }

    






    public static function extra_roles(): array {
        if ( \Easy_MCP_AI\Config::is_locked( 'oauth_min_capability' ) && ! \Easy_MCP_AI\Config::is_locked( 'connect_roles' ) ) {
            return array();
        }
        $stored = \Easy_MCP_AI\Config::get( 'connect_roles' );
        if ( ! is_array( $stored ) ) {
            return array();
        }
        return array_values( array_filter( array_keys( self::site_roles() ), static function ( $role ) use ( $stored ) {
            return in_array( $role, $stored, true );
        } ) );
    }

    

    




    private static function site_roles(): array {
        $registry = \wp_roles();
        $roles    = isset( $registry->roles ) && is_array( $registry->roles ) ? $registry->roles : array();
        $out      = array();
        foreach ( $roles as $slug => $role ) {
            $out[ (string) $slug ] = isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ? $role['capabilities'] : array();
        }
        return $out;
    }

    





    public static function roles_granting( string $capability ): array {
        $out = array();
        foreach ( self::site_roles() as $slug => $capabilities ) {
            if ( ! empty( $capabilities[ $capability ] ) ) {
                $out[] = $slug;
            }
        }
        return $out;
    }

    






    public static function ticked_roles(): array {
        $floor  = self::channel_floor( self::CHANNEL_OAUTH );
        $ticked = self::roles_granting( $floor['capability'] );
        if ( ! $floor['filtered'] ) {
            $ticked = array_merge( $ticked, self::roles_granting( self::ALWAYS_CAPABILITY ), self::extra_roles() );
        }
        return array_values( array_filter( array_keys( self::site_roles() ), static function ( $role ) use ( $ticked ) {
            return in_array( $role, $ticked, true );
        } ) );
    }

    















    public static function split_ticks( array $ticked ): array {
        $always = self::roles_granting( self::ALWAYS_CAPABILITY );
        foreach ( self::FLOOR_CAPABILITIES as $capability ) {
            $holders = self::roles_granting( $capability );
            if ( $holders && ! array_diff( $holders, $ticked ) ) {
                return array( $capability, array_values( array_diff( $ticked, $holders, $always ) ) );
            }
        }
        return array( self::ALWAYS_CAPABILITY, array_values( array_diff( $ticked, $always ) ) );
    }

    








    public static function role_choices(): array {
        $registry = \wp_roles();
        $names    = isset( $registry->role_names ) && is_array( $registry->role_names ) ? $registry->role_names : array();
        $signup   = \get_option( 'users_can_register' ) ? (string) \get_option( 'default_role', '' ) : '';
        $rows     = array();
        foreach ( self::site_roles() as $slug => $capabilities ) {
            $rows[] = array(
                'value'  => $slug,
                'label'  => isset( $names[ $slug ] ) ? \translate_user_role( (string) $names[ $slug ] ) : $slug,
                'always' => ! empty( $capabilities[ self::ALWAYS_CAPABILITY ] ),
                'signup' => '' !== $signup && $signup === $slug,
            );
        }
        return $rows;
    }
}
