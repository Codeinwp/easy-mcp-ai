<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






class Update_Theme extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_update_theme';
    }

    public function get_description() {
        return 'Updates one installed theme to its available new version, exactly as the "Update now" link on Appearance → Themes does (download and replace the theme files; the active theme stays active). Required: `stylesheet` (the theme folder name — the `stylesheet` field from wp_list_themes, which also reports `update_available` and `new_version`). Optional: `version` — install that exact wordpress.org version instead (a downgrade is allowed), as `wp theme update --version` does; it needs the install_themes capability as well and works only for a theme wordpress.org\'s update data lists as its own; refused when the theme is already at that version or wordpress.org does not list it. Returns { stylesheet, name, old_version, new_version, active (whether it is the active theme or its parent), messages (the upgrader log, last 20 lines, with every URL cut to its host because package URLs can carry licence keys) }. Refused when no update is available (without `version`), when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress would need FTP/SSH credentials to write the files, and on multisite unless you are a super admin with manage_network_themes. The update runs within this request; while the active theme is being replaced WordPress puts the site in maintenance mode.';
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'update_themes';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'stylesheet' => array(
                    'type'        => 'string',
                    'description' => 'Theme folder name, e.g. "twentytwentyfive" (the `stylesheet` field from wp_list_themes).',
                ),
                'version'    => array(
                    'type'        => 'string',
                    'description' => 'Optional. Install this exact version from wordpress.org instead of the offered update, e.g. "1.2" (a downgrade is allowed). Omit to install the offered update. Needs install_themes too.',
                ),
            ),
            'required'   => array( 'stylesheet' ),
        );
    }

    public function execute( array $arguments ) {
        $stylesheet = $this->parse_stylesheet( $arguments );
        $version    = $this->parse_version_argument( $arguments );

        $this->assert_may_update();
        if ( null !== $version ) {
            $this->assert_may_install_a_version();
        }

        list( $name, $old_version ) = $this->installed_header( $stylesheet );

        $this->load_upgrader();
        $this->assert_filesystem_available( get_theme_root( $stylesheet ) );
        $this->raise_time_limit();

        $offer = $this->chosen_offer( $stylesheet, $name, $old_version, $version );

        
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader( $skin );
        $result   = $this->run_bulk_upgrade( $upgrader, $stylesheet, $offer );
        self::refuse_ignored_offer( $offer, $result, $stylesheet, (string) $version );

        $this->interpret_upgrade_result( $result, $skin, $upgrader, $stylesheet, 'Theme update failed.' );

        
        $updated     = wp_get_theme( $stylesheet );
        $new_version = $updated->exists() ? (string) $updated->get( 'Version' ) : '';
        $active      = wp_get_theme();

        return array(
            'stylesheet'  => $stylesheet,
            'name'        => $name,
            'old_version' => $old_version,
            'new_version' => $new_version,
            'active'      => $active->get_stylesheet() === $stylesheet || $active->get_template() === $stylesheet,
            'messages'    => $this->upgrade_messages( $skin ),
        );
    }

    










    private function chosen_offer( string $stylesheet, string $name, string $old_version, ?string $version ): ?array {
        if ( null === $version ) {
            $this->assert_update_available( $stylesheet, $name, $old_version );
            return null;
        }
        return $this->version_offer( $stylesheet, '' !== $name ? $name : $stylesheet, $old_version, $version );
    }

    








    private function run_bulk_upgrade( \Theme_Upgrader $upgrader, string $stylesheet, ?array $offer ) {
        $filter = null === $offer ? null : $this->offer_once( $stylesheet, $offer );
        try {
            return $upgrader->bulk_upgrade( array( $stylesheet ) );
        } finally {
            if ( null !== $filter ) {
                remove_filter( 'site_transient_update_themes', $filter, PHP_INT_MAX );
            }
        }
    }

    











    private static function refuse_ignored_offer( ?array $offer, $result, string $stylesheet, string $version ): void {
        if ( null !== $offer && is_array( $result ) && isset( $result[ $stylesheet ] ) && true === $result[ $stylesheet ] ) {
            throw new \RuntimeException( sprintf( 'Version %s was not installed: WordPress read its update data without the chosen version (a plugin on this site replaces that data), so nothing changed.', $version ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    








    private function parse_stylesheet( array $arguments ): string {
        $raw = isset( $arguments['stylesheet'] ) && is_string( $arguments['stylesheet'] ) ? trim( $arguments['stylesheet'] ) : '';
        if ( '' === $raw ) {
            throw new \InvalidArgumentException( 'Missing required parameters: stylesheet' );
        }
        $stylesheet = preg_replace( '/[^A-z0-9_\-]/', '', $raw );
        if ( $stylesheet !== $raw ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid stylesheet "%s". Pass a theme folder name as returned by wp_list_themes.', $raw ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $stylesheet;
    }

    








    private function parse_version_argument( array $arguments ): ?string {
        if ( ! isset( $arguments['version'] ) ) {
            return null;
        }
        $raw = $arguments['version'];
        if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
            throw new \InvalidArgumentException( '"version" must be a version string such as "1.2". Omit it to install the offered update.' );
        }
        $version = trim( $raw );
        if ( strlen( $version ) > 64 || ! preg_match( '/^[0-9A-Za-z][0-9A-Za-z.+_-]*\z/', $version ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid version "%s". Pass a version wordpress.org lists for this theme, such as "1.2".', substr( $version, 0, 64 ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $version;
    }

    









    private function assert_may_install_a_version(): void {
        if ( ! current_user_can( 'install_themes' ) ) {
            throw new \RuntimeException( 'Sorry, installing a chosen theme version also needs the install_themes capability: wp-admin installs a specific version only through Upload Theme, which requires it.' );
        }
    }

    





















    private function version_offer( string $stylesheet, string $label, string $old_version, string $version ): array {
        $entry = $this->update_entry( $stylesheet );
        $url   = null !== $entry ? (string) $this->update_field( $entry, 'url' ) : '';

        if ( null === $entry || null !== $this->update_field( $entry, 'id' ) || 0 !== strpos( $url, 'https://wordpress.org/themes/' ) ) {
            throw new \RuntimeException( sprintf( '%s cannot be installed at a chosen version: wordpress.org\'s update data does not list it as a wordpress.org theme, so there is no version list to install from.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $version === $old_version ) {
            throw new \RuntimeException( sprintf( '%s is already at version %s; nothing was installed.', $label, $version ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $package = $this->version_package( $stylesheet, $version );

        
        $offer                = (array) $entry;
        $offer['new_version'] = $version;
        $offer['package']     = $package;
        
        
        unset( $offer['requires'], $offer['requires_php'] );
        return $offer;
    }

    








    private function update_entry( string $stylesheet ) {
        wp_update_themes();
        $transient = get_site_transient( 'update_themes' );
        $response  = $this->update_response( $transient );
        $no_update = ( is_object( $transient ) && isset( $transient->no_update ) && is_array( $transient->no_update ) ) ? $transient->no_update : array();
        return isset( $response[ $stylesheet ] ) ? $response[ $stylesheet ] : ( isset( $no_update[ $stylesheet ] ) ? $no_update[ $stylesheet ] : null );
    }

    








    private function version_package( string $stylesheet, string $version ): string {
        $versions = $this->version_list( $stylesheet );
        if ( 'trunk' === $version || ! isset( $versions[ $version ] ) || ! is_string( $versions[ $version ] ) ) {
            $listed = array_values( array_diff( array_map( 'strval', array_keys( $versions ) ), array( 'trunk' ) ) );
            usort( $listed, 'version_compare' );
            $listed = array_slice( $listed, -8 );
            throw new \RuntimeException( sprintf( 'wordpress.org lists no version "%s" of %s.%s', $version, $stylesheet, empty( $listed ) ? '' : ' Latest listed: ' . implode( ', ', $listed ) . '.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $package = $versions[ $version ];
        if ( 0 !== strpos( $package, 'https://downloads.wordpress.org/theme/' ) ) {
            throw new \RuntimeException( sprintf( 'Version %s of %s is not offered from downloads.wordpress.org (a plugin on this site answers wordpress.org\'s theme information requests), so it was not installed.', $version, $stylesheet ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $package;
    }

    






    private function version_list( string $stylesheet ): array {
        if ( ! function_exists( 'themes_api' ) ) {
            require_once ABSPATH . 'wp-admin/includes/theme.php';
        }
        $info = themes_api(
            'theme_information',
            array(
                'slug'   => $stylesheet,
                'fields' => array(
                    'versions' => true,
                    'sections' => false,
                ),
            )
        );
        if ( is_wp_error( $info ) ) {
            throw new \RuntimeException( $this->redact_urls( sprintf( 'wordpress.org did not return the version list for %s: %s', $stylesheet, $info->get_error_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return ( is_object( $info ) && isset( $info->versions ) && ( is_array( $info->versions ) || is_object( $info->versions ) ) ) ? (array) $info->versions : array();
    }

    











    private function offer_once( string $stylesheet, array $offer ): \Closure {
        $filter = null;
        $filter = static function ( $value ) use ( &$filter, $stylesheet, $offer ) {
            if ( ! is_object( $value ) ) {
                return $value;
            }
            remove_filter( 'site_transient_update_themes', $filter, PHP_INT_MAX );
            
            $value                   = clone $value;
            $response                = ( isset( $value->response ) && is_array( $value->response ) ) ? $value->response : array();
            $response[ $stylesheet ] = $offer;
            $value->response         = $response;
            return $value;
        };
        add_filter( 'site_transient_update_themes', $filter, PHP_INT_MAX );
        return $filter;
    }

    





    private function assert_may_update() {
        $this->assert_file_mods_allowed( 'themes' );

        
        if ( ! current_user_can( 'update_themes' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to update themes for this site.' );
        }
        $this->assert_network_update_allowed( 'manage_network_themes' );
    }

    






    private function installed_header( string $stylesheet ): array {
        $theme = wp_get_theme( $stylesheet );
        if ( ! $theme->exists() ) {
            throw new \RuntimeException(
                sprintf( 'Theme "%s" is not installed. Use wp_list_themes to see installed themes.', $stylesheet ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return array( (string) $theme->get( 'Name' ), (string) $theme->get( 'Version' ) );
    }

    








    private function assert_update_available( string $stylesheet, string $name, string $old_version ) {
        $current = get_site_transient( 'update_themes' );
        if ( empty( $current ) ) {
            wp_update_themes();
            $current = get_site_transient( 'update_themes' );
        }
        $response = $this->update_response( $current );
        if ( ! isset( $response[ $stylesheet ] ) ) {
            throw new \RuntimeException(
                sprintf( 'No update is available for %s (installed version %s). wp_list_themes reports update_available per theme.', '' !== $name ? $name : $stylesheet, $old_version ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }
}
