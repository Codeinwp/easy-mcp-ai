<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















































final class Self_Protection {

    
    const LABEL = 'Self-protection: ';

    





    const NAMESPACE_PATTERN = '/^_?easy[_-]mcp[_-]ai(?:[_-]|\z)/';

    
    const TRANSIENT_STORAGE_PREFIXES = array( '_site_transient_', '_transient_' );

    
    const SDK_OPTION_SUFFIXES = array( 'install', 'logger_flag', 'review_flag', 'translate_flag', 'welcome_upsell_flag', 'crash_data', 'ran_migrations', 'migrated_version' );

    
    const SDK_TRANSIENT_SUFFIXES = array( 'crash_backoff' );

    
    const SDK_CRON_SUFFIXES = array( 'log_activity', 'crash_flush' );

    
    const SPECIAL_OPTIONS = array( 'alloptions', 'notoptions' );

    














    const CORE_WRITE_OPTIONS = array(
        'siteurl'                 => 'changing the WordPress address moves the REST endpoint this MCP connection uses. Use `search-replace` (it rewrites every stored URL and writes siteurl and home last), or Settings › General in wp-admin',
        'home'                    => 'changing the site address moves the REST endpoint this MCP connection uses. Use `search-replace` (it rewrites every stored URL and writes siteurl and home last), or Settings › General in wp-admin',
        'active_plugins'          => 'it is the list of active plugins, this one included. Use `plugin activate` and `plugin deactivate`',
        'template'                => 'it selects the active theme. Use `theme activate`',
        'stylesheet'              => 'it selects the active theme. Use `theme activate`',
        'default_role'            => 'it is the role every new account receives. Change it under Settings › General in wp-admin; give one user a role with `user set-role`',
        'users_can_register'      => 'it opens public registration. Change it under Settings › General in wp-admin; add one account with `user create`',
        'admin_email'             => 'WordPress confirms a new administration email address by mail before using it. Change it under Settings › General in wp-admin',
        'cron'                    => 'it holds every scheduled event, this plugin\'s included. Use `cron event schedule`, `cron event unschedule` and `cron event delete`',
        'rewrite_rules'           => 'WordPress regenerates it from the permalink structure. Use `rewrite flush` or `rewrite structure`',
        'permalink_structure'     => 'an empty structure (Plain) leaves WordPress without rewrite rules, so the /wp-json/ routes this MCP connection uses stop resolving, and core\'s sanitizing turns more values than \'\' into Plain. Use `rewrite structure`, which refuses Plain and rebuilds the rules',
        'db_version'              => 'WordPress compares it with its own version to run database upgrades. Use `core update-db`',
        'initial_db_version'      => 'WordPress compares it with its own version to run database upgrades. Use `core update-db`',
        'uninstall_plugins'       => 'it registers the uninstall callbacks of plugins. Use `plugin uninstall`',
        'recently_activated'      => 'WordPress records recently deactivated plugins there. Use `plugin activate` and `plugin deactivate`',
        'auto_update_plugins'     => 'it lists the plugins that update automatically. Use `plugin auto-updates enable` and `plugin auto-updates disable`',
        'auto_update_themes'      => 'it lists the themes that update automatically. Use `theme auto-updates enable` and `theme auto-updates disable`',
        'site_admins'             => 'it lists the super admins of the network. Use `super-admin add` and `super-admin remove`',
        'active_sitewide_plugins' => 'it lists the network-activated plugins. Use `plugin activate --network` and `plugin deactivate --network`',
    );

    
    const ROLE_OPTION_REASON = 'it holds every role and its capabilities. Use `role create`, `role delete`, `role reset`, `cap add` and `cap remove`';

    





    const UPDATE_TRANSIENTS = array(
        'update_core'    => 'WordPress fills it from WordPress.org, and the package it names is what a core update installs. Use `core check-update` to refresh it and `core update` to install',
        'update_plugins' => 'WordPress fills it from WordPress.org, and the package each entry names is what a plugin update installs. Use `plugin list` to refresh it and `plugin update` to install',
        'update_themes'  => 'WordPress fills it from WordPress.org, and the package each entry names is what a theme update installs. Use `theme list` to refresh it and `theme update` to install',
    );

    








    const SECRET_OPTIONS = array(
        'auth_key'                => 'a secret key that signs login cookies',
        'auth_salt'               => 'a salt that signs login cookies',
        'secure_auth_key'         => 'a secret key that signs secure login cookies',
        'secure_auth_salt'        => 'a salt that signs secure login cookies',
        'logged_in_key'           => 'a secret key that signs logged-in cookies',
        'logged_in_salt'          => 'a salt that signs logged-in cookies',
        'nonce_key'               => 'a secret key that signs nonces',
        'nonce_salt'              => 'a salt that signs nonces',
        'secret_key'              => 'the secret key WordPress signs every other hash with',
        'recovery_mode_auth_key'  => 'the secret key that signs the recovery-mode cookie',
        'recovery_mode_auth_salt' => 'the salt that signs the recovery-mode cookie',
        'recovery_keys'           => 'the keys of the recovery-mode links WordPress emails after a fatal error',
    );

    






    const CACHE_GROUPS = array(
        'easy_mcp_ai'    => 'it holds the validated API-key rows of the plugin that serves this MCP connection and its rate-limit and lockout counters. They expire on their own; the plugin\'s Connections and Settings screens in wp-admin change what they hold',
        'options'        => 'it holds every option, this plugin\'s settings and credentials included. Use `option get` and `option update`, which apply their own checks',
        'site-options'   => 'it holds every network option. Use `site option get` and `site option update`, which apply their own checks',
        'transient'      => 'it holds the transients, the MCP sessions included. Use `transient get`, `transient set` and `transient delete`, which apply their own checks',
        'site-transient' => 'it holds the site transients, among them the update offers. Use `transient get --network` and `transient set --network`, which apply their own checks',
        'users'          => 'it holds user rows, password hashes included. Use `user get` and `user list`',
        'userlogins'     => 'it maps logins to user rows, password hashes included. Use `user get` and `user list`',
        'useremail'      => 'it maps email addresses to user rows, password hashes included. Use `user get` and `user list`',
        'userslugs'      => 'it maps user slugs to user rows, password hashes included. Use `user get` and `user list`',
        'user_meta'      => 'it holds every user\'s meta, session tokens and application passwords included. Use `user meta get` and `user meta list`',
    );

    
    const USER_META_KEYS = array(
        'session_tokens'         => 'it holds every login session of the user. Use `user session list` and `user session destroy`',
        '_application_passwords' => 'it holds the application passwords of the user. Use `user application-password list`, `user application-password update` and `user application-password delete`',
    );

    
    const HISTORY_CAPABILITY = 'easy_mcp_ai_view_all_history';

    
    const PRIVILEGED_CAPABILITIES = array( 'easy_mcp_ai_read_files', 'easy_mcp_ai_run_wp_cli', 'easy_mcp_ai_execute_php', 'easy_mcp_ai_search_replace' );

    
    const OWN_COMMAND = 'easy-mcp-ai';

    
    const OWN_SUBCOMMANDS = array(
        'config export'     => "'easy-mcp-ai config export' prints the settings of the plugin that serves this MCP connection, which wp_cli_run never reads. Run it with the real wp binary on the server, or read the settings on the plugin's Settings screen in wp-admin.",
        'privileged status' => "'easy-mcp-ai privileged status' reports the privileged-tool switches of the plugin that serves this MCP connection, which wp_cli_run never reads. Run it with the real wp binary on the server, or see Settings › Advanced in wp-admin.",
    );

    
    const REASON_PLUGIN_OPTION       = "'%s' belongs to the plugin that serves this MCP connection, and wp_cli_run never reads or writes that plugin's settings or state. Change them on the plugin's screens in wp-admin.";
    const REASON_PLUGIN_TRANSIENT    = "the transient '%s' belongs to the plugin that serves this MCP connection (MCP sessions, rate-limit and lockout counters, one-time keys), and wp_cli_run never reads or writes it. It expires on its own; the plugin's Connections and Settings screens in wp-admin change what it holds.";
    const REASON_PLUGIN_TRANSIENT_AT = "'%s' stores a transient of the plugin that serves this MCP connection (MCP sessions, rate-limit and lockout counters, one-time keys), and wp_cli_run never reads or writes it. It expires on its own; the plugin's Connections and Settings screens in wp-admin change what it holds.";
    const REASON_SPECIAL_OPTION      = "'%s' is not an option but WordPress's in-memory cache of options, and WordPress stops any request that writes it. Use `option list` (with --autoload=on for the autoloaded options) to read options.";
    const REASON_CORE_WRITE          = "wp_cli_run does not write the '%s' option: %s.";
    const REASON_UPDATE_TRANSIENT    = "wp_cli_run does not write the site transient '%s': %s.";
    const REASON_UPDATE_TRANSIENT_AT = "wp_cli_run does not write '%s', which stores the %s site transient: %s.";
    const REASON_SECRET_OPTION       = "wp_cli_run never prints '%s': it is %s. WP-CLI's config shuffle-salts, which rotates the salts, runs only outside wp_cli_run.";
    const REASON_OPTION_BYTES        = 'wp_cli_run refuses an option or transient name containing a space, a control character or a non-ASCII byte: the options table compares names without regard to case, accents or trailing spaces, so such a name can address a different row, this plugin\'s among them. Pass the name in plain ASCII, as `option list` prints it.';
    const REASON_CACHE_GROUP         = "wp_cli_run does not touch the object-cache group '%s': %s.";
    const REASON_CACHE_BYTES         = 'wp_cli_run refuses an object-cache group containing a space, a control character or a non-ASCII byte: several object-cache backends strip or rewrite such bytes, so the group can address a protected one. Pass the group in plain ASCII.';
    const REASON_CRON_HOOK           = "'%s' is a scheduled task of the plugin that serves this MCP connection; the plugin runs and reschedules it itself, and wp_cli_run never runs, schedules or removes it. `cron event list` shows when it runs next.";
    const REASON_CRON_BYTES          = 'wp_cli_run refuses a cron hook name containing a control character or a non-ASCII byte for this command. Pass the hook in plain ASCII, as `cron event list` prints it.';
    const REASON_PLUGIN              = "'%s' is the plugin that serves this MCP connection; deactivating, deleting, uninstalling, updating or reinstalling it through wp_cli_run would cut the connection off in the middle of the request. Do it from the Plugins screen in wp-admin.";
    const REASON_PLUGIN_BYTES        = 'wp_cli_run refuses a plugin name containing a control character or a non-ASCII byte for this command: on a case-insensitive file system such a name can reach the folder of the plugin that serves this MCP connection. Pass the plugin in plain ASCII, as `plugin list` prints it.';
    const REASON_PLUGIN_ESCAPES      = "'%s' points outside the plugins folder, so wp_cli_run cannot rule out that it names the plugin that serves this MCP connection. Pass the plugin as `plugin list` prints it.";
    const REASON_PLUGIN_UNKNOWN      = 'wp_cli_run cannot tell which plugin serves this MCP connection (EASY_MCP_AI_PLUGIN_BASENAME is not defined), so it refuses this command for every plugin. Use the Plugins screen in wp-admin.';
    const REASON_PLUGIN_USER_META    = "the user meta key '%s' belongs to the plugin that serves this MCP connection (a newly created API key shown once, or a saved key form), and wp_cli_run never reads or writes it. API keys are managed on the plugin's Connections screen in wp-admin.";
    const REASON_CORE_USER_META      = "wp_cli_run does not read or write the user meta key '%s' directly: %s.";
    const REASON_USER_META_BYTES     = 'wp_cli_run refuses a user meta key containing a space, a control character or a non-ASCII byte: the usermeta table compares keys without regard to case, accents or trailing spaces, so such a key can address a different one, this plugin\'s among them. Pass the key in plain ASCII, as `user meta list` prints it.';
    const REASON_HISTORY_CAPABILITY  = "wp_cli_run never grants or removes '%s': the plugin that serves this MCP connection gives it to administrators on activation, and it lets its holder read every user's MCP change history. It follows the administrator role, which `user set-role` assigns.";
    const REASON_PRIVILEGED_CAP      = "'%s' is a meta capability the plugin that serves this MCP connection maps at run time from its privileged-tool switches; no role or user holds it, and wp_cli_run never grants or removes it. Switch the tool on under Settings › Advanced in wp-admin.";
    const REASON_PLUGIN_CAPABILITY   = "'%s' is in the namespace of the plugin that serves this MCP connection, and wp_cli_run never grants or removes that plugin's capabilities. `cap list` shows what a role holds.";
    const REASON_CAPABILITY_BYTES    = 'wp_cli_run refuses a capability name containing a control character or a non-ASCII byte. Pass the capability in plain ASCII, as `cap list` prints it.';
    const REASON_OWN_COMMAND         = "'easy-mcp-ai' commands belong to the plugin that serves this MCP connection and are not available through wp_cli_run. Run them with the real wp binary on the server.";
    const REASON_CURRENT_SITE        = 'site %d is the site this MCP request runs on; deleting, archiving, deactivating or marking it as spam would drop or lock out the plugin\'s tables and API keys in the middle of the request. Do it from Network Admin › Sites in wp-admin, or through a connection on another site of the network.';

    
    private static $basename_override = null;

    






    public static function option( string $name, bool $write ): ?string {
        $name = trim( $name );
        if ( '' === $name ) {
            return null;
        }
        if ( ! self::is_printable( $name, false ) ) {
            return self::LABEL . self::REASON_OPTION_BYTES;
        }
        $lc = strtolower( $name );
        if ( preg_match( self::NAMESPACE_PATTERN, $lc ) || in_array( $lc, self::sdk_names( 'option' ), true ) ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN_OPTION, $name );
        }
        $transient = self::transient_row( $name, $lc, $write );
        if ( null !== $transient ) {
            return $transient;
        }
        return self::core_option( $name, $lc, $write );
    }

    





    private static function transient_row( string $name, string $lc, bool $write ): ?string {
        foreach ( self::TRANSIENT_STORAGE_PREFIXES as $prefix ) {
            if ( 0 === strpos( $lc, $prefix ) ) {
                $match = self::transient_match( (string) substr( $lc, strlen( $prefix ) ), $write );
                if ( null !== $match && 'plugin' === $match[0] ) {
                    return self::LABEL . sprintf( self::REASON_PLUGIN_TRANSIENT_AT, $name );
                }
                if ( null !== $match ) {
                    return self::LABEL . sprintf( self::REASON_UPDATE_TRANSIENT_AT, $name, $match[1], self::UPDATE_TRANSIENTS[ $match[1] ] );
                }
                return null;
            }
        }
        return null;
    }

    




    private static function core_option( string $name, string $lc, bool $write ): ?string {
        if ( in_array( $lc, self::SPECIAL_OPTIONS, true ) ) {
            return self::LABEL . sprintf( self::REASON_SPECIAL_OPTION, $name );
        }
        if ( $write ) {
            if ( isset( self::CORE_WRITE_OPTIONS[ $lc ] ) ) {
                return self::LABEL . sprintf( self::REASON_CORE_WRITE, $name, self::CORE_WRITE_OPTIONS[ $lc ] );
            }
            if ( self::is_role_option( $lc ) ) {
                return self::LABEL . sprintf( self::REASON_CORE_WRITE, $name, self::ROLE_OPTION_REASON );
            }
            return null;
        }
        if ( isset( self::SECRET_OPTIONS[ $lc ] ) ) {
            return self::LABEL . sprintf( self::REASON_SECRET_OPTION, $name, self::SECRET_OPTIONS[ $lc ] );
        }
        return null;
    }

    








    public static function transient( string $name, bool $write = true ): ?string {
        $name = trim( $name );
        if ( '' === $name ) {
            return null;
        }
        if ( ! self::is_printable( $name, false ) ) {
            return self::LABEL . self::REASON_OPTION_BYTES;
        }
        $match = self::transient_match( strtolower( $name ), $write );
        if ( null === $match ) {
            return null;
        }
        if ( 'plugin' === $match[0] ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN_TRANSIENT, $name );
        }
        return self::LABEL . sprintf( self::REASON_UPDATE_TRANSIENT, $name, self::UPDATE_TRANSIENTS[ $match[1] ] );
    }

    







    public static function cache_group( string $group ): ?string {
        $group = trim( $group );
        if ( '' === $group ) {
            return null;
        }
        if ( ! self::is_printable( $group, false ) ) {
            return self::LABEL . self::REASON_CACHE_BYTES;
        }
        foreach ( explode( ':', strtolower( $group ) ) as $segment ) {
            if ( isset( self::CACHE_GROUPS[ $segment ] ) ) {
                return self::LABEL . sprintf( self::REASON_CACHE_GROUP, $group, self::CACHE_GROUPS[ $segment ] );
            }
        }
        return null;
    }

    
    public static function cron_hook( string $hook ): ?string {
        $hook = trim( $hook );
        if ( '' === $hook ) {
            return null;
        }
        if ( ! self::is_printable( $hook, true ) ) {
            return self::LABEL . self::REASON_CRON_BYTES;
        }
        $lc = strtolower( $hook );
        if ( preg_match( self::NAMESPACE_PATTERN, $lc ) || in_array( $lc, self::sdk_names( 'cron' ), true ) ) {
            return self::LABEL . sprintf( self::REASON_CRON_HOOK, $hook );
        }
        return null;
    }

    
















    public static function plugin( string $plugin ): ?string {
        $own = self::plugin_path( self::basename() );
        if ( null === $own || '' === $own ) {
            return self::LABEL . self::REASON_PLUGIN_UNKNOWN;
        }
        $plugin = trim( $plugin );
        if ( '' === $plugin ) {
            return null;
        }
        if ( ! self::is_printable( $plugin, true ) ) {
            return self::LABEL . self::REASON_PLUGIN_BYTES;
        }
        $path = self::plugin_path( $plugin );
        if ( null === $path ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN_ESCAPES, $plugin );
        }
        if ( '' === $path ) {
            return null;
        }
        if ( self::is_own_path( $path, $own ) ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN, $plugin );
        }
        return null;
    }

    






    private static function is_own_path( string $path, string $own ): bool {
        $slash   = strpos( $own, '/' );
        $own_dir = false === $slash ? null : substr( $own, 0, $slash );
        $first   = explode( '/', $path )[0];
        return $path === $own || $path . '.php' === $own || ( null !== $own_dir && $first === $own_dir );
    }

    
    public static function user_meta_key( string $key ): ?string {
        $key = trim( $key );
        if ( '' === $key ) {
            return null;
        }
        if ( ! self::is_printable( $key, false ) ) {
            return self::LABEL . self::REASON_USER_META_BYTES;
        }
        $lc = strtolower( $key );
        if ( preg_match( self::NAMESPACE_PATTERN, $lc ) ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN_USER_META, $key );
        }
        if ( isset( self::USER_META_KEYS[ $lc ] ) ) {
            return self::LABEL . sprintf( self::REASON_CORE_USER_META, $key, self::USER_META_KEYS[ $lc ] );
        }
        return null;
    }

    
    public static function capability( string $cap ): ?string {
        $cap = trim( $cap );
        if ( '' === $cap ) {
            return null;
        }
        if ( ! self::is_printable( $cap, true ) ) {
            return self::LABEL . self::REASON_CAPABILITY_BYTES;
        }
        $lc = strtolower( $cap );
        if ( self::HISTORY_CAPABILITY === $lc ) {
            return self::LABEL . sprintf( self::REASON_HISTORY_CAPABILITY, $cap );
        }
        if ( in_array( $lc, self::PRIVILEGED_CAPABILITIES, true ) ) {
            return self::LABEL . sprintf( self::REASON_PRIVILEGED_CAP, $cap );
        }
        if ( preg_match( self::NAMESPACE_PATTERN, $lc ) ) {
            return self::LABEL . sprintf( self::REASON_PLUGIN_CAPABILITY, $cap );
        }
        return null;
    }

    





    public static function own_command( string $command ): ?string {
        $words = preg_split( '/\s+/', trim( $command ), -1, PREG_SPLIT_NO_EMPTY );
        if ( ! is_array( $words ) || ! $words ) {
            return null;
        }
        if ( 'wp' === strtolower( $words[0] ) ) {
            array_shift( $words );
        }
        if ( ! $words || self::OWN_COMMAND !== strtolower( $words[0] ) ) {
            return null;
        }
        $sub = strtolower( implode( ' ', array_slice( $words, 1, 2 ) ) );
        if ( isset( self::OWN_SUBCOMMANDS[ $sub ] ) ) {
            return self::LABEL . self::OWN_SUBCOMMANDS[ $sub ];
        }
        return self::LABEL . self::REASON_OWN_COMMAND;
    }

    







    public static function is_plugin_name( string $name ): bool {
        $lc = strtolower( trim( $name ) );
        if ( '' === $lc ) {
            return false;
        }
        if ( preg_match( self::NAMESPACE_PATTERN, $lc ) ) {
            return true;
        }
        foreach ( array( 'option', 'transient', 'cron' ) as $kind ) {
            if ( in_array( $lc, self::sdk_names( $kind ), true ) ) {
                return true;
            }
        }
        foreach ( self::TRANSIENT_STORAGE_PREFIXES as $prefix ) {
            if ( 0 === strpos( $lc, $prefix ) ) {
                $match = self::transient_match( (string) substr( $lc, strlen( $prefix ) ), false );
                return null !== $match && 'plugin' === $match[0];
            }
        }
        return false;
    }

    







    public static function omitted_names_text( array $names ): string {
        $named  = array();
        $plugin = 0;
        foreach ( array_values( array_unique( array_map( 'strval', $names ) ) ) as $name ) {
            if ( self::is_plugin_name( $name ) ) {
                ++$plugin;
            } else {
                $named[] = $name;
            }
        }
        sort( $named, SORT_STRING );
        $parts = array();
        if ( $named ) {
            $parts[] = implode( ', ', $named );
        }
        if ( $plugin ) {
            $parts[] = Handlers\Option_Handler::omission_summary( array( 'plugin' => $plugin ) );
        }
        return implode( '; ', $parts );
    }

    
    public static function current_site( int $blog_id ): ?string {
        $current = function_exists( 'get_current_blog_id' ) ? (int) \get_current_blog_id() : 1;
        if ( $blog_id === $current ) {
            return self::LABEL . sprintf( self::REASON_CURRENT_SITE, $blog_id );
        }
        return null;
    }

    




    public static function override_basename_for_tests( ?string $basename ): void {
        self::$basename_override = $basename;
    }

    
    
    

    
    private static function basename(): string {
        if ( null !== self::$basename_override ) {
            return self::$basename_override;
        }
        return defined( 'EASY_MCP_AI_PLUGIN_BASENAME' ) ? (string) EASY_MCP_AI_PLUGIN_BASENAME : '';
    }

    



    private static function is_printable( string $name, bool $allow_space ): bool {
        return 1 === preg_match( $allow_space ? '/^[\x20-\x7E]+\z/' : '/^[\x21-\x7E]+\z/', $name );
    }

    







    private static function transient_match( string $lc, bool $write ): ?array {
        $base = 0 === strpos( $lc, 'timeout_' ) ? (string) substr( $lc, strlen( 'timeout_' ) ) : $lc;
        if ( preg_match( self::NAMESPACE_PATTERN, $base ) || in_array( $base, self::sdk_names( 'transient' ), true ) ) {
            return array( 'plugin', $base );
        }
        if ( $write && isset( self::UPDATE_TRANSIENTS[ $base ] ) ) {
            return array( 'update', $base );
        }
        return null;
    }

    




    private static function is_role_option( string $lc ): bool {
        global $wpdb;
        $prefixes = array();
        if ( is_object( $wpdb ) ) {
            if ( method_exists( $wpdb, 'get_blog_prefix' ) ) {
                $prefixes[] = (string) $wpdb->get_blog_prefix();
            }
            if ( isset( $wpdb->prefix ) && is_string( $wpdb->prefix ) ) {
                $prefixes[] = $wpdb->prefix;
            }
        }
        $prefixes = array_filter( $prefixes, 'strlen' );
        if ( ! $prefixes ) {
            return 'user_roles' === substr( $lc, -strlen( 'user_roles' ) );
        }
        foreach ( $prefixes as $prefix ) {
            if ( strtolower( $prefix ) . 'user_roles' === $lc ) {
                return true;
            }
        }
        return false;
    }

    









    private static function sdk_names( string $kind ): array {
        $basename = str_replace( '\\', '/', self::basename() );
        $slash    = strpos( $basename, '/' );
        if ( false === $slash ) {
            return array();
        }
        $slug = strtolower( trim( substr( $basename, 0, $slash ) ) );
        $key  = str_replace( '-', '_', $slug );
        if ( '' === $slug || preg_match( self::NAMESPACE_PATTERN, $key ) ) {
            return array();
        }
        $suffixes = array(
            'option'    => self::SDK_OPTION_SUFFIXES,
            'transient' => self::SDK_TRANSIENT_SUFFIXES,
            'cron'      => self::SDK_CRON_SUFFIXES,
        );
        $names = array();
        foreach ( $suffixes[ $kind ] ?? array() as $suffix ) {
            $names[] = $key . '_' . $suffix;
        }
        if ( 'option' === $kind ) {
            $names[] = $slug . '_version';
        }
        return $names;
    }

    






    private static function plugin_path( string $raw ): ?string {
        $path = self::resolve_dots( self::map_symlinks( self::normalise_path( $raw ) ) );
        return null === $path ? null : self::relative_to_plugin_dirs( $path );
    }

    



    private static function map_symlinks( string $path ): string {
        global $wp_plugin_paths;
        if ( is_array( $wp_plugin_paths ) && $wp_plugin_paths ) {
            $paths = $wp_plugin_paths;
            arsort( $paths );
            foreach ( $paths as $dir => $realdir ) {
                $real = self::normalise_path( (string) $realdir );
                if ( '' !== $real && 0 === strpos( $path, $real ) ) {
                    $path = self::normalise_path( (string) $dir ) . substr( $path, strlen( $real ) );
                }
            }
        }
        return $path;
    }

    



    private static function resolve_dots( string $path ): ?string {
        $absolute = 0 === strpos( $path, '/' ) || 1 === preg_match( '#^[a-z]:/#', $path );
        $segments = array();
        foreach ( explode( '/', $path ) as $segment ) {
            if ( '' === $segment || '.' === $segment ) {
                continue;
            }
            if ( '..' === $segment ) {
                if ( ! $segments && ! $absolute ) {
                    return null;
                }
                array_pop( $segments );
                continue;
            }
            $segments[] = $segment;
        }
        return implode( '/', $segments );
    }

    
    private static function relative_to_plugin_dirs( string $path ): string {
        foreach ( array( 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR' ) as $constant ) {
            if ( ! defined( $constant ) ) {
                continue;
            }
            $dir = trim( self::normalise_path( (string) constant( $constant ) ), '/' );
            if ( '' !== $dir && 0 === strpos( $path . '/', $dir . '/' ) ) {
                return (string) substr( $path, strlen( $dir ) + 1 );
            }
        }
        return $path;
    }

    
    private static function normalise_path( string $path ): string {
        return (string) preg_replace( '#/+#', '/', strtolower( str_replace( '\\', '/', $path ) ) );
    }
}
