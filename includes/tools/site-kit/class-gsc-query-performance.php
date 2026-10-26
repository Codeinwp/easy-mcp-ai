<?php
namespace Easy_MCP_AI\Tools\Site_Kit;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








class GSC_Query_Performance extends Base_Tool {

    use Site_Kit_Rest;

    
    const DIMENSIONS = array( 'query', 'page', 'country', 'device', 'date', 'searchAppearance' );

    public function get_name() {
        return 'wp_sitekit_gsc_query_performance';
    }

    public function get_description() {
        return 'Reads Google Search Console clicks, impressions, CTR and average position for this site through Site Kit by Google\'s own connection, for the property Site Kit is connected to: no Google credentials needed in Easy MCP AI. Site Kit decides who gets data, as on its dashboard. Web search only, including fresh (not yet final) data; Site Kit limits a domain property to this site\'s URLs. Optional: `dimensions` (query, page, country, device, date, searchAppearance; omit for site totals), `start_date`/`end_date` (YYYY-MM-DD, both or neither; default the last 28 days), `url` (only this page), `row_limit` (1-25000, default 1000). Returns { rows: [{ keys, clicks, impressions, ctr, position }], count }. Site Kit offers no query filters and no other search types: for those, and for URL inspection or sitemaps, use the service-account tools (wp_gsc_query_performance, wp_gsc_inspect_url, wp_gsc_list_sitemaps).';
    }

    public function get_annotations() {
        return array(
            'title'           => 'Site Kit: Search Console performance',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'dimensions' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string', 'enum' => self::DIMENSIONS ),
                    'description' => 'Group rows by these. Omit for site totals.',
                ),
                'start_date' => array( 'type' => 'string', 'description' => 'YYYY-MM-DD. Send with end_date; omit both for the last 28 days.' ),
                'end_date'   => array( 'type' => 'string', 'description' => 'YYYY-MM-DD, on or after start_date.' ),
                'url'        => array( 'type' => 'string', 'description' => 'Only this page (a full URL on this site).' ),
                'row_limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 25000, 'description' => 'Most rows to return. Default 1000.' ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_site_kit();
        $rows = $this->site_kit_get( '/modules/search-console/data/searchanalytics', self::query_params( $arguments ) );
        $rows = is_array( $rows ) ? array_values( $rows ) : array();
        return array(
            'rows'  => $rows,
            'count' => count( $rows ),
        );
    }

    






    public static function query_params( array $arguments ): array {
        $params     = array();
        $dimensions = self::string_list( $arguments['dimensions'] ?? null, 'dimensions' );
        $unknown    = array_diff( $dimensions, self::DIMENSIONS );
        if ( ! empty( $unknown ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown dimension %s. Use: %s.', reset( $unknown ), implode( ', ', self::DIMENSIONS ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! empty( $dimensions ) ) {
            $params['dimensions'] = $dimensions;
        }

        $range = self::date_range( $arguments, 'start_date', 'end_date' );
        if ( null !== $range ) {
            $params['startDate'] = $range[0];
            $params['endDate']   = $range[1];
        }

        if ( isset( $arguments['url'] ) && '' !== $arguments['url'] ) {
            if ( ! is_string( $arguments['url'] ) ) {
                throw new \InvalidArgumentException( 'url must be a string.' );
            }
            $params['url'] = $arguments['url'];
        }

        if ( isset( $arguments['row_limit'] ) ) {
            $limit = filter_var( $arguments['row_limit'], FILTER_VALIDATE_INT );
            if ( false === $limit || $limit < 1 || $limit > 25000 ) {
                throw new \InvalidArgumentException( 'row_limit must be an integer from 1 to 25000.' );
            }
            $params['rowLimit'] = $limit;
        }

        return $params;
    }
}
