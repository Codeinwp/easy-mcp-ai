<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\CPT\List_CPT_Items;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Posts extends Base_Tool {

    const REST_ORDERBY = array( 'date', 'id', 'title', 'slug', 'modified', 'author', 'include', 'parent' );

    const QUERY_ORDERBY = array( 'menu_order', 'comment_count', 'meta_value', 'meta_value_num', 'rand' );

    const META_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE' );

    const TAX_OPERATORS = array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' );
    const TAX_FIELDS    = array( 'term_id', 'slug', 'name', 'term_taxonomy_id' );

    const DATE_KEYS    = array( 'year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second', 'after', 'before', 'inclusive', 'compare', 'column' );
    const DATE_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' );
    const DATE_COLUMNS = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );

    const MAX_QUERY_CLAUSES = 10;
    const MAX_QUERY_DEPTH   = 3;

    const REST_FIELDS = 'id,title,status,date,modified,slug,excerpt,author,categories,tags,featured_media,link';

    public function get_name() {
        return 'wp_list_posts';
    }

    public function get_description() {
        return 'Lists WordPress posts. Optional filters: `status` (publish/draft/pending/private/future/trash — default publish; use "any" to get all statuses), `statuses` (array of statuses, instead of status), `search`, `categories` (array of category IDs), `tags` (array of tag IDs), `author` (user ID), `author_exclude` (array of user IDs to exclude), `include` / `exclude` (arrays of post IDs; exclude and sticky false cannot be combined with include, which WordPress lets override them), `slug` (array of slugs), `sticky` (boolean), `has_password` (boolean), `post_mime_type`, `comment_count` (exact number of comments), `year` / `monthnum` / `day`, `after` / `before` (ISO 8601 date-time range on publish date), `date_query`, `tax_query`, `meta_key` / `meta_value` / `meta_compare`, `meta_query` (WP_Query structures from an allowlist; meta filters only on keys this caller could read: a protected key needs the right to edit every post, a status other than publish needs the right to read other authors\' posts in it, and a caller who cannot edit every post must also leave password-protected posts out: see has_password), `per_page` (max 100, default 10), `page`, `offset`, `orderby` (date/id/title/slug/modified/author/include/parent/menu_order/comment_count/meta_value/meta_value_num/rand — default date), `order` (asc/desc), `context` (view|edit — edit lists only posts you can edit), `raw_columns` (boolean, edit context only: each post also carries `raw`, every database column as stored — raw post_title, post_content and the rest). Returns { posts: [{ id, title, status, date, modified, slug, excerpt, has_excerpt, author, author_name, sticky, categories (array of IDs), tags (array of IDs), featured_media, link }], total, total_pages, page, per_page }. `excerpt` falls back to the start of the content when no excerpt is stored; `has_excerpt` is true only for a stored one. For CPT items use `wp_list_cpt_items`.';
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
                    'description' => 'Field to order results by. include orders by the include list; meta_value and meta_value_num need meta_key.',
                    'enum'        => array_merge( self::REST_ORDERBY, self::QUERY_ORDERBY ),
                    'default'     => 'date',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Order direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
                ),
                'statuses'   => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Several statuses at once (any registered status, or any). Use instead of status, not with it.',
                ),
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Schema-level field name only; sent to the REST controller, not get_posts().
                'include'    => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Only these post IDs.',
                ),
                // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Schema-level field name only; sent to the REST controller, not get_posts().
                'exclude'    => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'integer' ),
                    'description' => 'Leave out these post IDs. Not with include: WordPress ignores an exclusion when include is given.',
                ),
                'slug'       => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Only posts with one of these slugs.',
                ),
                'offset'     => array(
                    'type'        => 'integer',
                    'description' => 'Skip this many posts (replaces page-based paging when set).',
                    'minimum'     => 0,
                ),
                'sticky'     => array(
                    'type'        => 'boolean',
                    'description' => 'true: only sticky posts; false: no sticky posts (not with include, which WordPress lets override the exclusion).',
                ),
                'has_password' => array(
                    'type'        => 'boolean',
                    'description' => 'true: only password-protected posts; false: only posts without a password. A meta filter from a caller who cannot edit every post needs false (or, with edit_posts and edit_published_posts, author set to their own user ID and no author_exclude).',
                ),
                'post_mime_type' => array(
                    'type'        => 'string',
                    'description' => 'Only posts with this MIME type (WP_Query post_mime_type; posts normally have none).',
                ),
                'comment_count' => array(
                    'type'        => 'integer',
                    'description' => 'Only posts with exactly this many comments.',
                    'minimum'     => 0,
                ),
                'year'       => array(
                    'type'        => 'integer',
                    'description' => 'Only posts published in this year (four digits).',
                ),
                'monthnum'   => array(
                    'type'        => 'integer',
                    'description' => 'Only posts published in this month (1-12).',
                ),
                'day'        => array(
                    'type'        => 'integer',
                    'description' => 'Only posts published on this day of the month (1-31).',
                ),
                'date_query' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'object' ),
                    'description' => 'WP_Query date_query clauses, all of which must match, e.g. [{"after":"2026-01-01","column":"post_modified"}]. Clause keys: year, month, monthnum, week, w, dayofyear, day, dayofweek, dayofweek_iso, hour, minute, second, after, before, inclusive, compare, column (post_date, post_date_gmt, post_modified, post_modified_gmt); an object holding clauses and relation (AND/OR) nests. At most 10 clauses, 3 levels.',
                ),
                'tax_query'  => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'object' ),
                    'description' => 'WP_Query tax_query clauses, all of which must match, e.g. [{"taxonomy":"genre","field":"slug","terms":["sci-fi"]}]. Clause keys: taxonomy (one registered for posts), terms, field (term_id, slug, name, term_taxonomy_id), operator (IN, NOT IN, AND, EXISTS, NOT EXISTS), include_children; an object holding clauses and relation (AND/OR) nests. At most 10 clauses, 3 levels.',
                ),
                'meta_key'   => array(
                    'type'        => 'string',
                    'description' => 'Only posts that have this meta key (with meta_value: that value). Also the key meta_value / meta_value_num ordering uses.',
                ),
                'meta_value' => array(
                    'type'        => 'string',
                    'description' => 'Meta value to compare with meta_key\'s value. Needs meta_key.',
                ),
                'meta_compare' => array(
                    'type'        => 'string',
                    'description' => 'How meta_value is compared. Needs meta_key.',
                    'enum'        => self::META_COMPARE,
                ),
                'meta_query' => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'object' ),
                    'description' => 'WP_Query meta_query clauses, all of which must match, e.g. [{"key":"rating","value":"4","compare":">=","type":"NUMERIC"}]. Clause keys: key (required), value, compare, type; an object holding clauses and relation (AND/OR) nests. Keys follow the same readability rule as meta_key. At most 10 clauses, 3 levels.',
                ),
                'context'    => array(
                    'type'        => 'string',
                    'description' => 'view (default) lists posts you can read; edit lists only posts you can edit and needs edit_posts.',
                    'enum'        => array( 'view', 'edit' ),
                    'default'     => 'view',
                ),
                'raw_columns' => array(
                    'type'        => 'boolean',
                    'description' => 'Edit context only. Each post also carries raw: every database column as WordPress stores it (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count, filter). Default false.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $query  = array();
        $params = array_merge( $this->status_and_paging_params( $arguments ), $this->filter_params( $arguments ) );
        self::apply_order( $arguments, $params, $query );
        $params = array_merge( $params, $this->wp_cli_params( $arguments ) );

        $context = $this->list_context( $arguments );
        if ( 'edit' === $context ) {
            $params['context'] = 'edit';
        }
        $raw_columns = self::raw_columns_arg( $arguments, $context );

        $query = array_merge( $query, $this->query_vars( $arguments ) );
        $this->apply_meta_filters( $arguments, $params, $query );

        $request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        $request->set_param( '_fields', self::REST_FIELDS );

        $response = $this->dispatch( $request, $query );

        if ( $response->is_error() ) {
            return $this->empty_page_or_refusal( $response, $params );
        }

        $posts = $response->get_data();

        return array_merge(
            array( 'posts' => self::post_rows( $posts, $context, $raw_columns ) ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $posts ) )
        );
    }

    private function status_and_paging_params( array $arguments ): array {
        $params = array();
        if ( ! empty( $arguments['status'] ) && isset( $arguments['statuses'] ) ) {
            throw new \InvalidArgumentException( 'Send status or statuses, not both.' );
        }

        if ( ! empty( $arguments['status'] ) ) {
            $params['status'] = $arguments['status'];
        }

        if ( isset( $arguments['statuses'] ) ) {
            $params['status'] = $this->parse_string_list( $arguments['statuses'], 'statuses' );
        }

        $params['per_page'] = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $params['page']     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        return $params;
    }

    private function filter_params( array $arguments ): array {
        $params = array();
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
        return $params;
    }

    private static function apply_order( array $arguments, array &$params, array &$query ): void {
        if ( ! empty( $arguments['orderby'] ) ) {
            if ( in_array( $arguments['orderby'], self::QUERY_ORDERBY, true ) ) {
                $query['orderby'] = $arguments['orderby'];
            } else {
                $params['orderby'] = $arguments['orderby'];
            }
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }
    }

    private function wp_cli_params( array $arguments ): array {
        $params = array();
        foreach ( array( 'include', 'exclude' ) as $key ) {
            if ( isset( $arguments[ $key ] ) ) {
                $params[ $key ] = $this->parse_id_list( $arguments[ $key ], $key );
            }
        }

        if ( isset( $arguments['slug'] ) ) {
            $params['slug'] = $this->parse_string_list( $arguments['slug'], 'slug' );
        }

        if ( isset( $arguments['offset'] ) ) {
            $params['offset'] = $this->parse_non_negative_int( $arguments['offset'], 'offset' );
        }

        if ( isset( $arguments['sticky'] ) ) {
            $params['sticky'] = rest_sanitize_boolean( $arguments['sticky'] );
        }

        self::refuse_exclusion_with_include( $params );
        return $params;
    }

    private static function refuse_exclusion_with_include( array $params ): void {
        if ( isset( $params['include'] ) && ( isset( $params['exclude'] ) || ( isset( $params['sticky'] ) && false === $params['sticky'] ) ) ) {
            throw new \InvalidArgumentException( sprintf( '%s cannot be combined with include: WordPress ignores an exclusion when include is given. Leave those posts out of include instead.', isset( $params['exclude'] ) ? 'exclude' : 'sticky: false' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function list_context( array $arguments ): string {
        $context = isset( $arguments['context'] ) && '' !== $arguments['context'] ? $arguments['context'] : 'view';
        $this->refuse_unless_one_of( 'context', $context, array( 'view', 'edit' ), $context, 'view or edit' );
        return $context;
    }

    private static function raw_columns_arg( array $arguments, string $context ): bool {
        $raw_columns = isset( $arguments['raw_columns'] ) && rest_sanitize_boolean( $arguments['raw_columns'] );
        if ( $raw_columns && 'edit' !== $context ) {
            throw new \InvalidArgumentException( 'raw_columns needs context edit: the raw columns include the post password and unfiltered content, which WordPress shows only to users who can edit the post.' );
        }
        return $raw_columns;
    }

    private function query_vars( array $arguments ): array {
        $query = array();
        if ( isset( $arguments['has_password'] ) ) {
            $query['has_password'] = rest_sanitize_boolean( $arguments['has_password'] );
        }

        if ( isset( $arguments['post_mime_type'] ) && '' !== $arguments['post_mime_type'] ) {
            if ( ! is_string( $arguments['post_mime_type'] ) ) {
                throw new \InvalidArgumentException( 'post_mime_type must be a string.' );
            }
            $query['post_mime_type'] = sanitize_text_field( $arguments['post_mime_type'] );
        }

        if ( isset( $arguments['comment_count'] ) ) {
            $query['comment_count'] = $this->parse_non_negative_int( $arguments['comment_count'], 'comment_count' );
        }

        $query = array_merge( $query, $this->date_part_vars( $arguments ) );

        if ( isset( $arguments['date_query'] ) ) {
            $query['date_query'] = $this->parse_query_structure( $arguments['date_query'], 'date_query' );
        }

        if ( isset( $arguments['tax_query'] ) ) {
            $query['tax_query'] = $this->parse_query_structure( $arguments['tax_query'], 'tax_query' );
        }
        return $query;
    }

    private function date_part_vars( array $arguments ): array {
        $query = array();
        foreach ( array( 'year' => array( 1, 9999 ), 'monthnum' => array( 1, 12 ), 'day' => array( 1, 31 ) ) as $key => $range ) {
            if ( isset( $arguments[ $key ] ) ) {
                $value = $this->parse_non_negative_int( $arguments[ $key ], $key );
                if ( $value < $range[0] || $value > $range[1] ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$d. Use %3$d to %4$d.', $key, $value, $range[0], $range[1] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $query[ $key ] = $value;
            }
        }
        return $query;
    }

    private function apply_meta_filters( array $arguments, array $params, array &$query ): void {
        $meta_keys = array();
        $query     = array_merge( $query, $this->single_meta_vars( $arguments, $meta_keys ) );
        if ( isset( $arguments['meta_query'] ) ) {
            $query['meta_query'] = $this->parse_query_structure( $arguments['meta_query'], 'meta_query', $meta_keys );
        }
        if ( isset( $query['orderby'] ) && in_array( $query['orderby'], array( 'meta_value', 'meta_value_num' ), true ) && ! isset( $query['meta_key'] ) ) {
            throw new \InvalidArgumentException( sprintf( 'orderby %s needs meta_key: the key whose value orders the posts.', $query['orderby'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! empty( $meta_keys ) ) {
            $this->refuse_unreadable_meta_filter( array_values( array_unique( $meta_keys ) ), $params, $query );
        }
    }

    private function single_meta_vars( array $arguments, array &$meta_keys ): array {
        $query = array();
        if ( isset( $arguments['meta_key'] ) && '' !== $arguments['meta_key'] ) {
            if ( ! is_string( $arguments['meta_key'] ) ) {
                throw new \InvalidArgumentException( 'meta_key must be a string.' );
            }
            $this->refuse_unstored_meta_key( $arguments['meta_key'], 'meta_key' );
            $query['meta_key'] = $arguments['meta_key'];
            $meta_keys[]       = $arguments['meta_key'];
        }
        foreach ( array( 'meta_value', 'meta_compare' ) as $key ) {
            if ( ! isset( $arguments[ $key ] ) ) {
                continue;
            }
            if ( ! isset( $query['meta_key'] ) ) {
                throw new \InvalidArgumentException( sprintf( '%s needs meta_key: a meta value is compared with one named key, never with every key.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( ! is_scalar( $arguments[ $key ] ) ) {
                throw new \InvalidArgumentException( sprintf( '%s must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $query[ $key ] = (string) $arguments[ $key ];
        }
        if ( isset( $query['meta_compare'] ) ) {
            $compare = strtoupper( $query['meta_compare'] );
            $this->refuse_unless_one_of( 'meta_compare', $compare, self::META_COMPARE, $query['meta_compare'], implode( ', ', self::META_COMPARE ) );
            $query['meta_compare'] = $compare;
        }
        return $query;
    }

    private static function queried_statuses( array $params ): array {
        $statuses = array();
        foreach ( isset( $params['status'] ) ? (array) $params['status'] : array( 'publish' ) as $entry ) {
            foreach ( preg_split( '/[\s,]+/', (string) $entry, -1, PREG_SPLIT_NO_EMPTY ) as $one ) {
                $statuses[] = $one;
            }
        }
        return empty( $statuses ) ? array( 'publish' ) : array_values( array_unique( $statuses ) );
    }

    private function empty_page_or_refusal( \WP_REST_Response $response, array $params ): array {
        $error = $response->as_error();
        if ( $this->is_invalid_page_error( $error ) ) {
            return array_merge(
                array( 'posts' => array() ),
                $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
            );
        }
        throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function post_rows( array $posts, string $context, bool $raw_columns ): array {
        $result       = array();
        $author_names = self::author_names( $posts );
        foreach ( $posts as $post ) {
            if ( 'edit' === $context && ! current_user_can( 'edit_post', (int) $post['id'] ) ) {
                continue;
            }
            $object = get_post( (int) $post['id'] );
            $item = array(
                'id'             => $post['id'],
                'title'          => wp_strip_all_tags( $post['title']['rendered'] ),
                'status'         => $post['status'],
                'date'           => $post['date'],
                'modified'       => $post['modified'],
                'slug'           => $post['slug'],
                'excerpt'        => wp_strip_all_tags( $post['excerpt']['rendered'] ),
                'has_excerpt'    => is_object( $object ) && '' !== trim( (string) ( $object->post_excerpt ?? '' ) ),
                'author'         => $post['author'],
                'author_name'    => $author_names[ (int) $post['author'] ] ?? null,
                'sticky'         => isset( $post['sticky'] ) ? (bool) $post['sticky'] : is_sticky( (int) $post['id'] ),
                'categories'     => $post['categories'],
                'tags'           => $post['tags'],
                'featured_media' => $post['featured_media'],
                'link'           => $post['link'],
            );
            if ( $raw_columns ) {
                $item['raw'] = is_object( $object ) ? get_object_vars( $object ) : null;
            }
            $result[] = $item;
        }
        return $result;
    }

    private static function author_names( array $posts ): array {
        $ids = array();
        foreach ( $posts as $post ) {
            if ( ! empty( $post['author'] ) ) {
                $ids[ (int) $post['author'] ] = true;
            }
        }
        $names = array();
        if ( $ids ) {
            foreach ( (array) get_users( array( 'include' => array_keys( $ids ), 'fields' => array( 'ID', 'display_name' ) ) ) as $user ) {
                if ( is_object( $user ) && isset( $user->ID ) ) {
                    $names[ (int) $user->ID ] = (string) $user->display_name;
                }
            }
        }
        return $names;
    }

    private function dispatch( \WP_REST_Request $request, array $query ): \WP_REST_Response {
        $filter = static function ( $args, $current ) use ( $request, $query ) {
            if ( $current !== $request || ! is_array( $args ) ) {
                return $args;
            }
            foreach ( $query as $key => $value ) {
                if ( 'date_query' === $key ) {
                    $args['date_query']   = isset( $args['date_query'] ) && is_array( $args['date_query'] ) ? $args['date_query'] : array();
                    $args['date_query'][] = $value;
                } elseif ( 'tax_query' === $key ) {
                    $args['tax_query'] = empty( $args['tax_query'] ) ? $value : array(
                        'relation' => 'AND',
                        $args['tax_query'],
                        $value,
                    );
                } else {
                    $args[ $key ] = $value;
                }
            }
            return List_CPT_Items::with_tiebreak_marker( $args );
        };
        add_filter( 'rest_post_query', $filter, 10, 2 );
        add_filter( 'posts_clauses_request', array( List_CPT_Items::class, 'id_tiebreak_clauses' ), PHP_INT_MAX, 2 );
        try {
            return rest_do_request( $request );
        } finally {
            remove_filter( 'posts_clauses_request', array( List_CPT_Items::class, 'id_tiebreak_clauses' ), PHP_INT_MAX );
            remove_filter( 'rest_post_query', $filter, 10 );
        }
    }

    private function refuse_unreadable_meta_filter( array $keys, array $params, array &$query ): void {
        $caps = self::post_caps();
        self::refuse_protected_meta_filter( $keys, $caps );
        foreach ( self::queried_statuses( $params ) as $status ) {
            foreach ( self::caps_to_read_others_in( (string) $status, $caps ) as $needed ) {
                if ( ! current_user_can( $needed ) ) {
                    throw new \RuntimeException( sprintf( 'Meta filter with status %s refused: it can match other authors\' posts you cannot read, and the total would reveal their meta values (needs %s).', $status, $needed ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
        }
        self::refuse_password_protected_matches( $params, $query, $caps );
    }

    private static function refuse_password_protected_matches( array $params, array &$query, array $caps ): void {
        if ( ( isset( $query['has_password'] ) && false === $query['has_password'] ) || self::can_edit_every_post( $caps ) ) {
            return;
        }
        $user_id = get_current_user_id();
        $can_own = current_user_can( $caps['edit'] ) && current_user_can( $caps['edit_publish'] );
        if ( $can_own && isset( $params['author'] ) && $user_id === $params['author'] && ! isset( $params['author_exclude'] ) ) {
            $query['author__in'] = array( $user_id );
            return;
        }
        $own_posts = $can_own ? sprintf( ', or author: %d (only your own posts)', $user_id ) : '';
        throw new \RuntimeException( sprintf( 'Meta filter refused: it can match password-protected posts you cannot edit, whose hidden custom fields the total would reveal. Send has_password: false%s.', $own_posts ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function can_edit_every_post( array $caps ): bool {
        return current_user_can( $caps['edit_others'] ) && current_user_can( $caps['edit_publish'] ) && current_user_can( $caps['edit_private'] );
    }

    private static function post_caps(): array {
        $type = get_post_type_object( 'post' );
        $cap  = is_object( $type ) && isset( $type->cap ) ? (object) $type->cap : (object) array();
        return array(
            'edit'         => isset( $cap->edit_posts ) ? $cap->edit_posts : 'edit_posts',
            'edit_others'  => isset( $cap->edit_others_posts ) ? $cap->edit_others_posts : 'edit_others_posts',
            'edit_publish' => isset( $cap->edit_published_posts ) ? $cap->edit_published_posts : 'edit_published_posts',
            'edit_private' => isset( $cap->edit_private_posts ) ? $cap->edit_private_posts : 'edit_private_posts',
            'read_private' => isset( $cap->read_private_posts ) ? $cap->read_private_posts : 'read_private_posts',
        );
    }

    private static function refuse_protected_meta_filter( array $keys, array $caps ): void {
        foreach ( $keys as $key ) {
            if ( is_protected_meta( $key, 'post' ) && ! self::can_edit_every_post( $caps ) ) {
                throw new \RuntimeException( sprintf( 'Meta filter on protected key "%s" refused: protected meta is shown only to users who can edit the post, and this query can match posts you cannot edit (needs %s, %s and %s).', $key, $caps['edit_others'], $caps['edit_publish'], $caps['edit_private'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    private static function caps_to_read_others_in( string $status, array $caps ): array {
        $object = function_exists( 'get_post_status_object' ) ? get_post_status_object( $status ) : null;
        if ( 'publish' === $status || ( is_object( $object ) && ! empty( $object->public ) ) ) {
            return array();
        }
        if ( 'any' === $status ) {
            return array( $caps['edit_others'], $caps['read_private'] );
        }
        if ( 'private' === $status || ( is_object( $object ) && ! empty( $object->private ) ) ) {
            return array( $caps['read_private'] );
        }
        return array( $caps['edit_others'] );
    }

    private function refuse_unstored_meta_key( string $key, string $label ): void {
        if ( '0' === $key ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s "0": WordPress drops a meta key of "0" and would compare the value with every meta key.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! preg_match( '/^[\x21-\x7E]+\z/', $key ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: use the key in plain printable ASCII with no spaces, as WordPress stores it.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function parse_query_structure( $value, string $kind, array &$meta_keys = array() ): array {
        $query = $this->parse_json_param( $value, $kind );
        if ( array() === $query ) {
            throw new \InvalidArgumentException( sprintf( '%s must hold at least one clause.', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $this->is_first_order_clause( $query, $kind ) ) {
            throw new \InvalidArgumentException( sprintf( '%1$s must be a list of clauses, e.g. [{...}], not a single clause object.', $kind ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $count = 0;
        $this->check_query_group( $query, $kind, 1, $count, $meta_keys );
        return $query;
    }

    private function check_query_group( array $group, string $kind, int $depth, int &$count, array &$meta_keys ): void {
        if ( $depth > self::MAX_QUERY_DEPTH ) {
            throw new \InvalidArgumentException( sprintf( '%1$s nests deeper than %2$d levels.', $kind, self::MAX_QUERY_DEPTH ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        foreach ( $group as $key => $entry ) {
            if ( 'relation' === $key ) {
                $relation = is_string( $entry ) ? strtoupper( $entry ) : '';
                $this->refuse_unless_one_of( $kind . '.relation', $relation, array( 'AND', 'OR' ), is_scalar( $entry ) ? $entry : gettype( $entry ), 'AND or OR' );
                continue;
            }
            if ( ! is_array( $entry ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: "%2$s" is not a clause. Each entry is an object (a clause, or a group with relation).', $kind, (string) $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( $this->is_first_order_clause( $entry, $kind ) ) {
                ++$count;
                if ( $count > self::MAX_QUERY_CLAUSES ) {
                    throw new \InvalidArgumentException( sprintf( '%1$s holds more than %2$d clauses.', $kind, self::MAX_QUERY_CLAUSES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $this->check_query_clause( $entry, $kind, $meta_keys );
            } else {
                $this->check_query_group( $entry, $kind, $depth + 1, $count, $meta_keys );
            }
        }
    }

    private function is_first_order_clause( array $entry, string $kind ): bool {
        if ( 'meta_query' === $kind ) {
            return array_key_exists( 'key', $entry ) || array_key_exists( 'value', $entry ) || array_key_exists( 'compare', $entry ) || array_key_exists( 'compare_key', $entry ) || array_key_exists( 'type', $entry ) || array_key_exists( 'type_key', $entry );
        }
        if ( 'tax_query' === $kind ) {
            return (bool) array_intersect( array( 'taxonomy', 'terms', 'field', 'operator', 'include_children' ), array_keys( $entry ) );
        }
        return (bool) array_intersect( self::DATE_KEYS, array_keys( $entry ) );
    }

    private function check_query_clause( array $clause, string $kind, array &$meta_keys ): void {
        $allowed = array(
            'meta_query' => array( 'key', 'value', 'compare', 'type' ),
            'tax_query'  => array( 'taxonomy', 'terms', 'field', 'operator', 'include_children' ),
            'date_query' => self::DATE_KEYS,
        );
        $unknown = array_diff( array_map( 'strval', array_keys( $clause ) ), $allowed[ $kind ] );
        if ( ! empty( $unknown ) ) {
            throw new \InvalidArgumentException( sprintf( '%1$s: unsupported clause key(s) %2$s. Allowed: %3$s.', $kind, implode( ', ', $unknown ), implode( ', ', $allowed[ $kind ] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        if ( 'meta_query' === $kind ) {
            $this->check_meta_clause( $clause, $meta_keys );
            return;
        }

        if ( 'tax_query' === $kind ) {
            $this->check_tax_clause( $clause );
            return;
        }

        $this->check_date_clause( $clause );
    }

    private function check_meta_clause( array $clause, array &$meta_keys ): void {
        if ( ! isset( $clause['key'] ) || ! is_string( $clause['key'] ) || '' === $clause['key'] ) {
            throw new \InvalidArgumentException( 'meta_query: every clause needs key, one meta key as a string; a clause without it compares the value with every key.' );
        }
        $this->refuse_unstored_meta_key( $clause['key'], 'meta_query.key' );
        $meta_keys[] = $clause['key'];
        if ( isset( $clause['compare'] ) ) {
            $compare = is_string( $clause['compare'] ) ? strtoupper( $clause['compare'] ) : '';
            $this->refuse_unless_one_of( 'meta_query.compare', $compare, self::META_COMPARE, $clause['compare'], implode( ', ', self::META_COMPARE ) );
        }
        if ( isset( $clause['type'] ) && ( ! is_string( $clause['type'] ) || ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', strtoupper( $clause['type'] ) ) ) ) {
            throw new \InvalidArgumentException( 'meta_query.type must be one of NUMERIC, BINARY, CHAR, DATE, DATETIME, DECIMAL, SIGNED, TIME, UNSIGNED.' );
        }
        if ( isset( $clause['value'] ) ) {
            $this->refuse_non_scalar_values( $clause['value'], 'meta_query.value' );
        }
    }

    private function check_tax_clause( array $clause ): void {
        $taxonomy   = isset( $clause['taxonomy'] ) && is_string( $clause['taxonomy'] ) ? $clause['taxonomy'] : '';
        $registered = get_object_taxonomies( 'post', 'names' );
        $registered = is_array( $registered ) ? $registered : array();
        if ( ! in_array( $taxonomy, $registered, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'tax_query: taxonomy "%1$s" is not registered for posts. Taxonomies: %2$s.', $taxonomy, empty( $registered ) ? '(none)' : implode( ', ', $registered ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( isset( $clause['field'] ) ) {
            $this->refuse_unless_one_of( 'tax_query.field', $clause['field'], self::TAX_FIELDS, $clause['field'], implode( ', ', self::TAX_FIELDS ) );
        }
        if ( isset( $clause['operator'] ) ) {
            $operator = is_string( $clause['operator'] ) ? strtoupper( $clause['operator'] ) : '';
            $this->refuse_unless_one_of( 'tax_query.operator', $operator, self::TAX_OPERATORS, $clause['operator'], implode( ', ', self::TAX_OPERATORS ) );
        }
        if ( isset( $clause['terms'] ) ) {
            $this->refuse_non_scalar_values( $clause['terms'], 'tax_query.terms' );
        }
    }

    private function check_date_clause( array $clause ): void {
        if ( isset( $clause['column'] ) ) {
            $this->refuse_unless_one_of( 'date_query.column', $clause['column'], self::DATE_COLUMNS, $clause['column'], implode( ', ', self::DATE_COLUMNS ) );
        }
        if ( isset( $clause['compare'] ) ) {
            $compare = is_string( $clause['compare'] ) ? strtoupper( $clause['compare'] ) : '';
            $this->refuse_unless_one_of( 'date_query.compare', $compare, self::DATE_COMPARE, $clause['compare'], implode( ', ', self::DATE_COMPARE ) );
        }
        foreach ( array( 'after', 'before' ) as $edge ) {
            $this->check_date_edge( $clause, $edge );
        }
        foreach ( array_diff( self::DATE_KEYS, array( 'after', 'before', 'inclusive', 'compare', 'column' ) ) as $unit ) {
            if ( isset( $clause[ $unit ] ) ) {
                $this->refuse_non_scalar_values( $clause[ $unit ], 'date_query.' . $unit );
            }
        }
    }

    private function check_date_edge( array $clause, string $edge ): void {
        if ( ! isset( $clause[ $edge ] ) ) {
            return;
        }
        if ( is_array( $clause[ $edge ] ) ) {
            $bad = array_diff( array_map( 'strval', array_keys( $clause[ $edge ] ) ), array( 'year', 'month', 'day', 'hour', 'minute', 'second' ) );
            if ( ! empty( $bad ) ) {
                throw new \InvalidArgumentException( sprintf( 'date_query.%1$s: unsupported key(s) %2$s. Use year, month, day, hour, minute, second.', $edge, implode( ', ', $bad ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $this->refuse_non_scalar_values( $clause[ $edge ], 'date_query.' . $edge );
        } elseif ( ! is_string( $clause[ $edge ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'date_query.%s must be a date string or an object of year, month, day, hour, minute, second.', $edge ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function refuse_non_scalar_values( $value, string $label ): void {
        $values = is_array( $value ) ? $value : array( $value );
        foreach ( $values as $one ) {
            if ( ! is_scalar( $one ) ) {
                throw new \InvalidArgumentException( sprintf( '%s must be a value or a list of values.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    private function parse_id_list( $value, string $label ): array {
        $list = is_scalar( $value ) && ! is_string( $value ) ? array( $value ) : $this->parse_json_param( $value, $label );
        $out  = array();
        foreach ( $list as $id ) {
            if ( is_int( $id ) && $id > 0 ) {
                $out[] = $id;
            } elseif ( is_string( $id ) && ctype_digit( $id ) && (int) $id > 0 ) {
                $out[] = (int) $id;
            } else {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$s is not a post ID (a positive integer).', $label, is_scalar( $id ) ? (string) $id : gettype( $id ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        if ( empty( $out ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must hold at least one post ID.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array_values( array_unique( $out ) );
    }

    private function parse_string_list( $value, string $label ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : array( $value );
        }
        if ( ! is_array( $value ) || empty( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a non-empty array of strings.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( $value as $item ) {
            if ( ! is_string( $item ) || '' === trim( $item ) ) {
                throw new \InvalidArgumentException( sprintf( '%s entries must be non-empty strings.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = trim( $item );
        }
        return array_values( array_unique( $out ) );
    }

    private function parse_non_negative_int( $value, string $label ): int {
        if ( is_int( $value ) && $value >= 0 ) {
            return $value;
        }
        if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
            return (int) $value;
        }
        throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be a whole number of 0 or more, got: %2$s', $label, is_scalar( $value ) ? (string) $value : gettype( $value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
