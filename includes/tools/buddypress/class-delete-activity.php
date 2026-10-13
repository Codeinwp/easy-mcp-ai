<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Activity extends Base_Tool {

    public function get_name() {
        return 'wp_bp_delete_activity';
    }

    public function get_description() {
        return 'Permanently deletes a BuddyPress activity item from the stream. Required: `id` (the activity item ID — get it from `wp_bp_list_activity`). Deletion is immediate and irreversible. Returns { deleted: true, id }. Requires BuddyPress Activity component enabled.';
    }

    public function get_category() {
        return 'buddypress';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'The BuddyPress activity item ID to delete.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'activity' ) ) {
            throw new \RuntimeException( 'The BuddyPress Activity component is not enabled. Go to Settings → BuddyPress → Components and enable it.' );
        }

        $id = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
        $this->rest_request( 'DELETE', '/buddypress/v1/activity/' . $id );

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }
}
