<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Group_Members extends Base_Tool {

    public function get_name() {
        return 'wp_bp_list_group_members';
    }

    public function get_description() {
        return 'Lists members of a BuddyPress group. Required: `group_id`. Optional: `per_page` (default 20), `page`. Returns { members: [...], group_id, page } where each member includes id, name, user_login, avatar_urls, profile_url, role (member/mod/admin), last_activity. Use `wp_bp_list_groups` to find the group_id. Requires BuddyPress Groups component enabled.';
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
                'group_id' => array(
                    'type'        => 'integer',
                    'description' => 'The BuddyPress group ID whose members to list.',
                ),
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
            ),
            'required'   => array( 'group_id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'groups' ) ) {
            throw new \RuntimeException( 'The BuddyPress Groups component is not enabled. Go to Settings → BuddyPress → Components and enable it.' );
        }

        $group_id = $this->parse_required_id( $arguments['group_id'] ?? null, 'group_id' );
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params   = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'     => $page,
        );

        $params['roles'] = 'member,admin,mod';

        $data = $this->rest_request( 'GET', '/buddypress/v1/groups/' . $group_id . '/members', $params );

        return array(
            'members'  => $data,
            'group_id' => $group_id,
            'page'     => $page,
        );
    }
}
