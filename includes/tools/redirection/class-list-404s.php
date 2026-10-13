<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-redirection-rest.php';

class List_404s extends Base_Tool {

    use Redirection_Rest;

    




    const GROUP_BY = array( 'url', 'ip', 'agent' );

    public function get_name() {
        return 'wp_redirection_list_404s';
    }

    public function get_description() {
        return 'Lists the 404 (page not found) log the Redirection plugin keeps, newest first. Optional: `search` (part of the requested URL), `ip`, `group_by` ("url", "ip" or "agent": one row per value with its hit count, most frequent first — use group_by "url" to find the broken URLs worth redirecting), `orderby` ("url" or "ip"; with group_by only "count"), `direction`, `per_page` (5-200, default 25), `page` (1-based). Returns { entries: [{ id, url, created, created_time, ip, agent, referrer, http_code, request_method }], total, page, per_page, total_pages }, or with group_by { groups: [{ value, count }], group_by, total, … }. `ip` is empty unless IP logging is on in Redirection\'s options. Requires the Redirection plugin active with 404 logging on.';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'search'    => array(
                    'type'        => 'string',
                    'description' => 'Part of the requested URL to match (substring).',
                ),
                'ip'        => array(
                    'type'        => 'string',
                    'description' => 'Only requests from this IP address.',
                ),
                'group_by'  => array(
                    'type'        => 'string',
                    'enum'        => self::GROUP_BY,
                    'description' => 'One row per URL, IP or user agent with its hit count.',
                ),
                'orderby'   => array(
                    'type'        => 'string',
                    'enum'        => array( 'url', 'ip', 'count' ),
                    'description' => 'Sort field. Default: newest first, or most frequent first with group_by. With group_by only "count" is accepted; without it, "url" or "ip".',
                ),
                'direction' => array(
                    'type'        => 'string',
                    'enum'        => array( 'asc', 'desc' ),
                    'description' => 'Sort direction. Default: desc.',
                ),
                'per_page'  => array(
                    'type'        => 'integer',
                    'description' => 'Rows per page, 5-200 (Redirection\'s own limits).',
                    'default'     => 25,
                    'minimum'     => 5,
                    'maximum'     => 200,
                ),
                'page'      => array(
                    'type'        => 'integer',
                    'description' => 'Page number, starting at 1.',
                    'default'     => 1,
                    'minimum'     => 1,
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $this->require_redirection();

        list( $per_page, $page, $vendor_page ) = self::paging( $arguments );
        $group_by = $this->group_by( $arguments );
        $params   = array_merge(
            array(
                'per_page' => $per_page,
                'page'     => $vendor_page,
            ),
            null !== $group_by ? array( 'groupBy' => $group_by ) : array(),
            self::filters( $arguments )
        );
        $this->ordering( $arguments, array( 'url', 'ip', 'count' ), $params );

        $data   = $this->rest_request( 'GET', self::$redirection_ns . '/404', $params );
        $rows   = self::rows( $data );
        $paging = self::paging_result( self::int_of( is_array( $data ) ? $data : array(), 'total' ), $page, $per_page );

        if ( null !== $group_by ) {
            return array_merge(
                array(
                    'groups'   => array_map(
                        function ( array $row ) use ( $group_by ): array {
                            return array(
                                'value' => isset( $row[ $group_by ] ) ? self::str_of( $row, $group_by ) : self::str_of( $row, 'id' ),
                                'count' => self::int_of( $row, 'count' ),
                            );
                        },
                        $rows
                    ),
                    'group_by' => $group_by,
                ),
                $paging
            );
        }
        return array_merge( array( 'entries' => array_map( array( __CLASS__, 'entry' ), $rows ) ), $paging );
    }

    





    private function group_by( array $arguments ): ?string {
        $group_by = null;
        if ( isset( $arguments['group_by'] ) && '' !== $arguments['group_by'] ) {
            $this->refuse_unless_one_of( 'group_by', $arguments['group_by'], self::GROUP_BY, $arguments['group_by'], implode( ', ', self::GROUP_BY ) );
            $group_by = (string) $arguments['group_by'];
        }
        $orderby = isset( $arguments['orderby'] ) ? $arguments['orderby'] : '';
        if ( 'count' === $orderby && null === $group_by ) {
            throw new \InvalidArgumentException( 'orderby "count" needs group_by: only grouped rows have a count.' );
        }
        if ( null !== $group_by && '' !== $orderby && 'count' !== $orderby ) {
            
            
            throw new \InvalidArgumentException( sprintf( 'orderby "%s" cannot be used with group_by: grouped rows are ordered by their count. Leave orderby out, or send "count".', is_scalar( $orderby ) ? (string) $orderby : gettype( $orderby ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $group_by;
    }

    





    private static function filters( array $arguments ): array {
        $filters = array();
        if ( isset( $arguments['search'] ) && '' !== trim( (string) $arguments['search'] ) ) {
            $filters['url'] = (string) $arguments['search'];
        }
        if ( isset( $arguments['ip'] ) && '' !== trim( (string) $arguments['ip'] ) ) {
            $filters['ip'] = trim( (string) $arguments['ip'] );
        }
        return empty( $filters ) ? array() : array( 'filterBy' => $filters );
    }

    





    private static function entry( array $row ): array {
        return array(
            'id'             => self::int_of( $row, 'id' ),
            'url'            => self::str_of( $row, 'url' ),
            'created'        => self::str_of( $row, 'created' ),
            'created_time'   => self::str_of( $row, 'created_time' ),
            'ip'             => self::str_of( $row, 'ip' ),
            'agent'          => self::str_of( $row, 'agent' ),
            'referrer'       => self::str_of( $row, 'referrer' ),
            'http_code'      => self::int_of( $row, 'http_code' ),
            'request_method' => self::str_of( $row, 'request_method' ),
        );
    }
}
