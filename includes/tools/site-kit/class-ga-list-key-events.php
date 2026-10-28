<?php
namespace Easy_MCP_AI\Tools\Site_Kit;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GA_List_Key_Events extends Base_Tool {

    use Site_Kit_Rest;

    public function get_name() {
        return 'wp_sitekit_ga_list_key_events';
    }

    public function get_description() {
        return 'Lists the key events (formerly "conversions") of the Google Analytics 4 property Site Kit by Google is connected to, through Site Kit\'s own connection: no Google credentials needed in Easy MCP AI. Site Kit decides who gets them, as on its dashboard. Use an event\'s `eventName` with the keyEvents metric or an eventName dimension filter in wp_sitekit_ga_run_report. No arguments. Returns { key_events: [{ name, eventName, countingMethod, createTime, custom, deletable, defaultValue? }], count }. Site Kit cannot create or delete key events: use the service-account tools wp_ga_create_key_event and wp_ga_delete_key_event.';
    }

    public function get_annotations() {
        return array(
            'title'           => 'Site Kit: GA4 key events',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
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
        $events = $this->site_kit_get( '/modules/analytics-4/data/key-events' );
        $events = is_array( $events ) ? array_values( $events ) : array();
        return array(
            'key_events' => $events,
            'count'      => count( $events ),
        );
    }
}
