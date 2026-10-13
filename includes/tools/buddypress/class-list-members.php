<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Members extends Base_Tool {

    public function get_name() {
        return 'wp_bp_list_members';
    }

    public function get_description() {
        return 'Lists BuddyPress community members with optional filtering by type and search, paginated via `per_page` (default 20, max 100) and `page`. `include` limits results to the given user IDs while other filters (search, type, pagination) still apply normally. `user_ids`, when passed, overrides all other filters — the result is built from exactly those user IDs only, ignoring search/type/pagination. Returns id, name, profile URL, avatar, and last active date. Requires BuddyPress active.';
    }

    public function get_category() {
        return 'buddypress';
    }

    public function get_required_capability() {
        return 'read';
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
                'per_page' => array(
                    'type'    => 'integer',
                    'default' => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'    => 'integer',
                    'default' => 1,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search members by name or username.',
                ),
                'type'     => array(
                    'type'    => 'string',
                    'enum'    => array( 'newest', 'active', 'popular', 'online', 'alphabetical' ),
                    'default' => 'newest',
                ),
                'user_ids' => array(
                    'type'        => 'array',
                    'description' => 'Filter to a specific list of user IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'include'  => array(
                    'type'        => 'array',
                    'description' => 'Limit results to these user IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'exclude'  => array( // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
                    'type'        => 'array',
                    'description' => 'Exclude these user IDs from results.',
                    'items'       => array( 'type' => 'integer' ),
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        $page   = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'     => $page,
            'type'     => isset( $arguments['type'] ) ? sanitize_text_field( $arguments['type'] ) : 'newest',
        );

        if ( isset( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }
        if ( ! empty( $arguments['user_ids'] ) ) {
            $params['user_ids'] = array_map( 'absint', $this->parse_json_param( $arguments['user_ids'], 'user_ids' ) );
        }
        if ( ! empty( $arguments['include'] ) ) {
            $params['include'] = array_map( 'absint', $this->parse_json_param( $arguments['include'], 'include' ) );
        }
        if ( ! empty( $arguments['exclude'] ) ) {
            $params['exclude'] = array_map( 'absint', $this->parse_json_param( $arguments['exclude'], 'exclude' ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
        }

        $data = $this->rest_request( 'GET', '/buddypress/v1/members', $params );

        return array(
            'members' => $data,
            'page'    => $page,
        );
    }
}
