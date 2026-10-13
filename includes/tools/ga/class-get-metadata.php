<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Metadata extends Base_Tool {

    public function get_name() { return 'wp_ga_get_metadata'; }

    public function get_description() {
        return 'Returns the catalog of all dimensions and metrics available for Analytics reports on a property (including custom ones). Critical discovery tool — call this to validate dimension/metric names before sending a report query. Pass property_id="universal" or omit it to get the cross-property universal catalog. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Get Analytics metadata',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id' => array( 'type' => 'string', 'default' => 'universal', 'description' => 'Property id (numeric or properties/{id}), or the literal "universal" for the cross-property catalog. Omit for universal.' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        
        
        
        if ( empty( $arguments['property_id'] ) ) {
            $property = 'properties/0';
        } else {
            $property = GA_Client::normalize_property( (string) $arguments['property_id'] );
        }

        $data = GA_Client::get( "https://analyticsdata.googleapis.com/v1beta/{$property}/metadata" );

        $result = array(
            'dimensions'  => $data['dimensions'] ?? array(),
            'metrics'     => $data['metrics'] ?? array(),
            'comparisons' => $data['comparisons'] ?? array(),
        );
        return $result;
    }
}
