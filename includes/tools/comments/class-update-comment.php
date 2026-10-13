<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Comment extends Base_Tool {

    
    
    
    
    use Comment_Moderation;

    public function get_name() {
        return 'wp_update_comment';
    }

    public function get_description() {
        return 'Updates an existing WordPress comment (PATCH semantics). Required: `comment_id`, plus at least one of `content`, `status` (approve/hold/spam/trash — use "approve" to approve a pending comment, "hold" to return it to moderation, "spam" to mark as spam, "trash" to soft-delete). Returns { id, status }. Use `wp_get_comment` to retrieve the full updated comment. To permanently delete use `wp_delete_comment` with force=true. For moderation alone there are dedicated tools: `wp_approve_comment`, `wp_unapprove_comment`, `wp_spam_comment`, `wp_unspam_comment`, `wp_trash_comment`, `wp_untrash_comment`, `wp_bulk_moderate_comments`, and `wp_reply_to_comment` to reply.';
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
                    'description' => 'The ID of the comment to update.',
                ),
                'content'    => array(
                    'type'        => 'string',
                    'description' => 'The new content for the comment. HTML is allowed and will be sanitized.',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'The new status for the comment.',
                    'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_id' ) );

        $comment_id = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );

        
        $action = self::status_sent( $arguments ) ? $this->comment_status_to_action( (string) $arguments['status'] ) : null;

        if ( ! isset( $arguments['content'] ) ) {
            if ( null === $action ) {
                
                throw new \InvalidArgumentException( 'Nothing to update: pass content or status.' );
            }
            return $this->update_status_only( $comment_id, $action );
        }

        $params = array( 'content' => wp_kses_post( $arguments['content'] ) );

        if ( null !== $action ) {
            $params['status'] = sanitize_key( $arguments['status'] );
        }

        
        
        
        
        
        
        
        
        $existing = get_comment( $comment_id );
        if ( $existing && '' !== (string) $existing->comment_author_IP && rest_is_ip_address( (string) $existing->comment_author_IP ) ) {
            $params['author_ip'] = (string) $existing->comment_author_IP;
        }

        $data = $this->rest_request( 'POST', '/wp/v2/comments/' . $comment_id, $params );

        return array(
            'id'     => $data['id'],
            'status' => $data['status'],
        );
    }

    
















    private function update_status_only( int $comment_id, string $action ): array {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            throw new \RuntimeException( 'Invalid comment ID.' );
        }
        if ( ! empty( $comment->comment_post_ID ) && ! get_post( (int) $comment->comment_post_ID ) ) {
            throw new \RuntimeException( 'Invalid post ID.' );
        }

        if ( ! $this->comment_action_is_noop( $comment, $action ) ) {
            $this->run_core_status_change( $comment, $action );
        }

        
        
        
        if ( ! get_comment( $comment_id ) ) {
            return array(
                'id'     => $comment_id,
                'status' => 'deleted',
            );
        }

        $data = $this->rest_request( 'GET', '/wp/v2/comments/' . $comment_id, array( 'context' => 'edit' ) );

        return array(
            'id'     => $data['id'],
            'status' => $data['status'],
        );
    }
}
