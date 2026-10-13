<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Search_Posts extends Base_Tool {

    public function get_name() {
        return 'wp_search_posts';
    }

    public function get_description() {
        return 'Searches published posts by keyword, in every public post type or the one named in `subtype`. Never returns drafts, pending, private or scheduled posts, or media, so an empty result does not mean the item is missing; for those use wp_list_posts / wp_list_pages with `search` and `status: "any"`, or wp_list_media with `search`. Returns { results: [{ id, title, url (the permalink), type, subtype, snippet (only with `snippet: true`) }], total, total_pages, page, per_page, query }. To include terms, use wp_search.';
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
                'query'     => array(
                    'type'        => 'string',
                    'description' => 'The search query string.',
                ),
                'subtype'   => array(
                    'type'        => 'string',
                    'description' => 'Post type subtype to search within (e.g. post, page). Maps to the WP REST API /wp/v2/search "subtype" parameter. Omitting this searches all searchable post types (WP defaults "subtype" to "any").',
                ),
                'per_page'  => array(
                    'type'        => 'integer',
                    'description' => 'Number of results per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'      => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'snippet'        => array(
                    'type'        => 'boolean',
                    'description' => 'When true, include a `snippet` field on each result: a plain-text excerpt (HTML, shortcodes, and block markup stripped) windowed around the first match of the query. Lets you judge relevance or read the matched context without a follow-up wp_get_post per result. Default false (response is unchanged when omitted).',
                    'default'     => false,
                ),
                'snippet_length' => array(
                    'type'        => 'integer',
                    'description' => 'Maximum snippet length in characters (20-1000, default 200). Only used when snippet is true.',
                    'default'     => 200,
                    'minimum'     => 20,
                    'maximum'     => 1000,
                ),
            ),
            'required'   => array( 'query' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'query' ) );

        $query  = sanitize_text_field( $arguments['query'] );
        $page   = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $params = array(
            'search'   => $query,
            'type'     => 'post',
            'per_page' => isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10,
            'page'     => $page,
        );

        if ( ! empty( $arguments['subtype'] ) ) {
            $params['subtype'] = sanitize_text_field( $arguments['subtype'] );
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/search' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'results' => array() ),
                    $this->pagination_meta( null, $page, $params['per_page'], 0 ),
                    array( 'query' => $query )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $items = $response->get_data();

        $want_snippet = ! empty( $arguments['snippet'] );
        $snippet_len  = isset( $arguments['snippet_length'] ) ? (int) $arguments['snippet_length'] : 200;

        $results = array();
        foreach ( $items as $item ) {
            $row = array(
                'id'      => $item['id'],
                'title'   => $item['title'],
                'url'     => $item['url'],
                'type'    => $item['type'],
                'subtype' => isset( $item['subtype'] ) ? $item['subtype'] : '',
            );
            
            
            if ( $want_snippet && 'post' === ( $item['type'] ?? '' ) ) {
                $post = get_post( (int) $item['id'] );
                if ( $post && isset( $post->post_content ) && ! post_password_required( $post ) ) {
                    $row['snippet'] = $this->build_search_snippet( $post->post_content, $query, $snippet_len );
                } else {
                    $row['snippet'] = '';
                }
            }
            $results[] = $row;
        }

        return array_merge(
            array( 'results' => $results ),
            $this->pagination_meta( $response, $page, $params['per_page'], count( $items ) ),
            array( 'query' => $query )
        );
    }
}
