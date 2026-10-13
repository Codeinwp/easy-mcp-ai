<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































class Reply_To_Comment extends Base_Tool {

    use Comment_Moderation;

    public function get_name() {
        return 'wp_reply_to_comment';
    }

    public function get_description() {
        return 'Replies to a WordPress comment as the connected user, the same as Reply on the Comments screen: the new comment is posted on the same post as the parent, threaded under it, and authored by the user the API key or OAuth grant belongs to (there is no way to reply as someone else). Required: `comment_id` (the comment being replied to), `content` (the reply text; HTML is sanitized). Optional: `approve_parent` (default false) — when the parent comment is pending, approve it too ("Approve and Reply"); ignored when the parent is not pending. The caller must be able to edit the post (WordPress\'s edit_post check, as in wp-admin). Refused when the post is a draft, pending review or in the Trash, when its comments are closed, when the parent comment is spam or trashed, and when the content is empty. The reply is approved automatically, as WordPress does for moderators. Returns the new reply in the same shape as `wp_get_comment` — { id, post, author_name, author_email, content (raw), status, date, parent, link } — plus `parent_approved` (bool) when approve_parent was requested. Related: `wp_approve_comment`, `wp_create_comment` for a new top-level comment, `wp_update_comment` to edit a reply.';
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
                'comment_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the comment to reply to. The reply is posted on the same post.',
                ),
                'content'        => array(
                    'type'        => 'string',
                    'description' => 'The reply text. HTML is allowed and will be sanitized.',
                ),
                'approve_parent' => array(
                    'type'        => 'boolean',
                    'description' => 'When the parent comment is pending, approve it as well ("Approve and Reply"). Ignored when the parent is not pending.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'comment_id', 'content' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_id', 'content' ) );

        $parent_id      = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );
        $content        = $this->parse_reply_content( $arguments['content'] );
        $parent         = $this->load_reply_parent( $parent_id );
        $approve_parent = ! empty( $arguments['approve_parent'] ) && rest_sanitize_boolean( $arguments['approve_parent'] );
        $will_approve   = $approve_parent && '0' === (string) $parent->comment_approved;

        
        if ( $will_approve && ! current_user_can( 'edit_comment', (int) $parent->comment_ID ) ) {
            throw new \RuntimeException( sprintf( 'Sorry, you are not allowed to approve comment %d.', $parent_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        
        $params = array(
            'post'    => (int) $parent->comment_post_ID,
            'parent'  => $parent_id,
            'content' => wp_kses_post( $content ),
        );

        $created  = $this->rest_request( 'POST', '/wp/v2/comments', $params );
        $reply_id = isset( $created['id'] ) ? (int) $created['id'] : 0;
        if ( $reply_id < 1 ) {
            throw new \RuntimeException( 'WordPress did not return the ID of the new reply.' );
        }

        $approval = $will_approve ? $this->approve_parent_after_reply( $parent ) : array( 'parent_approved' => false );

        $data = $this->rest_request( 'GET', '/wp/v2/comments/' . $reply_id, array( 'context' => 'edit' ) );
        $out  = Get_Comment::format( $data );

        return $approve_parent ? array_merge( $out, $approval ) : $out;
    }

    








    private function parse_reply_content( $value ) {
        $content = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $content ) {
            throw new \InvalidArgumentException( 'Please type your comment text: `content` is empty.' );
        }
        return $content;
    }

    









    private function load_reply_parent( $parent_id ) {
        $parent = get_comment( $parent_id );
        if ( ! $parent ) {
            throw new \RuntimeException( sprintf( 'Comment %d not found.', $parent_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $post_id = (int) $parent->comment_post_ID;
        $post    = get_post( $post_id );
        if ( ! $post ) {
            throw new \RuntimeException( sprintf( 'The post of comment %d no longer exists.', $parent_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( 'Sorry, you are not allowed to reply to comments on this post: replying requires permission to edit the post.' );
        }

        if ( empty( $post->post_status ) || in_array( $post->post_status, array( 'draft', 'pending', 'trash' ), true ) ) {
            throw new \RuntimeException( 'You cannot reply to a comment on a draft post.' );
        }

        
        
        
        
        if ( isset( $parent->comment_type ) && 'note' === $parent->comment_type ) {
            throw new \RuntimeException( sprintf( 'Comment %d is a block editor note, not a comment, and cannot be replied to with this tool.', $parent_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        
        $parent_status = (string) $parent->comment_approved;
        if ( 'spam' === $parent_status || 'trash' === $parent_status ) {
            throw new \RuntimeException( sprintf( 'Comment %d is in %s. Restore it before replying.', $parent_id, $parent_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $parent;
    }

    












    private function approve_parent_after_reply( $parent ) {
        try {
            $this->run_core_status_change( $parent, 'approve' );
            return array( 'parent_approved' => true );
        } catch ( \RuntimeException $e ) {
            return array(
                'parent_approved'      => false,
                'parent_approve_error' => $e->getMessage(),
            );
        }
    }
}
