<?php
namespace Easy_MCP_AI\Tools\GSC;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GSC\GSC_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Query_Performance extends Base_Tool {

    public function get_name() { return 'wp_gsc_query_performance'; }

    public function get_description() {
        return 'Queries Google Search Console performance data. Returns clicks, impressions, CTR, and average position. Supports grouping by one or more dimensions (query, page, country, device, date, hour, searchAppearance). Filtering supports only query, page, country, device, and searchAppearance — date and hour are group-by dimensions only and cannot be used in `filters`. Covers web, news, image, video, discover, and googleNews search types. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'gsc'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Query Search Console performance',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'site_url'         => array( 'type' => 'string', 'description' => 'Property URL. Uses configured default if omitted.' ),
                'start_date'       => array( 'type' => 'string', 'description' => 'Start date in YYYY-MM-DD format.' ),
                'end_date'         => array( 'type' => 'string', 'description' => 'End date in YYYY-MM-DD format.' ),
                'dimensions'       => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string', 'enum' => array( 'query', 'page', 'country', 'device', 'date', 'hour', 'searchAppearance' ) ),
                    'description' => 'Dimensions to group results by. Omit for site totals.',
                    'default'     => array(),
                ),
                'search_type'      => array( 'type' => 'string', 'enum' => array( 'web', 'news', 'image', 'video', 'discover', 'googleNews' ), 'default' => 'web', 'description' => 'Search type to filter by (case-sensitive).' ),
                'filters'          => array(
                    'type'        => 'array',
                    'description' => 'Dimension filters. Each filter: {dimension, operator, expression}. Operators: equals, notEquals, contains, notContains, includingRegex, excludingRegex. `dimension` must be one of query, page, country, device, searchAppearance — date and hour are not filterable (group-by only) and are rejected.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'dimension'  => array( 'type' => 'string', 'enum' => array( 'query', 'page', 'country', 'device', 'searchAppearance' ) ),
                            'operator'   => array( 'type' => 'string' ),
                            'expression' => array( 'type' => 'string' ),
                        ),
                        'required' => array( 'dimension', 'operator', 'expression' ),
                    ),
                    'default' => array(),
                ),
                'aggregation_type' => array( 'type' => 'string', 'enum' => array( 'auto', 'byPage', 'byProperty', 'byNewsShowcasePanel' ), 'default' => 'auto', 'description' => 'How data is aggregated.' ),
                'data_state'       => array( 'type' => 'string', 'enum' => array( 'final', 'all', 'hourly_all' ), 'default' => 'final', 'description' => 'Data freshness. hourly_all requires the "hour" dimension to be included.' ),
                'row_limit'        => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 25000, 'default' => 1000, 'description' => 'Maximum rows to return.' ),
                'start_row'        => array( 'type' => 'integer', 'minimum' => 0, 'default' => 0, 'description' => 'Pagination offset.' ),
            ),
            'required' => array( 'start_date', 'end_date' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'start_date', 'end_date' ) );
        foreach ( array( 'start_date', 'end_date' ) as $field ) {
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments[ $field ] ) ) {
                throw new \RuntimeException( "{$field} must be in YYYY-MM-DD format." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        if ( strcmp( $arguments['end_date'], $arguments['start_date'] ) < 0 ) {
            throw new \RuntimeException( "end_date ({$arguments['end_date']}) must be on or after start_date ({$arguments['start_date']})." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $data_state = $arguments['data_state'] ?? 'final';
        $dimensions = isset( $arguments['dimensions'] ) ? array_map( 'strval', (array) $arguments['dimensions'] ) : array();
        if ( 'hourly_all' === $data_state && ! in_array( 'hour', $dimensions, true ) ) {
            throw new \RuntimeException( 'data_state=hourly_all requires the "hour" dimension to be included.' );
        }

        $site_url = ! empty( $arguments['site_url'] )
            ? GSC_Client::validate_site_url( (string) $arguments['site_url'] )
            : GSC_Client::default_site_url();

        $body = array(
            'startDate'       => $arguments['start_date'],
            'endDate'         => $arguments['end_date'],
            'type'            => $arguments['search_type'] ?? 'web',
            'aggregationType' => $arguments['aggregation_type'] ?? 'auto',
            'dataState'       => $data_state,
            'rowLimit'        => (int) ( $arguments['row_limit'] ?? 1000 ),
            'startRow'        => (int) ( $arguments['start_row'] ?? 0 ),
        );

        if ( ! empty( $dimensions ) ) {
            $body['dimensions'] = $dimensions;
        }

        if ( ! empty( $arguments['filters'] ) ) {
            $allowed_ops  = array( 'equals', 'notEquals', 'contains', 'notContains', 'includingRegex', 'excludingRegex' );
            
            
            $allowed_dims = array( 'query', 'page', 'country', 'device', 'searchAppearance' );
            $filters      = array();
            foreach ( $arguments['filters'] as $f ) {
                $dim  = (string) ( $f['dimension'] ?? '' );
                $op   = $f['operator'] ?? '';
                $expr = (string) ( $f['expression'] ?? '' );
                if ( ! in_array( $dim, $allowed_dims, true ) ) {
                    throw new \InvalidArgumentException( 'Invalid filter dimension "' . $dim . '". Must be one of: ' . implode( ', ', $allowed_dims ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( ! in_array( $op, $allowed_ops, true ) ) {
                    throw new \InvalidArgumentException( 'Invalid filter operator.' );
                }
                if ( strlen( $expr ) > 4096 ) {
                    throw new \InvalidArgumentException( 'Filter expression exceeds 4096 characters.' );
                }
                $filters[] = array(
                    'dimension'  => $dim,
                    'operator'   => $op,
                    'expression' => $expr,
                );
            }
            $body['dimensionFilterGroups'] = array(
                array(
                    'groupType' => 'and',
                    'filters'   => $filters,
                ),
            );
        }

        $encoded_site = rawurlencode( $site_url );
        $url          = "https://www.googleapis.com/webmasters/v3/sites/{$encoded_site}/searchAnalytics/query";

        $data = GSC_Client::post( $url, $body );

        $result = array(
            'rows'                    => $data['rows'] ?? array(),
            'responseAggregationType' => $data['responseAggregationType'] ?? null,
            'row_count'               => count( $data['rows'] ?? array() ),
        );
        return $result;
    }
}
