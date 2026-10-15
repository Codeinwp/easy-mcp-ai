<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












class List_Plugins extends Base_Tool {

    use Lifecycle_Guard;

    
    const UPDATE_FIELDS = 'update, update_version, update_unavailable_reason, auto_update';

    
    const VERSION_HIGHER = 'version higher than expected';

    public function get_name() {
        return 'wp_list_plugins';
    }

    public function get_description() {
        return 'Lists installed WordPress plugins. Optional: `status` ("active" | "inactive" | "network-active" (multisite only) | "must-use" | "dropin" — omit for all), `include_mu_dropins` (boolean, default false: also list must-use and drop-in plugins, which WordPress shows only on a single site or to a network administrator with manage_network_plugins), `skip_update_check` (boolean, default true: read WordPress\'s stored update data and contact nobody; false deletes it and asks wordpress.org again first, as `wp plugin list` does — only for a user with update_plugins). Returns { plugins: [{ plugin (folder/file.php identifier), name, description, status, version, author, update ("none" | "available" | "unavailable" | "version higher than expected"), update_version, update_unavailable_reason, auto_update (boolean: in WordPress\'s auto-update list), requires (WordPress version), requires_php, recently_active }], total, warnings }. The update fields and auto_update are null for a user without the update_plugins capability (a warning names the cause) and for must-use and drop-in rows. requires and requires_php are those of the offered update when there is one, otherwise the plugin\'s own header. Requires administrator access. The `plugin` field is the identifier used by WordPress plugin functions; pass it to wp_update_plugin. ' . self::lifecycle_hint();
    }

    






    public static function lifecycle_hint(): string {
        return \Easy_MCP_AI\Config::brand( 'Installing, deleting and uninstalling plugins have their own tools, which are off by default: when none is in your tools, tell the user an administrator can switch them on in wp-admin under Easy MCP AI › Settings › Advanced › Disabled tools, or allow them for this connection.' );
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'activate_plugins';
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
            'properties' => array(
                'status'             => array(
                    'type'        => 'string',
                    'description' => 'Filter plugins by status. "network-active" exists only on multisite. "must-use" and "dropin" list only those plugins (no include_mu_dropins needed).',
                    'enum'        => array( 'active', 'inactive', 'network-active', 'must-use', 'dropin' ),
                ),
                'include_mu_dropins' => array(
                    'type'        => 'boolean',
                    'description' => 'Also list must-use and drop-in plugins (status "must-use" / "dropin"), as `wp plugin list` does. Default false.',
                    'default'     => false,
                ),
                'skip_update_check'  => array(
                    'type'        => 'boolean',
                    'description' => 'Default true: read the update data WordPress already stored, with no outbound request. false: delete it and ask wordpress.org again before listing, as `wp plugin list` does without --skip-update-check (needs update_plugins).',
                    'default'     => true,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $status       = self::status_argument( $arguments );
        $include_more = isset( $arguments['include_mu_dropins'] ) && rest_sanitize_boolean( $arguments['include_mu_dropins'] );
        
        $skip_check    = isset( $arguments['skip_update_check'] ) ? rest_sanitize_boolean( $arguments['skip_update_check'] ) : true;
        $advanced_only = is_string( $status ) && in_array( $status, array( 'must-use', 'dropin' ), true );

        $warnings    = array();
        $see_updates = $this->prepare_update_data( $skip_check, $warnings );

        $result = $advanced_only ? array() : $this->rest_rows( $status, $see_updates );

        if ( $advanced_only || $include_more ) {
            $result = array_merge( $result, $this->advanced_rows( $status, $warnings ) );
        }

        return array(
            'plugins'  => $result,
            'total'    => count( $result ),
            'warnings' => $warnings,
        );
    }

    








    private static function status_argument( array $arguments ) {
        $status = ! empty( $arguments['status'] ) ? $arguments['status'] : '';
        if ( 'network-active' === $status && ! is_multisite() ) {
            throw new \InvalidArgumentException( 'status "network-active" exists only on a multisite network. On a single site use "active".' );
        }
        return $status;
    }

    









    private function prepare_update_data( bool $skip_check, array &$warnings ): bool {
        $see_updates = current_user_can( 'update_plugins' );
        if ( ! $see_updates ) {
            $warnings[] = $this->hidden_updates_warning( $skip_check );
        } elseif ( ! $skip_check ) {
            $this->load_plugin_admin_functions();
            delete_site_transient( 'update_plugins' );
            wp_update_plugins();
        }
        return $see_updates;
    }

    








    private function rest_rows( $status, bool $see_updates ): array {
        $plugins = self::rest_plugins( $status );
        $context = $this->update_context( $see_updates );

        $result = array();
        foreach ( $plugins as $plugin ) {
            $result[] = $this->plugin_row( $plugin, $see_updates, $context );
        }
        return $result;
    }

    






    private static function rest_plugins( $status ): array {
        $params = array(
            'context' => 'edit',
        );

        if ( ! empty( $status ) ) {
            $params['status'] = $status;
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/plugins' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $response->get_data();
    }

    







    private function update_context( bool $see_updates ): array {
        $transient       = $see_updates ? get_site_transient( 'update_plugins' ) : false;
        $auto_updates    = $see_updates ? get_site_option( 'auto_update_plugins' ) : array();
        $recently_active = get_option( 'recently_activated' );
        return array(
            'responses'       => $this->update_response( $transient ),
            'no_update'       => ( is_object( $transient ) && isset( $transient->no_update ) && is_array( $transient->no_update ) ) ? $transient->no_update : array(),
            'auto_updates'    => is_array( $auto_updates ) ? $auto_updates : array(),
            'recently_active' => is_array( $recently_active ) ? $recently_active : array(),
        );
    }

    







    private function plugin_row( array $plugin, bool $see_updates, array $context ): array {
        
        $plugin_id = $plugin['plugin'];
        if ( substr( $plugin_id, -4 ) !== '.php' ) {
            $plugin_id .= '.php';
        }
        $row = array(
            'plugin'      => $plugin_id,
            'name'        => $plugin['name'],
            'description' => is_array( $plugin['description'] ) ? ( $plugin['description']['raw'] ?? '' ) : ( (string) $plugin['description'] ),
            'status'      => $plugin['status'],
            'version'     => $plugin['version'],
            'author'      => $plugin['author'],
        );

        $header_wp  = self::string_header( $plugin, 'requires_wp' );
        $header_php = self::string_header( $plugin, 'requires_php' );

        $row += $see_updates
            ? $this->update_columns( $plugin_id, (string) $plugin['version'], $context, $header_wp, $header_php )
            : array(
                'update'                    => null,
                'update_version'            => null,
                'update_unavailable_reason' => null,
                'requires'                  => $header_wp,
                'requires_php'              => $header_php,
                'auto_update'               => null,
            );
        
        
        
        $row['recently_active'] = array_key_exists( $plugin_id, $context['recently_active'] );

        return $this->ordered( $row );
    }

    






    private static function string_header( array $plugin, string $field ): string {
        return isset( $plugin[ $field ] ) && is_string( $plugin[ $field ] ) ? $plugin[ $field ] : '';
    }

    









    private function update_columns( string $plugin_id, string $version, array $context, string $header_wp, string $header_php ): array {
        $fields                = $this->update_fields(
            $version,
            isset( $context['responses'][ $plugin_id ] ) ? $context['responses'][ $plugin_id ] : null,
            isset( $context['no_update'][ $plugin_id ] ) ? $context['no_update'][ $plugin_id ] : null,
            $header_wp,
            $header_php
        );
        $fields['auto_update'] = in_array( $plugin_id, $context['auto_updates'], true );
        return $fields;
    }

    











    private function update_fields( string $installed, $offer, $no_update, string $header_wp, string $header_php ): array {
        $fields = $this->offer_fields( $offer, $header_wp, $header_php );
        if ( null === $offer && null !== $no_update ) {
            $fields = $this->no_update_fields( $fields, $installed, $no_update );
        }
        return $fields;
    }

    









    private function offer_fields( $offer, string $header_wp, string $header_php ): array {
        $requires     = null !== $offer ? $this->update_field( $offer, 'requires' ) : null;
        $requires_php = null !== $offer ? $this->update_field( $offer, 'requires_php' ) : null;
        $reason       = '';

        if ( ! empty( $requires_php ) && ! version_compare( PHP_VERSION, (string) $requires_php, '>=' ) ) {
            $update = 'unavailable';
            $reason = sprintf( 'This update requires PHP version %s, but the version installed is %s.', (string) $requires_php, PHP_VERSION );
        } else {
            $update = null !== $offer ? 'available' : 'none';
        }

        return array(
            'update'                    => $update,
            'update_version'            => null !== $offer ? (string) $this->update_field( $offer, 'new_version' ) : '',
            'update_unavailable_reason' => $reason,
            'requires'                  => null !== $requires ? (string) $requires : $header_wp,
            'requires_php'              => null !== $requires_php ? (string) $requires_php : $header_php,
        );
    }

    










    private function no_update_fields( array $fields, string $installed, $no_update ): array {
        $latest = (string) $this->update_field( $no_update, 'new_version' );
        if ( version_compare( $installed, $latest, '>' ) ) {
            $fields['update']       = self::VERSION_HIGHER;
            $fields['requires']     = (string) $this->update_field( $no_update, 'requires' );
            $fields['requires_php'] = (string) $this->update_field( $no_update, 'requires_php' );
        } elseif ( version_compare( $installed, $latest, '<' ) ) {
            $fields['update']                    = 'unavailable';
            $fields['update_version']            = $latest;
            $fields['requires']                  = (string) $this->update_field( $no_update, 'requires' );
            $fields['requires_php']              = (string) $this->update_field( $no_update, 'requires_php' );
            $fields['update_unavailable_reason'] = sprintf( 'This update requires WordPress version %s, but the version installed is %s.', $fields['requires'], (string) get_bloginfo( 'version' ) );
        }
        return $fields;
    }

    











    private function advanced_rows( $status, array &$warnings ): array {
        
        
        if ( ! current_user_can( 'activate_plugins' ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to manage plugins for this site.' );
        }
        if ( is_multisite() && ! current_user_can( 'manage_network_plugins' ) ) {
            $warnings[] = 'Must-use and drop-in plugins are not listed: on a multisite network WordPress shows them only in Network Admin, which needs the manage_network_plugins capability.';
            return array();
        }

        $this->load_plugin_admin_functions();
        $rows = array();

        foreach ( array( 'must-use' => 'mustuse', 'dropin' => 'dropins' ) as $row_status => $context ) {
            if ( ! empty( $status ) && ! in_array( $row_status, (array) $status, true ) ) {
                continue;
            }
            $rows = array_merge( $rows, $this->advanced_rows_of( $row_status, $context, $warnings ) );
        }

        return $rows;
    }

    









    private function advanced_rows_of( string $row_status, string $context, array &$warnings ): array {
        if ( ! apply_filters( 'show_advanced_plugins', true, $context ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            $warnings[] = sprintf( 'The %s plugins are not listed: a show_advanced_plugins filter on this site hides them from the Plugins screen.', 'must-use' === $row_status ? 'must-use' : 'drop-in' );
            return array();
        }
        $found = 'must-use' === $row_status ? get_mu_plugins() : get_dropins();
        $about = 'must-use' === $row_status ? array() : _get_dropins();
        $rows  = array();
        foreach ( (array) $found as $file => $data ) {
            $rows[] = $this->advanced_row( (string) $file, (array) $data, $row_status, $about );
        }
        return $rows;
    }

    










    private function advanced_row( string $file, array $data, string $status, array $about ): array {
        $description = isset( $data['Description'] ) ? (string) $data['Description'] : '';
        if ( isset( $about[ $file ][0] ) && '' !== (string) $about[ $file ][0] ) {
            
            $description = (string) $about[ $file ][0];
        }
        return $this->ordered(
            array(
                'plugin'                    => $file,
                'name'                      => isset( $data['Name'] ) && '' !== (string) $data['Name'] ? (string) $data['Name'] : $file,
                'description'               => $description,
                'status'                    => $status,
                'version'                   => isset( $data['Version'] ) ? (string) $data['Version'] : '',
                'author'                    => isset( $data['Author'] ) ? (string) $data['Author'] : '',
                'update'                    => null,
                'update_version'            => null,
                'update_unavailable_reason' => null,
                'auto_update'               => false,
                'requires'                  => '',
                'requires_php'              => '',
                'recently_active'           => false,
            )
        );
    }

    





    private function ordered( array $row ): array {
        $order = array( 'plugin', 'name', 'description', 'status', 'version', 'author', 'update', 'update_version', 'update_unavailable_reason', 'auto_update', 'requires', 'requires_php', 'recently_active' );
        $out   = array();
        foreach ( $order as $key ) {
            $out[ $key ] = array_key_exists( $key, $row ) ? $row[ $key ] : null;
        }
        return $out;
    }

    







    private function hidden_updates_warning( bool $skip_check ): string {
        if ( ! wp_is_file_mod_allowed( 'capability_update_core' ) ) {
            $cause = 'file modifications are disabled on this site (DISALLOW_FILE_MODS, or a file_mod_allowed filter), so WordPress withholds update data from every user';
        } elseif ( is_multisite() && ! is_super_admin() ) {
            $cause = 'on a multisite network WordPress shows update data only to super admins (the update_plugins capability)';
        } else {
            $cause = 'they need the update_plugins capability, which your account lacks';
        }
        return sprintf( 'The fields %s are empty (null): %s.%s', self::UPDATE_FIELDS, $cause, $skip_check ? '' : ' No update check was made.' );
    }
}
