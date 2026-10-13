<?php
namespace Easy_MCP_AI\Tools\Comments;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


































trait Comment_Moderation {

    








    private static $comment_status_actions = array(
        'approved' => 'approve',
        'approve'  => 'approve',
        '1'        => 'approve',
        'hold'     => 'unapprove',
        'spam'     => 'spam',
        'unspam'   => 'unspam',
        'trash'    => 'trash',
        'untrash'  => 'untrash',
    );

    







    protected static function status_sent( array $arguments ): bool {
        return isset( $arguments['status'] ) && '' !== $arguments['status'];
    }

    












    protected function comment_status_to_action( string $status, array $allowed = array() ): string {
        $key = sanitize_key( $status );
        if ( ! isset( self::$comment_status_actions[ $key ] ) || ( $allowed && ! in_array( self::$comment_status_actions[ $key ], $allowed, true ) ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid status: %s. Use approve, hold, spam or trash.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return self::$comment_status_actions[ $key ];
    }

    











    protected function empty_trash_days() {
        return defined( 'EMPTY_TRASH_DAYS' ) ? EMPTY_TRASH_DAYS : 30;
    }

    













    protected function refuse_trash_when_disabled() {
        if ( ! $this->empty_trash_days() ) {
            throw new \RuntimeException(
                'Trash is disabled on this site (EMPTY_TRASH_DAYS is 0), so trashing would permanently delete the comment. Nothing was changed. To delete it, call wp_delete_comment with force=true.'
            );
        }
    }

    















    protected function load_moderatable_comment( $comment_id ) {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            throw new \RuntimeException( sprintf( 'Comment %d not found.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( ! current_user_can( 'edit_comment', (int) $comment->comment_ID ) ) {
            throw new \RuntimeException( sprintf( 'Sorry, you are not allowed to moderate comment %d: moderating a comment requires permission to edit the post it belongs to.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        if ( isset( $comment->comment_type ) && 'note' === $comment->comment_type ) {
            throw new \RuntimeException( sprintf( 'Comment %d is a block editor note, not a comment. The comment moderation tools do not act on notes.', $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        $post = get_post( (int) $comment->comment_post_ID );
        if ( $post && 'trash' === $post->post_status ) {
            throw new \RuntimeException( 'You cannot moderate this comment because the associated post is in the Trash. Restore the post first, then try again.' );
        }

        return $comment;
    }

    









    protected function comment_rest_status( $comment ) {
        $approved = (string) $comment->comment_approved;
        if ( '0' === $approved ) {
            return 'hold';
        }
        if ( '1' === $approved ) {
            return 'approved';
        }
        return $approved;
    }

    















    protected function comment_action_is_noop( $comment, $action ) {
        $approved = (string) $comment->comment_approved;
        switch ( $action ) {
            case 'approve':
                return '1' === $approved;
            case 'unapprove':
                return '0' === $approved;
            case 'spam':
                return 'spam' === $approved;
            case 'unspam':
                return 'spam' !== $approved;
            case 'trash':
                return 'trash' === $approved;
            case 'untrash':
                return 'trash' !== $approved;
        }
        return false;
    }

    












    protected function run_core_status_change( $comment, $action ) {
        switch ( $action ) {
            case 'approve':
                $result = wp_set_comment_status( $comment, 'approve', true );
                break;
            case 'unapprove':
                $result = wp_set_comment_status( $comment, 'hold', true );
                break;
            case 'spam':
                $result = wp_spam_comment( $comment );
                break;
            case 'unspam':
                $result = wp_unspam_comment( $comment );
                break;
            case 'trash':
                $result = wp_trash_comment( $comment );
                break;
            case 'untrash':
                $result = wp_untrash_comment( $comment );
                break;
            default:
                throw new \InvalidArgumentException( sprintf( 'Unknown moderation action: %s', $action ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( is_wp_error( $result ) ) {
            throw new \RuntimeException( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! $result ) {
            throw new \RuntimeException( sprintf( 'WordPress could not change the status of comment %d.', (int) $comment->comment_ID ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    protected function apply_comment_action( $comment, $action ) {
        if ( 'trash' === $action ) {
            $this->refuse_trash_when_disabled();
        }

        if ( $this->comment_action_is_noop( $comment, $action ) ) {
            return false;
        }

        $this->run_core_status_change( $comment, $action );
        return true;
    }

    







    protected function moderate_single_comment( array $arguments, $action ) {
        $this->validate_required( $arguments, array( 'comment_id' ) );
        $comment_id = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );

        $comment = $this->load_moderatable_comment( $comment_id );
        $changed = $this->apply_comment_action( $comment, $action );

        
        
        $data = $this->rest_request( 'GET', '/wp/v2/comments/' . $comment_id, array( 'context' => 'edit' ) );

        $out            = Get_Comment::format( $data );
        $out['changed'] = $changed;
        return $out;
    }
}
