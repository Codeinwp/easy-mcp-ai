<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Group extends Base_Tool {

    public function get_name() {
        return 'wp_bp_get_group';
    }

    public function get_description() {
        return 'Gets a single BuddyPress group by ID. Required: `id`. Returns the raw BuddyPress REST API group object — key fields: id, name, slug, link, description (object with raw/rendered), status (public/private/hidden), total_member_count, date_created, creator_id, admins (array of user objects), mods (array of user objects), types, last_activity, avatar_urls. Use `wp_bp_list_groups` to discover group IDs. Requires BuddyPress Groups component enabled.';
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
                    'description' => 'The BuddyPress group ID.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'groups' ) ) {
            throw new \RuntimeException( 'The BuddyPress Groups component is not enabled. Go to Settings → BuddyPress → Components and enable it.' );
        }

        $id   = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
        $data = $this->rest_request( 'GET', '/buddypress/v1/groups/' . $id );

        return $data;
    }
}
