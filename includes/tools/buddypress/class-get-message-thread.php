<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Message_Thread extends Base_Tool {

    public function get_name() {
        return 'wp_bp_get_message_thread';
    }

    public function get_description() {
        return 'Gets a single BuddyPress private message thread by ID, including all messages in the thread. Required: `id` (thread ID — get it from `wp_bp_list_message_threads`). Returns the raw BuddyPress REST API thread object — fields: id, message_id (last message id), last_sender_id, subject ({raw, rendered}), excerpt ({raw, rendered}), message ({raw, rendered} — latest message body), date, date_gmt, unread_count, sender_ids (array), recipients (array of user objects), messages (array — each with id, thread_id, sender_id, subject ({raw,rendered}), message ({raw,rendered}), date_sent), starred_message_ids (when Messages-Star sub-component enabled). Only the authenticated user\'s own threads are accessible. Requires BuddyPress Messages component enabled.';
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
                    'description' => 'The message thread ID (from wp_bp_list_message_threads).', 'type' => 'integer',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'messages' ) ) {
            throw new \RuntimeException( 'The BuddyPress Messages component is not enabled. Go to Settings → BuddyPress → Components and enable it.' );
        }

        $id   = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
        $data = $this->rest_request( 'GET', '/buddypress/v1/messages/' . $id );

        return $data;
    }
}
