<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Menu extends Base_Tool {

    public function get_name() {
        return 'wp_get_menu';
    }

    public function get_description() {
        return 'Gets a single WordPress navigation menu by ID. Returns { id, name, slug, description }. Does not include menu items — call `wp_list_menu_items` with the same id for the items themselves. To find a menu by name/slug first, use `wp_list_menus`. Requires WordPress 5.9+.';
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
                'menu_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to retrieve.',
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $data    = $this->rest_request( 'GET', '/wp/v2/menus/' . $menu_id, array( 'context' => 'edit' ) );

        return array(
            'id'          => $data['id'],
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'],
        );
    }
}
