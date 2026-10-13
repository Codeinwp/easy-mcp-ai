<?php
namespace Easy_MCP_AI\Tools\Search;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Search extends Base_Tool {

    public function get_name() {
        return 'wp_search';
    }

    public function get_description() {
        return 'Searches published content by keyword: posts of every public type by default, terms with `type: "term"`. Never returns drafts, pending, private or scheduled posts, or media, so an empty result does not mean the item is missing; for those use wp_list_posts / wp_list_pages with `search` and `status: "any"`, or wp_list_media with `search`. Returns { results: [{ id, title, url, type, subtype, snippet (only with `snippet: true`) }], total, total_pages, page, per_page, query }.';
    }

    public function get_category() {
        return 'search';
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
                'query'    => array(
                    'type'        => 'string',
                    'description' => 'The search query string.',
                ),
                'type'     => array(
                    'type'        => 'string',
                    'description' => 'Object type to search. Omitted means "post" (every public post type); pass "term" for categories, tags and other taxonomies.',
                    'enum'        => array( 'post', 'term', 'post-format' ),
                ),
                'subtype'  => array(
                    'type'        => 'string',
                    'description' => 'Limit results to a specific subtype (e.g. post, page, category, post_tag, or any custom post type/taxonomy slug).',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of results per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'snippet'        => array(
                    'type'        => 'boolean',
                    'description' => 'When true, include a `snippet` field on each POST result: a plain-text excerpt (HTML, shortcodes, and block markup stripped) windowed around the first match of the query, so you can judge relevance without a follow-up wp_get_post. Non-post results (terms) never carry a snippet. Default false (response is unchanged when omitted).',
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

        $query   = sanitize_text_field( $arguments['query'] );
        $request = new \WP_REST_Request( 'GET', '/wp/v2/search' );
        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $request->set_param( 'search', $query );
        $request->set_param( 'per_page', $per_page );
        $request->set_param( 'page', $page );

        if ( ! empty( $arguments['type'] ) ) {
            $request->set_param( 'type', sanitize_text_field( $arguments['type'] ) );
        }

        if ( ! empty( $arguments['subtype'] ) ) {
            $request->set_param( 'subtype', sanitize_text_field( $arguments['subtype'] ) );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'results' => array() ),
                    $this->pagination_meta( null, $page, $per_page, 0 ),
                    array( 'query' => $query )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $items   = $response->get_data();

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
            $this->pagination_meta( $response, $page, $per_page, count( $items ) ),
            array( 'query' => $query )
        );
    }
}
