<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;
use Easy_MCP_AI\Tools\Package_Source;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Install_Theme extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;
    use Package_Source;

    public function get_name() {
        return 'wp_install_theme';
    }

    public function get_description() {
        return 'Installs a theme, as Appearance → Themes → Add New does: from the wordpress.org directory by `slug`, or from a ZIP given as `url` (an HTTPS link; a wordpress.org theme page and github.com/<owner>/<repo>/releases/latest work too), `file` (a file the client hands over) or `content_base64` (the ZIP inline, at most 2 MB). Pass exactly one. The theme is NOT activated: call wp_switch_theme with the returned `stylesheet`. A child theme whose parent is missing gets the parent from wordpress.org when it is listed there. Optional: `version` (slug only: that wordpress.org version), `folder` (ZIP only: the folder under wp-content/themes, instead of the one the ZIP names), `force` (boolean, default false: replace a theme in the same folder). Returns { slug, stylesheet, name, version, parent, changed, replaced, active, messages (the installer log, URLs cut to their host) }; for a ZIP slug is null and it adds parent_installed, source (host and path, never the query), sha256 and bytes. Refused when the folder is taken (pass force: true, or use wp_update_theme), when wordpress.org does not list the slug or version, when a download is not public HTTPS, over 50 MB or not a ZIP, when WordPress refuses the package (no style.css, its Requires PHP / Requires at least), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the themes folder or wp-content directly, for a ZIP without the upload_themes capability, and on multisite unless you are a super admin with manage_network_themes. Off by default; an administrator switches it on. Runs within this request and reaches the download host.';
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'install_themes';
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
                    'description' => 'A wordpress.org theme slug: the last part of its wordpress.org address, e.g. "twentytwentyfive" for wordpress.org/themes/twentytwentyfive. Pass a link to a ZIP as url instead.',
                ),
                'url'            => array(
                    'type'        => 'string',
                    'description' => 'An HTTPS link to the theme\'s ZIP file (signed download links work), a wordpress.org theme page, or github.com/<owner>/<repo>/releases/latest. Public hosts only, at most 50 MB.',
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
                    'description' => 'The theme\'s ZIP file, base64-encoded (a data: URI prefix is accepted), at most 2 MB decoded: a child theme, or a theme you wrote.',
                ),
                'folder'         => array(
                    'type'        => 'string',
                    'description' => 'Optional, with url, file or content_base64: the folder name under wp-content/themes, instead of the one the ZIP names (a GitHub source archive is named after its repository).',
                ),
                'version'        => array(
                    'type'        => 'string',
                    'description' => 'Optional, with slug only. A version wordpress.org lists for this theme, e.g. "1.2". Omit to install the latest version. Themes have no development version.',
                ),
                'force'          => array(
                    'type'        => 'boolean',
                    'description' => 'Replace a theme already installed in the theme\'s folder (its files are overwritten; an active theme stays active). Default false: an installed theme is refused.',
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
            $source = $this->parse_package_source( $arguments, 'theme' );
            $force  = $this->parse_force( $arguments );
            if ( 'slug' === $source['type'] ) {
                return $this->describe_wporg( (string) $source['slug'], $this->parse_package_version( $arguments, false ), $force );
            }
        } catch ( \Throwable $e ) {
            return parent::describe( self::package_preview_arguments( $arguments ) );
        }
        return sprintf(
            /* translators: 1: where the ZIP comes from, 2: the target folder, 3: optional replacement note */
            __( 'Install a theme from %1$s into %2$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            self::describe_package_source( $source, 'theme' ),
            null !== $source['folder'] ? 'wp-content/themes/' . $source['folder'] : __( 'the wp-content/themes folder the ZIP names', 'easy-mcp-ai' ),
            $force ? __( ', replacing any theme already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    private function describe_wporg( string $slug, ?string $version, bool $force ): string {
        return sprintf(
            /* translators: 1: theme slug, 2: "the latest version" or "version 1.2", 3: optional replacement note */
            __( 'Install the theme "%1$s" from wordpress.org (%2$s) into wp-content/themes/%1$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            $slug,
            null === $version ? __( 'the latest version', 'easy-mcp-ai' ) : sprintf( /* translators: %s: version */ __( 'version %s', 'easy-mcp-ai' ), $version ),
            $force ? __( ', replacing any theme already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    public function execute( array $arguments ) {
        $source = $this->parse_package_source( $arguments, 'theme' );
        if ( 'slug' === $source['type'] ) {
            return $this->install_from_wporg( (string) $source['slug'], $arguments );
        }
        return $this->install_from_package( $source, $arguments );
    }

    private function install_from_wporg( string $slug, array $arguments ): array {
        $version = $this->parse_package_version( $arguments, false );
        $force   = $this->parse_force( $arguments );

        $this->refuse_theme_installs_without_rights();

        $replacing = self::refuse_existing_theme( $slug, $force );

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( get_theme_root(), 'wp-content/themes' );
        $this->raise_time_limit();

        $offer = $this->wporg_package( 'theme', $slug, $version );

        $skin     = $this->new_upgrader_skin();
        $upgrader = $this->new_theme_upgrader( $skin );
        $package  = $offer['package'];
        list( $result, $printed ) = $this->without_output(
            function () use ( $upgrader, $package, $replacing ) {
                return $upgrader->install( $package, array( 'overwrite_package' => $replacing ) );
            }
        );

        $this->interpret_install_result( $result, $skin, 'Theme installation failed.' );

        return $this->result( $slug, $offer, $upgrader, $skin, $replacing, '' !== $printed );
    }

    private function install_from_package( array $source, array $arguments ): array {
        $force = $this->parse_force( $arguments );

        $this->refuse_theme_installs_without_rights();
        if ( ! current_user_can( 'upload_themes' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to upload themes on this site, so a theme cannot be installed from a URL or a ZIP. A wordpress.org slug may still work.' );
        }
        if ( null !== $source['folder'] ) {
            $taken = $this->theme_destination( (string) $source['folder'], $force, null );
            if ( is_wp_error( $taken ) ) {
                throw new \RuntimeException( $taken->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $this->load_upgrader();
        $this->refuse_unless_direct_filesystem( get_theme_root(), 'wp-content/themes' );
        $this->raise_time_limit();

        $package = $this->fetch_package( $source, 'theme' );
        try {
            $skin     = $this->new_upgrader_skin();
            $upgrader = $this->new_theme_upgrader( $skin );
            list( $result, $printed, $replacing, $folder ) = $this->install_package_file(
                $upgrader,
                $package,
                $force,
                function ( string $folder, object $upgrader ) use ( $force ) {
                    return $this->theme_destination( $folder, $force, $upgrader );
                }
            );
            $this->interpret_install_result( $result, $skin, 'Theme installation failed.' );
            return $this->package_result( $package, $upgrader, $skin, $replacing, (string) $folder, '' !== $printed );
        } finally {
            $this->discard_package( $package );
        }
    }

    private function refuse_theme_installs_without_rights(): void {
        $this->refuse_when_file_mods_are_off( 'themes cannot be installed' );
        if ( ! current_user_can( 'install_themes' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to install themes on this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_themes', 'installing themes' );
    }

    private static function refuse_existing_theme( string $slug, bool $force ): bool {
        $existing  = wp_get_theme( $slug );
        $replacing = $existing->exists();
        if ( $replacing && ! $force ) {
            throw new \RuntimeException( sprintf( 'The theme "%s" is already installed (version %s). Pass force: true to replace it, use wp_update_theme to update it, or wp_switch_theme to activate it.', $slug, (string) $existing->get( 'Version' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $replacing;
    }

    private function theme_destination( string $folder, bool $force, ?object $upgrader ) {
        $theme = wp_get_theme( $folder );
        if ( ! $theme->exists() && ! $this->theme_folder_exists( $folder ) ) {
            return false;
        }
        if ( $force ) {
            return true;
        }
        $holds   = $theme->exists() ? sprintf( 'holds %s %s', (string) $theme->get( 'Name' ), (string) $theme->get( 'Version' ) ) : 'already exists';
        $data    = null !== $upgrader && isset( $upgrader->new_theme_data ) && is_array( $upgrader->new_theme_data ) ? $upgrader->new_theme_data : array();
        $package = ! empty( $data['Version'] ) && is_scalar( $data['Version'] ) ? sprintf( '; this ZIP holds version %s', (string) $data['Version'] ) : '';
        return new \WP_Error( 'easy_mcp_ai_folder_exists', substr( sprintf( 'The theme folder "%1$s" %2$s%3$s. Pass force: true to replace it.', $folder, trim( $holds ), $package ), 0, 200 ) );
    }

    protected function theme_folder_exists( string $folder ): bool {
        return is_dir( get_theme_root() . '/' . $folder );
    }

    private function result( string $slug, array $offer, object $upgrader, \WP_Ajax_Upgrader_Skin $skin, bool $replacing, bool $printed ): array {
        $theme      = self::installed_theme( $upgrader );
        $stylesheet = null !== $theme ? (string) $theme->get_stylesheet() : $slug;
        $parent     = null !== $theme && method_exists( $theme, 'parent' ) ? $theme->parent() : false;
        $active     = wp_get_theme();

        $out = array(
            'slug'       => $slug,
            'stylesheet' => $stylesheet,
            'name'       => self::theme_header( $theme, 'Name', $offer['name'] ),
            'version'    => self::theme_header( $theme, 'Version', $offer['version'] ),
            'parent'     => $parent ? (string) $parent->get_stylesheet() : null,
            'changed'    => true,
            'replaced'   => $replacing,
            'active'     => $active->get_stylesheet() === $stylesheet || $active->get_template() === $stylesheet,
            'messages'   => $this->upgrade_messages( $skin ),
        );
        if ( $printed ) {
            $out['output_discarded'] = true;
        }
        return $out;
    }

    private function package_result( array $package, object $upgrader, \WP_Ajax_Upgrader_Skin $skin, bool $replacing, string $folder, bool $printed ): array {
        $theme      = self::installed_theme( $upgrader );
        $data       = isset( $upgrader->new_theme_data ) && is_array( $upgrader->new_theme_data ) ? $upgrader->new_theme_data : array();
        $stylesheet = null !== $theme ? (string) $theme->get_stylesheet() : $folder;
        $template   = null !== $theme ? (string) $theme->get_template() : ( isset( $data['Template'] ) && is_string( $data['Template'] ) ? $data['Template'] : '' );
        $is_child   = '' !== $template && $template !== $stylesheet;
        $active     = wp_get_theme();

        $out = array_merge(
            array(
                'slug'             => null,
                'stylesheet'       => $stylesheet,
                'name'             => self::theme_header( $theme, 'Name', isset( $data['Name'] ) && is_scalar( $data['Name'] ) ? (string) $data['Name'] : '' ),
                'version'          => self::theme_header( $theme, 'Version', isset( $data['Version'] ) && is_scalar( $data['Version'] ) ? (string) $data['Version'] : '' ),
                'parent'           => $is_child ? $template : null,
                'parent_installed' => $is_child ? wp_get_theme( $template )->exists() : null,
                'changed'          => true,
                'replaced'         => $replacing,
                'active'           => '' !== $stylesheet && ( $active->get_stylesheet() === $stylesheet || $active->get_template() === $stylesheet ),
                'messages'         => array_slice( array_merge( $package['notes'], $this->upgrade_messages( $skin ) ), -20 ),
            ),
            self::package_fields( $package )
        );
        if ( $printed ) {
            $out['output_discarded'] = true;
        }
        return $out;
    }

    private static function installed_theme( object $upgrader ): ?object {
        $theme = method_exists( $upgrader, 'theme_info' ) ? $upgrader->theme_info() : false;
        return is_object( $theme ) && method_exists( $theme, 'exists' ) && $theme->exists() ? $theme : null;
    }

    private static function theme_header( ?object $theme, string $header, string $fallback ): string {
        return null !== $theme && '' !== (string) $theme->get( $header ) ? (string) $theme->get( $header ) : $fallback;
    }

    private function parse_force( array $arguments ): bool {
        return isset( $arguments['force'] ) && rest_sanitize_boolean( $arguments['force'] );
    }

    protected function new_theme_upgrader( \WP_Ajax_Upgrader_Skin $skin ): object {
        return new \Theme_Upgrader( $skin );
    }
}
