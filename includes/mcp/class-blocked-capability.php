<?php

namespace Easy_MCP_AI\MCP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Blocked_Capability {

    const UNFILTERED_HTML = array( 'edit_css', 'unfiltered_html' );

    const FILE_MODS = array(
        'update_plugins',
        'delete_plugins',
        'install_plugins',
        'upload_plugins',
        'update_themes',
        'delete_themes',
        'install_themes',
        'upload_themes',
        'update_core',
    );

    const SUPER_ADMIN_ONLY = array(
        'edit_css',
        'unfiltered_html',
        'update_plugins',
        'delete_plugins',
        'install_plugins',
        'upload_plugins',
        'update_themes',
        'delete_themes',
        'install_themes',
        'upload_themes',
        'update_core',
        'delete_user',
        'delete_users',
    );

    public static function cause( string $capability ): ?string {
        $user_id = \get_current_user_id();
        if ( ! \in_array( 'do_not_allow', (array) \map_meta_cap( $capability, $user_id ), true ) ) {
            return null;
        }
        if ( \in_array( $capability, self::UNFILTERED_HTML, true ) && \defined( 'DISALLOW_UNFILTERED_HTML' ) && \DISALLOW_UNFILTERED_HTML ) {
            return 'no account on this site can have while DISALLOW_UNFILTERED_HTML is set in wp-config.php, administrators included, unless the site\'s own code allows it; only the site owner can change that';
        }
        if ( \in_array( $capability, self::FILE_MODS, true ) && ! \wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            return \defined( 'DISALLOW_FILE_MODS' ) && \DISALLOW_FILE_MODS
                ? 'no account on this site can have while DISALLOW_FILE_MODS is set in wp-config.php, administrators included, unless the site\'s own code allows it; only the site owner can change that'
                : null;
        }
        if ( ! \is_multisite() || \is_super_admin( $user_id ) ) {
            return null;
        }
        if ( \in_array( $capability, self::SUPER_ADMIN_ONLY, true ) ) {
            return 'on this multisite network only a super admin has, not a site administrator, unless the site\'s own code allows it';
        }
        if ( 'create_users' === $capability && ! \get_site_option( 'add_new_users' ) ) {
            return 'on this multisite network only a super admin has, unless the network\'s settings let site administrators add new users (Network Admin › Settings) or the site\'s own code allows it';
        }
        return null;
    }
}
