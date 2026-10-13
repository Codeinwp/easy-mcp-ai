<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Menus extends Base_Tool {

    public function get_name() {
        return 'wp_list_menus';
    }

    public function get_description() {
        return 'Lists all registered WordPress navigation menus (`nav_menu` taxonomy terms). Returns { menus: [{ id, name, slug, description }], total, total_pages, page, per_page }. Does not return the items themselves — use `wp_list_menu_items` for that. No input parameters (up to 100 menus are returned in a single page). Requires WordPress 5.9+.';
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
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menus' );
        $request->set_param( 'per_page', 100 );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $menus = $response->get_data();

        $result = array();
        foreach ( $menus as $menu ) {
            $result[] = array(
                'id'          => $menu['id'],
                'name'        => $menu['name'],
                'slug'        => $menu['slug'],
                'description' => $menu['description'],
            );
        }

        
        return array_merge(
            array( 'menus' => $result ),
            $this->pagination_meta( $response, 1, 100, count( $menus ) )
        );
    }
}
