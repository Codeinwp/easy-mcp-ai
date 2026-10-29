<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;
use Easy_MCP_AI\Tools\Package_Source;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Install_Plugin extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;
    use Package_Source;

    public function get_name() {
        return 'wp_install_plugin';
    }

    public function get_description() {
        return 'Installs a plugin, as Plugins → Add New does: from the wordpress.org directory by `slug`, or from a ZIP given as `url` (an HTTPS link; a wordpress.org plugin page and github.com/<owner>/<repo>/releases/latest work too), `file` (a file the client hands over) or `content_base64` (the ZIP inline, at most 2 MB). Pass exactly one. The plugin is NOT activated: call wp_activate_plugin with the returned `plugin`. Optional: `version` (slug only: that wordpress.org version, or "dev" for the development version), `folder` (ZIP only: the folder under wp-content/plugins, instead of the one the ZIP names), `force` (boolean, default false: replace a copy in the same folder). Returns { slug, plugin (the plugin file, e.g. "akismet/akismet.php"), name, version, changed, replaced, active, messages (the installer log, URLs cut to their host) }; for a ZIP slug is null and it adds source (host and path, never the query), sha256 and bytes. Refused when the folder is taken (pass force: true, or use wp_update_plugin), when wordpress.org does not list the slug or version, when a download is not public HTTPS, over 50 MB or not a ZIP, when WordPress refuses the package (no plugin header, its Requires PHP / Requires at least), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the plugins folder or wp-content directly, for a ZIP without the upload_plugins capability, on multisite unless you are a super admin with manage_network_plugins, and for Easy MCP AI\'s own folder. Off by default; an administrator switches it on. Runs within this request and reaches the download host.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'install_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    public function is_disabled_by_default() {
        return true;
    }

    public function get_approval_policy( array $arguments ) {
        return 'always';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'slug'           => array(
                    'type'        => 'string',
                    'description' => 'A wordpress.org plugin slug: the last part of its wordpress.org address, e.g. "akismet" for wordpress.org/plugins/akismet. Pass a link to a ZIP as url instead.',
                ),
                'url'            => array(
                    'type'        => 'string',
                    'description' => 'An HTTPS link to the plugin\'s ZIP file (signed download links work), a wordpress.org plugin page, or github.com/<owner>/<repo>/releases/latest. Public hosts only, at most 50 MB.',
                ),
                'file'           => array(
                    'type'        => 'object',
                    'description' => 'A ZIP the client hands over (a file the user attached). The client fills this in; download_url is a temporary HTTPS URL the site fetches.',
                    'properties'  => array(
                        'download_url' => array( 'type' => 'string' ),
                        'file_id'      => array( 'type' => 'string' ),
                        'mime_type'    => array( 'type' => 'string' ),
                        'file_name'    => array( 'type' => 'string' ),
                    ),
                    'required'    => array( 'download_url', 'file_id' ),
                ),
                'content_base64' => array(
                    'type'        => 'string',
                    'description' => 'The plugin\'s ZIP file, base64-encoded (a data: URI prefix is accepted), at most 2 MB decoded: a small plugin, or one you wrote.',
                ),
                'folder'         => array(
                    'type'        => 'string',
                    'description' => 'Optional, with url, file or content_base64: the folder name under wp-content/plugins, instead of the one the ZIP names (a GitHub source archive is named after its repository).',
                ),
                'version'        => array(
                    'type'        => 'string',
                    'description' => 'Optional, with slug only. A version wordpress.org lists for this plugin, e.g. "5.3.2", or "dev" for the development version. Omit to install the latest version.',
                ),
                'force'          => array(
                    'type'        => 'boolean',
                    'description' => 'Replace a copy that is already installed in the plugin\'s folder (its files are overwritten; it keeps its active state). Default false: an installed plugin is refused.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function get_definition() {
        $definition                               = parent::get_definition();
        $definition['_meta']                      = isset( $definition['_meta'] ) && is_array( $definition['_meta'] ) ? $definition['_meta'] : array();
        $definition['_meta']['openai/fileParams'] = array( 'file' );
        return $definition;
    }

    public function redact_for_record( array $arguments ) {
        return self::package_record( $arguments );
    }

    public function describe( array $arguments ) {
        try {
            $source = $this->parse_package_source( $arguments, 'plugin' );
            $force  = $this->parse_force( $arguments );
            if ( 'slug' === $source['type'] ) {
                return $this->describe_wporg( (string) $source['slug'], $this->parse_package_version( $arguments, true ), $force );
            }
        } catch ( \Throwable $e ) {
            return parent::describe( self::package_preview_arguments( $arguments ) );
        }
        return sprintf(
            /* translators: 1: where the ZIP comes from, 2: the target folder, 3: optional replacement note */
            __( 'Install a plugin from %1$s into %2$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            self::describe_package_source( $source, 'plugin' ),
            null !== $source['folder'] ? 'wp-content/plugins/' . $source['folder'] : __( 'the wp-content/plugins folder the ZIP names', 'easy-mcp-ai' ),
            $force ? __( ', replacing any copy already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    private function describe_wporg( string $slug, ?string $version, bool $force ): string {
        return sprintf(
            /* translators: 1: plugin slug, 2: "the latest version" or "version 1.2", 3: optional replacement note */
            __( 'Install the plugin "%1$s" from wordpress.org (%2$s) into wp-content/plugins/%1$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            $slug,
            null === $version ? __( 'the latest version', 'easy-mcp-ai' ) : sprintf( /* translators: %s: version */ __( 'version %s', 'easy-mcp-ai' ), $version ),
            $force ? __( ', replacing any copy already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    public function execute( array $arguments ) {
        $source = $this->parse_package_source( $arguments, 'plugin' );
        if ( 'slug' === $source['type'] ) {
            return $this->install_from_wporg( (string) $source['slug'], $arguments );
        }
        return $this->install_from_package( $source, $arguments );
    }

    private function install_from_wporg( string $slug, array $arguments ): array {
        $version = $this->parse_package_version( $arguments, true );
        $force   = $this->parse_force( $arguments );

        $this->refuse_plugin_installs_without_rights();

        $this->load_plugin_admin_functions();
        $replacing = $this->refuse_existing_install( $slug, $force );

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( WP_PLUGIN_DIR, 'wp-content/plugins' );
        $this->raise_time_limit();

        $offer = $this->wporg_package( 'plugin', $slug, $version );

        $skin     = $this->new_upgrader_skin();
        $upgrader = $this->new_plugin_upgrader( $skin );
        $package  = $offer['package'];
        list( $result, $printed ) = $this->without_output(
            function () use ( $upgrader, $package, $replacing ) {
                return $upgrader->install( $package, array( 'overwrite_package' => $replacing ) );
            }
        );

        $this->interpret_install_result( $result, $skin, 'Plugin installation failed.' );

        return $this->result( $slug, $offer, $upgrader, $skin, $replacing, '' !== $printed );
    }

    private function install_from_package( array $source, array $arguments ): array {
        $force = $this->parse_force( $arguments );

        $this->refuse_plugin_installs_without_rights();
        if ( ! current_user_can( 'upload_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to upload plugins on this site, so a plugin cannot be installed from a URL or a ZIP. A wordpress.org slug may still work.' );
        }
        $this->load_plugin_admin_functions();
        if ( null !== $source['folder'] ) {
            $taken = $this->plugin_destination( (string) $source['folder'], $force, null );
            if ( is_wp_error( $taken ) ) {
                throw new \RuntimeException( $taken->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( WP_PLUGIN_DIR, 'wp-content/plugins' );
        $this->raise_time_limit();

        $package = $this->fetch_package( $source, 'plugin' );
        try {
            $skin     = $this->new_upgrader_skin();
            $upgrader = $this->new_plugin_upgrader( $skin );
            list( $result, $printed, $replacing ) = $this->install_package_file(
                $upgrader,
                $package,
                $force,
                function ( string $folder, object $upgrader ) use ( $force ) {
                    return $this->plugin_destination( $folder, $force, $upgrader );
                }
            );
            $this->interpret_install_result( $result, $skin, 'Plugin installation failed.' );
            return $this->package_result( $package, $upgrader, $skin, $replacing, '' !== $printed );
        } finally {
            $this->discard_package( $package );
        }
    }

    private function refuse_plugin_installs_without_rights(): void {
        $this->refuse_when_file_mods_are_off( 'plugins cannot be installed' );
        if ( ! current_user_can( 'install_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to install plugins on this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_plugins', 'installing plugins' );
    }

    private function plugin_destination( string $folder, bool $force, ?object $upgrader ) {
        if ( self::is_own_folder( $folder ) ) {
            return new \WP_Error( 'easy_mcp_ai_own_folder', 'Easy MCP AI cannot install over its own folder over MCP: replacing its files mid-request would cut off this connection. Use wp-admin → Plugins instead.' );
        }
        $file = self::plugin_in_folder( get_plugins(), $folder );
        if ( null === $file && ! $this->plugin_folder_exists( $folder ) ) {
            return false;
        }
        if ( $force ) {
            return true;
        }
        $data    = null !== $upgrader && isset( $upgrader->new_plugin_data ) && is_array( $upgrader->new_plugin_data ) ? $upgrader->new_plugin_data : array();
        $package = ! empty( $data['Version'] ) && is_scalar( $data['Version'] ) ? sprintf( '; this ZIP holds version %s', (string) $data['Version'] ) : '';
        $holds   = null !== $file ? sprintf( 'holds %s', $file ) : 'already exists';
        return new \WP_Error( 'easy_mcp_ai_folder_exists', substr( sprintf( 'The plugin folder "%1$s" %2$s%3$s. Pass force: true to replace it.', $folder, $holds, $package ), 0, 200 ) );
    }

    private function parse_force( array $arguments ): bool {
        return isset( $arguments['force'] ) && rest_sanitize_boolean( $arguments['force'] );
    }

    private function refuse_existing_install( string $slug, bool $force ): bool {
        self::refuse_self_reinstall( $slug, $force );

        $installed = get_plugins();
        $file      = self::plugin_in_folder( $installed, $slug );
        if ( null !== $file ) {
            if ( ! $force ) {
                throw new \RuntimeException( sprintf( 'The plugin "%s" is already installed as %s. Pass force: true to replace it, use wp_update_plugin to update it, or wp_activate_plugin to activate it.', $slug, $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return true;
        }

        $this->refuse_copy_in_another_folder( $installed, $slug );

        if ( $this->plugin_folder_exists( $slug ) ) {
            if ( ! $force ) {
                throw new \RuntimeException( sprintf( 'A folder named "%s" already exists in wp-content/plugins but holds no plugin. Pass force: true to replace it.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return true;
        }
        return false;
    }

    private static function refuse_self_reinstall( string $slug, bool $force ): void {
        if ( $force && self::is_own_folder( $slug ) ) {
            throw new \RuntimeException( 'Easy MCP AI cannot reinstall itself over MCP: replacing its files mid-request would cut off this connection. Use wp-admin → Plugins instead.' );
        }
    }

    private static function is_own_folder( string $folder ): bool {
        $own = defined( 'EASY_MCP_AI_PLUGIN_BASENAME' ) ? (string) EASY_MCP_AI_PLUGIN_BASENAME : '';
        return '' !== $own && false !== strpos( $own, '/' ) && strtolower( dirname( $own ) ) === strtolower( $folder );
    }

    private static function plugin_in_folder( array $installed, string $slug ): ?string {
        foreach ( array_keys( $installed ) as $file ) {
            if ( 0 === strpos( (string) $file, $slug . '/' ) ) {
                return (string) $file;
            }
        }
        return null;
    }

    private function refuse_copy_in_another_folder( array $installed, string $slug ): void {
        $transient = get_site_transient( 'update_plugins' );
        foreach ( array( 'response', 'no_update' ) as $bucket ) {
            $entries = ( is_object( $transient ) && isset( $transient->$bucket ) && is_array( $transient->$bucket ) ) ? $transient->$bucket : array();
            foreach ( $entries as $file => $entry ) {
                if ( isset( $installed[ $file ] ) && $slug === (string) $this->update_field( $entry, 'slug' ) ) {
                    throw new \RuntimeException( sprintf( 'The plugin "%s" is already installed as %s. Installing it again would add a second copy; use wp_update_plugin to update it.', $slug, $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
        }
    }

    protected function plugin_folder_exists( string $slug ): bool {
        return is_dir( WP_PLUGIN_DIR . '/' . $slug );
    }

    protected function new_plugin_upgrader( \WP_Ajax_Upgrader_Skin $skin ): object {
        return new \Plugin_Upgrader( $skin );
    }

    private function result( string $slug, array $offer, object $upgrader, \WP_Ajax_Upgrader_Skin $skin, bool $replacing, bool $printed ): array {
        $file = self::installed_main_file( $upgrader );
        $data = null !== $file ? self::installed_headers( $file ) : array();

        $result = array(
            'slug'     => $slug,
            'plugin'   => $file,
            'name'     => ! empty( $data['Name'] ) ? (string) $data['Name'] : $offer['name'],
            'version'  => ! empty( $data['Version'] ) ? (string) $data['Version'] : $offer['version'],
            'changed'  => true,
            'replaced' => $replacing,
            'active'   => null !== $file && ( is_plugin_active( $file ) || is_plugin_active_for_network( $file ) ),
            'messages' => $this->upgrade_messages( $skin ),
        );
        if ( null === $file ) {
            $result['note'] = 'WordPress installed the package but could not tell which file is its main plugin file; use wp_list_plugins to find it.';
        }
        if ( $printed ) {
            $result['output_discarded'] = true;
        }
        return $result;
    }

    private function package_result( array $package, object $upgrader, \WP_Ajax_Upgrader_Skin $skin, bool $replacing, bool $printed ): array {
        $data   = isset( $upgrader->new_plugin_data ) && is_array( $upgrader->new_plugin_data ) ? $upgrader->new_plugin_data : array();
        $result = $this->result(
            '',
            array(
                'package' => '',
                'name'    => isset( $data['Name'] ) && is_scalar( $data['Name'] ) ? (string) $data['Name'] : '',
                'version' => isset( $data['Version'] ) && is_scalar( $data['Version'] ) ? (string) $data['Version'] : '',
            ),
            $upgrader,
            $skin,
            $replacing,
            $printed
        );
        $result['slug']     = null;
        $result['messages'] = array_slice( array_merge( $package['notes'], $result['messages'] ), -20 );
        return array_merge( $result, self::package_fields( $package ) );
    }

    private static function installed_main_file( object $upgrader ): ?string {
        $file = method_exists( $upgrader, 'plugin_info' ) ? $upgrader->plugin_info() : false;
        return is_string( $file ) && '' !== $file ? $file : null;
    }

    private static function installed_headers( string $file ): array {
        $installed = get_plugins();
        return isset( $installed[ $file ] ) ? $installed[ $file ] : array();
    }
}
