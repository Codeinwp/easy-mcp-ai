<?php
namespace Easy_MCP_AI\Tools\Taxonomy;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Terms extends Base_Tool {

    const META_COMPARE = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN', 'EXISTS', 'NOT EXISTS', 'REGEXP', 'NOT REGEXP', 'RLIKE' );

    const QUERY_ORDERBY = array( 'parent', 'term_taxonomy_id', 'none', 'meta_value', 'meta_value_num' );

    public function get_name() {
        return 'wp_list_terms';
    }

    public function get_description() {
        return 'Lists terms of ANY taxonomy exposed in the REST API — media folders such as `mlo-category`, product categories and brands (product_cat, product_brand), ACF or other custom taxonomies, as well as category and post_tag. Use `wp_get_taxonomies` to discover the taxonomy slugs. Required: `taxonomy` (the taxonomy slug). Optional: `search`, `parent` (filter by parent ID; use 0 for top-level only), `per_page` (default 100), `page`, `orderby` (id/term_id/name/slug/count/include/term_group/description/parent/term_taxonomy_id/none/meta_value/meta_value_num — default name), `order` (asc/desc), `hide_empty` (boolean, default false), and the get_terms() filters `include` / `exclude` / `exclude_tree` / `term_taxonomy_id` (arrays of IDs), `slug` / `name` (arrays of exact values), `child_of` (every descendant of a term), `childless` (boolean), `name__like` / `description__like` (substring), `pad_counts` (boolean: add descendants\' counts to each parent\'s count), `meta_key` with optional `meta_value` and `meta_compare` (needs the taxonomy\'s edit_terms capability, the one reading term meta needs; a protected key, starting with an underscore, also needs manage_options), `include_url` (boolean, default false). Returns { taxonomy, terms: [{ id, name, slug, description, parent, count, term_taxonomy_id, term_group, url (only with include_url) }], total, total_pages, page, per_page }. Reads through the taxonomy\'s own REST route — its `rest_namespace` (default wp/v2) and `rest_base` — so a taxonomy registered under a custom namespace is reached too. Capability resolved dynamically per taxonomy via cap->assign_terms.';
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
                'taxonomy'   => array(
                    'type'        => 'string',
                    'description' => 'The taxonomy slug (e.g. category, post_tag, product_cat, mlo-category). Use wp_get_taxonomies to list the available slugs.',
                ),
                'per_page'   => array(
                    'type'        => 'integer',
                    'description' => 'Number of terms per page (1-100).',
                    'default'     => 100,
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
                    'description' => 'Search query to filter terms.',
                ),
                'parent'     => array(
                    'type'        => 'integer',
                    'description' => 'Parent term ID to filter by (hierarchical taxonomies only; 0 for top-level terms).',
                ),
                'hide_empty' => array(
                    'type'        => 'boolean',
                    'description' => 'Whether to hide terms with no objects assigned.',
                    'default'     => false,
                ),
                'orderby'    => array(
                    'type'        => 'string',
                    'description' => 'Field to order results by. term_id is the same as id; include keeps the order of `include`; none leaves the database order; meta_value and meta_value_num (numeric) sort by the value of `meta_key`, which they require.',
                    'enum'        => array( 'id', 'name', 'slug', 'count', 'include', 'term_id', 'term_group', 'description', 'parent', 'term_taxonomy_id', 'none', 'meta_value', 'meta_value_num' ),
                    'default'     => 'name',
                ),
                'order'      => array(
                    'type'        => 'string',
                    'description' => 'Sort direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'asc',
                ),
                'include'           => array(
                    'type'        => 'array',
                    'description' => 'Only these term IDs. WordPress ignores exclude and exclude_tree when include is given, so they cannot be combined.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'exclude'           => array(
                    'type'        => 'array',
                    'description' => 'Leave out these term IDs.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'exclude_tree'      => array(
                    'type'        => 'array',
                    'description' => 'Leave out these term IDs and every descendant of each.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'term_taxonomy_id'  => array(
                    'type'        => 'array',
                    'description' => 'Only terms with these term_taxonomy_id values.',
                    'items'       => array( 'type' => 'integer' ),
                ),
                'slug'              => array(
                    'type'        => 'array',
                    'description' => 'Only terms with one of these slugs.',
                    'items'       => array( 'type' => 'string' ),
                ),
                'name'              => array(
                    'type'        => 'array',
                    'description' => 'Only terms with one of these names (the whole name, not a part of it; see name__like for that).',
                    'items'       => array( 'type' => 'string' ),
                ),
                'child_of'          => array(
                    'type'        => 'integer',
                    'description' => 'Only descendants (children, grandchildren and so on) of this term ID. Hierarchical taxonomies only; cannot be combined with a parent above 0, which WordPress lets override it. total and total_pages then count the descendants (counted separately, since WordPress\'s own total ignores child_of).',
                ),
                'childless'         => array(
                    'type'        => 'boolean',
                    'description' => 'true: only terms that have no children.',
                ),
                'name__like'        => array(
                    'type'        => 'string',
                    'description' => 'Only terms whose name contains this text.',
                ),
                'description__like' => array(
                    'type'        => 'string',
                    'description' => 'Only terms whose description contains this text.',
                ),
                'pad_counts'        => array(
                    'type'        => 'boolean',
                    'description' => 'true: each term\'s count also includes the objects of its descendants. Hierarchical taxonomies only (a flat taxonomy has no descendants).',
                ),
                'meta_key'          => array(
                    'type'        => 'string',
                    'description' => 'Only terms that have this term meta key (in plain printable ASCII, no spaces). Needs the taxonomy\'s edit_terms capability, the one reading term meta needs; a protected key (starting with an underscore) also needs manage_options. Also the key orderby meta_value / meta_value_num sorts by.',
                ),
                'meta_value'        => array(
                    'type'        => 'string',
                    'description' => 'With meta_key: only terms whose value for that key matches (see meta_compare). Requires meta_key; must not be empty (to match any value use meta_compare EXISTS).',
                ),
                'meta_compare'      => array(
                    'type'        => 'string',
                    'description' => 'With meta_key: how meta_value is compared. Default "=". IN and NOT IN take a comma-separated meta_value, BETWEEN and NOT BETWEEN exactly two comma-separated values; EXISTS and NOT EXISTS need no meta_value.',
                    'enum'        => self::META_COMPARE,
                ),
                'include_url'       => array(
                    'type'        => 'boolean',
                    'description' => 'true: add each term\'s archive URL as `url`. Default false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'taxonomy' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'taxonomy' ) );
        $taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );
        $tax_obj  = $this->listable_taxonomy( $taxonomy );

        $rest_base = ! empty( $tax_obj->rest_base ) ? (string) $tax_obj->rest_base : $taxonomy;
        $namespace = self::taxonomy_rest_namespace( $tax_obj, $taxonomy );

        $params = $this->basic_params( $arguments );

        $query_args = $this->term_query_args( $arguments, $tax_obj, $taxonomy );

        $this->apply_orderby( $arguments, $params, $query_args );

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }

        $params = $this->with_collection_filters( $arguments, $params, $query_args );

        $request = new \WP_REST_Request( 'GET', '/' . $namespace . '/' . $rest_base );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = $this->dispatch( $request, $taxonomy, $query_args );

        if ( $response->is_error() ) {
            return $this->empty_page_or_refusal( $response, $taxonomy, $params );
        }

        $terms       = $response->get_data();
        $include_url = isset( $arguments['include_url'] ) && rest_sanitize_boolean( $arguments['include_url'] );

        $result = array();
        foreach ( (array) $terms as $term ) {
            $result[] = $this->term_row( $term, $taxonomy, $include_url );
        }

        return array_merge(
            array(
                'taxonomy' => $taxonomy,
                'terms'    => $result,
            ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( (array) $terms ) )
        );
    }

    private function listable_taxonomy( string $taxonomy ): object {
        $tax_obj = get_taxonomy( $taxonomy );
        if ( ! $tax_obj ) {
            throw new \InvalidArgumentException(
                sprintf( 'Unknown taxonomy: %s', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( empty( $tax_obj->show_in_rest ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Taxonomy %s is not exposed in the REST API (show_in_rest is false), so its terms cannot be listed.', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
            throw new \RuntimeException(
                sprintf( 'Insufficient capability for taxonomy %s.', $taxonomy ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $tax_obj;
    }

    private function basic_params( array $arguments ): array {
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

        return $params;
    }

    private function apply_orderby( array $arguments, array &$params, array &$query_args ): void {
        if ( empty( $arguments['orderby'] ) ) {
            return;
        }
        $orderby = $arguments['orderby'];
        if ( is_string( $orderby ) && in_array( $orderby, self::QUERY_ORDERBY, true ) ) {
            if ( in_array( $orderby, array( 'meta_value', 'meta_value_num' ), true ) && ! isset( $query_args['meta_key'] ) ) {
                throw new \InvalidArgumentException( sprintf( 'orderby %s needs meta_key: it sorts by the value of that key, and without one WordPress falls back to sorting by name.', $orderby ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $query_args['orderby'] = $orderby;
            return;
        }
        $params['orderby'] = 'term_id' === $orderby ? 'id' : $orderby;
    }

    private function with_collection_filters( array $arguments, array $params, array $query_args ): array {
        foreach ( array( 'include', 'exclude' ) as $key ) {
            $ids = $this->id_list_arg( $arguments, $key, 'include' === $key );
            if ( null !== $ids ) {
                $params[ $key ] = $ids;
            }
        }
        $slugs = $this->string_list_arg( $arguments, 'slug' );
        if ( null !== $slugs ) {
            $params['slug'] = $slugs;
        }
        if ( isset( $params['include'] ) && ( ! empty( $params['exclude'] ) || ! empty( $query_args['exclude_tree'] ) ) ) {
            throw new \InvalidArgumentException( 'include cannot be combined with exclude or exclude_tree: WordPress ignores both exclusions when include is given.' );
        }
        return $params;
    }

    private function empty_page_or_refusal( \WP_REST_Response $response, string $taxonomy, array $params ): array {
        $error = $response->as_error();
        if ( $this->is_invalid_page_error( $error ) ) {
            return array_merge(
                array(
                    'taxonomy' => $taxonomy,
                    'terms'    => array(),
                ),
                $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
            );
        }
        throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private function term_row( array $term, string $taxonomy, bool $include_url ): array {
        $row = array_merge(
            array(
                'id'          => $term['id'],
                'name'        => $term['name'],
                'slug'        => $term['slug'],
                'description' => $term['description'] ?? '',
                'parent'      => $term['parent'] ?? 0,
                'count'       => $term['count'] ?? 0,
            ),
            $this->term_object_fields( (int) $term['id'], $taxonomy )
        );
        if ( $include_url ) {
            $row['url'] = isset( $term['link'] ) && is_string( $term['link'] ) ? $term['link'] : null;
        }
        return $row;
    }

    private function term_object_fields( int $term_id, string $taxonomy ): array {
        $object  = get_term( $term_id, $taxonomy );
        $is_term = is_object( $object ) && ! is_wp_error( $object ) && isset( $object->term_taxonomy_id );
        return array(
            'term_taxonomy_id' => $is_term ? (int) $object->term_taxonomy_id : null,
            'term_group'       => $is_term && isset( $object->term_group ) ? (int) $object->term_group : null,
        );
    }

    private function dispatch( \WP_REST_Request $request, string $taxonomy, array $query_args ): \WP_REST_Response {
        if ( empty( $query_args ) ) {
            return rest_do_request( $request );
        }

        $hook   = 'rest_' . $taxonomy . '_query';
        $filter = static function ( $prepared_args, $current = null ) use ( $request, $query_args ) {
            if ( $current !== $request || ! is_array( $prepared_args ) ) {
                return $prepared_args;
            }
            return array_merge( $prepared_args, $query_args );
        };
        $final   = null;
        $capture = static function ( $prepared_args, $current = null ) use ( $request, &$final ) {
            if ( $current === $request && is_array( $prepared_args ) ) {
                $final = $prepared_args;
            }
            return $prepared_args;
        };
        $recount = isset( $query_args['child_of'] );

        add_filter( $hook, $filter, PHP_INT_MIN, 2 );
        if ( $recount ) {
            add_filter( $hook, $capture, PHP_INT_MAX, 2 );
        }
        try {
            $response = rest_do_request( $request );
        } finally {
            remove_filter( $hook, $filter, PHP_INT_MIN );
            if ( $recount ) {
                remove_filter( $hook, $capture, PHP_INT_MAX );
            }
        }

        if ( is_array( $final ) && ! $response->is_error() ) {
            $this->apply_tree_count( $response, $request, $final );
        }
        return $response;
    }

    private function apply_tree_count( \WP_REST_Response $response, \WP_REST_Request $request, array $final ): void {
        $count_args = $final;
        unset( $count_args['number'], $count_args['offset'] );
        $count_args['fields']                 = 'ids';
        $count_args['update_term_meta_cache'] = false;
        $total                                = $this->count_matching_terms( $count_args );
        if ( null !== $total ) {
            $per_page = max( 1, (int) $request->get_param( 'per_page' ) );
            $response->header( 'X-WP-Total', $total );
            $response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );
        }
    }

    protected function count_matching_terms( array $args ): ?int {
        $ids = get_terms( $args );
        return is_array( $ids ) ? count( $ids ) : null;
    }

    private function term_query_args( array $arguments, object $tax_obj, string $taxonomy ): array {
        $args = $this->id_and_name_args( $arguments );

        $child_of = $this->child_of_arg( $arguments );
        if ( null !== $child_of ) {
            $args['child_of'] = $child_of;
        }

        $args = array_merge( $args, $this->flag_args( $arguments ), $this->like_args( $arguments ) );

        return array_merge( $args, $this->meta_query_args( $arguments, $tax_obj, $taxonomy ) );
    }

    private function id_and_name_args( array $arguments ): array {
        $args = array();
        foreach ( array( 'exclude_tree', 'term_taxonomy_id' ) as $key ) {
            $ids = $this->id_list_arg( $arguments, $key, 'term_taxonomy_id' === $key );
            if ( null !== $ids ) {
                $args[ $key ] = $ids;
            }
        }

        $names = $this->string_list_arg( $arguments, 'name' );
        if ( null !== $names ) {
            $args['name'] = $names;
        }
        return $args;
    }

    private function child_of_arg( array $arguments ): ?int {
        if ( ! array_key_exists( 'child_of', $arguments ) || null === $arguments['child_of'] ) {
            return null;
        }
        $child_of = $this->parse_required_id( $arguments['child_of'], 'child_of' );
        if ( isset( $arguments['parent'] ) && absint( $arguments['parent'] ) > 0 ) {
            throw new \InvalidArgumentException( 'child_of cannot be combined with a parent above 0: WordPress ignores child_of when parent is set. Use one of them.' );
        }
        return $child_of;
    }

    private function flag_args( array $arguments ): array {
        $args = array();
        foreach ( array( 'childless', 'pad_counts' ) as $key ) {
            if ( array_key_exists( $key, $arguments ) && null !== $arguments[ $key ] ) {
                $args[ $key ] = rest_sanitize_boolean( $arguments[ $key ] );
            }
        }
        return $args;
    }

    private function like_args( array $arguments ): array {
        $args = array();
        foreach ( array( 'name__like', 'description__like' ) as $key ) {
            if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
                continue;
            }
            if ( ! is_string( $arguments[ $key ] ) || '' === $arguments[ $key ] ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s: send the text to look for (WordPress ignores an empty value).', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $args[ $key ] = $arguments[ $key ];
        }
        return $args;
    }

    private function meta_query_args( array $arguments, object $tax_obj, string $taxonomy ): array {
        $key     = $this->scalar_string_arg( $arguments, 'meta_key' );
        $value   = $this->scalar_string_arg( $arguments, 'meta_value' );
        $compare = $this->scalar_string_arg( $arguments, 'meta_compare' );

        if ( null === $key ) {
            if ( null !== $value || null !== $compare ) {
                throw new \InvalidArgumentException( 'meta_value and meta_compare need meta_key: without a key WordPress compares the value against every term meta key.' );
            }
            return array();
        }

        $this->refuse_unaddressable_meta_key( $key );
        $this->refuse_unreadable_meta_key( $key, $tax_obj, $taxonomy );

        $vars = array( 'meta_key' => $key ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key

        $upper = null;
        if ( null !== $compare ) {
            $upper = strtoupper( $compare );
            $this->refuse_unless_one_of( 'meta_compare', $upper, self::META_COMPARE, $compare, implode( ', ', self::META_COMPARE ) );
            $vars['meta_compare'] = $upper;
        }

        if ( null !== $value ) {
            $this->refuse_ignored_meta_value( $value, $upper );
            $vars['meta_value'] = $value; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
        } elseif ( null !== $upper && ! in_array( $upper, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'meta_compare %s needs a meta_value: without one WordPress ignores the operator and matches every term with the key.', $upper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return $vars;
    }

    private function refuse_unaddressable_meta_key( string $key ): void {
        if ( '0' === $key ) {
            throw new \InvalidArgumentException( 'Invalid meta_key "0": WordPress drops a meta key of "0" and would compare the value with every term meta key.' );
        }
        if ( ! preg_match( '/^[\x21-\x7E]+\z/', $key ) ) {
            throw new \InvalidArgumentException( 'Invalid meta_key: use the key in plain printable ASCII with no spaces, as WordPress stores it.' );
        }
    }

    private function refuse_unreadable_meta_key( string $key, object $tax_obj, string $taxonomy ): void {
        if ( false === $this->caller_may_read_term_meta() ) {
            throw new \RuntimeException( 'Filtering or sorting terms by term meta is refused: this connection may not read term meta, and the filter would reveal values it cannot read.' );
        }
        if ( ! current_user_can( $tax_obj->cap->edit_terms ) ) {
            throw new \RuntimeException( sprintf( 'Filtering or sorting terms by term meta needs the %1$s capability for taxonomy %2$s, the capability reading its term meta needs.', $tax_obj->cap->edit_terms, $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( is_protected_meta( $key, 'term' ) && ! current_user_can( 'manage_options' ) ) {
            throw new \RuntimeException( sprintf( 'Filtering or sorting by the protected meta key %s requires the manage_options capability.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private function refuse_ignored_meta_value( string $value, ?string $upper ): void {
        if ( '' === $value ) {
            throw new \InvalidArgumentException( 'meta_value cannot be empty: WordPress ignores an empty value and would match every term with the key. To match any value, use meta_compare EXISTS.' );
        }
        if ( 'NOT EXISTS' === $upper ) {
            throw new \InvalidArgumentException( 'meta_value has no effect with meta_compare NOT EXISTS: WordPress ignores it. Send meta_key and meta_compare only.' );
        }
        if ( in_array( $upper, array( 'BETWEEN', 'NOT BETWEEN' ), true ) && 2 !== count( preg_split( '/[,\s]+/', $value ) ) ) {
            throw new \InvalidArgumentException( sprintf( 'meta_compare %s needs exactly two comma-separated values in meta_value, for example "10,20".', $upper ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    protected function caller_may_read_term_meta(): ?bool {
        if ( ! class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
            return null;
        }
        return \Easy_MCP_AI\MCP\Server::current_call_may_use( 'wp_get_term_meta' );
    }

    private function id_list_arg( array $arguments, string $key, bool $non_empty ): ?array {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return null;
        }
        $list = $this->parse_json_param( $arguments[ $key ], $key );
        if ( $non_empty && empty( $list ) ) {
            throw new \InvalidArgumentException( sprintf( '%s cannot be an empty list: WordPress reads an empty list as no filter and would return every term. Omit it instead.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $ids = array();
        foreach ( array_values( $list ) as $i => $id ) {
            $ids[] = $this->parse_required_id( $id, $key . '[' . $i . ']' );
        }
        return $ids;
    }

    private function string_list_arg( array $arguments, string $key ): ?array {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return null;
        }
        $list = $this->parse_json_param( $arguments[ $key ], $key );
        if ( empty( $list ) ) {
            throw new \InvalidArgumentException( sprintf( '%s cannot be an empty list: WordPress reads an empty list as no filter and would return every term. Omit it instead.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $out = array();
        foreach ( array_values( $list ) as $i => $item ) {
            if ( ! is_string( $item ) || '' === $item ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %s[%d]: each entry must be a non-empty string.', $key, $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $out[] = $item;
        }
        return $out;
    }

    private function scalar_string_arg( array $arguments, string $key ): ?string {
        if ( ! array_key_exists( $key, $arguments ) || null === $arguments[ $key ] ) {
            return null;
        }
        $value = $arguments[ $key ];
        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }
        if ( ! is_string( $value ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    public static function taxonomy_rest_namespace( object $tax_obj, string $taxonomy ): string {
        $namespace = isset( $tax_obj->rest_namespace ) && is_string( $tax_obj->rest_namespace )
            ? trim( $tax_obj->rest_namespace, '/' )
            : '';
        if ( '' === $namespace ) {
            return 'wp/v2';
        }
        foreach ( explode( '/', $namespace ) as $segment ) {
            if ( '' === $segment || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $segment ) ) {
                throw new \RuntimeException(
                    sprintf(
                        'Taxonomy %s declares a rest_namespace ("%s") the tool cannot route to: the segment "%s" is not a valid REST namespace segment.',
                        $taxonomy, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $namespace, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        $segment // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    )
                );
            }
        }
        return $namespace;
    }
}
