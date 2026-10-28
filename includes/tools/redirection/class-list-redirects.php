<?php
namespace Easy_MCP_AI\Tools\Redirection;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Redirects extends Base_Tool {

    use Redirection_Rest;

    const ORDERS = array( 'id', 'source', 'last_count', 'last_access', 'position' );

    public function get_name() {
        return 'wp_redirection_list_redirects';
    }

    public function get_description() {
        return 'Lists redirects managed by the Redirection plugin. Optional: `search` (part of the source URL), `target` (part of the destination), `group_id`, `status` ("enabled" or "disabled"), `action_type`, `orderby`, `direction`, `per_page` (5-200, default 25), `page` (1-based). Returns { redirects: [{ id, source, match_type, action_type, code, target, title, group_id, position, enabled, regex, ignore_case, ignore_trailing_slash, query_handling, hits, last_access }], total, page, per_page, total_pages }. `target` is null for match types other than "url"; those carry `action_data` instead. Requires the Redirection plugin active.';
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
                'search'      => array(
                    'type'        => 'string',
                    'description' => 'Part of the source URL to match (substring, case-insensitive).',
                ),
                'target'      => array(
                    'type'        => 'string',
                    'description' => 'Part of the destination to match (substring).',
                ),
                'group_id'    => array(
                    'type'        => 'integer',
                    'description' => 'Only redirects in this group (ids from wp_redirection_list_groups).',
                ),
                'status'      => array(
                    'type'        => 'string',
                    'enum'        => array( 'enabled', 'disabled' ),
                    'description' => 'Only enabled or only disabled redirects.',
                ),
                'action_type' => array(
                    'type'        => 'string',
                    'enum'        => self::action_types(),
                    'description' => 'Only redirects with this action: url (redirect to a URL), error (answer an HTTP error), nothing, random (a random post), pass (serve the target without redirecting).',
                ),
                'orderby'     => array(
                    'type'        => 'string',
                    'enum'        => self::ORDERS,
                    'description' => 'Sort field. Default: id.',
                ),
                'direction'   => array(
                    'type'        => 'string',
                    'enum'        => array( 'asc', 'desc' ),
                    'description' => 'Sort direction. Default: desc.',
                ),
                'per_page'    => array(
                    'type'        => 'integer',
                    'description' => 'Redirects per page, 5-200 (Redirection\'s own limits).',
                    'default'     => 25,
                    'minimum'     => 5,
                    'maximum'     => 200,
                ),
                'page'        => array(
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
        $params  = array(
            'per_page' => $per_page,
            'page'     => $vendor_page,
        );
        $filters = $this->filters( $arguments );
        if ( ! empty( $filters ) ) {
            $params['filterBy'] = $filters;
        }
        $this->ordering( $arguments, self::ORDERS, $params );

        $data = $this->rest_request( 'GET', self::$redirection_ns . '/redirect', $params );
        return array_merge(
            array( 'redirects' => array_map( array( __CLASS__, 'normalize_redirect' ), self::rows( $data ) ) ),
            self::paging_result( self::int_of( is_array( $data ) ? $data : array(), 'total' ), $page, $per_page )
        );
    }

    private function filters( array $arguments ): array {
        $filters = array();
        foreach ( array( 'search' => 'url', 'target' => 'target' ) as $arg => $filter ) {
            if ( isset( $arguments[ $arg ] ) && '' !== trim( (string) $arguments[ $arg ] ) ) {
                $filters[ $filter ] = (string) $arguments[ $arg ];
            }
        }
        if ( isset( $arguments['group_id'] ) && '' !== $arguments['group_id'] ) {
            $filters['group'] = (string) $this->parse_required_id( $arguments['group_id'], 'group_id' );
        }
        if ( isset( $arguments['status'] ) && '' !== $arguments['status'] ) {
            $this->refuse_unless_one_of( 'status', $arguments['status'], array( 'enabled', 'disabled' ), $arguments['status'], 'enabled or disabled' );
            $filters['status'] = $arguments['status'];
        }
        if ( isset( $arguments['action_type'] ) && '' !== $arguments['action_type'] ) {
            $this->refuse_unless_one_of( 'action_type', $arguments['action_type'], self::action_types(), $arguments['action_type'], implode( ', ', self::action_types() ) );
            $filters['action'] = $arguments['action_type'];
        }
        return $filters;
    }
}
