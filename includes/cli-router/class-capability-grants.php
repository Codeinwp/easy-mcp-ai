<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

























final class Capability_Grants {

    






    const NETWORK_CAPS = array( 'create_sites', 'delete_sites', 'manage_network', 'manage_sites', 'manage_network_users', 'manage_network_plugins', 'manage_network_themes', 'manage_network_options', 'upgrade_network', 'setup_network' );

    
    public static function is_network_cap( string $cap ): bool {
        return in_array( $cap, self::NETWORK_CAPS, true );
    }

    









    public static function holds( string $cap ): bool {
        if ( \is_multisite() && \is_super_admin() ) {
            return true;
        }
        $user    = function_exists( 'get_userdata' ) ? \get_userdata( (int) \get_current_user_id() ) : false;
        $allcaps = ( is_object( $user ) && isset( $user->allcaps ) && is_array( $user->allcaps ) ) ? $user->allcaps : array();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's filter, applied as WP_User::has_cap() applies it.
        $allcaps = \apply_filters( 'user_has_cap', $allcaps, array( $cap ), array( $cap, (int) \get_current_user_id() ), $user );
        return is_array( $allcaps ) && ! empty( $allcaps[ $cap ] );
    }

    





    public static function refusal( string $cap ): ?string {
        if ( self::is_network_cap( $cap ) && ! \is_super_admin() ) {
            return 'network';
        }
        if ( ! self::holds( $cap ) ) {
            return 'not_held';
        }
        return null;
    }
}
