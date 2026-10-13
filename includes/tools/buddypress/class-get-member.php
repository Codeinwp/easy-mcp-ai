<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Member extends Base_Tool {

    public function get_name() {
        return 'wp_bp_get_member';
    }

    public function get_description() {
        return "Gets a BuddyPress member's profile by ID. Required: `id` (the WordPress user ID). Returns the raw BuddyPress REST API member object — key fields: id, name, mention_name, link (profile URL), user_login, registered_date, roles, avatar_urls, last_activity (object with date/timediff), total_friend_count, xprofile (extended profile fields array, if XProfile component enabled), friendship_status (boolean), friendship_status_slug. Use `wp_bp_list_members` to discover member IDs.";
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The BuddyPress member ID (WordPress user ID).',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        $id   = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
        $data = $this->rest_request( 'GET', '/buddypress/v1/members/' . $id );

        return $data;
    }
}
