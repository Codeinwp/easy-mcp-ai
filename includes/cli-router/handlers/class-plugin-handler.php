<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Batch;
use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Formatter;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Output;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Plugin_Handler extends Command_Handler {

    const OBJ_FIELDS = array( 'name', 'status', 'update', 'version', 'update_version', 'auto_update' );

    const GET_FIELDS = array( 'name', 'title', 'author', 'version', 'description', 'status' );

    const INVALID_VERSION_MESSAGE = 'version higher than expected';

    const NOT_CAPTURED_AUTO_SINGLE  = 'The auto_update_plugins option is recorded by change history only while it records every option (not in allowlist mode); wp_history_apply cannot restore it.';
    const NOT_CAPTURED_AUTO_NETWORK = 'The auto_update_plugins network option (wp_sitemeta) is recorded by change history only while its meta capture setting is on (the default), and a deleted network option is recorded without its previous value; wp_history_apply cannot restore it.';

    const AUTO_UPDATE_OPTION = 'auto_update_plugins';

    const PLUGIN_CHECKSUMS_ENDPOINT = 'https://downloads.wordpress.org/plugin-checksums/';
    const CORE_CHECKSUMS_ENDPOINT   = 'https://api.wordpress.org/core/checksums/1.0/';

    const STATUS_TEMPLATE = "Plugin %9{{slug}}%n details:\n    Name: {{name}}\n    Status: {{status}}%n\n    Version: {{version}}\n    Author: {{author}}\n    Description: {{description}}\n";

    const COLOR_TOKENS = array( '%y', '%g', '%b', '%r', '%p', '%m', '%c', '%w', '%k', '%n', '%Y', '%G', '%B', '%R', '%P', '%M', '%C', '%W', '%K', '%N', '%3', '%2', '%4', '%1', '%5', '%6', '%7', '%0', '%F', '%U', '%8', '%9', '%_' );

    const STATUS_SHORT = array(
        'inactive'       => 'I',
        'active'         => 'A',
        'active-network' => 'N',
        'must-use'       => 'M',
        'parent'         => 'P',
        'dropin'         => 'D',
    );
    const STATUS_LONG  = array(
        'inactive'       => 'Inactive',
        'active'         => 'Active',
        'active-network' => 'Network Active',
        'must-use'       => 'Must Use',
        'parent'         => 'Parent',
        'dropin'         => 'Drop-In',
    );
    const STATUS_COLOR = array(
        'inactive'       => '',
        'active'         => '%g',
        'active-network' => '%g',
        'must-use'       => '%c',
        'parent'         => '%p',
        'dropin'         => '%B',
    );

    const NOTE_PATH          = "wp_cli_run prints the path relative to the WordPress root (ABSPATH) where WP-CLI prints the absolute server path, which names the hosting account and its directory layout (plan Q21). Prefix it with the WordPress root to use it on the server.";
    const NOTE_PATH_OUTSIDE  = "wp_cli_run prints the path from the plugins folder's own name where WP-CLI prints the absolute server path: this site keeps its plugins outside the WordPress root, and the absolute path names the hosting account and its directory layout (plan Q21).";
    const MSG_FILE_MODS      = 'File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter), so WordPress withholds %s from every user, in wp-admin too.';
    const MSG_NEEDS_CAP      = 'Sorry, you are not allowed to do this: wp_cli_run needs the %s capability for %s, as %s in wp-admin does.';
    const MSG_AUTO_OFF       = 'Plugin auto-updates are turned off on this site (AUTOMATIC_UPDATER_DISABLED, an automatic_updater_disabled filter or the plugins_auto_update_enabled filter), so the Plugins screen offers no auto-update toggle and wp_cli_run does not change the setting either.';
    const MSG_MU_HIDDEN      = 'Must-use and drop-in plugins are not listed: on a multisite network WordPress shows them only in Network Admin, which needs the manage_network_plugins capability.';
    const MSG_ADVANCED_FILTERED = 'The %s plugins are not listed: a show_advanced_plugins filter on this site hides them from the Plugins screen.';

    public static function load_plugin_admin(): void {
        if ( ( ! function_exists( 'get_plugins' ) || ! function_exists( 'get_plugin_data' ) || ! function_exists( 'is_plugin_active_for_network' ) ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }

    public static function plugin_name( string $basename ): string {
        if ( false === strpos( $basename, '/' ) ) {
            return basename( $basename, '.php' );
        }
        return dirname( $basename );
    }

    public static function all_plugins(): array {
        self::load_plugin_admin();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, as WP-CLI reads it.
        $plugins = \apply_filters( 'all_plugins', \get_plugins() );
        return is_array( $plugins ) ? $plugins : array();
    }

    public static function status_of( string $file ): string {
        self::load_plugin_admin();
        if ( \is_plugin_active_for_network( $file ) ) {
            return 'active-network';
        }
        if ( \is_plugin_active( $file ) ) {
            return 'active';
        }
        return 'inactive';
    }

    public static function check_optional_args_and_all( Output $out, array $args, bool $all, string $verb = 'install', $exclude = null ): array {
        if ( $all ) {
            $args = array_map( array( __CLASS__, 'plugin_name' ), array_map( 'strval', array_keys( self::all_plugins() ) ) );
        }

        if ( $all && $exclude ) {
            $exclude_list = explode( ',', trim( (string) $exclude, ',' ) );
            $args         = array_filter(
                $args,
                static function ( string $slug ) use ( $exclude_list ): bool {
                    return ! in_array( $slug, $exclude_list, true );
                }
            );
        }

        if ( empty( $args ) ) {
            if ( ! $all ) {
                $out->error( 'Please specify one or more plugins, or use --all.' );
            }
            $out->success( 'No plugins ' . Values::past_tense_verb( $verb ) . '.' );
        }

        return $args;
    }

    public static function caller_sees( string $file ): bool {
        if ( ! \is_multisite() ) {
            return true;
        }
        self::load_plugin_admin();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter (WP_Plugins_List_Table::prepare_items()).
        $show = (bool) \apply_filters( 'show_network_active_plugins', \current_user_can( 'manage_network_plugins' ) );
        if ( $show ) {
            return true;
        }
        if ( \is_network_only_plugin( $file ) && ! \is_plugin_active( $file ) ) {
            return false;
        }
        return ! \is_plugin_active_for_network( $file );
    }

    public static function find( string $name ) {
        $plugin = Fetchers::of( 'plugin' )->get( $name );
        if ( $plugin && ! self::caller_sees( (string) $plugin->file ) ) {
            return false;
        }
        return $plugin;
    }

    public static function find_check( string $name, Output $out ): object {
        $plugin = self::find( $name );
        if ( ! $plugin ) {
            $out->error( sprintf( Fetchers::MESSAGES['plugin'], $name ) );
        }
        return $plugin;
    }

    public static function find_many( array $names, Output $out ): array {
        $found = array();
        foreach ( $names as $name ) {
            $plugin = self::find( (string) $name );
            if ( $plugin ) {
                $found[] = $plugin;
            } else {
                $out->warning( sprintf( Fetchers::MESSAGES['plugin'], $name ) );
            }
        }
        return $found;
    }

    public static function plugin_data( string $file ): array {
        self::load_plugin_admin();
        $data = \get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
        return is_array( $data ) ? $data : array();
    }

    public static function may_see_updates(): bool {
        return \current_user_can( 'update_plugins' );
    }

    public static function hidden_updates_warning(): string {
        if ( function_exists( 'wp_is_file_mod_allowed' ) && ! \wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            $cause = 'file modifications are disabled on this site (DISALLOW_FILE_MODS, or a file_mod_allowed filter), so WordPress withholds update data from every user';
        } elseif ( \is_multisite() && ! \is_super_admin() ) {
            $cause = 'on a multisite network WordPress shows update data only to super admins (the update_plugins capability)';
        } else {
            $cause = 'it needs the update_plugins capability, which your account lacks';
        }
        return sprintf( 'Plugin updates are not shown and no update check was made: %s.', $cause );
    }

    public static function item_list( bool $see_updates ): array {
        $items           = array();
        $duplicate_names = array();

        $auto_updates = \get_site_option( self::AUTO_UPDATE_OPTION );
        if ( ! is_array( $auto_updates ) ) {
            $auto_updates = array();
        }

        $recently_active = \is_network_admin() ? \get_site_option( 'recently_activated' ) : \get_option( 'recently_activated' );
        if ( false === $recently_active ) {
            $recently_active = array();
        }
        $recently_active_files = array_keys( (array) $recently_active );

        $reason = null;
        foreach ( self::all_plugins() as $file => $details ) {
            $file            = (string) $file;
            $all_update_info = $see_updates ? \get_site_transient( 'update_plugins' ) : false;
            $update_info     = self::update_info( $all_update_info, $file );

            $duplicate_names[ self::plugin_name( $file ) ][] = $file;
            $items[ $file ] = self::item_row( $file, (array) $details, $update_info, $auto_updates, $recently_active_files, $reason );

            if ( null === $update_info && is_object( $all_update_info ) ) {
                self::apply_no_update( $items[ $file ], $all_update_info, $file );
            }
        }

        self::rename_duplicates( $items, $duplicate_names );

        return $items;
    }

    private static function update_info( $all_update_info, string $file ): ?array {
        return ( is_object( $all_update_info ) && isset( $all_update_info->response[ $file ] ) && null !== $all_update_info->response[ $file ] ) ? (array) $all_update_info->response[ $file ] : null;
    }

    private static function item_row( string $file, array $details, ?array $update_info, array $auto_updates, array $recently_active_files, ?string &$reason ): array {
        $update = self::item_update( $file, $update_info, $reason );
        return array(
            'name'                      => self::plugin_name( $file ),
            'status'                    => self::status_of( $file ),
            'update'                    => $update['update'],
            'update_version'            => $update['update_version'],
            'update_package'            => $update['update_package'],
            'version'                   => isset( $details['Version'] ) ? $details['Version'] : '',
            'update_id'                 => $file,
            'title'                     => isset( $details['Name'] ) ? $details['Name'] : '',
            'description'               => wordwrap( isset( $details['Description'] ) ? (string) $details['Description'] : '' ),
            'file'                      => $file,
            'auto_update'               => in_array( $file, $auto_updates, true ),
            'author'                    => isset( $details['Author'] ) ? $details['Author'] : '',
            'tested_up_to'              => '',
            'requires'                  => $update['requires'],
            'requires_php'              => $update['requires_php'],
            'wporg_status'              => '',
            'wporg_last_updated'        => '',
            'recently_active'           => in_array( $file, $recently_active_files, true ),
            'update_unavailable_reason' => isset( $reason ) ? $reason : '',
        );
    }

    private static function item_update( string $file, ?array $update_info, ?string &$reason ): array {
        $plugin_data  = self::plugin_data( $file );
        $requires     = self::offer_field( $update_info, 'requires' );
        $requires_php = self::offer_field( $update_info, 'requires_php' );

        $compatible_php = empty( $requires_php ) || version_compare( PHP_VERSION, (string) $requires_php, '>=' );

        if ( ! $compatible_php ) {
            $update = 'unavailable';

            $reason = sprintf(
                'This update requires PHP version %s, but the version installed is %s.',
                $requires_php,
                PHP_VERSION
            );
        } else {
            $update = $update_info ? 'available' : 'none';
        }

        return array(
            'update'         => $update,
            'update_version' => self::offer_field( $update_info, 'new_version' ),
            'update_package' => self::offer_field( $update_info, 'package' ),
            'requires'       => null === $requires ? self::non_empty_header( $plugin_data, 'RequiresWP' ) : $requires,
            'requires_php'   => null === $requires_php ? self::non_empty_header( $plugin_data, 'RequiresPHP' ) : $requires_php,
        );
    }

    private static function offer_field( ?array $update_info, string $key ) {
        return isset( $update_info ) && isset( $update_info[ $key ] ) ? $update_info[ $key ] : null;
    }

    private static function non_empty_header( array $plugin_data, string $key ): string {
        return ! empty( $plugin_data[ $key ] ) ? (string) $plugin_data[ $key ] : '';
    }

    private static function apply_no_update( array &$item, object $all_update_info, string $file ): void {
        global $wp_version;
        $plugin_update_info = isset( $all_update_info->no_update[ $file ] ) ? $all_update_info->no_update[ $file ] : null;
        $latest             = is_object( $plugin_update_info ) && isset( $plugin_update_info->new_version ) ? (string) $plugin_update_info->new_version : null;
        if ( null === $latest ) {
            return;
        }
        $requires = self::no_update_requires( $plugin_update_info );

        if ( version_compare( (string) $item['version'], $latest, '>' ) ) {
            $item['update']       = self::INVALID_VERSION_MESSAGE;
            $item['requires']     = $requires['requires'];
            $item['requires_php'] = $requires['requires_php'];
        }

        if ( version_compare( (string) $item['version'], $latest, '<' ) ) {
            $item['update']         = 'unavailable';
            $item['update_version'] = $latest;
            $item['requires']       = $requires['requires'];
            $item['requires_php']   = $requires['requires_php'];

            $requires_text = isset( $plugin_update_info->requires ) ? (string) $plugin_update_info->requires : '';

            $item['update_unavailable_reason'] = "This update requires WordPress version {$requires_text}, but the version installed is {$wp_version}.";
        }
    }

    private static function no_update_requires( object $plugin_update_info ): array {
        return array(
            'requires'     => isset( $plugin_update_info->requires ) ? $plugin_update_info->requires : null,
            'requires_php' => isset( $plugin_update_info->requires_php ) ? $plugin_update_info->requires_php : null,
        );
    }

    private static function rename_duplicates( array &$items, array $duplicate_names ): void {
        foreach ( $duplicate_names as $name => $files ) {
            if ( count( $files ) <= 1 ) {
                continue;
            }
            foreach ( $files as $file ) {
                $items[ $file ]['name'] = str_replace( '.' . pathinfo( $file, PATHINFO_EXTENSION ), '', $file );
            }
        }
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $assoc_args = $inv->assoc;
        $plugin     = self::find_check( isset( $inv->args[0] ) ? (string) $inv->args[0] : '', $ctx->out );
        $file       = (string) $plugin->file;

        $plugin_data = self::plugin_data( $file );

        $plugin_obj = (object) array(
            'name'             => self::plugin_name( $file ),
            'title'            => isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : '',
            'author'           => isset( $plugin_data['Author'] ) ? $plugin_data['Author'] : '',
            'version'          => isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '',
            'description'      => wordwrap( isset( $plugin_data['Description'] ) ? (string) $plugin_data['Description'] : '' ),
            'status'           => self::status_of( $file ),
            'requires_wp'      => ! empty( $plugin_data['RequiresWP'] ) ? $plugin_data['RequiresWP'] : '',
            'requires_php'     => ! empty( $plugin_data['RequiresPHP'] ) ? $plugin_data['RequiresPHP'] : '',
            'requires_plugins' => ! empty( $plugin_data['RequiresPlugins'] ) ? $plugin_data['RequiresPlugins'] : '',
        );

        if ( empty( $assoc_args['fields'] ) ) {
            $assoc_args['fields'] = self::GET_FIELDS;
        }

        $formatter = new Formatter( $ctx->out, $assoc_args, self::OBJ_FIELDS, 'plugin' );
        $formatter->display_item( $plugin_obj );
    }

    public function is_installed( Invocation $inv, Context $ctx ): void {
        $ctx->out->halt( self::find( isset( $inv->args[0] ) ? (string) $inv->args[0] : '' ) ? 0 : 1 );
    }

    public function is_active( Invocation $inv, Context $ctx ): void {
        $network_wide = Read_Value::flag( $inv->assoc, 'network' );
        $plugin       = self::find( isset( $inv->args[0] ) ? (string) $inv->args[0] : '' );
        if ( ! $plugin ) {
            $ctx->out->halt( 1 );
        }
        $ctx->out->halt( ( $network_wide ? 'active-network' : 'active' ) === self::status_of( (string) $plugin->file ) ? 0 : 1 );
    }

    public function path( Invocation $inv, Context $ctx ): void {
        $path = \untrailingslashit( WP_PLUGIN_DIR );

        if ( ! empty( $inv->args ) ) {
            $plugin = self::find_check( (string) reset( $inv->args ), $ctx->out );
            $path  .= '/' . $plugin->file;

            if ( Read_Value::flag( $inv->assoc, 'dir' ) ) {
                $path = dirname( $path );
            }
        }

        list( $shown, $inside ) = self::display_path( $path );
        $ctx->out->line( $shown );
        $ctx->note( $inside ? self::NOTE_PATH : self::NOTE_PATH_OUTSIDE );
    }

    public static function display_path( string $path ): array {
        $path = str_replace( '\\', '/', $path );
        $root = rtrim( str_replace( '\\', '/', (string) ABSPATH ), '/' ) . '/';
        if ( '/' !== $root && 0 === strpos( $path . '/', $root ) ) {
            return array( (string) substr( $path, strlen( $root ) ), true );
        }
        $plugins = rtrim( str_replace( '\\', '/', (string) WP_PLUGIN_DIR ), '/' );
        if ( '' !== $plugins && 0 === strpos( $path . '/', $plugins . '/' ) ) {
            return array( basename( $plugins ) . (string) substr( $path, strlen( $plugins ) ), false );
        }
        return array( basename( $path ), false );
    }

    public function status( Invocation $inv, Context $ctx ): void {
        $see = self::may_see_updates();
        if ( $see ) {
            \wp_update_plugins();
        } else {
            $ctx->out->warning( self::hidden_updates_warning() );
        }

        if ( empty( $inv->args ) ) {
            $this->status_all( $ctx, $see );
        } else {
            $this->status_single( (string) reset( $inv->args ), $ctx, $see );
        }
    }

    private function status_all( Context $ctx, bool $see ): void {
        $items = self::all_items( $ctx->out, $see );

        $n = count( $items );

        $ctx->out->log( sprintf( '%d installed %s:', $n, 1 === $n ? 'plugin' : 'plugins' ) );

        $padding = 0;
        foreach ( $items as $details ) {
            $padding = max( $padding, strlen( (string) $details['name'] ) );
        }

        foreach ( $items as $details ) {
            $line  = 'available' === $details['update'] ? ' %yU%n' : '  ';
            $line .= self::format_status( (string) $details['status'], 'short' );
            $line .= ' ' . str_pad( (string) $details['name'], $padding ) . '%n';
            if ( ! empty( $details['version'] ) ) {
                $line .= ' ' . $details['version'];
            }
            $ctx->out->line( self::decolorize( $line ) );
        }

        $ctx->out->line();

        $statuses    = array_unique( array_column( $items, 'status' ) );
        $legend_line = array();
        foreach ( $statuses as $status ) {
            $legend_line[] = sprintf( '%s%s = %s%%n', self::STATUS_COLOR[ $status ], self::STATUS_SHORT[ $status ], self::STATUS_LONG[ $status ] );
        }
        if ( in_array( 'available', array_column( $items, 'update' ), true ) ) {
            $legend_line[] = '%yU = Update Available%n';
        }
        $ctx->out->line( 'Legend: ' . self::decolorize( implode( ', ', $legend_line ) ) );
    }

    private function status_single( string $name, Context $ctx, bool $see ): void {
        $plugin = self::find_check( $name, $ctx->out );
        $file   = (string) $plugin->file;

        self::load_plugin_admin();
        $all     = \get_plugins();
        $details = isset( $all[ $file ] ) && is_array( $all[ $file ] ) ? $all[ $file ] : array();

        $status  = self::format_status( self::status_of( $file ), 'long' );
        $version = isset( $details['Version'] ) ? (string) $details['Version'] : '';

        if ( $see ) {
            $update_list = \get_site_transient( 'update_plugins' );
            if ( is_object( $update_list ) && isset( $update_list->response[ $file ] ) ) {
                $version .= ' (%gUpdate available%n)';
            }
        }

        $values = array(
            '{{slug}}'        => self::plugin_name( $file ),
            '{{status}}'      => $status,
            '{{version}}'     => $version,
            '{{name}}'        => isset( $details['Name'] ) ? (string) $details['Name'] : '',
            '{{author}}'      => isset( $details['Author'] ) ? (string) $details['Author'] : '',
            '{{description}}' => isset( $details['Description'] ) ? (string) $details['Description'] : '',
        );
        $ctx->out->out( self::decolorize( strtr( self::STATUS_TEMPLATE, $values ) ) );
    }

    public static function all_items( Output $out, bool $see ): array {
        $items = self::item_list( $see );

        if ( \is_multisite() && ! \current_user_can( 'manage_network_plugins' ) ) {
            $out->warning( self::MSG_MU_HIDDEN );
            return $items;
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core's own filter, applied as the Plugins screen applies it.
        if ( \apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ) {
            self::add_mu_rows( $items );
        } else {
            $out->warning( sprintf( self::MSG_ADVANCED_FILTERED, 'must-use' ) );
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- as above.
        if ( \apply_filters( 'show_advanced_plugins', true, 'dropins' ) ) {
            self::add_dropin_rows( $items );
        } else {
            $out->warning( sprintf( self::MSG_ADVANCED_FILTERED, 'drop-in' ) );
        }

        return $items;
    }

    private static function add_mu_rows( array &$items ): void {
        foreach ( (array) \get_mu_plugins() as $file => $mu_plugin ) {
            $file           = (string) $file;
            $items[ $file ] = array(
                'name'               => self::plugin_name( $file ),
                'status'             => 'must-use',
                'update'             => false,
                'update_version'     => null,
                'update_package'     => null,
                'version'            => ! empty( $mu_plugin['Version'] ) ? $mu_plugin['Version'] : '',
                'update_id'          => '',
                'title'              => ! empty( $mu_plugin['Name'] ) && isset( $mu_plugin['Title'] ) ? $mu_plugin['Title'] : '',
                'description'        => ! empty( $mu_plugin['Description'] ) ? $mu_plugin['Description'] : '',
                'file'               => $file,
                'auto_update'        => false,
                'tested_up_to'       => '',
                'requires'           => '',
                'requires_php'       => '',
                'wporg_status'       => '',
                'wporg_last_updated' => '',
            );
        }
    }

    private static function add_dropin_rows( array &$items ): void {
        $raw_data = \_get_dropins();
        foreach ( (array) \get_dropins() as $name => $item_data ) {
            $name           = (string) $name;
            $items[ $name ] = array(
                'name'               => $name,
                'title'              => isset( $item_data['Title'] ) ? $item_data['Title'] : '',
                'description'        => ! empty( $raw_data[ $name ][0] ) ? $raw_data[ $name ][0] : '',
                'status'             => 'dropin',
                'update'             => false,
                'update_version'     => null,
                'update_package'     => null,
                'update_id'          => '',
                'file'               => $name,
                'auto_update'        => false,
                'author'             => isset( $item_data['Author'] ) ? $item_data['Author'] : '',
                'tested_up_to'       => '',
                'requires'           => '',
                'requires_php'       => '',
                'wporg_status'       => '',
                'wporg_last_updated' => '',
            );
        }
    }

    private static function format_status( string $status, string $format ): string {
        $map = 'short' === $format ? self::STATUS_SHORT : self::STATUS_LONG;
        return ( isset( self::STATUS_COLOR[ $status ] ) ? self::STATUS_COLOR[ $status ] : '' ) . ( isset( $map[ $status ] ) ? $map[ $status ] : '' );
    }

    public static function decolorize( string $text ): string {
        $text = str_replace( '%%', "%\u{be}", $text );
        $text = str_replace( self::COLOR_TOKENS, '', $text );
        return str_replace( "%\u{be}", '%', $text );
    }

    public function preflight_search( Invocation $inv, Context $ctx ): void {
        self::require_file_cap( $ctx, 'install_plugins', 'searching the plugin directory', 'the Add Plugins screen', 'plugin installs' );
    }

    public function search( Invocation $inv, Context $ctx ): void {
        $term = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';

        $defaults   = array(
            'per-page' => 10,
            'page'     => 1,
            'fields'   => implode( ',', array( 'name', 'slug', 'rating' ) ),
        );
        $assoc_args = array_merge( $defaults, $inv->assoc );
        $fields     = array();
        foreach ( explode( ',', (string) $assoc_args['fields'] ) as $field ) {
            $fields[ $field ] = true;
        }

        $format    = ! empty( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
        $formatter = new Formatter( $ctx->out, $assoc_args, self::OBJ_FIELDS, 'plugin' );

        $api_args = array(
            'per_page' => (int) $assoc_args['per-page'],
            'page'     => (int) $assoc_args['page'],
            'search'   => $term,
            'fields'   => $fields,
        );

        $api   = self::query_plugins( $api_args, $ctx );
        $items = $api->plugins;

        self::add_plugin_urls( $items );

        if ( 'table' === $format ) {
            $info  = isset( $api->info ) ? $api->info : array();
            $count = is_array( $info ) && isset( $info['results'] ) ? $info['results'] : 'unknown';
            $ctx->out->success( sprintf( 'Showing %s of %s %s.', count( $items ), $count, 'plugins' ) );
        }

        $formatter->display_items( $items );
    }

    private static function query_plugins( array $api_args, Context $ctx ): object {
        if ( ! function_exists( 'plugins_api' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin-install.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }
        $api = \plugins_api( 'query_plugins', $api_args );

        if ( \is_wp_error( $api ) ) {
            $ctx->out->error( $api->get_error_message() . ' Try again' );
        }

        if ( ! is_object( $api ) || ! isset( $api->plugins ) ) {
            $ctx->out->error( 'API error. Try Again.' );
        }

        return $api;
    }

    private static function add_plugin_urls( iterable $items ): void {
        foreach ( $items as $item_object ) {
            if ( $item_object instanceof \stdClass ) {
                $item_object->url = "https://wordpress.org/plugins/{$item_object->slug}/";
            }
        }
    }

    public function preflight_auto_updates( Invocation $inv, Context $ctx ): void {
        self::require_file_cap( $ctx, 'update_plugins', 'plugin auto-updates', 'the auto-update toggle on the Plugins screen', 'plugin auto-updates' );
        if ( ! function_exists( 'wp_is_auto_update_enabled_for_type' ) && is_readable( ABSPATH . 'wp-admin/includes/update.php' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) && ! \wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
            $ctx->out->error( self::MSG_AUTO_OFF );
        }
    }

    public function preflight_auto_updates_read( Invocation $inv, Context $ctx ): void {
        self::require_file_cap( $ctx, 'update_plugins', 'plugin auto-updates', 'the auto-update column on the Plugins screen', 'plugin auto-updates' );
    }

    public function auto_updates_enable( Invocation $inv, Context $ctx ): void {
        $this->toggle_auto_updates( $inv, $ctx, true );
    }

    public function auto_updates_disable( Invocation $inv, Context $ctx ): void {
        $this->toggle_auto_updates( $inv, $ctx, false );
    }

    public function describe_auto_updates( Invocation $inv ): array {
        $enable = 'plugin auto-updates enable' === $inv->command;
        $lines  = array();
        try {
            $plan = self::auto_update_targets( $inv, new Output( true ), $enable );
        } catch ( \Easy_MCP_AI\Cli_Router\Cli_Exit $e ) {
            return array( 'No plugin is named, so nothing changes.' );
        }
        foreach ( $plan['missing'] as $name ) {
            $lines[] = sprintf( "Plugin '%s': not installed → nothing to change.", $name );
        }
        $walk = self::walk_auto_updates( $plan['plugins'], self::stored_auto_updates(), $enable, $plan['only'] );
        foreach ( $walk['events'] as $event ) {
            $now = $event['enabled'] ? 'enabled' : 'disabled';
            if ( 'already' === $event['action'] ) {
                $lines[] = sprintf( "Plugin auto-updates for '%s' (%s): already %s → unchanged (WP-CLI counts it as a failure).", $event['name'], $event['file'], $now );
            } else {
                $lines[] = sprintf( "Plugin auto-updates for '%s' (%s): %s → %s.", $event['name'], $event['file'], $now, $enable ? 'enabled' : 'disabled' );
            }
        }
        if ( ! $lines ) {
            $lines[] = 'No plugin is named, so nothing changes.';
        }
        return $lines;
    }

    private static function stored_auto_updates(): array {
        $auto_updates = \get_site_option( self::AUTO_UPDATE_OPTION );
        return is_array( $auto_updates ) ? $auto_updates : array();
    }

    private static function auto_update_targets( Invocation $inv, Output $out, bool $enable ): array {
        $all  = (bool) Read_Value::flag( $inv->assoc, 'all', false );
        $only = (bool) Read_Value::flag( $inv->assoc, $enable ? 'disabled-only' : 'enabled-only', false );

        $args = self::check_optional_args_and_all( $out, array_values( array_map( 'strval', $inv->args ) ), $all );
        if ( ! $args ) {
            return array(
                'plugins' => array(),
                'missing' => array(),
                'only'    => $only,
                'empty'   => true,
            );
        }

        $plugins = self::find_many( $args, $out );
        $missing = array();
        if ( ! $all ) {
            $remaining = array();
            foreach ( $plugins as $plugin ) {
                $remaining[] = (string) $plugin->name;
            }
            foreach ( $args as $name ) {
                $at = array_search( (string) $name, $remaining, true );
                if ( false === $at ) {
                    $missing[] = (string) $name;
                } else {
                    unset( $remaining[ $at ] );
                }
            }
        }
        return array(
            'plugins' => $plugins,
            'missing' => $missing,
            'only'    => $only,
            'empty'   => false,
        );
    }

    private static function walk_auto_updates( array $plugins, array $auto_updates, bool $enable, bool $only ): array {
        $events    = array();
        $count     = 0;
        $successes = 0;
        foreach ( $plugins as $plugin ) {
            $enabled = in_array( $plugin->file, $auto_updates, true );
            if ( $only && $enabled === $enable ) {
                continue;
            }
            ++$count;
            $action = 'change';
            if ( $enabled === $enable ) {
                $action = 'already';
            } elseif ( $enable ) {
                $auto_updates[] = $plugin->file;
                ++$successes;
            } else {
                $auto_updates = array_diff( $auto_updates, array( $plugin->file ) );
                ++$successes;
            }
            $events[] = array(
                'name'    => (string) $plugin->name,
                'file'    => (string) $plugin->file,
                'enabled' => $enabled,
                'action'  => $action,
            );
        }
        return array(
            'events'    => $events,
            'list'      => $auto_updates,
            'count'     => $count,
            'successes' => $successes,
        );
    }

    private function toggle_auto_updates( Invocation $inv, Context $ctx, bool $enable ): void {
        $plan = self::auto_update_targets( $inv, $ctx->out, $enable );
        if ( $plan['empty'] ) {
            return;
        }

        $before = \get_site_option( self::AUTO_UPDATE_OPTION );
        $walk   = self::walk_auto_updates( $plan['plugins'], self::stored_auto_updates(), $enable, $plan['only'] );
        self::warn_already( $walk['events'], $enable, $ctx->out );

        if ( 0 === $walk['count'] ) {
            $ctx->out->error( sprintf( 'No plugins provided to %s auto-updates for.', $enable ? 'enable' : 'disable' ) );
        }

        self::store_auto_updates( $walk['list'], $enable, $before, $ctx );

        $missing = count( $plan['missing'] );
        Values::report_batch( $ctx->out, 'plugin auto-update', $enable ? 'enable' : 'disable', $walk['count'] + $missing, $walk['successes'], $walk['count'] - $walk['successes'] + $missing );
    }

    private static function warn_already( array $events, bool $enable, Output $out ): void {
        foreach ( $events as $event ) {
            if ( 'already' === $event['action'] ) {
                $out->warning( sprintf( 'Auto-updates already %s for plugin %s.', $enable ? 'enabled' : 'disabled', $event['name'] ) );
            }
        }
    }

    private static function store_auto_updates( array $list, bool $enable, $before, Context $ctx ): void {
        $ctx->not_captured( \is_multisite() ? self::NOT_CAPTURED_AUTO_NETWORK : self::NOT_CAPTURED_AUTO_SINGLE );
        if ( $enable || count( $list ) > 0 ) {
            \update_site_option( self::AUTO_UPDATE_OPTION, $list );
        } else {
            \delete_site_option( self::AUTO_UPDATE_OPTION );
        }
        if ( \get_site_option( self::AUTO_UPDATE_OPTION ) !== $before ) {
            $ctx->mark_changed();
        }
    }

    public function auto_updates_status( Invocation $inv, Context $ctx ): void {
        $assoc_args    = $inv->assoc;
        $all           = (bool) Read_Value::flag( $assoc_args, 'all', false );
        $enabled_only  = Read_Value::flag( $assoc_args, 'enabled-only', false );
        $disabled_only = Read_Value::flag( $assoc_args, 'disabled-only', false );

        if ( $enabled_only && $disabled_only ) {
            $ctx->out->error(
                '--enabled-only and --disabled-only are mutually exclusive and '
                . 'cannot be used at the same time.'
            );
        }

        $args = self::check_optional_args_and_all( $ctx->out, array_values( array_map( 'strval', $inv->args ) ), $all );
        if ( ! $args ) {
            return;
        }

        $plugins      = self::find_many( $args, $ctx->out );
        $auto_updates = \get_site_option( self::AUTO_UPDATE_OPTION );
        if ( ! is_array( $auto_updates ) ) {
            $auto_updates = array();
        }

        $results = self::status_rows( $plugins, $auto_updates, (bool) $enabled_only, (bool) $disabled_only );

        $formatter = new Formatter( $ctx->out, $assoc_args, array( 'name', 'status' ), 'plugin' );
        $formatter->display_items( $results );
    }

    private static function status_rows( array $plugins, array $auto_updates, bool $enabled_only, bool $disabled_only ): array {
        $results = array();
        foreach ( $plugins as $plugin ) {
            $enabled = in_array( $plugin->file, $auto_updates, true );
            if ( $enabled_only && ! $enabled ) {
                continue;
            }
            if ( $disabled_only && $enabled ) {
                continue;
            }
            $results[] = array(
                'name'   => $plugin->name,
                'file'   => $plugin->file,
                'status' => $enabled ? 'enabled' : 'disabled',
            );
        }
        return $results;
    }

    public function verify_checksums( Invocation $inv, Context $ctx ): void {
        $assoc_args  = $inv->assoc;
        $all         = (bool) Read_Value::flag( $assoc_args, 'all', false );
        $strict      = (bool) Read_Value::flag( $assoc_args, 'strict', false );
        $plugins     = self::unfiltered_many( $all ? self::all_plugin_names() : array_values( array_map( 'strval', $inv->args ) ), $ctx->out );
        $exclude     = Read_Value::flag( $assoc_args, 'exclude', '' );
        $version_arg = isset( $assoc_args['version'] ) ? $assoc_args['version'] : '';

        if ( empty( $plugins ) && ! $all ) {
            $ctx->out->error( 'You need to specify either one or more plugin slugs to check or use the --all flag to check all plugins.' );
        }

        $exclude_list = explode( ',', (string) $exclude );
        self::refuse_over_cap( $inv, $ctx, $plugins, $exclude_list );

        $skips        = 0;
        $errors       = array();
        $plugins_data = null;

        foreach ( $plugins as $plugin ) {
            $version = self::version_to_check( $plugin, $version_arg, $plugins_data );

            if ( in_array( $plugin->name, $exclude_list, true ) ) {
                ++$skips;
                continue;
            }

            if ( 'hello' === $plugin->name ) {
                $this->verify_hello_dolly_from_core( $ctx, $errors );
                continue;
            }

            if ( ! self::verify_plugin( $ctx, $plugin, $version, $strict, $errors ) ) {
                ++$skips;
            }
        }

        if ( ! empty( $errors ) ) {
            $formatter = new Formatter( $ctx->out, $assoc_args, array( 'plugin_name', 'file', 'message' ) );
            $formatter->display_items( $errors );
        }

        $total     = count( $plugins );
        $failures  = count( array_unique( array_column( $errors, 'plugin_name' ) ) );
        $successes = $total - $failures - $skips;

        Values::report_batch( $ctx->out, 'plugin', 'verify', $total, $successes, $failures, $skips );
    }

    private static function refuse_over_cap( Invocation $inv, Context $ctx, array $plugins, array $exclude_list ): void {
        $checked = 0;
        foreach ( $plugins as $plugin ) {
            if ( ! in_array( $plugin->name, $exclude_list, true ) ) {
                ++$checked;
            }
        }
        if ( $checked > Batch::cap( $inv ) ) {
            $ctx->out->error( sprintf( Batch::OVER_CAP, Batch::cap( $inv ), $inv->command, $checked ) );
        }
    }

    private static function version_to_check( object $plugin, $version_arg, ?array &$plugins_data ) {
        if ( ! empty( $version_arg ) ) {
            return $version_arg;
        }
        if ( null === $plugins_data ) {
            self::load_plugin_admin();
            $plugins_data = \get_plugins();
        }
        return array_key_exists( $plugin->file, $plugins_data ) ? $plugins_data[ $plugin->file ]['Version'] : false;
    }

    private static function verify_plugin( Context $ctx, object $plugin, $version, bool $strict, array &$errors ): bool {
        if ( false === $version ) {
            $ctx->out->warning( "Could not retrieve the version for plugin {$plugin->name}, skipping." );
            return false;
        }

        try {
            $checksums = self::get_plugin_checksums( (string) $plugin->name, (string) $version );
        } catch ( \RuntimeException $exception ) {
            $ctx->out->warning( $exception->getMessage() );
            $checksums = false;
        }

        if ( false === $checksums ) {
            $ctx->out->warning( "Could not retrieve the checksums for version {$version} of plugin {$plugin->name}, skipping." );
            return false;
        }

        self::verify_plugin_files( $plugin, $checksums, $strict, $errors );
        return true;
    }

    private static function verify_plugin_files( object $plugin, array $checksums, bool $strict, array &$errors ): void {
        foreach ( self::get_plugin_files( (string) $plugin->file ) as $file ) {
            if ( ! array_key_exists( $file, $checksums ) ) {
                $errors[] = self::checksum_error( (string) $plugin->name, $file, 'File was added' );
                continue;
            }

            if ( ! $strict && in_array( strtolower( $file ), array( 'readme.txt', 'readme.md' ), true ) ) {
                continue;
            }
            $result = self::check_file_checksum( dirname( (string) $plugin->file ) . '/' . $file, $checksums[ $file ] );
            if ( true !== $result ) {
                $errors[] = self::checksum_error( (string) $plugin->name, $file, is_string( $result ) ? $result : 'Checksum does not match' );
            }
        }
    }

    private static function checksum_error( string $plugin_name, string $file, string $message ): array {
        return array(
            'plugin_name' => $plugin_name,
            'file'        => $file,
            'message'     => $message,
        );
    }

    private function verify_hello_dolly_from_core( Context $ctx, array &$errors ): void {
        $file       = 'hello.php';
        $wp_version = (string) \get_bloginfo( 'version', 'display' );
        $checksums  = false;

        try {
            $url      = self::CORE_CHECKSUMS_ENDPOINT . '?' . http_build_query(
                array(
                    'version' => $wp_version,
                    'locale'  => 'en_US',
                ),
                '',
                '&'
            );
            $response = self::json_get_request( $url );
            if ( is_array( $response ) && isset( $response['checksums'] ) && is_array( $response['checksums'] ) ) {
                $checksums = $response['checksums'];
            }
        } catch ( \RuntimeException $exception ) {
            $ctx->out->error( $exception->getMessage() );
        }

        if ( ! is_array( $checksums ) || ! isset( $checksums['wp-content/plugins/hello.php'] ) ) {
            $ctx->out->error( "Couldn't get hello.php checksum from WordPress.org." );
        }

        $md5_file = md5_file( WP_PLUGIN_DIR . '//' . $file );
        if ( $md5_file !== $checksums['wp-content/plugins/hello.php'] ) {
            $errors[] = self::checksum_error( 'hello', $file, 'Checksum does not match' );
        }
    }

    private static function get_plugin_checksums( string $plugin, string $version ) {
        $response = self::json_get_request( sprintf( '%s%s/%s.json', self::PLUGIN_CHECKSUMS_ENDPOINT, $plugin, $version ) );
        if ( ! is_array( $response ) || ! isset( $response['files'] ) || ! is_array( $response['files'] ) ) {
            return false;
        }
        return $response['files'];
    }

    private static function json_get_request( string $url ) {
        $response = \wp_remote_get(
            $url,
            array(
                'headers' => array( 'Accept' => 'application/json' ),
                'timeout' => 30,
            )
        );
        if ( \is_wp_error( $response ) ) {
            throw new \RuntimeException( $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = (int) \wp_remote_retrieve_response_code( $response );
        if ( 200 > $code || 300 <= $code ) {
            throw new \RuntimeException( "Couldn't fetch response from {$url} (HTTP code {$code})." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $data = json_decode( trim( (string) \wp_remote_retrieve_body( $response ) ), true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            throw new \RuntimeException( 'Failed to decode JSON: ' . json_last_error_msg() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $data;
    }

    private static function all_plugin_names(): array {
        self::load_plugin_admin();
        $names = array();
        foreach ( array_keys( \get_plugins() ) as $file ) {
            $names[] = self::plugin_name( (string) $file );
        }
        return $names;
    }

    private static function unfiltered_many( array $names, Output $out ): array {
        self::load_plugin_admin();
        $installed = array_keys( \get_plugins() );
        $found     = array();
        foreach ( $names as $name ) {
            $match = false;
            foreach ( $installed as $file ) {
                $file = (string) $file;
                if ( "{$name}.php" === $file ||
                    ( $name && $file === $name ) ||
                    ( dirname( $file ) === $name && '.' !== $name ) ) {
                    $match = (object) compact( 'name', 'file' );
                    break;
                }
            }
            if ( $match ) {
                $found[] = $match;
            } else {
                $out->warning( sprintf( Fetchers::MESSAGES['plugin'], $name ) );
            }
        }
        return $found;
    }

    private static function get_plugin_files( string $path ): array {
        $folder = dirname( WP_PLUGIN_DIR . '/' . $path );

        if ( WP_PLUGIN_DIR === $folder ) {
            return (array) $path;
        }

        $root     = rtrim( $folder, '/\\' ) . '/';
        $filtered = array();
        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator( $root, \RecursiveDirectoryIterator::SKIP_DOTS ),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ( $files as $file_info ) {
                if ( $file_info->isFile() ) {
                    $filtered[] = str_replace( '\\', '/', (string) substr( $file_info->getPathname(), strlen( $root ) ) );
                }
            }
        } catch ( \Exception $e ) {
            return $filtered;
        }
        return $filtered;
    }

    private static function check_file_checksum( string $path, $checksums ) {
        $absolute = WP_PLUGIN_DIR . '/' . $path;
        if ( is_array( $checksums ) && array_key_exists( 'sha256', $checksums ) ) {
            return in_array( hash_file( 'sha256', $absolute ), (array) $checksums['sha256'], true );
        }

        if ( ! is_array( $checksums ) || ! array_key_exists( 'md5', $checksums ) ) {
            return 'No matching checksum algorithm found';
        }

        return in_array( hash_file( 'md5', $absolute ), (array) $checksums['md5'], true );
    }

    private static function require_file_cap( Context $ctx, string $cap, string $what, string $screen, string $withheld ): void {
        if ( function_exists( 'wp_is_file_mod_allowed' ) && ! \wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            $ctx->out->error( sprintf( self::MSG_FILE_MODS, $withheld ) );
        }
        if ( ! \current_user_can( $cap ) ) {
            $ctx->out->error( sprintf( self::MSG_NEEDS_CAP, $cap, $what, $screen ) );
        }
    }
}
