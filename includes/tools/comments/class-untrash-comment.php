<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Untrash_Comment extends Base_Tool {

    use Comment_Moderation;

    public function get_name() {
        return 'wp_untrash_comment';
    }

    public function get_description() {
        return 'Restores a WordPress comment from the Trash (Restore on the Comments screen), returning it to the status it had before it was trashed — approved, pending or spam (pending when no previous status was recorded). Required: `comment_id`. Only acts on a comment currently in the Trash: for any other comment nothing is written and `changed` is false. Returns the comment in the same shape as `wp_get_comment` — { id, post, author_name, author_email, content (raw), status, date, parent, link } — plus `changed` (bool). The caller must be able to edit the post the comment belongs to (WordPress\'s edit_comment check, as in wp-admin); otherwise the call is refused before anything is written. Related: `wp_trash_comment`, `wp_list_comments` with status="trash".';
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
                    'description' => 'The ID of the trashed comment to restore.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        return $this->moderate_single_comment( $arguments, 'untrash' );
    }
}
