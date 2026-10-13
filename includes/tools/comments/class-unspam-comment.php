<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Unspam_Comment extends Base_Tool {

    use Comment_Moderation;

    public function get_name() {
        return 'wp_unspam_comment';
    }

    public function get_description() {
        return 'Removes a WordPress comment from spam (Not Spam on the Comments screen), restoring the status it had before it was marked as spam — usually approved or pending (pending when no previous status was recorded). Required: `comment_id`. Only acts on a comment currently in spam: for any other comment nothing is written and `changed` is false. Returns the comment in the same shape as `wp_get_comment` — { id, post, author_name, author_email, content (raw), status, date, parent, link } — plus `changed` (bool). The caller must be able to edit the post the comment belongs to (WordPress\'s edit_comment check, as in wp-admin); otherwise the call is refused before anything is written. Related: `wp_spam_comment`, `wp_approve_comment`, `wp_list_comments` with status="spam".';
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
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'comment_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the spam comment to restore.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        return $this->moderate_single_comment( $arguments, 'unspam' );
    }
}
