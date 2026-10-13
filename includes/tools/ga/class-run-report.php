<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Run_Report extends Base_Tool {

    public function get_name() { return 'wp_ga_run_report'; }

    public function get_description() {
        return 'Runs a custom Google Analytics 4 report. Returns sessions, activeUsers, screenPageViews, eventCount, engagementRate, totalRevenue, or any other GA4 metric sliced by any combination of dimensions (country, deviceCategory, pagePath, date, etc.), with optional filtering, ordering, and pagination. Call `wp_ga_get_metadata` first to discover valid dimension/metric apiName values for the property — GA4 metrics are not the same as Universal Analytics metrics (no "clicks" metric in GA4). Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Run Analytics report',
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
                'date_ranges'           => array(
                    'type'        => 'array',
                    'description' => 'Array of {start_date, end_date, name?}. Dates are YYYY-MM-DD, NdaysAgo, yesterday, or today. Max 4 ranges.',
                    'maxItems'    => 4,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'start_date' => array( 'type' => 'string' ),
                            'end_date'   => array( 'type' => 'string' ),
                            'name'       => array( 'type' => 'string' ),
                        ),
                        'required'   => array( 'start_date', 'end_date' ),
                    ),
                ),
                'dimensions'            => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'default' => array(), 'description' => 'Dimension apiName strings (e.g. country, deviceCategory, pagePath, date).' ),
                'metrics'               => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Metric apiName strings (e.g. activeUsers, sessions, screenPageViews, totalRevenue). REQUIRED.' ),
                'dimension_filter'      => array( 'type' => 'object', 'description' => 'Google FilterExpression shape — {andGroup|orGroup|notExpression|filter}.' ),
                'metric_filter'         => array( 'type' => 'object', 'description' => 'FilterExpression applied post-aggregation (HAVING clause).' ),
                'order_bys'             => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'default' => array(), 'description' => 'OrderBy objects — {dimension|metric: ..., desc: bool}.' ),
                'limit'                 => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 250000, 'default' => 10000 ),
                'offset'                => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0 ),
                'metric_aggregations'   => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'TOTAL', 'MINIMUM', 'MAXIMUM', 'COUNT' ) ), 'default' => array() ),
                'currency_code'         => array( 'type' => 'string', 'description' => 'ISO 4217 code, e.g. USD. Defaults to property currency.' ),
                'keep_empty_rows'       => array( 'type' => 'boolean', 'default' => false ),
                'return_property_quota' => array( 'type' => 'boolean', 'default' => false ),
                'cohort_spec'           => array( 'type' => 'object', 'description' => 'Cohort group definition. See GA4 Data API CohortSpec — defines cohort(s) and optional cohortReportSettings/cohortsRange.' ),
            ),
            'required' => array( 'date_ranges', 'metrics' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'date_ranges', 'metrics' ) );
        if ( ! is_array( $arguments['metrics'] ) ) {
            throw new \RuntimeException( 'metrics must be an array of metric apiName strings (e.g. activeUsers). Call wp_ga_get_metadata for the catalog.' );
        }
        if ( empty( $arguments['metrics'] ) ) {
            throw new \RuntimeException( 'metrics must contain at least one metric apiName string (e.g. activeUsers). Call wp_ga_get_metadata for the catalog.' );
        }
        if ( ! is_array( $arguments['date_ranges'] ) ) {
            throw new \RuntimeException( 'date_ranges must be an array of {start_date, end_date} objects.' );
        }
        if ( count( $arguments['date_ranges'] ) > 4 ) {
            throw new \RuntimeException( 'date_ranges accepts at most 4 ranges per Google Analytics Data API limit.' );
        }

        $property = ! empty( $arguments['property_id'] )
            ? GA_Client::normalize_property( (string) $arguments['property_id'] )
            : GA_Client::default_property_id();

        $body = array(
            'dateRanges' => GA_Client::build_date_ranges( $arguments['date_ranges'] ),
            'metrics'    => array_map( function ( $m ) { return array( 'name' => (string) $m ); }, $arguments['metrics'] ),
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
        if ( isset( $arguments['offset'] ) ) {
            $body['offset'] = (int) $arguments['offset'];
        }
        if ( ! empty( $arguments['metric_aggregations'] ) ) {
            $body['metricAggregations'] = array_values( (array) $arguments['metric_aggregations'] );
        }
        if ( ! empty( $arguments['currency_code'] ) ) {
            $body['currencyCode'] = (string) $arguments['currency_code'];
        }
        if ( ! empty( $arguments['keep_empty_rows'] ) ) {
            $body['keepEmptyRows'] = true;
        }
        if ( ! empty( $arguments['return_property_quota'] ) ) {
            $body['returnPropertyQuota'] = true;
        }
        if ( ! empty( $arguments['cohort_spec'] ) ) {
            if ( ! is_array( $arguments['cohort_spec'] ) ) {
                throw new \RuntimeException( 'cohort_spec must be a CohortSpec object. See GA4 Data API docs.' );
            }
            $body['cohortSpec'] = $arguments['cohort_spec'];
        }
        $data = GA_Client::post( "https://analyticsdata.googleapis.com/v1beta/{$property}:runReport", $body );

        
        
        
        if ( is_array( $data ) && ! isset( $data['error'] ) ) {
            $data['row_count']       = count( $data['rows'] ?? array() );
            $data['total_row_count'] = isset( $data['rowCount'] ) ? (int) $data['rowCount'] : null;
        }
        return $data;
    }
}
