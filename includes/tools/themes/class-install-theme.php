<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;
use Easy_MCP_AI\Tools\Package_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Install_Theme extends Base_Tool {

    use Lifecycle_Guard;
    use Package_Guard;

    public function get_name() {
        return 'wp_install_theme';
    }

    public function get_description() {
        return 'Installs a theme from the wordpress.org theme directory by its slug, as the Install button on Appearance → Themes → Add New does. The theme is NOT activated: call wp_switch_theme with the returned `stylesheet` to activate it. When it is a child theme whose parent is not installed, WordPress installs the parent from wordpress.org too. Required: `slug` (the wordpress.org slug, e.g. "twentytwentyfive"; a zip file, server path or URL is refused). Optional: `version` (install that wordpress.org version instead of the latest), `force` (boolean, default false: replace a theme already installed in the same folder). Returns { slug, stylesheet, name, version, parent, changed, replaced, active, messages (the installer log, every URL cut to its host) }. Refused when the theme is already installed (pass force: true to replace it, or use wp_update_theme), when wordpress.org does not list the slug or version, when WordPress refuses the package (its Requires PHP / Requires at least headers), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress cannot write the themes folder or wp-content directly, and on multisite unless you are a super admin with manage_network_themes. Off by default; an administrator switches it on. Runs within this request and reaches wordpress.org.';
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
                'slug'    => array(
                    'type'        => 'string',
                    'description' => 'The theme\'s wordpress.org slug: the last part of its wordpress.org address, e.g. "twentytwentyfive" for wordpress.org/themes/twentytwentyfive. Not a zip file, path or URL.',
                ),
                'version' => array(
                    'type'        => 'string',
                    'description' => 'Optional. A version wordpress.org lists for this theme, e.g. "1.2". Omit to install the latest version. Themes have no development version.',
                ),
                'force'   => array(
                    'type'        => 'boolean',
                    'description' => 'Replace a theme already installed in the theme\'s folder (its files are overwritten; an active theme stays active). Default false: an installed theme is refused.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'slug' ),
        );
    }

    public function describe( array $arguments ) {
        try {
            $slug    = $this->parse_wporg_slug( $arguments, 'theme' );
            $version = $this->parse_package_version( $arguments, false );
        } catch ( \Throwable $e ) {
            return parent::describe( $arguments );
        }
        return sprintf(
            /* translators: 1: theme slug, 2: "the latest version" or "version 1.2", 3: optional replacement note */
            __( 'Install the theme "%1$s" from wordpress.org (%2$s) into wp-content/themes/%1$s%3$s. It is not activated.', 'easy-mcp-ai' ),
            $slug,
            null === $version ? __( 'the latest version', 'easy-mcp-ai' ) : sprintf( /* translators: %s: version */ __( 'version %s', 'easy-mcp-ai' ), $version ),
            $this->parse_force( $arguments ) ? __( ', replacing any theme already installed there', 'easy-mcp-ai' ) : ''
        );
    }

    public function execute( array $arguments ) {
        $slug    = $this->parse_wporg_slug( $arguments, 'theme' );
        $version = $this->parse_package_version( $arguments, false );
        $force   = $this->parse_force( $arguments );

        $this->refuse_when_file_mods_are_off( 'themes cannot be installed' );
        if ( ! current_user_can( 'install_themes' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to install themes on this site.' );
        }
        $this->refuse_unless_network_file_admin( 'manage_network_themes', 'installing themes' );

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

    private static function refuse_existing_theme( string $slug, bool $force ): bool {
        $existing  = wp_get_theme( $slug );
        $replacing = $existing->exists();
        if ( $replacing && ! $force ) {
            throw new \RuntimeException( sprintf( 'The theme "%s" is already installed (version %s). Pass force: true to replace it, use wp_update_theme to update it, or wp_switch_theme to activate it.', $slug, (string) $existing->get( 'Version' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $replacing;
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
