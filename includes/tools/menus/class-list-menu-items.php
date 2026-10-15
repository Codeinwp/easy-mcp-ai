<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Menu_Items extends Base_Tool {

    
    const LOAD_PER_PAGE = 100;

    




    const LOAD_MAX_PAGES = 100;

    public function get_name() {
        return 'wp_list_menu_items';
    }

    public function get_description() {
        return 'Lists all items in a WordPress navigation menu (published items; 100 per page by default). Each item includes: type (custom=custom URL, post_type=links to a post/page, taxonomy=links to a term, post_type_archive), type_label (the label wp-admin shows for that type, e.g. "Page", "Custom Link"), title (as displayed, after title filters, tags stripped), title_raw (the item\'s own title as stored, or for an item without one the linked post\'s or term\'s title, before the menu item\'s display filters and without tag stripping), url, object (post type or taxonomy slug, e.g. "post", "page", "category"), object_id (the ID of the linked post/term for post_type and taxonomy items), menu_order (1-based position), parent (parent item ID, 0 if top-level), target ("_blank" or "" for the same tab), attr_title (the link\'s title attribute), description, classes (array of CSS classes) and xfn (array of link relationship values).';
    }

    public function get_category() {
        return 'menus';
    }

    public function get_required_capability() {
        return 'edit_theme_options';
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
                'menu_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to retrieve items from.',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Number of items per page (1–100). Default: 100.',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number (1-based). Default: 1.',
                    'minimum'     => 1,
                    'default'     => 1,
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id  = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $per_page = isset( $arguments['per_page'] ) ? max( 1, min( 100, (int) $arguments['per_page'] ) ) : 100;
        $page     = isset( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;

        $request = new \WP_REST_Request( 'GET', '/wp/v2/menu-items' );
        $request->set_param( 'menus', $menu_id );
        $request->set_param( 'per_page', $per_page );
        $request->set_param( 'page', $page );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            return $this->empty_page_or_throw( $response->as_error(), $page, $per_page );
        }

        $items = $response->get_data();

        $result = array();
        foreach ( $items as $item ) {
            $result[] = self::item_row( $item );
        }

        return array_merge(
            array( 'items' => $result ),
            $this->pagination_meta( $response, $page, $per_page, count( $items ) )
        );
    }

    









    private function empty_page_or_throw( \WP_Error $error, int $page, int $per_page ): array {
        if ( $this->is_invalid_page_error( $error ) ) {
            return array_merge(
                array( 'items' => array() ),
                $this->pagination_meta( null, $page, $per_page, 0 )
            );
        }
        throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    








    private static function item_row( array $item ): array {
        return array(
            'id'          => $item['id'],
            'title'       => wp_strip_all_tags( $item['title']['rendered'] ),
            'url'         => $item['url'],
            'parent'      => $item['parent'],
            'menu_order'  => $item['menu_order'],
            'type'        => $item['type'],
            'object'      => $item['object'],
            'object_id'   => $item['object_id'],
            'type_label'  => self::text_field( $item, 'type_label' ),
            'title_raw'   => isset( $item['title']['raw'] ) ? (string) $item['title']['raw'] : '',
            'target'      => self::text_field( $item, 'target' ),
            'attr_title'  => self::text_field( $item, 'attr_title' ),
            'description' => self::text_field( $item, 'description' ),
            'classes'     => self::list_field( $item, 'classes' ),
            'xfn'         => self::list_field( $item, 'xfn' ),
        );
    }

    






    private static function text_field( array $item, string $field ): string {
        return isset( $item[ $field ] ) ? (string) $item[ $field ] : '';
    }

    







    private static function list_field( array $item, string $field ): array {
        return isset( $item[ $field ] ) && is_array( $item[ $field ] ) ? array_values( $item[ $field ] ) : array();
    }

    




























    public static function every_item( int $menu_id ): array {
        $items = array();
        $page  = 1;
        do {
            $response = self::items_page( $menu_id, $page );
            $batch    = (array) $response->get_data();
            foreach ( $batch as $item ) {
                if ( is_array( $item ) && isset( $item['id'] ) ) {
                    $items[ (int) $item['id'] ] = $item;
                }
            }
            $headers     = $response->get_headers();
            $total_pages = isset( $headers['X-WP-TotalPages'] ) ? (int) $headers['X-WP-TotalPages'] : null;
            $more        = null !== $total_pages ? $page < $total_pages : count( $batch ) >= self::LOAD_PER_PAGE;
            ++$page;
        } while ( $more && $page <= self::LOAD_MAX_PAGES );

        if ( $more ) {
            throw new \RuntimeException( sprintf( 'Menu %d has more than %d items; refusing to plan a change against a menu that could not be read in full.', $menu_id, self::LOAD_PER_PAGE * self::LOAD_MAX_PAGES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        uasort( $items, array( __CLASS__, 'by_menu_order_then_id' ) );
        return $items;
    }

    







    public static function by_menu_order_then_id( array $a, array $b ): int {
        $order = (int) $a['menu_order'] - (int) $b['menu_order'];
        return 0 !== $order ? $order : (int) $a['id'] - (int) $b['id'];
    }

    



















    public static function stored_items( int $menu_id ): array {
        $posts = get_posts(
            array(
                'post_type'              => 'nav_menu_item',
                'post_status'            => array( 'publish', 'draft' ),
                'posts_per_page'         => -1,
                'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- the menu is a nav_menu term; core reads its items the same way.
                    array(
                        'taxonomy'         => 'nav_menu',
                        'field'            => 'term_id',
                        'terms'            => $menu_id,
                        'include_children' => false,
                    ),
                ),
                'orderby'                => 'ID',
                'order'                  => 'ASC',
                'no_found_rows'          => true,
                'update_post_term_cache' => false,
                'suppress_filters'       => true,
            )
        );
        $items = array();
        foreach ( $posts as $post ) {
            $items[ (int) $post->ID ] = array(
                'menu_order' => (int) $post->menu_order,
                'parent'     => (int) get_post_meta( (int) $post->ID, '_menu_item_menu_item_parent', true ),
            );
        }
        return $items;
    }

    








    private static function items_page( int $menu_id, int $page ): \WP_REST_Response {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menu-items' );
        $request->set_param( 'menus', $menu_id );
        $request->set_param( 'per_page', self::LOAD_PER_PAGE );
        $request->set_param( 'page', $page );
        $request->set_param( 'status', array( 'publish', 'draft' ) );
        $request->set_param( 'orderby', 'id' );
        $request->set_param( 'order', 'asc' );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( 'rest_no_route' === $error->get_error_code() ) {
                
                throw new \RuntimeException( 'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.' );
            }
            throw new \RuntimeException( sprintf( 'The items of menu %d could not be read: %s', $menu_id, self::rest_error_message( $error ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $response;
    }

    














    public static function unsaveable( array $item ): bool {
        $type = isset( $item['type'] ) ? (string) $item['type'] : '';
        if ( 'custom' === $type ) {
            $title = '';
            if ( isset( $item['title'] ) ) {
                $title = is_array( $item['title'] ) ? (string) ( $item['title']['raw'] ?? '' ) : (string) $item['title'];
            }
            return '' === $title || empty( $item['url'] );
        }
        if ( 'post_type_archive' === $type ) {
            return ! post_type_exists( isset( $item['object'] ) ? (string) $item['object'] : '' );
        }
        return false;
    }
}
