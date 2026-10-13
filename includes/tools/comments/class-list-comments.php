<?php
namespace Easy_MCP_AI\Tools\Comments;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Comments extends Base_Tool {

    public function get_name() {
        return 'wp_list_comments';
    }

    public function get_description() {
        return 'Lists WordPress comments. Optional: `post_id` (filter by post — omit for all posts), `status` (approve/hold/spam/trash/all — default "approve" for published comments; use "hold" to list pending moderation queue), `search`, `author` (filter to a registered author user ID), `author_exclude` (array of author user IDs to exclude), `after`/`before` (ISO 8601 date-time bounds), `per_page` (default 10, max 100), `page`. Returns { comments: [{ id, post, author_name, author_email, content, status, date, parent }], total, total_pages, page, per_page }.';
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
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'  => array(
                    'type'        => 'integer',
                    'description' => 'Filter comments by post ID.',
                ),
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Filter comments by status.',
                    'enum'        => array( 'approve', 'hold', 'spam', 'trash', 'all' ),
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
                'after'    => array(
                    'type'        => 'string',
                    'description' => 'Only comments posted on or after this ISO 8601 date-time (e.g. "2026-01-01T00:00:00").',
                ),
                'before'   => array(
                    'type'        => 'string',
                    'description' => 'Only comments posted on or before this ISO 8601 date-time (e.g. "2026-12-31T23:59:59").',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params = array(
            'context' => 'edit',
        );

        if ( isset( $arguments['post_id'] ) ) {
            $params['post'] = absint( $arguments['post_id'] );
        }

        if ( ! empty( $arguments['status'] ) ) {
            $params['status'] = $this->list_status( $arguments['status'] );
        }

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

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

        $request = new \WP_REST_Request( 'GET', '/wp/v2/comments' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'comments' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $comments = $response->get_data();

        $result = array();
        foreach ( $comments as $comment ) {
            $result[] = array(
                'id'           => $comment['id'],
                'post'         => $comment['post'],
                'author_name'  => $comment['author_name'],
                'author_email' => $comment['author_email'],
                'content'      => wp_strip_all_tags( $comment['content']['rendered'] ),
                'status'       => $comment['status'],
                'date'         => $comment['date'],
                'parent'       => $comment['parent'],
            );
        }

        return array_merge(
            array( 'comments' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $comments ) )
        );
    }

    











    private function list_status( $status ): string {
        $key = is_scalar( $status ) ? sanitize_key( (string) $status ) : '';
        $this->refuse_unless_one_of( 'status', $key, array( 'approve', 'hold', 'spam', 'trash', 'all' ), $status, 'approve, hold, spam, trash or all' );
        return $key;
    }
}
