<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Menu_Items extends Base_Tool {

    public function get_name() {
        return 'wp_list_menu_items';
    }

    public function get_description() {
        return 'Lists all items in a WordPress navigation menu. Each item includes: type (custom=custom URL, post_type=links to a post/page, taxonomy=links to a term), object (post type or taxonomy slug, e.g. "post", "page", "category"), object_id (the ID of the linked post/term for post_type and taxonomy items), menu_order (1-based position), and parent (parent item ID, 0 if top-level).';
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
            $error = $response->as_error();
            if ( $this->is_invalid_page_error( $error ) ) {
                return array_merge(
                    array( 'items' => array() ),
                    $this->pagination_meta( null, $page, $per_page, 0 )
                );
            }
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $items = $response->get_data();

        $result = array();
        foreach ( $items as $item ) {
            $result[] = array(
                'id'         => $item['id'],
                'title'      => wp_strip_all_tags( $item['title']['rendered'] ),
                'url'        => $item['url'],
                'parent'     => $item['parent'],
                'menu_order' => $item['menu_order'],
                'type'       => $item['type'],
                'object'     => $item['object'],
                'object_id'  => $item['object_id'],
            );
        }

        return array_merge(
            array( 'items' => $result ),
            $this->pagination_meta( $response, $page, $per_page, count( $items ) )
        );
    }
}
