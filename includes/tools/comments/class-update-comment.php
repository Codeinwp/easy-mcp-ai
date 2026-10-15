<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Comment extends Base_Tool {

    
    
    
    
    use Comment_Moderation;

    





    const CLEARABLE_STRINGS = array( 'author_name', 'author_email', 'author_url' );

    






    const VALUE_STRINGS = array( 'author_ip', 'author_user_agent', 'date', 'date_gmt' );

    



    const SNAPSHOT_COLUMNS = array( 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id' );

    public function get_name() {
        return 'wp_update_comment';
    }

    public function get_description() {
        return 'Updates an existing WordPress comment (PATCH semantics). Required: `comment_id`, plus at least one field to change: `content`, `status` (approve/hold/spam/trash — use "approve" to approve a pending comment, "hold" to return it to moderation, "spam" to mark as spam, "trash" to soft-delete), `author_name`, `author_email`, `author_url` (an empty string clears these three), `author_ip`, `author_user_agent`, `date` (site time) or `date_gmt` (UTC), ISO 8601, not both, `parent` (comment ID to thread under, on the same post; 0 makes it top-level), `post` (move the comment to another post; replies to it stay where they are), `author` (user ID; WordPress also sets author_name, author_email and author_url from that user unless you pass them), `meta` (object of comment meta; only keys registered for the REST API persist, dropped keys are reported as meta_ignored). The commenter\'s IP address and user agent are kept unless you pass new ones. Returns { id, status, changed } — changed is false when the call left the comment exactly as it was (plus meta_ignored and notice when a meta key was not saved). Use `wp_get_comment` to retrieve the full updated comment. To permanently delete use `wp_delete_comment` with force=true. For moderation alone there are dedicated tools: `wp_approve_comment`, `wp_unapprove_comment`, `wp_spam_comment`, `wp_unspam_comment`, `wp_trash_comment`, `wp_untrash_comment`, `wp_bulk_moderate_comments`, and `wp_reply_to_comment` to reply.';
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
                'author_name' => array(
                    'type'        => 'string',
                    'description' => 'The new display name of the comment author. An empty string clears it.',
                ),
                'author_email' => array(
                    'type'        => 'string',
                    'description' => 'The new email of the comment author. An empty string clears it.',
                ),
                'author_url' => array(
                    'type'        => 'string',
                    'description' => 'The new URL of the comment author. An empty string clears it.',
                ),
                'author_ip'  => array(
                    'type'        => 'string',
                    'description' => 'A new IP address for the comment author. Omit it to keep the stored address.',
                ),
                'author_user_agent' => array(
                    'type'        => 'string',
                    'description' => 'A new user agent for the comment author. Omit it to keep the stored one; it cannot be cleared.',
                ),
                'date'       => array(
                    'type'        => 'string',
                    'description' => 'The new date of the comment in the site\'s timezone, ISO 8601 (e.g. "2026-01-01T10:00:00"). Do not combine with date_gmt.',
                ),
                'date_gmt'   => array(
                    'type'        => 'string',
                    'description' => 'The new date of the comment in UTC, ISO 8601. Do not combine with date.',
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'The comment ID to thread this one under, which must be on the same post (the new one when post is passed) and must not be this comment, one of its replies or a block editor note. 0 makes it a top-level comment.',
                    'minimum'     => 0,
                ),
                'post'       => array(
                    'type'        => 'integer',
                    'description' => 'Move the comment to this post ID. Replies to it stay on the old post; pass parent too if the comment itself is a reply.',
                    'minimum'     => 1,
                ),
                'author'     => array(
                    'type'        => 'integer',
                    'description' => 'The user ID to attribute the comment to. WordPress also sets author_name, author_email and author_url from that user unless they are passed too.',
                    'minimum'     => 1,
                ),
                'meta'       => array(
                    'type'        => 'object',
                    'description' => 'Comment meta key-value pairs to set. Only keys registered for the REST API persist; dropped keys are reported back as meta_ignored.',
                ),
            ),
            'required'   => array( 'comment_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'comment_id' ) );

        $comment_id = $this->parse_required_id( $arguments['comment_id'], 'comment_id' );

        
        $action = self::status_sent( $arguments ) ? $this->comment_status_to_action( (string) $arguments['status'] ) : null;

        
        
        $fields    = $this->field_params( $arguments, $comment_id );
        $meta_sent = $this->apply_meta_argument( $arguments, $fields );

        if ( ! isset( $arguments['content'] ) && ! $fields ) {
            if ( null === $action ) {
                
                throw new \InvalidArgumentException( 'Nothing to update: pass content or status. Any other field this tool takes (author_name, author_email, author_url, author_ip, author_user_agent, date, date_gmt, parent, post, author, meta) also counts.' );
            }
            return $this->update_status_only( $comment_id, $action );
        }

        $params   = $this->with_content_and_status( $arguments, $fields, $action );
        $existing = get_comment( $comment_id );
        $params   = self::with_stored_ip( $params, $existing );

        $meta_keys = $meta_sent ? array_keys( $params['meta'] ) : array();
        $before    = self::snapshot( $existing, $comment_id, $meta_keys );

        $data = $this->rest_request( 'POST', '/wp/v2/comments/' . $comment_id, $params );

        
        
        
        $after = self::snapshot( get_comment( $comment_id ), $comment_id, $meta_keys );

        $result = array(
            'id'      => $data['id'],
            'status'  => $data['status'],
            'changed' => $before !== $after,
        );

        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'comment' ) );
        }

        return $result;
    }

    








    private function with_content_and_status( array $arguments, array $fields, ?string $action ): array {
        $params = $fields;
        if ( isset( $arguments['content'] ) ) {
            $params['content'] = wp_kses_post( $arguments['content'] );
        }

        if ( null !== $action ) {
            $params['status'] = sanitize_key( $arguments['status'] );
        }
        return $params;
    }

    

















    private static function with_stored_ip( array $params, ?object $existing ): array {
        if ( ! isset( $params['author_ip'] ) && $existing && '' !== (string) $existing->comment_author_IP && rest_is_ip_address( (string) $existing->comment_author_IP ) ) {
            $params['author_ip'] = (string) $existing->comment_author_IP;
        }
        return $params;
    }

    






















    private function field_params( array $arguments, int $comment_id ): array {
        $out = array_merge(
            $this->clearable_strings( $arguments ),
            $this->value_strings( $arguments ),
            $this->post_and_parent( $arguments, $comment_id )
        );

        $author = $this->optional_int( $arguments, 'author', 1 );
        if ( null !== $author ) {
            $out['author'] = $author;
        }

        return $out;
    }

    






    private function clearable_strings( array $arguments ): array {
        $out = array();
        foreach ( self::CLEARABLE_STRINGS as $key ) {
            if ( ! isset( $arguments[ $key ] ) ) {
                continue;
            }
            if ( ! is_string( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[ $key ] = $arguments[ $key ];
        }
        return $out;
    }

    







    private function value_strings( array $arguments ): array {
        $out = array();
        foreach ( self::VALUE_STRINGS as $key ) {
            if ( ! isset( $arguments[ $key ] ) ) {
                continue;
            }
            if ( ! is_string( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( '' === trim( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( '%s cannot be emptied through WordPress\'s REST API, so an empty value would change nothing. Pass a value, or omit it to keep the stored one.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[ $key ] = $arguments[ $key ];
        }

        if ( isset( $out['date'], $out['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'Pass date or date_gmt, not both: WordPress derives one from the other and would ignore date_gmt.' );
        }
        return $out;
    }

    









    private function post_and_parent( array $arguments, int $comment_id ): array {
        $out    = array();
        $post   = $this->optional_int( $arguments, 'post', 1 );
        $parent = $this->optional_int( $arguments, 'parent', 0 );
        if ( null !== $post ) {
            $out['post'] = $post;
        }
        if ( null !== $parent ) {
            if ( $parent > 0 ) {
                $comment = get_comment( $comment_id );
                if ( ! $comment ) {
                    throw new \RuntimeException( 'Invalid comment ID.' );
                }
                self::refuse_bad_parent( $parent, $comment_id, null !== $post ? $post : (int) $comment->comment_post_ID );
            }
            $out['parent'] = $parent;
        }
        return $out;
    }

    








    private function optional_int( array $arguments, string $key, int $min ): ?int {
        if ( ! isset( $arguments[ $key ] ) || '' === $arguments[ $key ] ) {
            return null;
        }
        $value = $arguments[ $key ];
        if ( is_int( $value ) ) {
            $int = $value;
        } elseif ( is_string( $value ) && preg_match( '/^\d+\z/', $value ) ) {
            $int = (int) $value;
        } else {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be an integer.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $int < $min ) {
            $why = array(
                'post'   => ' A comment cannot be detached from its post through this tool.',
                'author' => ' WordPress\'s REST API cannot detach a comment from its user; change author_name and author_email instead.',
            );
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be %2$d or greater.%3$s', $key, $min, $why[ $key ] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $int;
    }

    








    private static function refuse_bad_parent( int $parent, int $comment_id, int $post_id ): void {
        if ( $parent === $comment_id ) {
            throw new \RuntimeException( 'A comment cannot be its own parent.' );
        }
        $candidate = get_comment( $parent );
        if ( ! $candidate ) {
            throw new \RuntimeException( sprintf( 'Parent comment %d not found.', $parent ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $candidate->comment_type ) && 'note' === $candidate->comment_type ) {
            throw new \RuntimeException( sprintf( 'Comment %d is a block editor note, not a comment, and cannot be a parent.', $parent ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( (int) $candidate->comment_post_ID !== $post_id ) {
            throw new \RuntimeException( sprintf( 'Parent comment %1$d is on post %2$d, not on post %3$d: a reply must be on the same post as its parent.', $parent, (int) $candidate->comment_post_ID, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        self::refuse_reply_cycle( $parent, $comment_id, (int) $candidate->comment_parent );
    }

    










    private static function refuse_reply_cycle( int $parent, int $comment_id, int $cursor ): void {
        $seen = array();
        while ( $cursor > 0 && ! isset( $seen[ $cursor ] ) && count( $seen ) < 1000 ) {
            if ( $cursor === $comment_id ) {
                throw new \RuntimeException( sprintf( 'Comment %1$d is a reply below comment %2$d, so it cannot become its parent: the thread would loop.', $parent, $comment_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $seen[ $cursor ] = true;
            $ancestor        = get_comment( $cursor );
            $cursor          = $ancestor ? (int) $ancestor->comment_parent : 0;
        }
    }

    








    private static function snapshot( ?object $comment, int $comment_id, array $meta_keys ): ?array {
        if ( ! $comment ) {
            return null;
        }
        $out = array();
        foreach ( self::SNAPSHOT_COLUMNS as $column ) {
            $out[ $column ] = isset( $comment->$column ) ? (string) $comment->$column : '';
        }
        foreach ( $meta_keys as $key ) {
            $out[ 'meta:' . $key ] = get_comment_meta( $comment_id, (string) $key, false );
        }
        return $out;
    }

    




















    private function update_status_only( int $comment_id, string $action ): array {
        $comment = get_comment( $comment_id );
        if ( ! $comment ) {
            throw new \RuntimeException( 'Invalid comment ID.' );
        }
        if ( ! empty( $comment->comment_post_ID ) && ! get_post( (int) $comment->comment_post_ID ) ) {
            throw new \RuntimeException( 'Invalid post ID.' );
        }

        $changed = false;
        if ( ! $this->comment_action_is_noop( $comment, $action ) ) {
            $this->run_core_status_change( $comment, $action );
            $changed = true;
        }

        
        
        
        if ( ! get_comment( $comment_id ) ) {
            return array(
                'id'      => $comment_id,
                'status'  => 'deleted',
                'changed' => true,
            );
        }

        $data = $this->rest_request( 'GET', '/wp/v2/comments/' . $comment_id, array( 'context' => 'edit' ) );

        return array(
            'id'      => $data['id'],
            'status'  => $data['status'],
            'changed' => $changed,
        );
    }
}
