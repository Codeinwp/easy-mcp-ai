<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class List_Plugin_Updates extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_list_plugin_updates';
    }

    public function get_description() {
        return 'Lists installed plugins that have a newer version available, as Dashboard → Updates shows them. No parameters. Refreshes WordPress\'s update data first (WordPress rate-limits that check to once every 12 hours unless a plugin changed, so this may contact wordpress.org). Returns { updates: [{ plugin, name, version (installed), new_version, slug, requires (WordPress), tested (WordPress), requires_php, url, details_url (changelog), package_available (false when the update needs a licence or manual download and wp_update_plugin cannot install it) }], total, last_checked (Unix time of the last check, or null) }. Pass an entry\'s `plugin` to wp_update_plugin. Requires the update_plugins capability, which WordPress withholds when DISALLOW_FILE_MODS is set and, on multisite, from everyone except super admins.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'update_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $this->load_plugin_admin_functions();

        
        
        wp_update_plugins();

        $current   = get_site_transient( 'update_plugins' );
        $response  = $this->update_response( $current );
        $installed = get_plugins();

        $updates = array();
        foreach ( $installed as $file => $data ) {
            if ( isset( $response[ $file ] ) ) {
                $updates[] = $this->update_row( (string) $file, (array) $data, $response[ $file ] );
            }
        }

        
        
        return array(
            'updates'      => $updates,
            'total'        => count( $updates ),
            'last_checked' => ( is_object( $current ) && isset( $current->last_checked ) ) ? (int) $current->last_checked : null,
        );
    }

    








    private function update_row( string $file, array $data, $entry ): array {
        $slug = (string) $this->update_field( $entry, 'slug' );
        return array(
            'plugin'            => $file,
            'name'              => isset( $data['Name'] ) ? (string) $data['Name'] : '',
            'version'           => isset( $data['Version'] ) ? (string) $data['Version'] : '',
            'new_version'       => (string) $this->update_field( $entry, 'new_version' ),
            'slug'              => $slug,
            'requires'          => $this->update_field( $entry, 'requires' ),
            'tested'            => $this->update_field( $entry, 'tested' ),
            'requires_php'      => $this->update_field( $entry, 'requires_php' ),
            'url'               => $this->homepage_url( $entry ),
            
            
            'details_url'       => '' !== $slug ? self_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $slug ) . '&section=changelog' ) : null,
            'package_available' => '' !== (string) $this->update_field( $entry, 'package' ),
        );
    }

    











    private function homepage_url( $entry ) {
        $url = $this->update_field( $entry, 'url' );
        if ( ! is_string( $url ) || '' === $url ) {
            return null;
        }
        $package = $this->update_field( $entry, 'package' );
        if ( is_string( $package ) && '' !== $package && $url === $package ) {
            return null;
        }
        $cut = strcspn( $url, '?#' );
        return substr( $url, 0, $cut );
    }
}
