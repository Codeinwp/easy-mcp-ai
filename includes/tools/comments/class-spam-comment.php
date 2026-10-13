<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Spam_Comment extends Base_Tool {

    use Comment_Moderation;

    public function get_name() {
        return 'wp_spam_comment';
    }

    public function get_description() {
        return 'Marks a WordPress comment as spam (status becomes "spam"), the same as Spam on the Comments screen; WordPress remembers the previous status so `wp_unspam_comment` can restore it, and spam plugins such as Akismet are notified through core\'s hooks. Required: `comment_id`. If the comment is already spam nothing is written and `changed` is false. Returns the comment in the same shape as `wp_get_comment` — { id, post, author_name, author_email, content (raw), status, date, parent, link } — plus `changed` (bool). The caller must be able to edit the post the comment belongs to (WordPress\'s edit_comment check, as in wp-admin); otherwise the call is refused before anything is written. Related: `wp_unspam_comment`, `wp_bulk_moderate_comments`, `wp_delete_comment` for permanent deletion.';
    }

    public function get_category() {
        return 'comments';
    }

    public function get_required_capability() {
        
        
        return 'moderate_comments';
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
                'comment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the comment to mark as spam.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        return $this->moderate_single_comment( $arguments, 'spam' );
    }
}
