<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Comments extends Base_Tool {

    






    const STATUSES = array( 'approve', 'hold', 'spam', 'trash', 'all', 'any', 'post-trashed' );

    
    const ORDERBY = array( 'date', 'date_gmt', 'id', 'include', 'post', 'parent', 'type' );

    



    const META_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE' );

    public function get_name() {
        return 'wp_list_comments';
    }

    public function get_description() {
        return 'Lists WordPress comments. Optional: `post_id` (filter by post — omit for all posts) or `post_ids` (several posts), `post_exclude`, `status` (approve/hold/spam/trash/all/any/post-trashed — default "approve" for published comments; use "hold" to list the pending moderation queue; "all" is approved plus pending, "any" is every status) or `statuses` (several), `type` (default "comment"; "all" lists every type except block editor notes, as the Comments screen does; also pingback, trackback, pings, note or a custom type), `search`, `author` (filter to a registered author user ID), `author_exclude` (array of author user IDs to exclude), `author_email`, `author_url`, `include` / `exclude` (comment IDs), `parent` / `parent_exclude` (parent comment IDs; [0] = top-level only), `after`/`before` (ISO 8601 date-time bounds), `post_type`, `post_status`, `post_author`, `post_parent`, `post_name` (filters on the comment\'s post), `karma`, `meta_key` with optional `meta_value` and `meta_compare` (protected meta keys, those starting with an underscore, need manage_options), `orderby` (date/date_gmt/id/include/post/parent/type, default date_gmt), `order` (asc/desc, default desc), `per_page` (default 10, max 100), `page`, `offset` (skips that many comments; overrides page). Returns { comments: [{ id, post, author_name, author_email, content, status, date, parent }], total, total_pages, page, per_page } (plus `offset` when sent). With `include_details: true` each comment also carries author (user ID, 0 for a guest), author_url, author_ip, author_user_agent, date_gmt, type, karma, comment_approved (the raw stored value: "1", "0", "spam", "trash" or "post-trashed"), content_raw and link.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        $id_list = static function ( $description ) {
            return array(
                'type'        => 'array',
                'items'       => array( 'type' => 'integer' ),
                'description' => $description,
            );
        };

        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'  => array(
                    'type'        => 'integer',
                    'description' => 'Filter comments by post ID.',
                ),
                'post_ids' => $id_list( 'Filter to comments on any of these post IDs (0 = comments attached to no post). Use instead of post_id, not together with it.' ),
                'post_exclude' => $id_list( 'Exclude comments on these post IDs.' ),
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Filter comments by status. "all" is approved plus pending; "any" is every status, spam, trash and post-trashed included; "post-trashed" is a comment whose post is in the Trash.',
                    'enum'        => self::STATUSES,
                ),
                'statuses' => array(
                    'type'        => 'array',
                    'items'       => array(
                        'type' => 'string',
                        'enum' => self::STATUSES,
                    ),
                    'description' => 'Filter to comments in any of these statuses (same values as status). Use instead of status, not together with it.',
                ),
                'type'     => array(
                    'type'        => 'string',
                    'description' => 'Comment type. Default "comment" (WordPress\'s REST default). "all" lists every type except block editor notes, which is what the Comments screen shows; "pings" is pingbacks and trackbacks; "note" lists block editor notes; any other value is matched as a custom comment type.',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of comments per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'offset'   => array(
                    'type'        => 'integer',
                    'description' => 'Skip this many matching comments before the first one returned. When greater than 0 it overrides page.',
                    'minimum'     => 0,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search query to filter comments.',
                ),
                'author'   => array(
                    'type'        => 'integer',
                    'description' => 'Filter to comments by this registered (logged-in) author user ID.',
                ),
                'author_exclude' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of author user IDs to EXCLUDE from results.',
                ),
                'author_email' => array(
                    'type'        => 'string',
                    'description' => 'Only comments whose author email is exactly this address.',
                ),
                'author_url' => array(
                    'type'        => 'string',
                    'description' => 'Only comments whose author URL is exactly this value.',
                ),
                'include'  => $id_list( 'Only these comment IDs.' ),
                'exclude'  => $id_list( 'Exclude these comment IDs.' ),
                'parent'   => $id_list( 'Only replies to these parent comment IDs; [0] lists top-level comments only.' ),
                'parent_exclude' => $id_list( 'Exclude replies to these parent comment IDs; [0] excludes top-level comments.' ),
                'after'    => array(
                    'type'        => 'string',
                    'description' => 'Only comments posted on or after this ISO 8601 date-time (e.g. "2026-01-01T00:00:00").',
                ),
                'before'   => array(
                    'type'        => 'string',
                    'description' => 'Only comments posted on or before this ISO 8601 date-time (e.g. "2026-12-31T23:59:59").',
                ),
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'Only comments on posts of this post type, or a comma-separated list of post types.',
                ),
                'post_status' => array(
                    'type'        => 'string',
                    'description' => 'Only comments on posts with this post status, or a comma-separated list of statuses.',
                ),
                'post_author' => array(
                    'type'        => 'integer',
                    'description' => 'Only comments on posts written by this user ID.',
                    'minimum'     => 1,
                ),
                'post_parent' => array(
                    'type'        => 'integer',
                    'description' => 'Only comments on child posts of this post ID.',
                    'minimum'     => 1,
                ),
                'post_name' => array(
                    'type'        => 'string',
                    'description' => 'Only comments on the post with this slug.',
                ),
                'karma'    => array(
                    'type'        => 'integer',
                    'description' => 'Only comments with exactly this karma value.',
                ),
                'meta_key' => array(
                    'type'        => 'string',
                    'description' => 'Only comments that have this comment meta key. A protected key (starting with an underscore) needs the manage_options capability.',
                ),
                'meta_value' => array(
                    'type'        => 'string',
                    'description' => 'With meta_key: only comments whose value for that key matches (see meta_compare). Requires meta_key.',
                ),
                'meta_compare' => array(
                    'type'        => 'string',
                    'description' => 'With meta_key: how meta_value is compared. Default "=". IN, NOT IN, BETWEEN and NOT BETWEEN take a comma-separated meta_value.',
                    'enum'        => self::META_COMPARE,
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'description' => 'Sort by this attribute. Default "date_gmt". "include" keeps the order of the include list.',
                    'enum'        => self::ORDERBY,
                ),
                'order'    => array(
                    'type'        => 'string',
                    'description' => 'Sort direction. Default "desc".',
                    'enum'        => array( 'asc', 'desc' ),
                ),
                'include_details' => array(
                    'type'        => 'boolean',
                    'description' => 'Also return, for each comment: author (user ID, 0 for a guest), author_url, author_ip, author_user_agent, date_gmt, type, karma, comment_approved (raw stored value), content_raw (unrendered content) and link.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params = array_merge(
            array( 'context' => 'edit' ),
            $this->post_param( $arguments )
        );

        $statuses = $this->list_statuses( $arguments );
        $params   = array_merge( $params, $this->single_status_param( $arguments, $statuses ), $this->paging_params( $arguments ) );

        $offset = $this->offset_arg( $arguments );
        if ( null !== $offset ) {
            $params['offset'] = $offset;
        }

        $params = array_merge(
            $params,
            $this->basic_filter_params( $arguments ),
            $this->cli_filter_params( $arguments ),
            $this->sort_params( $arguments )
        );

        $query_vars = $this->query_vars( $arguments );
        if ( null !== $statuses ) {
            $this->apply_statuses( $statuses, $params, $query_vars );
        }

        $details = ! empty( $arguments['include_details'] ) && rest_sanitize_boolean( $arguments['include_details'] );

        $request = new \WP_REST_Request( 'GET', '/wp/v2/comments' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = $this->dispatch( $request, $query_vars );

        if ( $response->is_error() ) {
            return $this->empty_page_or_refusal( $response, $params, $offset );
        }

        $comments = $response->get_data();

        $result = array();
        foreach ( $comments as $comment ) {
            $result[] = $this->comment_row( $comment, $details );
        }

        return $this->with_offset(
            array_merge(
                array( 'comments' => $result ),
                $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $comments ) )
            ),
            $offset
        );
    }

    






    private function post_param( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['post_id'] ) ) {
            $params['post'] = absint( $arguments['post_id'] );
        }

        $post_ids = $this->id_list( $arguments, 'post_ids', 0 );
        if ( null !== $post_ids ) {
            if ( isset( $params['post'] ) ) {
                throw new \InvalidArgumentException( 'Pass post_id or post_ids, not both.' );
            }
            $params['post'] = $post_ids;
        }
        return $params;
    }

    







    private function single_status_param( array $arguments, ?array $statuses ): array {
        if ( empty( $arguments['status'] ) ) {
            return array();
        }
        if ( null !== $statuses ) {
            throw new \InvalidArgumentException( 'Pass status or statuses, not both.' );
        }
        return array( 'status' => $this->list_status( $arguments['status'] ) );
    }

    





    private function paging_params( array $arguments ): array {
        return array(
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10,
            'page'     => isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1,
        );
    }

    






    private function offset_arg( array $arguments ): ?int {
        if ( isset( $arguments['offset'] ) && '' !== $arguments['offset'] ) {
            return $this->int_arg( $arguments['offset'], 'offset', 0 );
        }
        return null;
    }

    







    private function basic_filter_params( array $arguments ): array {
        $params = array();
        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( ! empty( $arguments['author'] ) ) {
            $params['author'] = absint( $arguments['author'] );
        }

        if ( ! empty( $arguments['author_exclude'] ) ) {
            $params['author_exclude'] = array_map( 'absint', $this->parse_json_param( $arguments['author_exclude'], 'author_exclude' ) );
        }

        if ( ! empty( $arguments['after'] ) ) {
            $params['after'] = sanitize_text_field( $arguments['after'] );
        }

        if ( ! empty( $arguments['before'] ) ) {
            $params['before'] = sanitize_text_field( $arguments['before'] );
        }
        return $params;
    }

    










    private function cli_filter_params( array $arguments ): array {
        $params       = array();
        $author_email = $this->string_arg( $arguments, 'author_email' );
        if ( null !== $author_email ) {
            $params['author_email'] = $author_email;
        }
        foreach ( array( 'include' => 1, 'exclude' => 1, 'parent' => 0, 'parent_exclude' => 0 ) as $key => $min ) {
            $ids = $this->id_list( $arguments, $key, $min );
            if ( null !== $ids ) {
                $params[ $key ] = $ids;
            }
        }
        $type = $this->string_arg( $arguments, 'type' );
        if ( null !== $type ) {
            $params['type'] = $this->list_type( $type );
        }
        return $params;
    }

    






    private function sort_params( array $arguments ): array {
        $params = array();
        if ( isset( $arguments['orderby'] ) && '' !== $arguments['orderby'] ) {
            $this->refuse_unless_one_of( 'orderby', $arguments['orderby'], self::ORDERBY, $arguments['orderby'], implode( ', ', self::ORDERBY ) );
            $params['orderby'] = $arguments['orderby'];
        }
        if ( isset( $arguments['order'] ) && '' !== $arguments['order'] ) {
            $order = is_string( $arguments['order'] ) ? strtolower( $arguments['order'] ) : $arguments['order'];
            $this->refuse_unless_one_of( 'order', $order, array( 'asc', 'desc' ), $arguments['order'], 'asc or desc' );
            $params['order'] = $order;
        }
        return $params;
    }

    

















    private function apply_statuses( array $statuses, array &$params, array &$query_vars ): void {
        if ( 1 === count( $statuses ) ) {
            $params['status'] = $statuses[0];
            return;
        }
        $non_approve          = array_values( array_diff( $statuses, array( 'approve' ) ) );
        $params['status']     = $non_approve ? $non_approve[0] : 'approve';
        $query_vars['status'] = $statuses;
    }

    










    private function empty_page_or_refusal( \WP_REST_Response $response, array $params, ?int $offset ): array {
        $error = $response->as_error();
        if ( $this->is_invalid_page_error( $error ) ) {
            return $this->with_offset(
                array_merge(
                    array( 'comments' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                ),
                $offset
            );
        }
        throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private function comment_row( array $comment, bool $details ): array {
        $row = array(
            'id'           => $comment['id'],
            'post'         => $comment['post'],
            'author_name'  => $comment['author_name'],
            'author_email' => $comment['author_email'],
            'content'      => wp_strip_all_tags( $comment['content']['rendered'] ),
            'status'       => $comment['status'],
            'date'         => $comment['date'],
            'parent'       => $comment['parent'],
        );
        if ( $details ) {
            $row = array_merge( $row, self::details( $comment ) );
        }
        return $row;
    }

    











    private function dispatch( \WP_REST_Request $request, array $query_vars ): \WP_REST_Response {
        if ( empty( $query_vars ) ) {
            return rest_do_request( $request );
        }

        $filter = static function ( $prepared_args, $current = null ) use ( $request, $query_vars ) {
            if ( $current !== $request || ! is_array( $prepared_args ) ) {
                return $prepared_args;
            }
            return array_merge( $prepared_args, $query_vars );
        };

        add_filter( 'rest_comment_query', $filter, 10, 2 );
        try {
            return rest_do_request( $request );
        } finally {
            remove_filter( 'rest_comment_query', $filter, 10 );
        }
    }

    










    private function query_vars( array $arguments ): array {
        $vars = array();

        $post_exclude = $this->id_list( $arguments, 'post_exclude', 1 );
        if ( null !== $post_exclude ) {
            $vars['post__not_in'] = $post_exclude;
        }

        $vars = array_merge( $vars, $this->post_filter_vars( $arguments ) );

        if ( isset( $arguments['karma'] ) && '' !== $arguments['karma'] ) {
            $vars['karma'] = $this->int_arg( $arguments['karma'], 'karma', null );
        }

        $author_url = $this->string_arg( $arguments, 'author_url' );
        if ( null !== $author_url ) {
            $vars['author_url'] = $author_url;
        }

        return array_merge( $vars, $this->meta_vars( $arguments ) );
    }

    







    private function post_filter_vars( array $arguments ): array {
        $vars = array();
        foreach ( array( 'post_type', 'post_status' ) as $key ) {
            $value = $this->string_arg( $arguments, $key );
            if ( null !== $value ) {
                $vars[ $key ] = $this->key_list( $value, $key );
            }
        }

        
        
        
        foreach ( array( 'post_author', 'post_parent' ) as $key ) {
            if ( isset( $arguments[ $key ] ) && '' !== $arguments[ $key ] ) {
                $vars[ $key ] = $this->int_arg( $arguments[ $key ], $key, 1 );
            }
        }

        $post_name = $this->string_arg( $arguments, 'post_name' );
        if ( null !== $post_name ) {
            $vars['post_name'] = $post_name;
        }
        return $vars;
    }

    













    private function meta_vars( array $arguments ): array {
        $key     = $this->string_arg( $arguments, 'meta_key' );
        $value   = $this->string_arg( $arguments, 'meta_value' );
        $compare = $this->string_arg( $arguments, 'meta_compare' );

        
        
        if ( null !== $compare ) {
            $compare = strtoupper( $compare );
            $this->refuse_unless_one_of( 'meta_compare', $compare, self::META_COMPARE, $arguments['meta_compare'], implode( ', ', self::META_COMPARE ) );
        }

        if ( null === $key ) {
            if ( null !== $value || null !== $compare ) {
                throw new \InvalidArgumentException( 'meta_value and meta_compare need meta_key: without a key WordPress compares the value against every comment meta key.' );
            }
            return array();
        }

        $this->refuse_unreadable_meta_key( $key );

        $vars = array( 'meta_key' => $key ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
        if ( null !== $value ) {
            $vars['meta_value'] = $value; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        }
        if ( null !== $compare ) {
            $vars['meta_compare'] = $compare;
        }
        return $vars;
    }

    
















    private function refuse_unreadable_meta_key( string $key ): void {
        if ( '0' === $key || ! preg_match( '/^[\x21-\x7E]+\z/', $key ) ) {
            throw new \InvalidArgumentException( 'Invalid meta_key: use the key in plain printable ASCII with no spaces, as WordPress stores it.' );
        }
        if ( is_protected_meta( $key, 'comment' ) && ! current_user_can( 'manage_options' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Filtering by the protected meta key %s requires the manage_options capability.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private static function details( array $item ): array {
        $out = array();
        foreach ( array( 'author', 'author_url', 'author_ip', 'author_user_agent', 'date_gmt', 'type' ) as $key ) {
            if ( array_key_exists( $key, $item ) ) {
                $out[ $key ] = $item[ $key ];
            }
        }
        $comment = isset( $item['id'] ) ? get_comment( (int) $item['id'] ) : null;
        if ( $comment ) {
            $out['karma']            = isset( $comment->comment_karma ) ? (int) $comment->comment_karma : 0;
            $out['comment_approved'] = (string) $comment->comment_approved;
        }
        if ( isset( $item['content']['raw'] ) ) {
            $out['content_raw'] = $item['content']['raw'];
        }
        if ( array_key_exists( 'link', $item ) ) {
            $out['link'] = $item['link'];
        }
        return $out;
    }

    








    private function with_offset( array $result, ?int $offset ): array {
        if ( null !== $offset ) {
            $result['offset'] = $offset;
        }
        return $result;
    }

    











    private function list_status( $status ): string {
        $key = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
        
        
        $this->refuse_unless_one_of( 'status', $key, self::STATUSES, $status, 'approve, hold, spam, trash or all' );
        return $key;
    }

    






    private function list_statuses( array $arguments ): ?array {
        if ( ! isset( $arguments['statuses'] ) || '' === $arguments['statuses'] || array() === $arguments['statuses'] ) {
            return null;
        }
        $out = array();
        foreach ( $this->parse_json_param( $arguments['statuses'], 'statuses' ) as $status ) {
            $key = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
            $this->refuse_unless_one_of( 'statuses member', $key, self::STATUSES, $status, implode( ', ', self::STATUSES ) );
            $out[] = $key;
        }
        $out = array_values( array_unique( $out ) );
        return $out ? $out : null;
    }

    









    private function list_type( string $type ): string {
        $lower = strtolower( $type );
        if ( 'all' === $lower ) {
            return '';
        }
        $key = sanitize_key( $lower );
        if ( '' === $key || $key !== $lower ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid type: %s. Use comment, pingback, trackback, pings, note, all or a custom comment type key (lowercase letters, digits, dashes and underscores).', $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $key;
    }

    









    private function key_list( string $value, string $label ): array {
        $out = array();
        foreach ( explode( ',', $value ) as $part ) {
            $part = strtolower( trim( $part ) );
            if ( '' === $part ) {
                continue;
            }
            if ( sanitize_key( $part ) !== $part ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s. Use lowercase letters, digits, dashes and underscores, comma-separated for several.', $label, $part ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = $part;
        }
        if ( ! $out ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: no value given.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_values( array_unique( $out ) );
    }

    







    private function string_arg( array $arguments, string $key ): ?string {
        if ( ! isset( $arguments[ $key ] ) || '' === $arguments[ $key ] ) {
            return null;
        }
        if ( ! is_string( $arguments[ $key ] ) && ! is_int( $arguments[ $key ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return (string) $arguments[ $key ];
    }

    









    private function int_arg( $value, string $label, ?int $min ): int {
        if ( is_int( $value ) ) {
            $int = $value;
        } elseif ( is_string( $value ) && preg_match( '/^-?\d+\z/', $value ) ) {
            $int = (int) $value;
        } else {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be an integer.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( null !== $min && $int < $min ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be %2$d or greater.', $label, $min ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $int;
    }

    








    private function id_list( array $arguments, string $key, int $min ): ?array {
        if ( ! isset( $arguments[ $key ] ) || '' === $arguments[ $key ] || array() === $arguments[ $key ] ) {
            return null;
        }
        $raw = is_int( $arguments[ $key ] ) ? array( $arguments[ $key ] ) : $this->parse_json_param( $arguments[ $key ], $key );
        $out = array();
        foreach ( $raw as $id ) {
            $out[] = $this->int_arg( $id, $key . ' member', $min );
        }
        return $out ? array_values( array_unique( $out ) ) : null;
    }
}
