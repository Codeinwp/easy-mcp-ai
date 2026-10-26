<?php
namespace Easy_MCP_AI\Tools\Site_Kit;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}










class Get_Status extends Base_Tool {

    use Site_Kit_Rest;

    
    const MODULES = array(
        'analytics-4'    => array(
            'label' => 'Google Analytics 4',
            'tools' => array( 'wp_sitekit_ga_run_report', 'wp_sitekit_ga_list_key_events' ),
        ),
        'search-console' => array(
            'label' => 'Google Search Console',
            'tools' => array( 'wp_sitekit_gsc_query_performance' ),
        ),
    );

    public function get_name() {
        return 'wp_sitekit_get_status';
    }

    public function get_description() {
        return 'Shows whether Site Kit by Google can give this account Google Analytics 4 and Search Console data, and through whose Google connection. Call it first when unsure, or when another Site Kit tool is refused. No arguments. Returns { site_kit_version, modules: [{ module, label, active, connected, access, reason?, property_id?, tools }] }. `access` is "owner" (your own connection), "shared" (the owner\'s connection, shared with your role: Site Kit then allows only its shared-dashboard metrics and dimensions), "own_google_account" (the Google account you connected in Site Kit, which may not have access to the property), or "none" with the `reason`. `property_id` is shown only to users who can manage Site Kit.';
    }

    public function get_annotations() {
        return array(
            'title'           => 'Site Kit: status',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_site_kit();
        $listed  = $this->listed_modules();
        $modules = array();
        foreach ( self::MODULES as $slug => $module ) {
            $modules[] = $this->module_status( $slug, $module, $listed[ $slug ] ?? null );
        }
        return array(
            'site_kit_version' => GOOGLESITEKIT_VERSION,
            'modules'          => $modules,
        );
    }

    





    private function listed_modules(): array {
        try {
            $list = $this->site_kit_get( '/core/modules/data/list' );
        } catch ( Detailed_Tool_Error $e ) {
            return array();
        }
        $by_slug = array();
        foreach ( is_array( $list ) ? $list : array() as $module ) {
            if ( is_array( $module ) && isset( $module['slug'] ) && is_string( $module['slug'] ) ) {
                $by_slug[ $module['slug'] ] = $module;
            }
        }
        return $by_slug;
    }

    







    private function module_status( string $slug, array $module, ?array $listed ): array {
        $row = array(
            'module'    => $slug,
            'label'     => $module['label'],
            'active'    => null === $listed ? null : ! empty( $listed['active'] ),
            'connected' => null === $listed ? null : ! empty( $listed['connected'] ),
        );
        $settings = $this->module_settings( $slug );
        if ( isset( $settings['propertyID'] ) && is_scalar( $settings['propertyID'] ) && '' !== (string) $settings['propertyID'] ) {
            $row['property_id'] = (string) $settings['propertyID'];
        }

        list( $access, $reason ) = $this->access( $slug, $listed, $settings );
        $row['access'] = $access;
        if ( '' !== $reason ) {
            $row['reason'] = $reason;
        }
        $row['tools'] = $module['tools'];
        return $row;
    }

    







    private function module_settings( string $slug ): array {
        try {
            $settings = $this->site_kit_get( '/modules/' . $slug . '/data/settings' );
        } catch ( Detailed_Tool_Error $e ) {
            return array();
        }
        return is_array( $settings ) ? $settings : array();
    }

    







    private function access( string $slug, ?array $listed, array $settings ): array {
        if ( null !== $listed && empty( $listed['active'] ) ) {
            return array( 'none', 'The module is not active in Site Kit. An administrator can connect it under Site Kit › Settings › Connect more services.' );
        }
        if ( null !== $listed && empty( $listed['connected'] ) ) {
            return array( 'none', 'The module is not connected to a Google property yet. An administrator can finish its setup in Site Kit.' );
        }
        $shared = current_user_can( 'googlesitekit_read_shared_module_data', $slug );
        if ( ! current_user_can( 'googlesitekit_setup' ) && ! current_user_can( 'googlesitekit_view_posts_insights' ) ) {
            return $shared
                ? array( 'none', 'Site Kit shares this module with your role, but shows shared data only to users who have opened Site Kit\'s dashboard in wp-admin once. Open Site Kit › Dashboard, then try again.' )
                : array( 'none', 'Site Kit does not share this module with your role, and you have not connected your own Google account in Site Kit. An administrator can share it under Site Kit › Settings › Admin Settings › Dashboard sharing.' );
        }
        if ( self::owner_id( $listed, $settings ) === (int) get_current_user_id() ) {
            return array( 'owner', '' );
        }
        if ( $shared && empty( $listed['recoverable'] ) ) {
            return array( 'shared', '' );
        }
        if ( current_user_can( 'googlesitekit_view_authenticated_dashboard' ) ) {
            return array( 'own_google_account', '' );
        }
        if ( $shared ) {
            return array( 'none', 'Site Kit shares this module with your role, but its owner\'s Google connection is no longer usable. An administrator can reconnect the module in Site Kit.' );
        }
        return array( 'none', 'Site Kit does not share this module with your role, and you have not connected your own Google account in Site Kit. An administrator can share it under Site Kit › Settings › Admin Settings › Dashboard sharing.' );
    }

    







    private static function owner_id( ?array $listed, array $settings ): int {
        if ( isset( $listed['owner']['id'] ) && is_numeric( $listed['owner']['id'] ) ) {
            return (int) $listed['owner']['id'];
        }
        return isset( $settings['ownerID'] ) && is_numeric( $settings['ownerID'] ) ? (int) $settings['ownerID'] : 0;
    }
}
