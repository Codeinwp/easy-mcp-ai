<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Pages extends Base_Tool {

    public function get_name() {
        return 'wp_list_pages';
    }

    public function get_description() {
        return 'Lists WordPress pages. Optional: `status` (publish/draft/pending/private/future/trash/any — default publish), `search`, `parent` (filter by parent page ID; use 0 for top-level only), `author` (user ID), `author_exclude` (array of user IDs to exclude), `after` / `before` (ISO 8601 date-time range on publish date), `per_page` (default 10, max 100), `page`, `orderby` (date/id/title/slug/modified/menu_order/parent — default date), `order` (asc/desc). Returns { pages: [{ id, title, status, date, modified, slug, parent, menu_order, link }], total, total_pages, page, per_page }. Pages are hierarchical — use `parent` to browse the tree.';
    }

    public function get_category() {
        return 'pages';
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
                'status'   => array(
                    'type'        => 'string',
                    'description' => 'Page status filter (e.g. publish, draft, pending, private, future).',
                    'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash', 'any' ),
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of pages per page (1-100).',
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
                    'description' => 'Search query to filter pages.',
                ),
                'parent'   => array(
                    'type'        => 'integer',
                    'description' => 'Parent page ID to filter by.',
                ),
                'author'   => array(
                    'type'        => 'integer',
                    'description' => 'Author user ID to filter by.',
                ),
                'author_exclude' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of author user IDs to EXCLUDE from results.',
                ),
                'after'    => array(
                    'type'        => 'string',
                    'description' => 'Only pages published on or after this ISO 8601 date-time (e.g. "2026-01-01T00:00:00").',
                ),
                'before'   => array(
                    'type'        => 'string',
                    'description' => 'Only pages published on or before this ISO 8601 date-time (e.g. "2026-12-31T23:59:59").',
                ),
                'orderby'  => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by.',
                    'enum'        => array( 'date', 'id', 'title', 'slug', 'modified', 'menu_order', 'parent' ),
                    'default'     => 'date',
                ),
                'order'    => array(
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

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
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

        $request = new \WP_REST_Request( 'GET', '/wp/v2/pages' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'pages' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $pages = $response->get_data();

        $result = array();
        foreach ( $pages as $page ) {
            $result[] = array(
                'id'         => $page['id'],
                'title'      => wp_strip_all_tags( $page['title']['rendered'] ),
                'status'     => $page['status'],
                'date'       => $page['date'],
                'modified'   => $page['modified'],
                'slug'       => $page['slug'],
                'parent'     => $page['parent'],
                'menu_order' => $page['menu_order'],
                'link'       => $page['link'],
            );
        }

        return array_merge(
            array( 'pages' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $pages ) )
        );
    }
}
