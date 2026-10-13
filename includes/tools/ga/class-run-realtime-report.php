<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Run_Realtime_Report extends Base_Tool {

    public function get_name() { return 'wp_ga_run_realtime_report'; }

    public function get_description() {
        return 'Runs a realtime GA4 report — active users in the last 30 minutes (60 minutes for GA 360 properties). Useful for "what is happening right now" questions. Realtime dimensions/metrics are a strict subset of the core catalog (e.g. activeUsers, screenPageViews, eventCount, country, deviceCategory, eventName, minutesAgo). Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Run Analytics realtime report',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'property_id'           => array( 'type' => 'string', 'description' => 'Property id. Falls back to default.' ),
                'dimensions'            => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array(), 'description' => 'Realtime dimensions only (subset of core).' ),
                'metrics'               => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Realtime metrics. REQUIRED.' ),
                'minute_ranges'         => array(
                    'type'        => 'array',
                    'description' => 'Array of {startMinutesAgo, endMinutesAgo, name?}. Default: one range {29, 0}. Local validation: 0–59. Google enforces a 29-minute cap for standard GA4 properties (59 for GA 360) and returns a clear error for out-of-range values. endMinutesAgo must be <= startMinutesAgo.',
                    'items'       => array( 'type' => 'object' ),
                    'default'     => array( array( 'startMinutesAgo' => 29, 'endMinutesAgo' => 0 ) ),
                ),
                'dimension_filter'      => array( 'type' => 'object' ),
                'metric_filter'         => array( 'type' => 'object' ),
                'order_bys'             => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'default' => array() ),
                'limit'                 => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 250000, 'default' => 10000 ),
                'metric_aggregations'   => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'TOTAL', 'MINIMUM', 'MAXIMUM', 'COUNT' ) ), 'default' => array() ),
                'return_property_quota' => array( 'type' => 'boolean', 'default' => false ),
            ),
            'required' => array( 'metrics' ),
        );
    }

    public function execute( array $arguments ) {
        try {
            $this->validate_required( $arguments, array( 'metrics' ) );
            if ( ! is_array( $arguments['metrics'] ) ) {
                throw new \RuntimeException( 'metrics must be an array of realtime metric apiName strings (e.g. activeUsers).' );
            }
            if ( empty( $arguments['metrics'] ) ) {
                throw new \RuntimeException( 'metrics must contain at least one realtime metric apiName string (e.g. activeUsers).' );
            }

            $minute_ranges = $arguments['minute_ranges'] ?? array(
                array( 'startMinutesAgo' => 29, 'endMinutesAgo' => 0 ),
            );
            foreach ( $minute_ranges as $i => $range ) {
                if ( ! is_array( $range ) ) {
                    throw new \RuntimeException( "minute_ranges[{$i}] must be an object." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $start = isset( $range['startMinutesAgo'] ) ? (int) $range['startMinutesAgo'] : 29;
                $end   = isset( $range['endMinutesAgo'] )   ? (int) $range['endMinutesAgo']   : 0;
                if ( $start < 0 || $start > 59 ) {
                    throw new \RuntimeException( "minute_ranges[{$i}].startMinutesAgo ({$start}) must be between 0 and 59." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( $end < 0 || $end > 59 ) {
                    throw new \RuntimeException( "minute_ranges[{$i}].endMinutesAgo ({$end}) must be between 0 and 59." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( $end > $start ) {
                    throw new \RuntimeException( "minute_ranges[{$i}].endMinutesAgo ({$end}) must be less than or equal to startMinutesAgo ({$start})." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }

            $property = ! empty( $arguments['property_id'] )
                ? GA_Client::normalize_property( (string) $arguments['property_id'] )
                : GA_Client::default_property_id();

            $body = array(
                'metrics'      => array_map( function ( $m ) { return array( 'name' => (string) $m ); }, $arguments['metrics'] ),
                'minuteRanges' => array_values( $minute_ranges ),
            );
            if ( ! empty( $arguments['dimensions'] ) ) {
                $body['dimensions'] = array_map( function ( $d ) { return array( 'name' => (string) $d ); }, (array) $arguments['dimensions'] );
            }
            if ( ! empty( $arguments['dimension_filter'] ) ) {
                if ( ! is_array( $arguments['dimension_filter'] ) ) {
                    throw new \RuntimeException( 'dimension_filter must be a FilterExpression object (array). See GA4 Data API docs.' );
                }
                $body['dimensionFilter'] = $arguments['dimension_filter'];
            }
            if ( ! empty( $arguments['metric_filter'] ) ) {
                if ( ! is_array( $arguments['metric_filter'] ) ) {
                    throw new \RuntimeException( 'metric_filter must be a FilterExpression object (array). See GA4 Data API docs.' );
                }
                $body['metricFilter'] = $arguments['metric_filter'];
            }
            if ( ! empty( $arguments['order_bys'] ) ) {
                if ( ! is_array( $arguments['order_bys'] ) ) {
                    throw new \RuntimeException( 'order_bys must be an array of OrderBy objects.' );
                }
                $body['orderBys'] = $arguments['order_bys'];
            }
            if ( isset( $arguments['limit'] ) ) {
                $body['limit'] = (int) $arguments['limit'];
            }
            if ( ! empty( $arguments['metric_aggregations'] ) ) {
                $body['metricAggregations'] = array_values( (array) $arguments['metric_aggregations'] );
            }
            if ( ! empty( $arguments['return_property_quota'] ) ) {
                $body['returnPropertyQuota'] = true;
            }

            $data = GA_Client::post( "https://analyticsdata.googleapis.com/v1beta/{$property}:runRealtimeReport", $body );
        } catch ( \Exception $e ) {
            $msg = $e->getMessage();
            
            
            
            if ( false !== stripos( $msg, 'minuteRange' ) || false !== stripos( $msg, 'minute_range' ) ) {
                $msg .= ' (Hint: standard GA4 properties limit startMinutesAgo to 29. Only GA 360 supports up to 59.)';
            }
            throw new \RuntimeException( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( is_array( $data ) && ! isset( $data['error'] ) ) {
            $data['row_count'] = count( $data['rows'] ?? array() );
        }
        return $data;
    }
}
