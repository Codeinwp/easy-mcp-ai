<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Categories extends Base_Tool {

    public function get_name() {
        return 'wp_list_categories';
    }

    public function get_description() {
        return 'Lists WordPress categories. Optional: `search`, `parent` (filter by parent ID; use 0 for top-level only), `per_page` (default 100), `page`, `orderby` (id/name/slug/count/include — default name), `order` (asc/desc), `hide_empty` (boolean, default false). Returns { categories: [{ id, name, slug, description, parent, count }], total, total_pages, page, per_page }. Categories are hierarchical — use `parent` to walk the tree.';
    }

    public function get_category() {
        return 'taxonomy';
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
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of categories per page (1-100).',
                    'default'     => 100,
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
                    'description' => 'Search query to filter categories.',
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'Parent category ID to filter by.',
                ),
                'hide_empty' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide categories with no posts assigned.',
                    'default'     => false,
                ),
                'orderby'    => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by.',
                    'enum'        => array( 'id', 'name', 'slug', 'count', 'include' ),
                    'default'     => 'name',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'asc',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params = array();

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 100;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        if ( isset( $arguments['hide_empty'] ) ) {
            $params['hide_empty'] = (bool) $arguments['hide_empty'];
        }

        if ( ! empty( $arguments['orderby'] ) ) {
            $params['orderby'] = $arguments['orderby'];
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/categories' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'categories' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $categories = $response->get_data();

        $result = array();
        foreach ( $categories as $category ) {
            $result[] = array(
                'id'          => $category['id'],
                'name'        => $category['name'],
                'slug'        => $category['slug'],
                'description' => $category['description'],
                'parent'      => $category['parent'],
                'count'       => $category['count'],
            );
        }

        return array_merge(
            array( 'categories' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $categories ) )
        );
    }
}
