<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\CPT\List_CPT_Items;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if ( ! class_exists( List_CPT_Items::class, false ) ) {
    require_once dirname( __DIR__ ) . '/cpt/class-list-cpt-items.php';
}

class List_Media extends Base_Tool {

    
    const ORDERBY = array( 'date', 'id', 'title', 'slug', 'modified', 'author', 'parent', 'include' );

    
    const STATUSES = array( 'inherit', 'private', 'trash' );

    public function get_name() {
        return 'wp_list_media';
    }

    public function get_description() {
        return 'Lists media library items (`per_page` 10 by default, 100 at most; `page` or `offset`). Filters: `search`, `media_type` (image/video/audio/application), `mime_type` (one exact type, e.g. "image/png"), `author`, `author_exclude`, `parent` (the post an item is attached to; 0 = unattached only) or `parent_exclude` (0 = leave unattached items out), `include` or `exclude` (item IDs), `after` / `before` (upload date, ISO 8601), `status` (inherit by default; private, trash, a comma-separated list, or any = inherit and private), and `taxonomy_filters` / `taxonomy_exclude` (taxonomy slug → term IDs, e.g. {"mlo-category": [12]} for a media folder; attachment taxonomies shown in the REST API only — find them with `wp_get_taxonomies`, their terms with `wp_list_terms`), joined by `taxonomy_relation` (AND by default, or OR). Sort with `orderby` (date by default; id, title, slug, modified, author, parent, include) and `order` (desc by default); items that tie are ordered by ID, so pages never repeat or skip one. `context` edit lists only items you can edit and allows `raw_columns` (each item also carries `raw`, every stored column). Returns { media: [{ id, title, alt_text, mime_type, media_type, source_url, date, parent, terms }], total, total_pages, page, per_page }: `parent` is 0 when unattached, `terms` maps each taxonomy slug to its term IDs. Unattached is not unused: an unattached item can still be a featured image or appear in post content, and an attached one can be unused.';
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
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'Only items attached to this post ID. 0 lists unattached items only. Not with parent_exclude.',
                    'minimum'     => 0,
                ),
                'parent_exclude' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Leave out items attached to these post IDs (0 leaves out unattached items). Not with parent, which WordPress lets override it.',
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
                    'description' => 'Field to order results by. include keeps the order of `include`.',
                    'enum'        => self::ORDERBY,
                    'default'     => 'date',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Order direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
                ),
                'status'     => array(
                    'type'        => 'string',
                    'description' => 'inherit (default: every ordinary media item), private, trash (only with MEDIA_TRASH), a comma-separated list of those, or any (inherit and private, as WP_Query reads any for attachments). A status other than inherit needs edit_posts.',
                ),
                'context'    => array(
                    'type'        => 'string',
                    'description' => 'view (default): items you can read. edit: only items you can edit (needs edit_posts).',
                    'enum'        => array( 'view', 'edit' ),
                    'default'     => 'view',
                ),
            ) + array_intersect_key( List_CPT_Items::shared_filter_schema( false ), array_flip( array( 'include', 'exclude', 'offset', 'raw_columns' ) ) ),
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

        
        
        $params = array_merge( $params, List_CPT_Items::collection_params( $arguments, array( 'include', 'exclude', 'offset', 'parent', 'parent_exclude' ) ) );
        if ( isset( $params['offset'] ) && $params['page'] > 1 ) {
            throw new \InvalidArgumentException( 'Send either offset or page, not both: WP_Query ignores page when offset is set.' );
        }

        $statuses = self::statuses( $arguments );
        if ( null !== $statuses ) {
            $params['status'] = $statuses;
        }

        $raw     = List_CPT_Items::raw_columns( $arguments );
        $context = List_CPT_Items::resolve_context( $arguments, 'view', $raw );
        if ( 'edit' === $context ) {
            $params['context'] = 'edit';
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

        
        $response = List_CPT_Items::dispatch( $request, 'attachment', array() );

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
            
            
            if ( ! List_CPT_Items::may_list_in_context( $context, $item['id'] ) ) {
                continue;
            }
            $row = array(
                'id'         => $item['id'],
                'title'      => wp_strip_all_tags( $item['title']['rendered'] ),
                'mime_type'  => $item['mime_type'],
                'source_url' => $item['source_url'],
                'alt_text'   => $item['alt_text'] ?? '',
                'date'       => $item['date'],
                'media_type' => $item['media_type'],
                
                'parent'     => isset( $item['post'] ) ? (int) $item['post'] : 0,
                'terms'      => $this->item_taxonomy_terms( $item, $taxonomies ),
            );
            if ( $raw ) {
                $row['raw'] = List_CPT_Items::raw_row( $item['id'] );
            }
            $result[] = $row;
        }

        return array_merge(
            array( 'media' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $media ) )
        );
    }

    









    private static function statuses( array $arguments ): ?array {
        if ( ! isset( $arguments['status'] ) || '' === $arguments['status'] ) {
            return null;
        }
        if ( ! is_string( $arguments['status'] ) ) {
            throw new \InvalidArgumentException( 'status must be a string: inherit, private, trash, a comma-separated list of those, or any.' );
        }
        $statuses = array();
        foreach ( array_filter( array_map( 'trim', explode( ',', $arguments['status'] ) ), 'strlen' ) as $status ) {
            if ( 'any' === $status ) {
                $statuses[] = 'inherit';
                $statuses[] = 'private';
            } elseif ( in_array( $status, self::STATUSES, true ) ) {
                $statuses[] = $status;
            } else {
                throw new \InvalidArgumentException( sprintf( 'Invalid status: %s. Media items take inherit, private, trash, or any.', $status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        return $statuses ? array_values( array_unique( $statuses ) ) : null;
    }
}
