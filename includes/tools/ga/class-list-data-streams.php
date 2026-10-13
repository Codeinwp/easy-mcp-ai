<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Data_Streams extends Base_Tool {

    public function get_name() { return 'wp_ga_list_data_streams'; }

    public function get_description() {
        return 'Lists web, iOS, and Android data streams configured on a GA4 property. Useful for confirming a property is actually receiving traffic from the expected domain or app. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'List Analytics data streams',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id' => array( 'type' => 'string', 'description' => 'Property id (numeric or properties/{id}). Falls back to default.' ),
                'page_size'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 200 ),
                'page_token'  => array( 'type' => 'string' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $property = ! empty( $arguments['property_id'] )
            ? GA_Client::normalize_property( (string) $arguments['property_id'] )
            : GA_Client::default_property_id();
        $size  = max( 1, min( 200, (int) ( $arguments['page_size'] ?? 200 ) ) );
        $token = isset( $arguments['page_token'] ) ? (string) $arguments['page_token'] : '';

        $url = "https://analyticsadmin.googleapis.com/v1beta/{$property}/dataStreams?pageSize=" . $size;
        if ( '' !== $token ) {
            $url .= '&pageToken=' . rawurlencode( $token );
        }
        $data = GA_Client::get( $url );

        $result = array(
            'dataStreams'   => $data['dataStreams'] ?? array(),
            'nextPageToken' => $data['nextPageToken'] ?? null,
            'count'         => count( $data['dataStreams'] ?? array() ),
        );
        return $result;
    }
}
