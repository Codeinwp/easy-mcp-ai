<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Media extends Base_Tool {

    public function get_name() {
        return 'wp_list_media';
    }

    public function get_description() {
        return 'Lists WordPress media library items. Optional filters: `search`, `media_type` (filter by type: image/video/audio/application), `mime_type` (e.g. "image/jpeg", "image/png", "application/pdf"), `author` (uploader user ID), `author_exclude` (array of user IDs to exclude), `after` / `before` (ISO 8601 date-time range on upload date), `taxonomy_filters` (object: taxonomy slug → array of term IDs, e.g. {"mlo-category": [12]} to list a media folder; only taxonomies registered on attachments and exposed in the REST API are accepted — discover them with `wp_get_taxonomies`, their terms with `wp_list_terms`), `taxonomy_exclude` (same shape, excludes items in those terms), `taxonomy_relation` (AND/OR across taxonomies, default AND), `per_page` (max 100, default 10), `page`, `orderby` (date/id/title/modified — default date), `order` (asc/desc). Returns { media: [{ id, title, alt_text, mime_type, media_type, source_url, date, terms: { "<taxonomy slug>": [term IDs] } }], total, total_pages, page, per_page }. `terms` is empty when no attachment taxonomy is exposed in the REST API.';
    }

    public function get_category() {
        return 'media';
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
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of media items per page (1-100).',
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
                    'description' => 'Search query to filter media items.',
                ),
                'media_type' => array(
                    'type'        => 'string',
                    'description' => 'Media type to filter by.',
                    'enum'        => array( 'image', 'video', 'audio', 'application' ),
                ),
                'mime_type'  => array(
                    'type'        => 'string',
                    'description' => 'MIME type to filter by (e.g. image/jpeg, application/pdf).',
                ),
                'author'     => array(
                    'type'        => 'integer',
                    'description' => 'Uploader/author user ID to filter by.',
                ),
                'author_exclude' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Array of author user IDs to EXCLUDE from results.',
                ),
                'after'      => array(
                    'type'        => 'string',
                    'description' => 'Only media uploaded on or after this ISO 8601 date-time (e.g. "2026-01-01T00:00:00").',
                ),
                'before'     => array(
                    'type'        => 'string',
                    'description' => 'Only media uploaded on or before this ISO 8601 date-time (e.g. "2026-12-31T23:59:59").',
                ),
                'taxonomy_filters' => array(
                    'type'        => 'object',
                    'description' => 'Only items assigned to these terms: an object keyed by taxonomy slug whose values are arrays of term IDs, e.g. {"mlo-category": [12, 15]}. Only taxonomies registered on attachments and exposed in the REST API are accepted; a JSON string of the same object is also accepted.',
                ),
                'taxonomy_exclude' => array(
                    'type'        => 'object',
                    'description' => 'Exclude items assigned to these terms; same shape as taxonomy_filters.',
                ),
                'taxonomy_relation' => array(
                    'type'        => 'string',
                    'description' => 'How multiple taxonomies in taxonomy_filters combine.',
                    'enum'        => array( 'AND', 'OR' ),
                    'default'     => 'AND',
                ),
                'orderby'    => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by.',
                    'enum'        => array( 'date', 'id', 'title', 'modified' ),
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

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        if ( ! empty( $arguments['search'] ) ) {
            $params['search'] = sanitize_text_field( $arguments['search'] );
        }

        if ( ! empty( $arguments['media_type'] ) ) {
            $params['media_type'] = $arguments['media_type'];
        }

        if ( ! empty( $arguments['mime_type'] ) ) {
            $params['mime_type'] = sanitize_text_field( $arguments['mime_type'] );
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

        
        
        
        
        $taxonomies = $this->rest_taxonomies_for( 'attachment' );

        if ( ! empty( $arguments['taxonomy_filters'] ) ) {
            foreach ( $this->parse_taxonomy_filter( $arguments['taxonomy_filters'], 'taxonomy_filters', $taxonomies ) as $rest_base => $ids ) {
                $params[ $rest_base ] = $ids;
            }
        }

        if ( ! empty( $arguments['taxonomy_exclude'] ) ) {
            foreach ( $this->parse_taxonomy_filter( $arguments['taxonomy_exclude'], 'taxonomy_exclude', $taxonomies ) as $rest_base => $ids ) {
                $params[ $rest_base . '_exclude' ] = $ids;
            }
        }

        if ( ! empty( $arguments['taxonomy_relation'] ) ) {
            $relation = strtoupper( (string) $arguments['taxonomy_relation'] );
            if ( ! in_array( $relation, array( 'AND', 'OR' ), true ) ) {
                throw new \InvalidArgumentException( 'taxonomy_relation must be AND or OR.' );
            }
            $params['tax_relation'] = $relation;
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/media' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'media' => array() ),
                    $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $media = $response->get_data();

        $result = array();
        foreach ( $media as $item ) {
            $result[] = array(
                'id'         => $item['id'],
                'title'      => wp_strip_all_tags( $item['title']['rendered'] ),
                'mime_type'  => $item['mime_type'],
                'source_url' => $item['source_url'],
                'alt_text'   => $item['alt_text'] ?? '',
                'date'       => $item['date'],
                'media_type' => $item['media_type'],
                'terms'      => $this->item_taxonomy_terms( $item, $taxonomies ),
            );
        }

        return array_merge(
            array( 'media' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $media ) )
        );
    }
}
