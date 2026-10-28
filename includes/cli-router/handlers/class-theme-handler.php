<?php
namespace Easy_MCP_AI\Cli_Router\Handlers;

use Easy_MCP_AI\Cli_Router\Command_Handler;
use Easy_MCP_AI\Cli_Router\Context;
use Easy_MCP_AI\Cli_Router\Fetchers;
use Easy_MCP_AI\Cli_Router\Invocation;
use Easy_MCP_AI\Cli_Router\Read_Value;
use Easy_MCP_AI\Cli_Router\Values;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Theme_Handler extends Command_Handler {

    const NOT_MULTISITE  = 'This is not a multisite installation.';
    const SPECIFY_THEMES = 'Please specify one or more themes, or use --all.';

    const STATUS_SHORT = array(
        'inactive' => 'I',
        'active'   => 'A',
        'parent'   => 'P',
    );
    const STATUS_LONG  = array(
        'inactive' => 'Inactive',
        'active'   => 'Active',
        'parent'   => 'Parent',
    );
    const STATUS_COLOR = array(
        'inactive' => '',
        'active'   => '%g',
        'parent'   => '%p',
    );

    const GET_FIELDS = array( 'name', 'title', 'version', 'status', 'parent_theme', 'template_dir', 'stylesheet_dir', 'template', 'stylesheet', 'screenshot', 'description', 'author', 'tags', 'theme_root', 'theme_root_uri' );

    const PATH_FIELDS = array( 'template_dir', 'stylesheet_dir', 'theme_root' );

    const STATUS_TEMPLATE = "Theme %9{{slug}}%n details:\n    Name: {{name}}\n    Status: {{status}}%n\n    Version: {{version}}\n    Author: {{author}}\n";

    const COLOR_TOKENS = array( '%y', '%g', '%b', '%r', '%p', '%m', '%c', '%w', '%k', '%n', '%Y', '%G', '%B', '%R', '%P', '%M', '%C', '%W', '%K', '%N', '%3', '%2', '%4', '%1', '%5', '%6', '%7', '%0', '%F', '%U', '%8', '%9', '%_' );

    const NOTE_PATHS        = 'Paths are printed relative to the WordPress root (ABSPATH), not as the absolute server path WP-CLI prints (plan Q21); a path outside it is reduced to its last segment.';
    const NOTE_ENABLE_NOOP  = "Nothing changed: '%s' was already enabled %s. WP-CLI prints the same Success line.";
    const NOTE_DISABLE_NOOP = "Nothing changed: '%s' was not enabled %s. WP-CLI prints the same Success line.";

    const REFUSE_ESCAPE        = "wp_cli_run refuses the theme name '%s': it climbs out of the themes folder ('..' or an absolute path), and WordPress would answer whether that directory exists elsewhere on the server, which wp_cli_run never reads. Pass a theme folder name as `theme list` prints it.";
    const REFUSE_FILE_MODS     = 'File modifications are disabled on this site (DISALLOW_FILE_MODS in wp-config.php, or a file_mod_allowed filter), so WordPress offers no theme installs to search for, in wp-admin or here.';
    const HIDDEN_UPDATES       = 'Update markers are hidden: %s.';
    const HIDDEN_FILE_MODS     = 'file modifications are disabled on this site (DISALLOW_FILE_MODS, or a file_mod_allowed filter), so WordPress withholds update data from every user';
    const HIDDEN_NETWORK       = 'on a multisite network WordPress shows update data only to super admins (the update_themes capability)';
    const HIDDEN_CAPABILITY    = 'they need the update_themes capability, which your account lacks';

    const NOT_CAPTURED_SITE_ALLOWED    = "The site's allowedthemes option is recorded by change history only while it records every option (not in allowlist mode); wp_history_apply cannot restore it.";
    const NOT_CAPTURED_NETWORK_ALLOWED = "The network's allowedthemes option (wp_sitemeta) is recorded by change history only while its meta capture setting is on (the default); wp_history_apply cannot restore it.";

    private static $reader = null;

    public static function wp( string $key, array $args = array() ) {
        $live = array( __CLASS__, 'live_' . $key );
        if ( null !== self::$reader ) {
            return call_user_func( self::$reader, $key, $args, $live );
        }
        return call_user_func_array( $live, $args );
    }

    public static function use_reader( ?callable $reader ): void {
        self::$reader = $reader;
    }

    public function preflight_name( Invocation $inv, Context $ctx ): void {
        $name = isset( $inv->args[0] ) ? (string) $inv->args[0] : '';
        if ( self::escapes_theme_root( $name ) ) {
            $ctx->out->error( sprintf( self::REFUSE_ESCAPE, $name ) );
        }
    }

    public function preflight_search( Invocation $inv, Context $ctx ): void {
        if ( ! self::file_mods_allowed() ) {
            $ctx->out->error( self::REFUSE_FILE_MODS );
        }
        $ctx->require_cap( 'install_themes' );
    }

    public function preflight_allowed( Invocation $inv, Context $ctx ): void {
        if ( ! self::wp( 'is_multisite' ) ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }
        if ( Read_Value::flag( $inv->assoc, 'network' ) ) {
            $ctx->require_network( 'manage_network_themes' );
        }
    }

    public function get( Invocation $inv, Context $ctx ): void {
        $theme = Fetchers::of( 'theme' )->get_check( $inv->args[0], $ctx->out );
        $data  = self::theme_data( $theme->get_stylesheet() );
        if ( null !== $data['errors'] ) {
            $ctx->out->error( (string) $data['errors'] );
        }

        $vars      = (array) self::wp( 'theme_vars', array( $data['stylesheet'] ) );
        $abspath   = (string) self::wp( 'abspath' );
        $theme_obj = new \stdClass();
        foreach ( self::GET_FIELDS as $var ) {
            $theme_obj->$var = array_key_exists( $var, $vars ) ? $vars[ $var ] : null;
        }
        $theme_obj->status      = self::status_of( $data );
        $theme_obj->description = wordwrap( (string) $theme_obj->description );
        foreach ( self::PATH_FIELDS as $var ) {
            $theme_obj->$var = self::display_path( (string) $theme_obj->$var, $abspath );
        }

        $assoc = $inv->assoc;
        if ( empty( $assoc['fields'] ) ) {
            $assoc['fields'] = self::GET_FIELDS;
        }
        self::note_paths_if_shown( $ctx, $assoc );
        $this->formatter( $ctx, $assoc, self::list_fields(), 'theme' )->display_item( $theme_obj );
    }

    private static function note_paths_if_shown( Context $ctx, array $assoc ): void {
        $shown = isset( $assoc['field'] ) && is_string( $assoc['field'] ) ? array( $assoc['field'] ) : ( is_array( $assoc['fields'] ) ? $assoc['fields'] : explode( ',', (string) $assoc['fields'] ) );
        foreach ( $shown as $field ) {
            $field = (string) $field;
            if ( in_array( $field, self::PATH_FIELDS, true ) || in_array( 'theme_' . $field, self::PATH_FIELDS, true ) ) {
                $ctx->note( self::NOTE_PATHS );
                return;
            }
        }
    }

    public function path( Invocation $inv, Context $ctx ): void {
        if ( empty( $inv->args ) ) {
            $path = (string) self::wp( 'wp_content_dir' ) . '/themes';
        } else {
            $theme = Fetchers::of( 'theme' )->get_check( array_values( $inv->args )[0], $ctx->out );
            $data  = self::theme_data( $theme->get_stylesheet() );
            $path  = (string) $data['stylesheet_dir'];
            if ( ! Read_Value::flag( $inv->assoc, 'dir' ) ) {
                $path .= '/style.css';
            }
        }
        $ctx->note( self::NOTE_PATHS );
        $ctx->out->line( self::display_path( $path, (string) self::wp( 'abspath' ) ) );
    }

    public function is_installed( Invocation $inv, Context $ctx ): void {
        $theme = (array) self::wp( 'exists', array( (string) $inv->args[0] ) );
        $ctx->out->halt( ! empty( $theme['exists'] ) ? 0 : 1 );
    }

    public function is_active( Invocation $inv, Context $ctx ): void {
        $theme = (array) self::wp( 'exists', array( (string) $inv->args[0] ) );
        if ( empty( $theme['exists'] ) ) {
            $ctx->out->halt( 1 );
        }
        $active = self::active_dirs();
        $dir    = (string) $theme['stylesheet_dir'];
        $ctx->out->halt( ( $dir === $active[0] || $dir === $active[1] ) ? 0 : 1 );
    }

    public function status( Invocation $inv, Context $ctx ): void {
        $args = array_values( $inv->args );
        if ( isset( $args[0] ) ) {
            $theme = Fetchers::of( 'theme' )->get_check( $args[0], $ctx->out );
            $data  = self::theme_data( $theme->get_stylesheet() );
            if ( null !== $data['errors'] ) {
                $ctx->out->error( (string) $data['errors'] );
            }
        }

        $see_updates = $this->see_updates( $ctx );
        if ( $see_updates ) {
            self::wp( 'wp_update_themes' );
        }

        if ( empty( $args ) ) {
            $this->status_all( $ctx, $see_updates );
            return;
        }

        $fetcher = Fetchers::of( 'theme' );
        $theme   = $fetcher->get_check( $args[0], $ctx->out );
        $data    = self::theme_data( $theme->get_stylesheet() );
        $version = $data['Version'];
        if ( $see_updates && self::has_update( $data['stylesheet'] ) ) {
            $version .= ' (%gUpdate available%n)';
        }
        $status = self::STATUS_COLOR[ self::status_of( $data ) ] . self::STATUS_LONG[ self::status_of( $data ) ];
        $ctx->out->out(
            self::decolorize(
                strtr(
                    self::STATUS_TEMPLATE,
                    array(
                        '{{slug}}'    => (string) $data['stylesheet'],
                        '{{status}}'  => $status,
                        '{{version}}' => self::mustache_value( $version ),
                        '{{name}}'    => self::mustache_value( $data['Name'] ),
                        '{{author}}'  => self::mustache_value( $data['Author'] ),
                    )
                )
            )
        );
    }

    public function search( Invocation $inv, Context $ctx ): void {
        $term       = (string) array_values( $inv->args )[0];
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
        $formatter = $this->formatter( $ctx, $assoc_args, self::list_fields(), 'theme' );

        $api = (array) self::wp(
            'themes_api',
            array(
                'query_themes',
                array(
                    'per_page' => (int) $assoc_args['per-page'],
                    'page'     => (int) $assoc_args['page'],
                    'search'   => $term,
                    'fields'   => $fields,
                ),
            )
        );

        if ( isset( $api['wp_error'] ) ) {
            $ctx->out->error( (string) $api['wp_error']['message'] . self::translate( ' Try again' ) );
        }
        if ( ! isset( $api['themes'] ) ) {
            $ctx->out->error( self::translate( 'API error. Try Again.' ) );
        }

        $items = self::search_items( (array) $api['themes'] );

        if ( 'table' === $format ) {
            $info  = isset( $api['info'] ) ? (array) $api['info'] : array();
            $count = isset( $info['results'] ) ? $info['results'] : 'unknown';
            $ctx->out->success( sprintf( 'Showing %s of %s %s.', count( $items ), $count, 'themes' ) );
        }

        $formatter->display_items( $items );
    }

    private static function search_items( array $themes ): array {
        $items = array();
        foreach ( $themes as $item ) {
            if ( is_array( $item ) ) {
                $item      = (object) $item;
                $item->url = 'https://wordpress.org/themes/' . ( isset( $item->slug ) ? $item->slug : '' ) . '/';
            }
            $items[] = $item;
        }
        return $items;
    }

    public function enable( Invocation $inv, Context $ctx ): void {
        $this->allowed_write( $inv, $ctx, true );
    }

    public function disable( Invocation $inv, Context $ctx ): void {
        $this->allowed_write( $inv, $ctx, false );
    }

    public function describe_allowed( Invocation $inv ): array {
        $enable  = 'enable' === (string) $inv->leaf['name'];
        $network = (bool) Read_Value::flag( $inv->assoc, 'network' );
        $name    = (string) $inv->args[0];
        $scope   = $network ? 'for the network' : 'for this site';
        $allowed = self::wp( $network ? 'site_option' : 'option', array( 'allowedthemes' ) );
        $now     = is_array( $allowed ) && ! empty( $allowed[ $name ] );
        $before  = $now ? 'enabled ' . $scope : 'not enabled ' . $scope;
        if ( $enable ) {
            $after = $now ? 'stays enabled (no change)' : 'enabled ' . $scope;
        } else {
            $after = $now ? 'disabled ' . $scope : 'stays disabled (no change)';
        }
        return array( sprintf( "Theme '%s': %s → %s", $name, $before, $after ) );
    }

    public function split_activate( Invocation $inv, Context $ctx ): ?array {
        if ( ! Read_Value::flag( $inv->assoc, 'activate' ) ) {
            return null;
        }
        $theme   = (string) $inv->args[0];
        $command = 'theme enable ' . self::shell_word( $theme ) . ( Read_Value::flag( $inv->assoc, 'network' ) ? ' --network' : '' );
        return array(
            array(
                'tool'      => 'wp_cli_run',
                'arguments' => array( 'command' => $command ),
            ),
            array(
                'tool'      => 'wp_switch_theme',
                'arguments' => array( 'stylesheet' => $theme ),
                'render'    => 'render_activate',
                'label'     => 'wp_switch_theme ' . $theme,
            ),
        );
    }

    public function render_activate( Invocation $inv, Context $ctx, array $result, array &$state ): bool {
        if ( empty( $result['ok'] ) ) {
            $ctx->out->error( (string) $result['error'] );
        }
        $payload = (array) $result['payload'];
        $label   = self::header_text( isset( $payload['name'] ) ? $payload['name'] : (string) $inv->args[0] );
        if ( empty( $payload['changed'] ) ) {
            $ctx->out->warning( "The '$label' theme is already active." );
            $ctx->note( sprintf( "Nothing changed: '%s' was already the active theme.", isset( $payload['stylesheet'] ) ? (string) $payload['stylesheet'] : (string) $inv->args[0] ) );
            return true;
        }
        $ctx->mark_changed();
        $ctx->out->success( "Switched to '$label' theme." );
        return true;
    }

    private function allowed_write( Invocation $inv, Context $ctx, bool $enable ): void {
        if ( ! self::wp( 'is_multisite' ) ) {
            $ctx->out->error( self::NOT_MULTISITE );
        }

        $theme   = Fetchers::of( 'theme' )->get_check( $inv->args[0], $ctx->out );
        $data    = self::theme_data( $theme->get_stylesheet() );
        $name    = self::header_text( $data['Name'] );
        $network = ! empty( $inv->assoc['network'] );
        $read    = $network ? 'site_option' : 'option';
        $write   = $network ? 'update_site_option' : 'update_option';
        $scope   = $network ? 'for the network' : 'for this site';
        $ctx->not_captured( $network ? self::NOT_CAPTURED_NETWORK_ALLOWED : self::NOT_CAPTURED_SITE_ALLOWED );

        $allowed_themes = self::allowed_after( self::wp( $read, array( 'allowedthemes' ) ), $data['stylesheet'], $enable );
        if ( self::wp( $write, array( 'allowedthemes', $allowed_themes ) ) ) {
            $ctx->mark_changed();
        } else {
            $ctx->note( sprintf( $enable ? self::NOTE_ENABLE_NOOP : self::NOTE_DISABLE_NOOP, $data['stylesheet'], $scope ) );
        }

        $ctx->out->success( self::allowed_success( $network, $enable, $name ) );
    }

    private static function allowed_after( $allowed_themes, string $stylesheet, bool $enable ) {
        if ( $enable ) {
            if ( empty( $allowed_themes ) ) {
                $allowed_themes = array();
            }
            $allowed_themes[ $stylesheet ] = true;
        } elseif ( ! empty( $allowed_themes[ $stylesheet ] ) ) {
            unset( $allowed_themes[ $stylesheet ] );
        }
        return $allowed_themes;
    }

    private static function allowed_success( bool $network, bool $enable, string $name ): string {
        if ( $network ) {
            return ( $enable ? 'Network enabled' : 'Network disabled' ) . " the '$name' theme.";
        }
        return ( $enable ? 'Enabled' : 'Disabled' ) . " the '$name' theme.";
    }

    public static function theme_data( string $stylesheet ): array {
        $data = self::wp( 'theme', array( $stylesheet ) );
        if ( ! is_array( $data ) ) {
            return array(
                'stylesheet'     => $stylesheet,
                'template'       => $stylesheet,
                'Name'           => $stylesheet,
                'Version'        => '',
                'Author'         => '',
                'Description'    => '',
                'RequiresWP'     => '',
                'RequiresPHP'    => '',
                'errors'         => null,
                'stylesheet_dir' => '',
            );
        }
        return $data;
    }

    public static function active_dirs(): array {
        $dirs = (array) self::wp( 'active_dirs' );
        return array( (string) ( $dirs[0] ?? '' ), (string) ( $dirs[1] ?? '' ) );
    }

    public static function status_of( array $data ): string {
        $active = self::active_dirs();
        $dir    = (string) $data['stylesheet_dir'];
        if ( $dir === $active[0] ) {
            return 'active';
        }
        if ( $dir === $active[1] ) {
            return 'parent';
        }
        return 'inactive';
    }

    public static function check_optional_args_and_all( array $args, bool $all, Context $ctx, string $verb = 'install' ): array {
        if ( $all ) {
            $args = array();
            foreach ( array_keys( (array) self::wp( 'themes' ) ) as $stylesheet ) {
                $args[] = basename( (string) $stylesheet );
            }
        }
        if ( empty( $args ) ) {
            if ( ! $all ) {
                $ctx->out->error( self::SPECIFY_THEMES );
            }
            $ctx->out->success( 'No themes ' . Values::past_tense_verb( $verb ) . '.' );
        }
        return array_values( array_map( 'strval', $args ) );
    }

    public static function list_fields(): array {
        $fields = array( 'name', 'status', 'update', 'version', 'update_version', 'auto_update' );
        if ( self::wp( 'is_multisite' ) ) {
            $fields[] = 'enabled';
        }
        return $fields;
    }

    public static function has_update( string $stylesheet ): bool {
        $update = self::wp( 'update_transient' );
        return is_array( $update ) && isset( $update['response'][ $stylesheet ] );
    }

    public static function file_mods_allowed(): bool {
        return ! function_exists( 'wp_is_file_mod_allowed' ) || (bool) \wp_is_file_mod_allowed( 'capability_update_core' );
    }

    public static function hidden_updates_cause(): ?string {
        if ( \current_user_can( 'update_themes' ) ) {
            return null;
        }
        if ( ! self::file_mods_allowed() ) {
            return self::HIDDEN_FILE_MODS;
        }
        if ( \is_multisite() && ! \is_super_admin() ) {
            return self::HIDDEN_NETWORK;
        }
        return self::HIDDEN_CAPABILITY;
    }

    private static function shell_word( string $word ): string {
        if ( '' !== $word && preg_match( '#^[A-Za-z0-9_\-.,:/@%+=]+\z#', $word ) ) {
            return $word;
        }
        return "'" . str_replace( "'", "'\\''", $word ) . "'";
    }

    public static function decolorize( string $string ): string {
        $string = str_replace( '%%', '%¾', $string );
        $string = str_replace( self::COLOR_TOKENS, '', $string );
        return str_replace( '%¾', '%', $string );
    }

    public static function display_path( string $path, string $abspath ): string {
        $path = str_replace( '\\', '/', $path );
        $root = rtrim( str_replace( '\\', '/', $abspath ), '/' ) . '/';
        if ( '' !== $path && '/' !== $root && 0 === strpos( $path, $root ) ) {
            return substr( $path, strlen( $root ) );
        }
        return '' === $path ? '' : basename( $path );
    }

    public static function escapes_theme_root( string $name ): bool {
        $name = str_replace( '\\', '/', $name );
        if ( 0 === strpos( $name, '/' ) || 1 === preg_match( '#^[A-Za-z]:#', $name ) ) {
            return true;
        }
        return in_array( '..', explode( '/', $name ), true );
    }

    public static function header_text( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    private function status_all( Context $ctx, bool $see_updates ): void {
        $items = $this->status_items( $see_updates );
        $n     = count( $items );
        $ctx->out->log( sprintf( '%d installed %s:', $n, 1 === $n ? 'theme' : 'themes' ) );

        $padding = 0;
        foreach ( $items as $details ) {
            $padding = max( $padding, strlen( $details['name'] ) );
        }
        foreach ( $items as $details ) {
            $line  = 'available' === $details['update'] ? ' %yU%n' : '  ';
            $line .= self::STATUS_COLOR[ $details['status'] ] . self::STATUS_SHORT[ $details['status'] ];
            $line .= ' ' . str_pad( $details['name'], $padding ) . '%n';
            if ( ! empty( $details['version'] ) ) {
                $line .= ' ' . $details['version'];
            }
            $ctx->out->line( self::decolorize( $line ) );
        }
        $ctx->out->line();

        $legend   = array();
        $statuses = array();
        foreach ( $items as $details ) {
            $statuses[ $details['status'] ] = true;
        }
        foreach ( array_keys( $statuses ) as $status ) {
            $legend[] = sprintf( '%s%s = %s%%n', self::STATUS_COLOR[ $status ], self::STATUS_SHORT[ $status ], self::STATUS_LONG[ $status ] );
        }
        foreach ( $items as $details ) {
            if ( 'available' === $details['update'] ) {
                $legend[] = '%yU = Update Available%n';
                break;
            }
        }
        $ctx->out->line( 'Legend: ' . self::decolorize( implode( ', ', $legend ) ) );
    }

    private function status_items( bool $see_updates ): array {
        $update   = $see_updates ? self::wp( 'update_transient' ) : null;
        $response = is_array( $update ) && isset( $update['response'] ) && is_array( $update['response'] ) ? $update['response'] : array();
        $versions = (array) self::wp( 'versions' );
        $items    = array();
        foreach ( (array) self::wp( 'themes' ) as $key => $data ) {
            $stylesheet = (string) $data['stylesheet'];
            $items[]    = array(
                'name'    => (string) $key,
                'status'  => self::status_of( $data ),
                'update'  => self::update_state( isset( $response[ $stylesheet ] ) ? (array) $response[ $stylesheet ] : null, $versions ),
                'version' => $data['Version'],
            );
        }
        return $items;
    }

    private static function update_state( ?array $update_info, array $versions ): string {
        if ( null === $update_info ) {
            return 'none';
        }
        $requires     = isset( $update_info['requires'] ) ? $update_info['requires'] : null;
        $requires_php = isset( $update_info['requires_php'] ) ? $update_info['requires_php'] : null;
        if ( ! empty( $requires_php ) && ! version_compare( (string) $versions['php'], (string) $requires_php, '>=' ) ) {
            return 'unavailable';
        }
        if ( ! empty( $requires ) && ! version_compare( (string) $versions['wp'], (string) $requires, '>=' ) ) {
            return 'unavailable';
        }
        return 'available';
    }

    private function see_updates( Context $ctx ): bool {
        $cause = self::hidden_updates_cause();
        if ( null !== $cause ) {
            $ctx->out->warning( sprintf( self::HIDDEN_UPDATES, $cause ) );
            return false;
        }
        return true;
    }

    private static function mustache_value( $value ): string {
        return is_scalar( $value ) ? (string) $value : '';
    }

    private static function translate( string $text ): string {
        return function_exists( '__' ) ? (string) \__( $text ) : $text; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.MissingArgDomain -- WP-CLI translates these exact strings with core's default text domain.
    }

    public static function live_themes(): array {
        $out = array();
        foreach ( \wp_get_themes() as $key => $theme ) {
            $out[ (string) $key ] = self::live_theme_data( $theme );
        }
        return $out;
    }

    public static function live_theme( string $stylesheet ): ?array {
        $themes = \wp_get_themes( array( 'errors' => null ) );
        return isset( $themes[ $stylesheet ] ) ? self::live_theme_data( $themes[ $stylesheet ] ) : null;
    }

    public static function live_theme_vars( string $stylesheet ): array {
        $themes = \wp_get_themes( array( 'errors' => null ) );
        if ( ! isset( $themes[ $stylesheet ] ) ) {
            return array();
        }
        $theme = $themes[ $stylesheet ];
        $vars  = array();
        foreach ( self::GET_FIELDS as $var ) {
            $vars[ $var ] = $theme->$var;
        }
        return $vars;
    }

    public static function live_exists( string $name ): array {
        $theme = \wp_get_theme( $name );
        return array(
            'exists'         => (bool) $theme->exists(),
            'stylesheet_dir' => (string) $theme->get_stylesheet_directory(),
        );
    }

    public static function live_active_dirs(): array {
        return array( (string) \get_stylesheet_directory(), (string) \get_template_directory() );
    }

    public static function live_option( string $name ) {
        return \get_option( $name );
    }

    public static function live_site_option( string $name ) {
        return \get_site_option( $name );
    }

    public static function live_update_option( string $name, $value ): bool {
        return (bool) \update_option( $name, $value );
    }

    public static function live_update_site_option( string $name, $value ): bool {
        return (bool) \update_site_option( $name, $value );
    }

    public static function live_delete_site_option( string $name ): bool {
        return (bool) \delete_site_option( $name );
    }

    public static function live_is_multisite(): bool {
        return (bool) \is_multisite();
    }

    public static function live_abspath(): string {
        return (string) ABSPATH;
    }

    public static function live_wp_content_dir(): string {
        return defined( 'WP_CONTENT_DIR' ) ? (string) WP_CONTENT_DIR : '';
    }

    public static function live_update_transient(): ?array {
        $transient = \get_site_transient( 'update_themes' );
        if ( ! is_object( $transient ) ) {
            return null;
        }
        $out = array(
            'checked'   => isset( $transient->checked ) && is_array( $transient->checked ) ? $transient->checked : array(),
            'response'  => array(),
            'no_update' => array(),
        );
        foreach ( array( 'response', 'no_update' ) as $key ) {
            if ( isset( $transient->$key ) && is_array( $transient->$key ) ) {
                foreach ( $transient->$key as $slug => $entry ) {
                    if ( null !== $entry ) {
                        $out[ $key ][ (string) $slug ] = (array) $entry;
                    }
                }
            }
        }
        return $out;
    }

    public static function live_wp_update_themes(): bool {
        if ( ! function_exists( 'wp_update_themes' ) ) {
            require_once ABSPATH . WPINC . '/update.php';
        }
        \wp_update_themes();
        return true;
    }

    public static function live_versions(): array {
        global $wp_version;
        return array(
            'wp'  => (string) $wp_version,
            'php' => PHP_VERSION,
        );
    }

    public static function live_themes_api( string $action, array $args ): array {
        if ( ! function_exists( 'themes_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        $api = \themes_api( $action, $args );
        if ( \is_wp_error( $api ) ) {
            return array(
                'wp_error' => array(
                    'code'    => (string) $api->get_error_code(),
                    'message' => (string) $api->get_error_message(),
                ),
            );
        }
        $decoded = json_decode( (string) \wp_json_encode( $api ), true );
        return is_array( $decoded ) ? $decoded : array();
    }

    public static function live_customizer_owned( array $names ): array {
        $candidates = array();
        foreach ( $names as $name ) {
            $name = (string) $name;
            if ( '' === trim( $name ) || false !== strpbrk( $name, '[]' ) || null !== \Easy_MCP_AI\Tools\Appearance\Customizer_Settings::denied_reason( $name ) ) {
                continue;
            }
            $candidates[] = $name;
        }
        if ( ! $candidates ) {
            return array();
        }
        try {
            return \Easy_MCP_AI\Tools\Appearance\Customizer_Settings::with_manager(
                static function ( object $manager ) use ( $candidates ): array {
                    $owned = array();
                    foreach ( $candidates as $name ) {
                        $setting = $manager->get_setting( $name );
                        if ( ! $setting ) {
                            $manager->add_dynamic_settings( array( $name ) );
                            $setting = $manager->get_setting( $name );
                        }
                        if ( $setting && 'theme_mod' === $setting->type && 'WP_Customize_Filter_Setting' !== get_class( $setting ) ) {
                            $owned[] = $name;
                        }
                    }
                    return $owned;
                }
            );
        } catch ( \Throwable $e ) {
            return $candidates;
        }
    }

    public static function live_theme_mods(): array {
        $mods = \get_theme_mods();
        return is_array( $mods ) ? $mods : array();
    }

    public static function live_remove_theme_mod( string $name ): bool {
        $before = \get_theme_mods();
        \remove_theme_mod( $name );
        return is_array( $before ) && isset( $before[ $name ] );
    }

    public static function live_remove_theme_mods(): bool {
        $current = \get_option( 'stylesheet' );
        $name    = \get_option( 'current_theme' );
        if ( false === $name ) {
            $name = \wp_get_theme()->get( 'Name' );
        }
        $existed = false !== \get_option( 'theme_mods_' . $current ) || false !== \get_option( 'mods_' . $name );
        \remove_theme_mods();
        return $existed;
    }

    public static function live_theme_mods_options(): array {
        $name = \get_option( 'current_theme' );
        if ( false === $name ) {
            $name = \wp_get_theme()->get( 'Name' );
        }
        return array( 'theme_mods_' . \get_option( 'stylesheet' ), 'mods_' . $name );
    }

    public static function live_auto_update_enabled(): bool {
        return ! function_exists( 'wp_is_auto_update_enabled_for_type' ) || (bool) \wp_is_auto_update_enabled_for_type( 'theme' );
    }

    private static function live_theme_data( \WP_Theme $theme ): array {
        $errors = $theme->errors();
        return array(
            'stylesheet'     => (string) $theme->get_stylesheet(),
            'template'       => (string) $theme->get_template(),
            'Name'           => $theme->get( 'Name' ),
            'Version'        => $theme->get( 'Version' ),
            'Author'         => $theme->get( 'Author' ),
            'Description'    => $theme->get( 'Description' ),
            'RequiresWP'     => $theme->get( 'RequiresWP' ),
            'RequiresPHP'    => $theme->get( 'RequiresPHP' ),
            'errors'         => \is_wp_error( $errors ) ? (string) $errors->get_error_message() : null,
            'stylesheet_dir' => (string) $theme->get_stylesheet_directory(),
        );
    }
}
