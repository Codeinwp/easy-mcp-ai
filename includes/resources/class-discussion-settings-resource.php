<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Discussion_Settings_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/discussion-settings';
    }

    public function get_name() {
        return 'Discussion Settings';
    }

    public function get_description() {
        return 'Default comment status, moderation rules, and comment registration requirements.';
    }

    public function read() {
        return array(
            'default_comment_status'       => get_option( 'default_comment_status', 'open' ),
            'require_name_email'           => (bool) get_option( 'require_name_email', true ),
            'comment_registration'         => (bool) get_option( 'comment_registration', false ),
            'close_comments_for_old_posts' => (bool) get_option( 'close_comments_for_old_posts', false ),
            'close_comments_days_old'      => (int) get_option( 'close_comments_days_old', 14 ),
            'comment_moderation'           => (bool) get_option( 'comment_moderation', false ),
            'comment_previously_approved'  => (bool) get_option( 'comment_previously_approved', true ),
        );
    }
}
