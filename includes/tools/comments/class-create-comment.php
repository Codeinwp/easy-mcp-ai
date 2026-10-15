<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Comment extends Base_Tool {

    
    
    use Comment_Moderation;

    







    const PASSTHROUGH_STRINGS = array( 'author_url', 'author_ip', 'author_user_agent', 'date', 'date_gmt' );

    public function get_name() {
        return 'wp_create_comment';
    }

    public function get_description() {
        return 'Creates a new WordPress comment. Required: `post` (the post/page ID to comment on), `content`. Optional: `author_name`, `author_email`, `status` (approve/hold/spam/trash — default "hold" pending moderation; authenticated users may be auto-approved per site settings), `author_url`, `author_ip` (default: the address of the MCP request), `author_user_agent`, `date` (site time) or `date_gmt` (UTC), ISO 8601, not both — default now, `parent` (the comment ID this replies to; it must be on the same post and not a block editor note), `author` (user ID to attribute the comment to; WordPress then fills author_name, author_email and author_url from that user unless you pass them; with no author fields at all the comment is attributed to the connected user), `meta` (object of comment meta; only keys registered for the REST API persist, dropped keys are reported as meta_ignored). Returns { id, post_id, status, date } (plus meta_ignored and notice when a meta key was not saved). Use `wp_get_comment` to fetch the full comment record after creation, and `wp_reply_to_comment` to reply as the connected user the way the Comments screen does.';
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
                'post'         => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post to comment on.',
                ),
                'content'      => array(
                    'type'        => 'string',
                    'description' => 'The content for the comment. HTML is allowed and will be sanitized.',
                ),
                'author_name'  => array(
                    'type'        => 'string',
                    'description' => 'The name of the comment author.',
                ),
                'author_email' => array(
                    'type'        => 'string',
                    'description' => 'The email of the comment author.',
                ),
                'status'       => array(
                    'type'        => 'string',
                    'description' => 'The status for the comment.',
                    'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
                ),
                'author_url'   => array(
                    'type'        => 'string',
                    'description' => 'The URL of the comment author.',
                ),
                'author_ip'    => array(
                    'type'        => 'string',
                    'description' => 'The IP address to record for the comment author. Default: the address of the MCP request.',
                ),
                'author_user_agent' => array(
                    'type'        => 'string',
                    'description' => 'The user agent to record for the comment author.',
                ),
                'date'         => array(
                    'type'        => 'string',
                    'description' => 'When the comment was posted, in the site\'s timezone, ISO 8601 (e.g. "2026-01-01T10:00:00"). Default now. Do not combine with date_gmt.',
                ),
                'date_gmt'     => array(
                    'type'        => 'string',
                    'description' => 'When the comment was posted, in UTC, ISO 8601. Default now. Do not combine with date.',
                ),
                'parent'       => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the comment this one replies to (0 or omitted for a top-level comment). It must exist, be on the same post and not be a block editor note.',
                    'minimum'     => 0,
                ),
                'author'       => array(
                    'type'        => 'integer',
                    'description' => 'The user ID to attribute the comment to. WordPress fills author_name, author_email and author_url from that user unless they are passed too.',
                    'minimum'     => 1,
                ),
                'meta'         => array(
                    'type'        => 'object',
                    'description' => 'Comment meta key-value pairs. Only keys registered for the REST API persist; dropped keys are reported back as meta_ignored.',
                ),
            ),
            'required'   => array( 'post', 'content' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post', 'content' ) );

        
        
        $status = self::status_sent( $arguments ) ? (string) $arguments['status'] : null;
        if ( null !== $status ) {
            $this->comment_status_to_action( $status, array( 'approve', 'unapprove', 'spam', 'trash' ) );
        }

        $params = array(
            'post'    => absint( $arguments['post'] ),
            'content' => wp_kses_post( $arguments['content'] ),
        );

        if ( ! empty( $arguments['author_name'] ) ) {
            $params['author_name'] = sanitize_text_field( $arguments['author_name'] );
        }

        if ( ! empty( $arguments['author_email'] ) ) {
            $params['author_email'] = sanitize_email( $arguments['author_email'] );
        }

        if ( null !== $status ) {
            $params['status'] = sanitize_key( $status );
        }

        
        
        $params    = array_merge( $params, $this->extra_fields( $arguments, $params['post'] ) );
        $meta_sent = $this->apply_meta_argument( $arguments, $params );

        $data = $this->rest_request( 'POST', '/wp/v2/comments', $params );

        $result = array(
            'id'      => $data['id'],
            'post_id' => $data['post'],
            'status'  => $data['status'],
            'date'    => $data['date'],
        );

        if ( $meta_sent ) {
            $result = array_merge( $result, $this->ignored_meta_report( $params['meta'], $data['meta'] ?? null, 'comment' ) );
        }

        return $result;
    }

    
















    private function extra_fields( array $arguments, int $post_id ): array {
        $out = $this->passthrough_strings( $arguments );

        if ( isset( $arguments['parent'] ) && '' !== $arguments['parent'] ) {
            $parent = $this->comment_id_arg( $arguments['parent'], 'parent', 0 );
            if ( $parent > 0 ) {
                self::refuse_bad_parent( $parent, $post_id );
                $out['parent'] = $parent;
            }
        }

        if ( isset( $arguments['author'] ) && '' !== $arguments['author'] ) {
            $out['author'] = $this->comment_id_arg( $arguments['author'], 'author', 1 );
        }

        return $out;
    }

    






    private function passthrough_strings( array $arguments ): array {
        $out = array();
        foreach ( self::PASSTHROUGH_STRINGS as $key ) {
            if ( ! isset( $arguments[ $key ] ) || '' === $arguments[ $key ] ) {
                continue;
            }
            if ( ! is_string( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[ $key ] = $arguments[ $key ];
        }
        if ( isset( $out['date'], $out['date_gmt'] ) ) {
            throw new \InvalidArgumentException( 'Pass date or date_gmt, not both: WordPress derives one from the other and would ignore date_gmt.' );
        }
        return $out;
    }

    








    private function comment_id_arg( $value, string $label, int $min ): int {
        if ( is_int( $value ) ) {
            $id = $value;
        } elseif ( is_string( $value ) && preg_match( '/^\d+\z/', $value ) ) {
            $id = (int) $value;
        } else {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be an integer.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $id < $min ) {
            $why = 'author' === $label ? ' WordPress cannot attribute a comment to user 0; to leave it unattributed, pass author_name and author_email instead.' : '';
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be %2$d or greater.%3$s', $label, $min, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $id;
    }

    










    private static function refuse_bad_parent( int $parent, int $post_id ): void {
        $comment = get_comment( $parent );
        if ( ! $comment ) {
            throw new \RuntimeException( sprintf( 'Parent comment %d not found.', $parent ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $comment->comment_type ) && 'note' === $comment->comment_type ) {
            throw new \RuntimeException( sprintf( 'Comment %d is a block editor note, not a comment, and cannot be a parent.', $parent ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( (int) $comment->comment_post_ID !== $post_id ) {
            throw new \RuntimeException( sprintf( 'Parent comment %1$d is on post %2$d, not on post %3$d: a reply must be on the same post as its parent.', $parent, (int) $comment->comment_post_ID, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
