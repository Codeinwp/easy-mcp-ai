<?php
namespace Easy_MCP_AI\Tools\Site_Kit;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






class GA_Run_Report extends Base_Tool {

    use Site_Kit_Rest;

    
    const MATCH_TYPES = array( 'EXACT', 'BEGINS_WITH', 'ENDS_WITH', 'CONTAINS', 'FULL_REGEXP', 'PARTIAL_REGEXP' );

    public function get_name() {
        return 'wp_sitekit_ga_run_report';
    }

    public function get_description() {
        return 'Runs a Google Analytics 4 report through Site Kit by Google\'s own connection, for the property Site Kit is connected to: no Google credentials needed in Easy MCP AI. Site Kit decides who gets data, as on its dashboard: its owner, a user with their own Google account connected in Site Kit, or a user whose role the owner shared the dashboard with (Site Kit then allows only its shared-dashboard metrics and dimensions). Site Kit limits every report to this site\'s hostnames. Required: `metrics` (GA4 apiNames, e.g. ["sessions", "totalUsers", "screenPageViews"]). Optional: `dimensions` (e.g. ["pagePath", "date", "sessionDefaultChannelGroup"]), `start_date`/`end_date` (YYYY-MM-DD, both or neither; default the last 28 days), `compare_start_date`/`compare_end_date`, `dimension_filters`, `url` (one page), `order_by`, `limit`. Returns Google\'s RunReportResponse as Site Kit returns it: { dimensionHeaders, metricHeaders, rows, totals, rowCount, metadata }. Call wp_sitekit_get_status first if unsure Site Kit gives this account Analytics data. For pivot, realtime or metadata reports, or to check which metrics and dimensions combine, use the service-account tools (wp_ga_run_pivot_report, wp_ga_run_realtime_report, wp_ga_get_metadata, wp_ga_check_compatibility).';
    }

    public function get_annotations() {
        return array(
            'title'           => 'Site Kit: GA4 report',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'metrics'            => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'minItems'    => 1,
                    'maxItems'    => 10,
                    'description' => 'GA4 metric apiNames, e.g. sessions, totalUsers, screenPageViews, engagementRate.',
                ),
                'dimensions'         => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'maxItems'    => 9,
                    'description' => 'GA4 dimension apiNames, e.g. pagePath, date, country, sessionDefaultChannelGroup. Omit for site totals.',
                ),
                'start_date'         => array( 'type' => 'string', 'description' => 'YYYY-MM-DD. Send with end_date; omit both for the last 28 days.' ),
                'end_date'           => array( 'type' => 'string', 'description' => 'YYYY-MM-DD, on or after start_date.' ),
                'compare_start_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD start of a second range to compare with. Needs start_date and end_date.' ),
                'compare_end_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD end of the comparison range.' ),
                'dimension_filters'  => array(
                    'type'        => 'array',
                    'description' => 'Rows to keep, all filters must match. At most one filter per dimension.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'dimension'  => array( 'type' => 'string', 'description' => 'Dimension apiName, e.g. pagePath.' ),
                            'match_type' => array( 'type' => 'string', 'enum' => self::MATCH_TYPES, 'description' => 'How each value is matched. Default EXACT.' ),
                            'values'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1, 'description' => 'A row matches when any value matches.' ),
                            'exclude'    => array( 'type' => 'boolean', 'description' => 'Keep the rows that do NOT match instead. Default false.' ),
                        ),
                        'required'   => array( 'dimension', 'values' ),
                    ),
                ),
                'url'                => array( 'type' => 'string', 'description' => 'Only this page of the site (a full URL or a path).' ),
                'order_by'           => array(
                    'type'        => 'array',
                    'description' => 'Sort order. Each item names exactly one of metric or dimension.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'metric'    => array( 'type' => 'string' ),
                            'dimension' => array( 'type' => 'string' ),
                            'desc'      => array( 'type' => 'boolean', 'description' => 'Descending. Default false.' ),
                        ),
                    ),
                ),
                'limit'              => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'description' => 'Most rows to return. Default: Google\'s, 10000.' ),
            ),
            'required'   => array( 'metrics' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_site_kit();
        return $this->site_kit_get( '/modules/analytics-4/data/report', self::report_params( $arguments ) );
    }

    






    public static function report_params( array $arguments ): array {
        $metrics = self::string_list( $arguments['metrics'] ?? null, 'metrics' );
        if ( empty( $metrics ) ) {
            throw new \InvalidArgumentException( 'metrics is required: at least one GA4 metric apiName, e.g. ["sessions"].' );
        }
        $params = array( 'metrics' => $metrics );

        $dimensions = self::string_list( $arguments['dimensions'] ?? null, 'dimensions' );
        if ( ! empty( $dimensions ) ) {
            $params['dimensions'] = $dimensions;
        }

        $range   = self::date_range( $arguments, 'start_date', 'end_date' );
        $compare = self::date_range( $arguments, 'compare_start_date', 'compare_end_date' );
        if ( null !== $compare && null === $range ) {
            throw new \InvalidArgumentException( 'compare_start_date and compare_end_date need start_date and end_date: Site Kit ignores a comparison range without a main range.' );
        }
        if ( null !== $range ) {
            $params['startDate'] = $range[0];
            $params['endDate']   = $range[1];
        }
        if ( null !== $compare ) {
            $params['compareStartDate'] = $compare[0];
            $params['compareEndDate']   = $compare[1];
        }

        $filters = self::dimension_filters( $arguments['dimension_filters'] ?? null );
        if ( ! empty( $filters ) ) {
            $params['dimensionFilters'] = $filters;
        }

        if ( isset( $arguments['url'] ) && '' !== $arguments['url'] ) {
            if ( ! is_string( $arguments['url'] ) ) {
                throw new \InvalidArgumentException( 'url must be a string.' );
            }
            $params['url'] = $arguments['url'];
        }

        $order_by = self::order_by( $arguments['order_by'] ?? null );
        if ( ! empty( $order_by ) ) {
            $params['orderby'] = $order_by;
        }

        if ( isset( $arguments['limit'] ) ) {
            $limit = filter_var( $arguments['limit'], FILTER_VALIDATE_INT );
            if ( false === $limit || $limit < 1 || $limit > 10000 ) {
                throw new \InvalidArgumentException( 'limit must be an integer from 1 to 10000.' );
            }
            $params['limit'] = $limit;
        }

        return $params;
    }

    







    private static function dimension_filters( $value ): array {
        if ( null === $value ) {
            return array();
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( 'dimension_filters must be an array of {dimension, values, match_type?, exclude?}.' );
        }
        $filters = array();
        foreach ( $value as $filter ) {
            if ( ! is_array( $filter ) || ! isset( $filter['dimension'] ) || ! is_string( $filter['dimension'] ) || '' === trim( $filter['dimension'] ) ) {
                throw new \InvalidArgumentException( 'Each dimension_filters item needs a dimension apiName and values.' );
            }
            $dimension = trim( $filter['dimension'] );
            if ( isset( $filters[ $dimension ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'dimension_filters names %s twice. Put every value for one dimension in one filter\'s values.', $dimension ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $values = self::string_list( $filter['values'] ?? null, 'dimension_filters values' );
            if ( empty( $values ) ) {
                throw new \InvalidArgumentException( sprintf( 'The %s filter needs at least one value.', $dimension ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $match_type = $filter['match_type'] ?? 'EXACT';
            if ( ! in_array( $match_type, self::MATCH_TYPES, true ) ) {
                throw new \InvalidArgumentException( 'match_type must be one of ' . implode( ', ', self::MATCH_TYPES ) . '.' );
            }
            $filters[ $dimension ] = array(
                'filterType'    => 'stringFilter',
                'matchType'     => $match_type,
                'value'         => 1 === count( $values ) ? $values[0] : $values,
                'notExpression' => ! empty( $filter['exclude'] ),
            );
        }
        return $filters;
    }

    






    private static function order_by( $value ): array {
        if ( null === $value ) {
            return array();
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( 'order_by must be an array of {metric|dimension, desc?}.' );
        }
        $order = array();
        foreach ( $value as $item ) {
            $metric    = is_array( $item ) && isset( $item['metric'] ) && is_string( $item['metric'] ) ? trim( $item['metric'] ) : '';
            $dimension = is_array( $item ) && isset( $item['dimension'] ) && is_string( $item['dimension'] ) ? trim( $item['dimension'] ) : '';
            if ( ( '' === $metric ) === ( '' === $dimension ) ) {
                throw new \InvalidArgumentException( 'Each order_by item names exactly one of metric or dimension.' );
            }
            $entry = array( 'desc' => ! empty( $item['desc'] ) );
            if ( '' !== $metric ) {
                $entry['metric'] = array( 'metricName' => $metric );
            } else {
                $entry['dimension'] = array( 'dimensionName' => $dimension );
            }
            $order[] = $entry;
        }
        return $order;
    }
}
