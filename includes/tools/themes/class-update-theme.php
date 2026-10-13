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
        return 'Updates one installed theme to its available new version, exactly as the "Update now" link on Appearance → Themes does (download and replace the theme files; the active theme stays active). Required: `stylesheet` (the theme folder name — the `stylesheet` field from wp_list_themes, which also reports `update_available` and `new_version`). Returns { stylesheet, name, old_version, new_version, active (whether it is the active theme or its parent), messages (the upgrader log, last 20 lines, with every URL cut to its host because package URLs can carry licence keys) }. Refused when no update is available, when file modifications are disabled (DISALLOW_FILE_MODS), when WordPress would need FTP/SSH credentials to write the files, and on multisite unless you are a super admin with manage_network_themes. The update runs within this request; while the active theme is being replaced WordPress puts the site in maintenance mode.';
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
            ),
            'required'   => array( 'stylesheet' ),
        );
    }

    public function execute( array $arguments ) {
        $stylesheet = $this->parse_stylesheet( $arguments );

        $this->assert_may_update();

        list( $name, $old_version ) = $this->installed_header( $stylesheet );

        $this->load_upgrader();
        $this->assert_filesystem_available( get_theme_root( $stylesheet ) );
        $this->raise_time_limit();

        $this->assert_update_available( $stylesheet, $name, $old_version );

        
        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Theme_Upgrader( $skin );
        $result   = $upgrader->bulk_upgrade( array( $stylesheet ) );

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
