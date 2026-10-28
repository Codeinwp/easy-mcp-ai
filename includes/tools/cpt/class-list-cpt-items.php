<?php
namespace Easy_MCP_AI\Tools\CPT;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Post_Type_Rest_Base;
use Easy_MCP_AI\Tools\Post_Type_Tool_Hint;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_CPT_Items extends Base_Tool {

    const QUERY_ORDERBY = array( 'comment_count', 'rand', 'meta_value', 'meta_value_num' );

    const REST_FIELDS = 'id,title,status,date,modified,link,slug';

    const QUERY_MAX_DEPTH = 3;

    const QUERY_MAX_CLAUSES = 10;

    const QUERY_FILTER_PRIORITY = 999;

    const TIEBREAK_VAR = 'easy_mcp_ai_id_tiebreak';

    const DATE_UNIT_KEYS = array( 'year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second' );

    const DATE_COLUMNS = array( 'post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt' );

    const DATE_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' );

    const TAX_FIELDS = array( 'term_id', 'slug', 'name' );

    const TAX_OPERATORS = array( 'IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS' );

    const META_COMPARE = array( '=', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS', 'RLIKE', 'REGEXP', 'NOT REGEXP', '>', '>=', '<', '<=', 'BETWEEN', 'NOT BETWEEN' );

    const META_TYPE_PATTERN = '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/';

    public function get_name() {
        return 'wp_list_cpt_items';
    }

    public function get_description() {
        return 'Lists items of any custom post type (CPT) registered with `show_in_rest=true`. Required: `rest_base` (e.g. "product", "events", "movies"), or the post_type slug, which is also accepted. `wp_get_post_types` lists every post type with its slug and rest_base. Optional, each detailed on its property: `per_page` (default 10, max 100), `page` or `offset`, `search`, `status` (default publish), `include` or `exclude` (not both), `slug`, `author` or `author_exclude` (not both), `parent` or `parent_exclude` (not both), `after` / `before`, `has_password`, `date_query` / `tax_query` / `meta_query` (WP_Query clause lists; meta_query needs edit_posts and only keys registered with show_in_rest for the type), `orderby` (default date), `order` (default desc), `context` (edit by default: only items you can edit; view: items you can read, for callers without edit access), `raw_columns` (edit context only: each item also carries `raw`, every database column as stored). Returns { items: [{ id, title, status, date, modified, link, slug, raw (only with raw_columns) }], total, total_pages, page, per_page, rest_base }. The tool is listed for every caller, but WordPress enforces each type\'s own edit capability on the request (edit_posts, or a custom one like WooCommerce\'s edit_products), so a caller may get a permission error on a specific CPT.';
    }

    public function get_category() {
        return 'cpt';
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
            'properties' => array_merge(
                array(
                    'rest_base' => array(
                        'type'        => 'string',
                        'description' => 'The post type\'s REST base (e.g. "product", "books"); its post type slug (e.g. "book") is also accepted.',
                    ),
                    'status'    => array(
                        'type'        => 'string',
                        'description' => 'Status filter. Default: publish.',
                        'default'     => 'publish',
                    ),
                    'per_page'  => array(
                        'type'        => 'integer',
                        'description' => 'Items per page. Default: 10.',
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 100,
                    ),
                    'page'      => array(
                        'type'        => 'integer',
                        'description' => 'Page number. Default: 1.',
                        'default'     => 1,
                    ),
                    'search'    => array(
                        'type'        => 'string',
                        'description' => 'Search keyword.',
                    ),
                    'orderby'   => array(
                        'type'        => 'string',
                        'description' => 'Sort by: date, id, title, slug, modified, author, parent, include (the order of include), menu_order (types with page-attributes), comment_count, rand, meta_value or meta_value_num (by the first meta_query clause\'s key; needs meta_query). Default: date.',
                        'default'     => 'date',
                    ),
                    'order'     => array(
                        'type'        => 'string',
                        'description' => 'Sort direction: asc, desc. Default: desc.',
                        'default'     => 'desc',
                    ),
                    'context'   => array(
                        'type'        => 'string',
                        'description' => 'edit (default): only items you can edit, with raw titles. view: items you can read (published ones for anyone), for callers without edit access.',
                        'enum'        => array( 'view', 'edit' ),
                        'default'     => 'edit',
                    ),
                    'author'    => array(
                        'type'        => 'integer',
                        'description' => 'Only items by this user ID. Refused when the post type does not support "author".',
                    ),
                    'author_exclude' => array(
                        'type'        => 'array',
                        'items'       => array( 'type' => 'integer' ),
                        'description' => 'User IDs whose items are left out. Refused when the post type does not support "author".',
                    ),
                    'parent'    => array(
                        'type'        => 'integer',
                        'description' => 'Only children of this item ID (0 = top-level items only). Refused when the post type is not hierarchical.',
                    ),
                    'after'     => array(
                        'type'        => 'string',
                        'description' => 'Only items published on or after this ISO 8601 date-time.',
                    ),
                    'before'    => array(
                        'type'        => 'string',
                        'description' => 'Only items published on or before this ISO 8601 date-time.',
                    ),
                ),
                self::shared_filter_schema( true )
            ),
            'required'   => array( 'rest_base' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'rest_base' ) );

        $rest_base = $this->resolve_post_type_rest_base( $arguments['rest_base'], 'rest_base' );

        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;
        $raw      = self::raw_columns( $arguments );
        $context  = self::resolve_context( $arguments, 'edit', $raw );
        $orderby  = self::sanitized_orderby( $arguments );
        $request  = self::collection_request( $rest_base, $arguments, $per_page, $page, $context, $orderby );

        $params = self::collection_params( $arguments, array( 'include', 'exclude', 'slug', 'offset', 'author', 'author_exclude', 'parent', 'parent_exclude', 'after', 'before' ) );
        if ( isset( $params['offset'] ) && $page > 1 ) {
            throw new \InvalidArgumentException( 'Send either offset or page, not both: WP_Query ignores page when offset is set.' );
        }
        list( $post_type, $vars ) = self::type_bound_query( $rest_base, $arguments, $params, $orderby );
        if ( '' === $post_type ) {
            $post_type = self::post_type_for_rest_base( $rest_base )[0];
        }
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = self::dispatch( $request, $post_type, $vars );

        if ( $response->is_error() ) {
            return $this->empty_page_or_refusal( $response, $rest_base, $page, $per_page );
        }

        $items   = $response->get_data();

        $result = array();
        foreach ( $items as $item ) {
            if ( ! self::may_list_in_context( $context, $item['id'] ) ) {
                continue;
            }
            $result[] = self::item_row( $item, $raw );
        }

        return array_merge(
            array( 'items' => $result ),
            $this->pagination_meta( $response, $page, $per_page, count( $items ) ),
            array( 'rest_base' => $rest_base )
        );
    }

    private static function sanitized_orderby( array $arguments ): string {
        return ! empty( $arguments['orderby'] ) ? sanitize_text_field( $arguments['orderby'] ) : '';
    }

    private static function collection_request( string $rest_base, array $arguments, int $per_page, int $page, string $context, string $orderby ): \WP_REST_Request {
        $request = new \WP_REST_Request( 'GET', self::post_type_route( $rest_base, null, 'GET' ) );
        $request->set_param( 'status', isset( $arguments['status'] ) ? sanitize_text_field( $arguments['status'] ) : 'publish' );
        $request->set_param( 'per_page', $per_page );
        $request->set_param( 'page', $page );
        $request->set_param( 'context', $context );
        $request->set_param( '_fields', self::REST_FIELDS );

        if ( ! empty( $arguments['search'] ) ) {
            $request->set_param( 'search', sanitize_text_field( $arguments['search'] ) );
        }
        if ( '' !== $orderby && ! in_array( $orderby, self::QUERY_ORDERBY, true ) ) {
            $request->set_param( 'orderby', $orderby );
        }
        if ( ! empty( $arguments['order'] ) ) {
            $request->set_param( 'order', sanitize_text_field( $arguments['order'] ) );
        }
        return $request;
    }

    private static function type_bound_query( string $rest_base, array $arguments, array $params, string $orderby ): array {
        $type_bound = array_values( array_intersect( array_keys( $params ), array( 'author', 'author_exclude', 'parent', 'parent_exclude' ) ) );
        $query_args = array();
        foreach ( array( 'has_password', 'date_query', 'tax_query', 'meta_query' ) as $name ) {
            if ( isset( $arguments[ $name ] ) ) {
                $query_args[] = $name;
            }
        }
        if ( in_array( $orderby, self::QUERY_ORDERBY, true ) ) {
            $query_args[] = 'orderby';
        }
        if ( ! $type_bound && ! $query_args ) {
            return array( '', array() );
        }
        list( $post_type, $post_type_object ) = self::post_type_for_rest_base( $rest_base );
        self::refuse_unregistered_collection_params( $type_bound, $post_type_object, $post_type );
        return array( $post_type, self::query_vars( $post_type, $arguments ) );
    }

    private function empty_page_or_refusal( \WP_REST_Response $response, string $rest_base, int $page, int $per_page ): array {
        $wp_error = $response->as_error();
        if ( 'rest_no_route' === $wp_error->get_error_code() ) {
            throw new \RuntimeException( Post_Type_Tool_Hint::no_route( $rest_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $this->is_invalid_page_error( $wp_error ) ) {
            return array_merge(
                array( 'items' => array() ),
                $this->pagination_meta( null, $page, $per_page, 0 ),
                array( 'rest_base' => $rest_base )
            );
        }
        throw new \RuntimeException( self::rest_error_message( $wp_error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function item_row( array $item, bool $raw ): array {
        $row = array(
            'id'       => $item['id'],
            'title'    => $item['title']['raw'] ?? wp_strip_all_tags( $item['title']['rendered'] ?? '' ),
            'status'   => $item['status'],
            'date'     => $item['date'],
            'modified' => $item['modified'] ?? '',
            'link'     => $item['link'] ?? '',
            'slug'     => $item['slug'] ?? '',
        );
        if ( $raw ) {
            $row['raw'] = self::raw_row( $item['id'] );
        }
        return $row;
    }

    public static function shared_filter_schema( bool $with_parent_exclude ): array {
        $schema = array(
            'include'        => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'integer' ),
                'description' => 'Only these IDs (non-empty). With orderby "include", results keep this order.',
            ),
            'exclude'        => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'integer' ),
                'description' => 'IDs to leave out. Not with include: WordPress ignores an exclusion when include is given.',
            ),
            'slug'           => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'string' ),
                'description' => 'Only items with one of these slugs (post_name).',
            ),
            'offset'         => array(
                'type'        => 'integer',
                'description' => 'Skip this many matching items. Replaces page: send one or the other.',
                'minimum'     => 0,
            ),
            'has_password'   => array(
                'type'        => 'boolean',
                'description' => 'true: only password-protected items; false: only items without a password.',
            ),
            'date_query'     => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'object' ),
                'description' => 'WP_Query date clauses (at most 10, nested 3 levels), joined with AND, e.g. [{"after": "2026-01-01", "before": {"year": 2026, "month": 6}}]. Clause keys: after, before, year, month, monthnum, week, w, dayofyear, day, dayofweek, dayofweek_iso, hour, minute, second, inclusive, compare, column (post_date, post_date_gmt, post_modified, post_modified_gmt). For OR, put clauses in one item: {"relation": "OR", "0": {...}, "1": {...}}.',
            ),
            'tax_query'      => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'object' ),
                'description' => 'WP_Query taxonomy clauses (at most 10, nested 3 levels), joined with AND, e.g. [{"taxonomy": "genre", "field": "slug", "terms": ["drama"]}]. Clause keys: taxonomy (registered for this post type and shown in the REST API), field (term_id, slug, name; default term_id), terms, operator (IN, NOT IN, AND, EXISTS, NOT EXISTS), include_children. For OR, put clauses in one item with "relation": "OR".',
            ),
            'meta_query'     => array(
                'type'        => 'array',
                'items'       => array( 'type' => 'object' ),
                'description' => 'WP_Query meta clauses (at most 10, nested 3 levels), joined with AND, e.g. [{"key": "price", "value": 10, "compare": ">", "type": "NUMERIC"}]. Clause keys: key (required; a meta key registered with show_in_rest for this post type, the keys wp_get_post_meta shows; plugin-integration SEO keys are refused), value, compare, type. Needs the edit_posts capability, and with a status other than publish also the type\'s edit_others_posts (private: read_private_posts; any: both). For OR, put clauses in one item with "relation": "OR".',
            ),
            'raw_columns'    => array(
                'type'        => 'boolean',
                'description' => 'Edit context only. Each item also carries raw: every database column as WordPress stores it (ID, post_author, post_date, post_date_gmt — 0000-00-00 00:00:00 for an unscheduled draft —, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count, filter). Default false.',
                'default'     => false,
            ),
        );
        if ( $with_parent_exclude ) {
            $schema['parent_exclude'] = array(
                'type'        => 'array',
                'items'       => array( 'type' => 'integer' ),
                'description' => 'Leave out items whose parent is one of these IDs (0 = top-level items). Hierarchical types only; not with parent, which WordPress lets override it.',
            );
        }
        return $schema;
    }

    public static function collection_params( array $arguments, array $names ): array {
        $params = array();
        foreach ( $names as $name ) {
            if ( ! isset( $arguments[ $name ] ) ) {
                continue;
            }
            $params = array_merge( $params, self::collection_param( $name, $arguments[ $name ] ) );
        }
        self::refuse_overridden_pairs( $params );
        return $params;
    }

    private static function collection_param( string $name, $value ): array {
        if ( in_array( $name, array( 'include', 'exclude', 'author_exclude', 'parent_exclude' ), true ) ) {
            return self::id_list_param( $name, $value );
        }
        if ( in_array( $name, array( 'parent', 'author', 'offset' ), true ) ) {
            return array( $name => self::whole_number( $value, $name, 'author' === $name ? 1 : 0 ) );
        }
        if ( 'slug' === $name ) {
            return array( $name => self::slug_list( $value ) );
        }
        if ( in_array( $name, array( 'after', 'before' ), true ) && '' !== $value ) {
            return array( $name => sanitize_text_field( (string) $value ) );
        }
        return array();
    }

    private static function id_list_param( string $name, $value ): array {
        $ids = self::id_list( $value, $name, 'parent_exclude' === $name ? 0 : 1 );
        if ( 'include' === $name && empty( $ids ) ) {
            throw new \InvalidArgumentException( 'include is empty. WordPress treats an empty include as no filter at all, so omit it to list everything.' );
        }
        return $ids ? array( $name => $ids ) : array();
    }

    private static function refuse_overridden_pairs( array $params ): void {
        foreach ( array( 'include' => 'exclude', 'parent' => 'parent_exclude', 'author' => 'author_exclude' ) as $in => $out ) {
            if ( isset( $params[ $in ], $params[ $out ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Send %1$s or %2$s, not both: WordPress applies only one of them and ignores the other.', $in, $out ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    public static function query_vars( string $post_type, array $arguments ): array {
        $vars = array();
        if ( isset( $arguments['has_password'] ) ) {
            $vars['has_password'] = (bool) rest_sanitize_boolean( $arguments['has_password'] );
        }
        if ( isset( $arguments['date_query'] ) ) {
            $vars['date_query'] = self::sanitize_query( $arguments['date_query'], 'date_query', 'date', array() );
        }
        if ( isset( $arguments['tax_query'] ) ) {
            $vars['tax_query'] = self::sanitize_query( $arguments['tax_query'], 'tax_query', 'tax', self::rest_taxonomy_names( $post_type ) );
        }
        if ( isset( $arguments['meta_query'] ) ) {
            $vars['meta_query'] = self::meta_query_var( $post_type, $arguments );
        }
        $orderby = self::query_orderby( $arguments, $vars );
        if ( null !== $orderby ) {
            $vars['orderby'] = $orderby;
        }
        return $vars;
    }

    private static function rest_taxonomy_names( string $post_type ): array {
        $taxonomies = array();
        foreach ( (array) get_object_taxonomies( $post_type, 'objects' ) as $key => $tax ) {
            if ( is_object( $tax ) && ! empty( $tax->show_in_rest ) ) {
                $taxonomies[] = ! empty( $tax->name ) ? (string) $tax->name : (string) $key;
            }
        }
        return $taxonomies;
    }

    private static function meta_query_var( string $post_type, array $arguments ): array {
        if ( ! current_user_can( 'edit_posts' ) ) {
            throw new \InvalidArgumentException( 'meta_query needs the edit_posts capability, the one wp_get_post_meta requires to read meta.' );
        }
        $meta_query = self::sanitize_query( $arguments['meta_query'], 'meta_query', 'meta', self::filterable_meta_keys( $post_type ) );
        self::refuse_meta_filter_beyond_readable_statuses( $post_type, isset( $arguments['status'] ) ? $arguments['status'] : null );
        return $meta_query;
    }

    private static function query_orderby( array $arguments, array $vars ): ?string {
        $orderby = isset( $arguments['orderby'] ) && is_scalar( $arguments['orderby'] ) ? (string) $arguments['orderby'] : '';
        if ( ! in_array( $orderby, self::QUERY_ORDERBY, true ) ) {
            return null;
        }
        if ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) && empty( $vars['meta_query'] ) ) {
            throw new \InvalidArgumentException( sprintf( 'orderby %s sorts by the first meta_query clause\'s key, and no meta_query was given.', $orderby ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $orderby;
    }

    public static function refuse_meta_filter_beyond_readable_statuses( string $post_type, $status ): void {
        list( $edit_others, $read_private ) = self::other_authors_capabilities( $post_type );

        foreach ( self::requested_statuses( $status ) as $one ) {
            foreach ( self::capabilities_to_read_others_in( $one, $edit_others, $read_private ) as $needed ) {
                if ( ! current_user_can( $needed ) ) {
                    throw new \RuntimeException( sprintf( 'meta_query with status %1$s refused: it can match other authors\' items you cannot read, and the total would reveal their meta values (needs %2$s).', $one, $needed ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
        }
    }

    private static function requested_statuses( $status ): array {
        $statuses = array();
        foreach ( is_array( $status ) ? $status : array( $status ) as $entry ) {
            if ( null === $entry ) {
                continue;
            }
            $text = is_scalar( $entry ) ? (string) $entry : 'any';
            foreach ( preg_split( '/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY ) as $one ) {
                $statuses[] = $one;
            }
        }
        if ( empty( $statuses ) ) {
            $statuses = array( 'publish' );
        }
        return array_values( array_unique( $statuses ) );
    }

    private static function other_authors_capabilities( string $post_type ): array {
        $type = get_post_type_object( $post_type );
        $cap  = is_object( $type ) && isset( $type->cap ) ? (object) $type->cap : (object) array();
        return array(
            isset( $cap->edit_others_posts ) ? (string) $cap->edit_others_posts : 'edit_others_posts',
            isset( $cap->read_private_posts ) ? (string) $cap->read_private_posts : 'read_private_posts',
        );
    }

    private static function capabilities_to_read_others_in( string $one, string $edit_others, string $read_private ): array {
        $object = function_exists( 'get_post_status_object' ) ? get_post_status_object( $one ) : null;
        if ( 'publish' === $one || ( is_object( $object ) && ! empty( $object->public ) ) ) {
            return array();
        }
        if ( 'any' === $one ) {
            return array( $edit_others, $read_private );
        }
        if ( 'private' === $one || ( is_object( $object ) && ! empty( $object->private ) ) ) {
            return array( $read_private );
        }
        return array( $edit_others );
    }

    public static function dispatch( \WP_REST_Request $request, string $post_type, array $vars ): \WP_REST_Response {
        $hook     = 'rest_' . $post_type . '_query';
        $applied  = false;
        $callback = static function ( $args, $query_request = null ) use ( $request, $vars, &$applied ) {
            if ( $query_request !== $request ) {
                return $args;
            }
            $applied = true;
            return List_CPT_Items::with_tiebreak_marker( List_CPT_Items::merge_query_vars( is_array( $args ) ? $args : array(), $vars ) );
        };
        add_filter( $hook, $callback, self::QUERY_FILTER_PRIORITY, 2 );
        add_filter( 'posts_clauses_request', array( self::class, 'id_tiebreak_clauses' ), PHP_INT_MAX, 2 );
        try {
            $response = rest_do_request( $request );
        } finally {
            remove_filter( 'posts_clauses_request', array( self::class, 'id_tiebreak_clauses' ), PHP_INT_MAX );
            remove_filter( $hook, $callback, self::QUERY_FILTER_PRIORITY );
        }
        if ( $vars && ! $applied && ! $response->is_error() ) {
            throw new \RuntimeException( sprintf( 'The filters %s could not be applied: the REST request never reached the post query. Nothing was listed.', implode( ', ', array_keys( $vars ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $response;
    }

    public static function with_tiebreak_marker( array $args ): array {
        $args[ self::TIEBREAK_VAR ] = true;
        return $args;
    }

    public static function id_tiebreak_clauses( $clauses, $query = null ) {
        if ( ! is_array( $clauses ) || ! isset( $clauses['orderby'] ) || ! is_string( $clauses['orderby'] ) || ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! $query->get( self::TIEBREAK_VAR ) ) {
            return $clauses;
        }
        $clauses['orderby'] = self::order_by_with_id( $clauses['orderby'] );
        return $clauses;
    }

    public static function order_by_with_id( string $orderby ): string {
        $clause = trim( $orderby );
        $posts  = isset( $GLOBALS['wpdb']->posts ) && is_string( $GLOBALS['wpdb']->posts ) ? $GLOBALS['wpdb']->posts : 'wp_posts';
        $id     = $posts . '.ID';
        $bare   = (string) preg_replace( "/'(?:[^'\\\\]|\\\\.)*'/s", "''", $clause );
        if ( '' === $clause || false !== stripos( $bare, 'RAND(' ) || preg_match( '/(^|[^A-Za-z0-9_])' . preg_quote( $id, '/' ) . '(?![A-Za-z0-9_])/i', $bare ) ) {
            return $orderby;
        }
        $direction = preg_match( '/\s(ASC|DESC)\s*\z/i', $clause, $m ) ? strtoupper( $m[1] ) : 'ASC';
        return $clause . ', ' . $id . ' ' . $direction;
    }

    public static function merge_query_vars( array $args, array $vars ): array {
        foreach ( $vars as $key => $value ) {
            if ( in_array( $key, array( 'date_query', 'tax_query', 'meta_query' ), true ) && ! empty( $args[ $key ] ) && is_array( $args[ $key ] ) ) {
                $args[ $key ] = array(
                    'relation' => 'AND',
                    $args[ $key ],
                    $value,
                );
            } else {
                $args[ $key ] = $value;
            }
        }
        return $args;
    }

    public static function raw_columns( array $arguments ): bool {
        return isset( $arguments['raw_columns'] ) && (bool) rest_sanitize_boolean( $arguments['raw_columns'] );
    }

    public static function resolve_context( array $arguments, string $default, bool $raw ): string {
        $context = isset( $arguments['context'] ) && is_scalar( $arguments['context'] ) && '' !== $arguments['context'] ? (string) $arguments['context'] : $default;
        if ( ! in_array( $context, array( 'view', 'edit' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid context: %s. Use view or edit.', $context ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( $raw && 'edit' !== $context ) {
            throw new \InvalidArgumentException( 'raw_columns needs context edit: the raw columns include the password and unfiltered content, which WordPress shows only to users who can edit the item.' );
        }
        return $context;
    }

    public static function may_list_in_context( string $context, $id ): bool {
        if ( 'edit' !== $context ) {
            return true;
        }
        if ( ! is_int( $id ) && ! ( is_string( $id ) && '' !== $id && ctype_digit( $id ) ) ) {
            return true;
        }
        return current_user_can( 'edit_post', (int) $id );
    }

    public static function raw_row( $id ): ?array {
        $post = get_post( (int) $id );
        return is_object( $post ) ? get_object_vars( $post ) : null;
    }

    public static function filterable_meta_keys( string $post_type ): array {
        $exposure = '\\Easy_MCP_AI\\Meta\\Meta_Exposure';
        if ( ! function_exists( 'get_registered_meta_keys' ) || ! class_exists( $exposure ) ) {
            return array();
        }
        $keys = array();
        foreach ( array( $post_type, '' ) as $subtype ) {
            foreach ( (array) get_registered_meta_keys( 'post', $subtype ) as $key => $args ) {
                if ( ! is_array( $args ) || empty( $args['show_in_rest'] ) ) {
                    continue;
                }
                if ( null !== $exposure::provider_for_key( (string) $key ) ) {
                    continue;
                }
                $keys[ (string) $key ] = true;
            }
        }
        return array_keys( $keys );
    }

    public static function sanitize_query( $value, string $label, string $kind, array $allowed ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : null;
        }
        if ( ! is_array( $value ) || array() === $value ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a non-empty list of clauses.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $count = 0;
        return self::query_group( $value, $label, $kind, $allowed, 0, $count );
    }

    private static function query_group( array $group, string $label, string $kind, array $allowed, int $depth, int &$count ): array {
        if ( ! self::is_query_group( $group ) ) {
            $group = array( $group );
        }
        $out     = array();
        $clauses = 0;
        foreach ( $group as $key => $item ) {
            if ( 'relation' === $key ) {
                $out['relation'] = self::one_of( strtoupper( is_scalar( $item ) ? (string) $item : '' ), array( 'AND', 'OR' ), $label . '.relation' );
                continue;
            }
            if ( ! is_int( $key ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: unknown key "%2$s". A group holds clauses and an optional relation.', $label, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = self::query_member( $item, $label . '[' . $key . ']', $label, $kind, $allowed, $depth, $count );
            ++$clauses;
        }
        if ( 0 === $clauses ) {
            throw new \InvalidArgumentException( sprintf( '%s holds no clause.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $out;
    }

    private static function query_member( $item, string $path, string $label, string $kind, array $allowed, int $depth, int &$count ): array {
        if ( ! is_array( $item ) || array() === $item ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a clause object.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( self::is_query_group( $item ) ) {
            if ( $depth >= self::QUERY_MAX_DEPTH ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: groups may be nested at most %2$d levels deep.', $label, self::QUERY_MAX_DEPTH ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return self::query_group( $item, $path, $kind, $allowed, $depth + 1, $count );
        }
        if ( ++$count > self::QUERY_MAX_CLAUSES ) {
            throw new \InvalidArgumentException( sprintf( '%1$s: at most %2$d clauses.', $label, self::QUERY_MAX_CLAUSES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( 'date' === $kind ) {
            return self::date_clause( $item, $path );
        }
        if ( 'tax' === $kind ) {
            return self::tax_clause( $item, $path, $allowed );
        }
        return self::meta_clause( $item, $path, $allowed );
    }

    private static function is_query_group( array $value ): bool {
        foreach ( array_keys( $value ) as $key ) {
            if ( is_int( $key ) || 'relation' === $key ) {
                return true;
            }
        }
        return false;
    }

    private static function date_clause( array $clause, string $path ): array {
        $out      = array();
        $has_time = false;
        foreach ( $clause as $key => $value ) {
            $key         = (string) $key;
            $out[ $key ] = self::date_clause_value( $key, $value, $path );
            if ( 'after' === $key || 'before' === $key || in_array( $key, self::DATE_UNIT_KEYS, true ) ) {
                $has_time = true;
            }
        }
        if ( ! $has_time ) {
            throw new \InvalidArgumentException( sprintf( '%1$s needs one of after, before, %2$s.', $path, implode( ', ', self::DATE_UNIT_KEYS ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $out;
    }

    private static function date_clause_value( string $key, $value, string $path ) {
        if ( in_array( $key, self::DATE_UNIT_KEYS, true ) ) {
            return self::number_or_numbers( $value, $path . '.' . $key );
        }
        if ( 'after' === $key || 'before' === $key ) {
            return self::date_bound( $value, $path . '.' . $key );
        }
        if ( 'inclusive' === $key ) {
            return (bool) rest_sanitize_boolean( $value );
        }
        if ( 'compare' === $key ) {
            return self::one_of( strtoupper( is_scalar( $value ) ? (string) $value : '' ), self::DATE_COMPARE, $path . '.compare' );
        }
        if ( 'column' === $key ) {
            return self::one_of( is_scalar( $value ) ? (string) $value : '', self::DATE_COLUMNS, $path . '.column' );
        }
        throw new \InvalidArgumentException( sprintf( '%1$s: unknown key "%2$s".', $path, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function date_bound( $value, string $path ) {
        if ( is_string( $value ) && '' !== trim( $value ) ) {
            return trim( $value );
        }
        if ( is_array( $value ) && array() !== $value ) {
            $out = array();
            foreach ( $value as $key => $part ) {
                $key = (string) $key;
                if ( ! in_array( $key, array( 'year', 'month', 'day', 'hour', 'minute', 'second' ), true ) ) {
                    throw new \InvalidArgumentException( sprintf( '%1$s: unknown key "%2$s". Use year, month, day, hour, minute, second.', $path, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $out[ $key ] = self::whole_number( $part, $path . '.' . $key, 0 );
            }
            return $out;
        }
        throw new \InvalidArgumentException( sprintf( '%s must be a date string or an object of year, month, day, hour, minute, second.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private static function tax_clause( array $clause, string $path, array $taxonomies ): array {
        foreach ( array_keys( $clause ) as $key ) {
            if ( ! in_array( (string) $key, array( 'taxonomy', 'field', 'terms', 'operator', 'include_children' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: unknown key "%2$s". Use taxonomy, field, terms, operator, include_children.', $path, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        $taxonomy = self::tax_clause_taxonomy( $clause, $path, $taxonomies );
        $field    = self::tax_clause_field( $clause, $path );
        $operator = isset( $clause['operator'] ) ? self::one_of( strtoupper( is_scalar( $clause['operator'] ) ? (string) $clause['operator'] : '' ), self::TAX_OPERATORS, $path . '.operator' ) : 'IN';
        $out      = array(
            'taxonomy' => $taxonomy,
            'field'    => $field,
            'operator' => $operator,
        );
        if ( isset( $clause['include_children'] ) ) {
            $out['include_children'] = (bool) rest_sanitize_boolean( $clause['include_children'] );
        }
        if ( in_array( $operator, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
            return $out;
        }
        $out['terms'] = self::tax_clause_terms( $clause, $path, $field, $operator );
        return $out;
    }

    private static function tax_clause_taxonomy( array $clause, string $path, array $taxonomies ): string {
        $taxonomy = isset( $clause['taxonomy'] ) && is_scalar( $clause['taxonomy'] ) ? (string) $clause['taxonomy'] : '';
        if ( ! in_array( $taxonomy, $taxonomies, true ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%1$s: taxonomy "%2$s" is not registered for this post type in the REST API. Accepted here: %3$s.',
                    $path, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    empty( $taxonomies ) ? '(none)' : implode( ', ', $taxonomies ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                )
            );
        }
        return $taxonomy;
    }

    private static function tax_clause_field( array $clause, string $path ): string {
        if ( ! isset( $clause['field'] ) ) {
            return 'term_id';
        }
        $raw_field = is_scalar( $clause['field'] ) ? (string) $clause['field'] : '';
        if ( 'term_taxonomy_id' === $raw_field ) {
            throw new \InvalidArgumentException( sprintf( '%s: field term_taxonomy_id is not accepted (WordPress does not check it against the clause\'s taxonomy). Use term_id, slug or name.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return self::one_of( $raw_field, self::TAX_FIELDS, $path . '.field' );
    }

    private static function tax_clause_terms( array $clause, string $path, string $field, string $operator ): array {
        if ( ! isset( $clause['terms'] ) ) {
            throw new \InvalidArgumentException( sprintf( '%1$s: terms is required with operator %2$s.', $path, $operator ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $terms = is_array( $clause['terms'] ) ? $clause['terms'] : array( $clause['terms'] );
        if ( empty( $terms ) ) {
            throw new \InvalidArgumentException( sprintf( '%s: terms is empty.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $clean = array();
        foreach ( $terms as $term ) {
            if ( 'term_id' === $field ) {
                $clean[] = self::whole_number( $term, $path . '.terms', 1 );
                continue;
            }
            $term = is_scalar( $term ) ? trim( (string) $term ) : '';
            if ( '' === $term ) {
                throw new \InvalidArgumentException( sprintf( '%1$s.terms: every %2$s must be a non-empty string.', $path, $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $clean[] = $term;
        }
        return $clean;
    }

    private static function meta_clause( array $clause, string $path, array $keys ): array {
        self::refuse_unknown_meta_clause_keys( $clause, $path );
        $out = array( 'key' => self::meta_clause_key( $clause, $path, $keys ) );
        if ( array_key_exists( 'value', $clause ) ) {
            $out['value'] = self::meta_clause_value( $clause['value'], $path );
        }
        if ( isset( $clause['compare'] ) ) {
            $out['compare'] = self::one_of( strtoupper( is_scalar( $clause['compare'] ) ? (string) $clause['compare'] : '' ), self::META_COMPARE, $path . '.compare' );
        }
        if ( isset( $clause['type'] ) ) {
            $out['type'] = self::meta_clause_type( $clause['type'], $path );
        }
        return $out;
    }

    private static function refuse_unknown_meta_clause_keys( array $clause, string $path ): void {
        foreach ( array_keys( $clause ) as $key ) {
            $key = (string) $key;
            if ( 'compare_key' === $key || 'type_key' === $key ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: %2$s is not accepted. It matches meta keys by pattern, which would reach keys this caller cannot read; name the key exactly.', $path, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ( ! in_array( $key, array( 'key', 'value', 'compare', 'type' ), true ) ) {
                throw new \InvalidArgumentException( sprintf( '%1$s: unknown key "%2$s". Use key, value, compare, type.', $path, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }

    private static function meta_clause_key( array $clause, string $path, array $keys ): string {
        $meta_key = isset( $clause['key'] ) && is_string( $clause['key'] ) ? $clause['key'] : '';
        if ( '' === $meta_key || ! in_array( $meta_key, $keys, true ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    '%1$s: meta key "%2$s" cannot be filtered on. Allowed: meta keys registered with show_in_rest for this post type (the keys wp_get_post_meta shows), other than plugin-integration SEO keys. On this site: %3$s.',
                    $path, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    $meta_key, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    empty( $keys ) ? '(none)' : implode( ', ', $keys ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                )
            );
        }
        return $meta_key;
    }

    private static function meta_clause_value( $value, string $path ) {
        if ( is_array( $value ) ) {
            foreach ( $value as $item ) {
                if ( ! is_scalar( $item ) ) {
                    throw new \InvalidArgumentException( sprintf( '%s.value: a list may hold only strings and numbers.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
            return array_values( $value );
        }
        if ( ! is_scalar( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s.value must be a string, a number or a list of them.', $path ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    private static function meta_clause_type( $type, string $path ): string {
        $type = strtoupper( is_scalar( $type ) ? (string) $type : '' );
        if ( ! preg_match( self::META_TYPE_PATTERN, $type ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s.type: %2$s. Use NUMERIC, BINARY, CHAR, DATE, DATETIME, DECIMAL, SIGNED, TIME or UNSIGNED.', $path, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $type;
    }

    private static function one_of( string $value, array $allowed, string $label ): string {
        if ( ! in_array( $value, $allowed, true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s. Use %3$s.', $label, $value, implode( ', ', $allowed ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    private static function number_or_numbers( $value, string $label ) {
        if ( is_array( $value ) ) {
            if ( empty( $value ) ) {
                throw new \InvalidArgumentException( sprintf( '%s is empty.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out = array();
            foreach ( $value as $item ) {
                $out[] = self::whole_number( $item, $label, 0 );
            }
            return $out;
        }
        return self::whole_number( $value, $label, 0 );
    }

    public static function whole_number( $value, string $label, int $min ): int {
        $int = null;
        if ( is_int( $value ) ) {
            $int = $value;
        } elseif ( is_float( $value ) && floor( $value ) === $value && abs( $value ) < PHP_INT_MAX ) {
            $int = (int) $value;
        } elseif ( is_string( $value ) && preg_match( '/^-?\d+\z/', $value ) ) {
            $int = (int) $value;
        }
        if ( null === $int || $int < $min ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: must be a whole number of %2$d or more, got: %3$s', $label, $min, (string) wp_json_encode( $value ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $int;
    }

    public static function id_list( $value, string $label, int $min ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : array_map( 'trim', explode( ',', $value ) );
        }
        if ( is_scalar( $value ) ) {
            $value = array( $value );
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( sprintf( '%s must be a list of IDs.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $ids = array();
        foreach ( $value as $item ) {
            if ( '' === $item ) {
                continue;
            }
            $ids[] = self::whole_number( $item, $label, $min );
        }
        return array_values( array_unique( $ids ) );
    }

    public static function slug_list( $value ): array {
        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            $value   = is_array( $decoded ) ? $decoded : explode( ',', $value );
        }
        if ( ! is_array( $value ) || empty( $value ) ) {
            throw new \InvalidArgumentException( 'slug must be a non-empty list of slugs.' );
        }
        $slugs = array();
        foreach ( $value as $slug ) {
            $slug = is_scalar( $slug ) ? trim( (string) $slug ) : '';
            if ( '' === $slug ) {
                throw new \InvalidArgumentException( 'slug: every entry must be a non-empty string.' );
            }
            $slugs[] = $slug;
        }
        return array_values( array_unique( $slugs ) );
    }

    public static function refuse_unregistered_collection_params( array $names, ?object $post_type_object, string $post_type ): void {
        if ( empty( $names ) || ! is_object( $post_type_object ) || ! method_exists( $post_type_object, 'get_rest_controller' ) ) {
            return;
        }
        $controller = $post_type_object->get_rest_controller();
        if ( ! is_object( $controller ) || ! method_exists( $controller, 'get_collection_params' ) ) {
            return;
        }
        $registered = (array) $controller->get_collection_params();
        foreach ( $names as $name ) {
            if ( array_key_exists( $name, $registered ) ) {
                continue;
            }
            $why = in_array( $name, array( 'parent', 'parent_exclude' ), true ) ? 'it is not hierarchical' : ( in_array( $name, array( 'author', 'author_exclude' ), true ) ? 'it does not support "author"' : 'its REST API collection has no such parameter' );
            throw new \InvalidArgumentException( sprintf( '%1$s: post type "%2$s" cannot be filtered by it (%3$s), so WordPress would ignore it.', $name, $post_type, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    public static function post_type_for_rest_base( string $rest_base ): array {
        return Post_Type_Rest_Base::require_served_type( $rest_base );
    }
}
