<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Tags extends Base_Tool {

    public function get_name() {
        return 'wp_list_tags';
    }

    public function get_description() {
        return 'Lists WordPress tags (flat taxonomy). Optional: `search`, `per_page` (default 100), `page`, `orderby` (id/name/slug/count/include — default name), `order` (asc/desc), `hide_empty` (boolean, default false). Returns { tags: [{ id, name, slug, description, count }], total, total_pages, page, per_page }. Tags have no hierarchy; use `wp_list_categories` for hierarchical terms.';
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
                    'description' => 'Number of tags per page (1-100).',
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
                    'description' => 'Search query to filter tags.',
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
                'hide_empty' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide tags with no posts assigned. Default false.',
                    'default'     => false,
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

        if ( ! empty( $arguments['orderby'] ) ) {
            $params['orderby'] = $arguments['orderby'];
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        if ( isset( $arguments['hide_empty'] ) ) {
            $params['hide_empty'] = (bool) $arguments['hide_empty'];
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/tags' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'tags' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $tags  = $response->get_data();

        $result = array();
        foreach ( $tags as $tag ) {
            $result[] = array(
                'id'          => $tag['id'],
                'name'        => $tag['name'],
                'slug'        => $tag['slug'],
                'description' => $tag['description'],
                'count'       => $tag['count'],
            );
        }

        return array_merge(
            array( 'tags' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $tags ) )
        );
    }
}
