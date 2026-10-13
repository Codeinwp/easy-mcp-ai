<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Trash_Comment extends Base_Tool {

    use Comment_Moderation;

    public function get_name() {
        return 'wp_trash_comment';
    }

    public function get_description() {
        return 'Moves a WordPress comment to the Trash (status becomes "trash"; recoverable with `wp_untrash_comment` until WordPress empties the Trash). Required: `comment_id`. Never deletes permanently: when the site has trash disabled (EMPTY_TRASH_DAYS = 0, where WordPress would delete instead of trashing) the call is refused and nothing changes — use `wp_delete_comment` with force=true for permanent deletion. If the comment is already in the Trash nothing is written and `changed` is false. Returns the comment in the same shape as `wp_get_comment` — { id, post, author_name, author_email, content (raw), status, date, parent, link } — plus `changed` (bool). The caller must be able to edit the post the comment belongs to (WordPress\'s edit_comment check, as in wp-admin); otherwise the call is refused before anything is written. Related: `wp_untrash_comment`, `wp_bulk_moderate_comments`.';
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
                    'description' => 'The ID of the comment to move to the Trash.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        return $this->moderate_single_comment( $arguments, 'trash' );
    }
}
