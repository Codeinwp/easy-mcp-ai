<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Menu extends Base_Tool {

    public function get_name() {
        return 'wp_delete_menu';
    }

    public function get_description() {
        return 'Permanently deletes a WordPress navigation menu and ALL items inside it. Required: `menu_id`. There is no trash for menus — this is irreversible. Theme locations previously assigned to this menu become unassigned (the location remains, just empty). Use `wp_list_menus` to find the menu_id first if you only have the name. Requires WordPress 5.9+.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'menu_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to delete.',
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $request = new \WP_REST_Request( 'DELETE', '/wp/v2/menus/' . $menu_id );
        $request->set_param( 'force', true );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error = $response->as_error();
            if ( 'rest_no_route' === $wp_error->get_error_code() ) {
                throw new \RuntimeException(
                    'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.'
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return array(
            'deleted' => true,
            'id'      => $menu_id,
        );
    }
}
