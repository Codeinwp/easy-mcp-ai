<?php
namespace Easy_MCP_AI\Tools\BuddyPress;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Message_Threads extends Base_Tool {

    public function get_name() {
        return 'wp_bp_list_message_threads';
    }

    public function get_description() {
        return 'Lists the authenticated user\'s private message threads. Optional: `box` (inbox/sentbox/starred — default "inbox"), `per_page` (default 20), `page`. Returns { threads: [...], page } where each thread includes id, subject, sender info, last message date, message_count, unread_count. Use `wp_bp_get_message_thread` to fetch all messages in a thread. Requires authentication and BuddyPress Messages component enabled.';
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
                'box'      => array(
                    'type'    => 'string',
                    'enum'    => array( 'inbox', 'sentbox', 'starred' ),
                    'default' => 'inbox',
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'BuddyPress' ) ) {
            throw new \RuntimeException( 'BuddyPress is not active on this site. Please install and activate BuddyPress to use this tool.' );
        }

        if ( function_exists( 'bp_is_active' ) && ! bp_is_active( 'messages' ) ) {
            throw new \RuntimeException( 'The BuddyPress Messages component is not enabled. Go to Settings → BuddyPress → Components and enable Private Messaging.' );
        }

        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            throw new \RuntimeException( 'Authentication required to list message threads.' );
        }

        $page   = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params = array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20,
            'page'     => $page,
            'box'      => isset( $arguments['box'] ) ? sanitize_text_field( $arguments['box'] ) : 'inbox',
            'user_id'  => $current_user_id,
        );

        $data = $this->rest_request( 'GET', '/buddypress/v1/messages', $params );

        return array(
            'threads' => $data,
            'page'    => $page,
        );
    }
}
