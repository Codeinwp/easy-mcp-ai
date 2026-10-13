<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Run_Pivot_Report extends Base_Tool {

    public function get_name() { return 'wp_ga_run_pivot_report'; }

    public function get_description() {
        return 'Runs a pivot-table Analytics report. Example: countries down the rows and devices across the columns, with sessions in each cell. Provide pivots in Google-native shape — array of {fieldNames[], orderBys[], offset, limit, metricAggregations[]}. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Run Analytics pivot report',
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
                    'description' => 'Array of {start_date, end_date, name?}. Max 4 ranges.',
                    'maxItems'    => 4,
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'start_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD, today, yesterday, or NdaysAgo.' ),
                            'end_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD, today, yesterday, or NdaysAgo.' ),
                            'name'       => array( 'type' => 'string', 'description' => 'Optional label for this range in the response.' ),
                        ),
                        'required'   => array( 'start_date', 'end_date' ),
                    ),
                ),
                'dimensions'            => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'All dimensions referenced in any pivot.' ),
                'metrics'               => array( 'description' => 'GA4 metric API names to report (e.g. activeUsers, sessions).', 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                'pivots'                => array( 'type' => 'array', 'items' => array( 'type' => 'object' ), 'description' => 'One Google-native Pivot object per pivot axis: {fieldNames[], orderBys[], offset, limit, metricAggregations[]}.' ),
                'dimension_filter'      => array( 'type' => 'object' ),
                'metric_filter'         => array( 'type' => 'object' ),
                'currency_code'         => array( 'type' => 'string' ),
                'keep_empty_rows'       => array( 'type' => 'boolean', 'default' => false ),
                'return_property_quota' => array( 'type' => 'boolean', 'default' => false ),
            ),
            'required' => array( 'date_ranges', 'dimensions', 'metrics', 'pivots' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'date_ranges', 'dimensions', 'metrics', 'pivots' ) );
        foreach ( array( 'date_ranges', 'dimensions', 'metrics', 'pivots' ) as $field ) {
            if ( ! is_array( $arguments[ $field ] ) ) {
                throw new \RuntimeException( "{$field} must be an array." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        if ( empty( $arguments['dimensions'] ) ) {
            throw new \RuntimeException( 'dimensions must contain at least one dimension apiName string.' );
        }
        if ( empty( $arguments['metrics'] ) ) {
            throw new \RuntimeException( 'metrics must contain at least one metric apiName string (e.g. sessions).' );
        }
        if ( empty( $arguments['pivots'] ) ) {
            throw new \RuntimeException( 'pivots must contain at least one Pivot object. See GA4 Data API runPivotReport docs.' );
        }
        if ( count( $arguments['date_ranges'] ) > 4 ) {
            throw new \RuntimeException( 'date_ranges accepts at most 4 ranges per Google Analytics Data API limit.' );
        }

        $property = ! empty( $arguments['property_id'] )
            ? GA_Client::normalize_property( (string) $arguments['property_id'] )
            : GA_Client::default_property_id();

        $body = array(
            'dateRanges' => GA_Client::build_date_ranges( $arguments['date_ranges'] ),
            'dimensions' => array_map( function ( $d ) { return array( 'name' => (string) $d ); }, $arguments['dimensions'] ),
            'metrics'    => array_map( function ( $m ) { return array( 'name' => (string) $m ); }, $arguments['metrics'] ),
            'pivots'     => array_values( $arguments['pivots'] ),
        );
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
        if ( ! empty( $arguments['currency_code'] ) ) {
            $body['currencyCode'] = (string) $arguments['currency_code'];
        }
        if ( ! empty( $arguments['keep_empty_rows'] ) ) {
            $body['keepEmptyRows'] = true;
        }
        if ( ! empty( $arguments['return_property_quota'] ) ) {
            $body['returnPropertyQuota'] = true;
        }

        $data = GA_Client::post( "https://analyticsdata.googleapis.com/v1beta/{$property}:runPivotReport", $body );

        if ( is_array( $data ) && ! isset( $data['error'] ) ) {
            $data['row_count'] = count( $data['rows'] ?? array() );
        }
        return $data;
    }
}
