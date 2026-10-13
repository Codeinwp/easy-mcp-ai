<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Posts extends Base_Tool {

    public function get_name() {
        return 'wp_list_posts';
    }

    public function get_description() {
        return 'Lists WordPress posts. Optional filters: `status` (publish/draft/pending/private/future/trash — default publish; use "any" to get all statuses), `search`, `categories` (array of category IDs), `tags` (array of tag IDs), `author` (user ID), `author_exclude` (array of user IDs to exclude), `after` / `before` (ISO 8601 date-time range on publish date), `per_page` (max 100, default 10), `page`, `orderby` (date/id/title/slug/modified/author — default date), `order` (asc/desc). Returns { posts: [{ id, title, status, date, modified, slug, excerpt, author, categories (array of IDs), tags (array of IDs), featured_media, link }], total, total_pages, page, per_page }. For CPT items use `wp_list_cpt_items`.';
    }

    public function get_category() {
        return 'posts';
    }

    public function get_required_capability() {
        return 'read';
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
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'Post status filter (e.g. publish, draft, pending, private, future).',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ),
                ),
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of posts per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'       => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'search'     => array(
                    'type'        => 'string',
                    'description' => 'Search query to filter posts.',
                ),
                'categories' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of category IDs to filter by.',
                ),
                'tags'       => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of tag IDs to filter by.',
                ),
                'author'     => array(
                    'type'        => 'integer',
                    'description' => 'Author user ID to filter by.',
                ),
                'author_exclude' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of author user IDs to EXCLUDE from results.',
                ),
                'after'      => array(
                    'type'        => 'string',
                    'description' => 'Only posts published on or after this ISO 8601 date-time (e.g. "2026-01-01T00:00:00").',
                ),
                'before'     => array(
                    'type'        => 'string',
                    'description' => 'Only posts published on or before this ISO 8601 date-time (e.g. "2026-12-31T23:59:59").',
                ),
                'orderby'    => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by.',
                    'enum'        => array( 'date', 'id', 'title', 'slug', 'modified', 'author' ),
                    'default'     => 'date',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Order direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params = array();

        if ( ! empty( $arguments['status'] ) ) {
            $params['status'] = $arguments['status'];
        }

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( ! empty( $arguments['categories'] ) ) {
            $params['categories'] = array_map( 'absint', $this->parse_json_param( $arguments['categories'], 'categories' ) );
        }

        if ( ! empty( $arguments['tags'] ) ) {
            $params['tags'] = array_map( 'absint', $this->parse_json_param( $arguments['tags'], 'tags' ) );
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

        if ( ! empty( $arguments['orderby'] ) ) {
            $params['orderby'] = $arguments['orderby'];
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'posts' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $posts = $response->get_data();

        $result = array();
        foreach ( $posts as $post ) {
            $result[] = array(
                'id'             => $post['id'],
                'title'          => wp_strip_all_tags( $post['title']['rendered'] ),
                'status'         => $post['status'],
                'date'           => $post['date'],
                'modified'       => $post['modified'],
                'slug'           => $post['slug'],
                'excerpt'        => wp_strip_all_tags( $post['excerpt']['rendered'] ),
                'author'         => $post['author'],
                'categories'     => $post['categories'],
                'tags'           => $post['tags'],
                'featured_media' => $post['featured_media'],
                'link'           => $post['link'],
            );
        }

        return array_merge(
            array( 'posts' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $posts ) )
        );
    }
}
