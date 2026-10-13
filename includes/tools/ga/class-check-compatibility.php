<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Compatibility extends Base_Tool {

    public function get_name() { return 'wp_ga_check_compatibility'; }

    public function get_description() {
        return 'Lists which dimensions and metrics can be safely combined in a GA4 report request. GA4 has compatibility constraints (e.g. cost-data dimensions can only combine with cost-data metrics) — call this tool to validate a planned query before running it. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Check Analytics report compatibility',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id'          => array( 'type' => 'string', 'description' => 'Property id. Falls back to default.' ),
                'dimensions'           => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
                'metrics'              => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array() ),
                'dimension_filter'     => array( 'type' => 'object' ),
                'metric_filter'        => array( 'type' => 'object' ),
                'compatibility_filter' => array( 'type' => 'string', 'enum' => array( 'COMPATIBLE', 'INCOMPATIBLE' ), 'default' => 'COMPATIBLE' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $property = ! empty( $arguments['property_id'] )
            ? GA_Client::normalize_property( (string) $arguments['property_id'] )
            : GA_Client::default_property_id();

        $body = array();
        if ( ! empty( $arguments['dimensions'] ) ) {
            $body['dimensions'] = array_map( function ( $d ) { return array( 'name' => (string) $d ); }, (array) $arguments['dimensions'] );
        }
        if ( ! empty( $arguments['metrics'] ) ) {
            $body['metrics'] = array_map( function ( $m ) { return array( 'name' => (string) $m ); }, (array) $arguments['metrics'] );
        }
        if ( ! empty( $arguments['dimension_filter'] ) ) {
            $body['dimensionFilter'] = $arguments['dimension_filter'];
        }
        if ( ! empty( $arguments['metric_filter'] ) ) {
            $body['metricFilter'] = $arguments['metric_filter'];
        }
        $body['compatibilityFilter'] = $arguments['compatibility_filter'] ?? 'COMPATIBLE';
        $data = GA_Client::post( "https://analyticsdata.googleapis.com/v1beta/{$property}:checkCompatibility", $body );

        return array(
            'dimensionCompatibilities' => $data['dimensionCompatibilities'] ?? array(),
            'metricCompatibilities'    => $data['metricCompatibilities'] ?? array(),
        );
    }
}
