<?php
namespace Easy_MCP_AI\Tools\Pages;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\CPT\List_CPT_Items;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Pages extends Base_Tool {

    




    const REST_FIELDS = 'id,title,status,date,modified,slug,parent,menu_order,link';

    public function get_name() {
        return 'wp_list_pages';
    }

    public function get_description() {
        return 'Lists WordPress pages. Optional: `status` (publish/draft/pending/private/future/trash/any — default publish), `search`, `parent` (filter by parent page ID; use 0 for top-level only), `parent_exclude` (parent page IDs to leave out; not with `parent`), `include` or `exclude` (page IDs; not both), `slug` (list of slugs), `author` (user ID), `author_exclude` (array of user IDs to exclude), `after` / `before` (ISO 8601 date-time range on publish date), `has_password` (true/false), `date_query` / `tax_query` / `meta_query` (WP_Query clause lists, at most 10 clauses, 3 levels; tax_query only on taxonomies registered for pages and shown in the REST API; meta_query only on meta keys registered with show_in_rest for pages, needs edit_posts, never on plugin-integration SEO keys, and with a status other than publish also edit_others_pages (private: read_private_pages; any: both)), `per_page` (default 10, max 100), `page`, `offset` (skip N pages; replaces `page`), `orderby` (date/id/title/slug/modified/menu_order/parent/author/include/comment_count/rand/meta_value/meta_value_num — default date; meta_value needs a meta_query), `order` (asc/desc), `context` (view by default; edit lists only pages you can edit), `raw_columns` (boolean, with context edit only: each page also carries `raw`, every database column as stored — raw post_title, post_name, post_date, comment_count and the rest). Returns { pages: [{ id, title, status, date, modified, slug, parent, menu_order, link, raw (only with raw_columns) }], total, total_pages, page, per_page }. Pages are hierarchical — use `parent` to browse the tree.';
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
                    'description' => 'Field to order results by. include keeps the order of include; meta_value / meta_value_num sort by the first meta_query clause\'s key and need meta_query.',
                    'enum'        => array( 'date', 'id', 'title', 'slug', 'modified', 'menu_order', 'parent', 'author', 'include', 'comment_count', 'rand', 'meta_value', 'meta_value_num' ),
                    'default'     => 'date',
                ),
                'order'    => array(
                    'type'        => 'string',
                    'description' => 'Order direction.',
                    'enum'        => array( 'asc', 'desc' ),
                    'default'     => 'desc',
                ),
                'context'  => array(
                    'type'        => 'string',
                    'description' => 'view (default): pages you can read. edit: only pages you can edit (needs edit_pages).',
                    'enum'        => array( 'view', 'edit' ),
                    'default'     => 'view',
                ),
            ) + List_CPT_Items::shared_filter_schema( true ),
        );
    }

    public function execute( array $arguments ) {
        $params = array_merge( self::status_and_paging_params( $arguments ), $this->author_date_and_sort_params( $arguments ) );
        $params = self::with_cli_filters( $arguments, $params );

        $raw     = List_CPT_Items::raw_columns( $arguments );
        $context = List_CPT_Items::resolve_context( $arguments, 'view', $raw );
        if ( 'edit' === $context ) {
            $params['context'] = 'edit';
        }
        $vars = List_CPT_Items::query_vars( 'page', $arguments );

        $request = new \WP_REST_Request( 'GET', '/wp/v2/pages' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }
        $request->set_param( '_fields', self::REST_FIELDS );

        $response = List_CPT_Items::dispatch( $request, 'page', $vars );

        if ( $response->is_error() ) {
            return $this->empty_page_or_refusal( $response, $params );
        }

        $pages = $response->get_data();

        $result = array();
        foreach ( $pages as $page ) {
            
            
            if ( ! List_CPT_Items::may_list_in_context( $context, $page['id'] ) ) {
                continue;
            }
            $result[] = self::page_row( $page, $raw );
        }

        return array_merge(
            array( 'pages' => $result ),
            $this->pagination_meta( $response, $params['page'], $params['per_page'], count( $pages ) )
        );
    }

    






    private static function status_and_paging_params( array $arguments ): array {
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
        return $params;
    }

    







    private function author_date_and_sort_params( array $arguments ): array {
        $params = array();
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

        
        
        if ( ! empty( $arguments['orderby'] ) && ! in_array( $arguments['orderby'], List_CPT_Items::QUERY_ORDERBY, true ) ) {
            $params['orderby'] = $arguments['orderby'];
        }

        if ( ! empty( $arguments['order'] ) ) {
            $params['order'] = $arguments['order'];
        }
        return $params;
    }

    









    private static function with_cli_filters( array $arguments, array $params ): array {
        $params = array_merge( $params, List_CPT_Items::collection_params( $arguments, array( 'include', 'exclude', 'slug', 'offset', 'parent_exclude' ) ) );
        
        if ( isset( $params['parent'], $params['parent_exclude'] ) ) {
            throw new \InvalidArgumentException( 'Send parent or parent_exclude, not both: WordPress applies only one of them and ignores the other.' );
        }
        if ( isset( $params['offset'] ) && $params['page'] > 1 ) {
            throw new \InvalidArgumentException( 'Send either offset or page, not both: WP_Query ignores page when offset is set.' );
        }
        return $params;
    }

    








    private function empty_page_or_refusal( \WP_REST_Response $response, array $params ): array {
        $error = $response->as_error();
        if ( $this->is_invalid_page_error( $error ) ) {
            return array_merge(
                array( 'pages' => array() ),
                $this->pagination_meta( null, $params['page'], $params['per_page'], 0 )
            );
        }
        throw new \RuntimeException( self::rest_error_message( $error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private static function page_row( array $page, bool $raw ): array {
        $row = array(
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
        if ( $raw ) {
            $row['raw'] = List_CPT_Items::raw_row( $page['id'] );
        }
        return $row;
    }
}
