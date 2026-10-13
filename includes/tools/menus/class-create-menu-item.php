<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Menu_Item extends Base_Tool {

    public function get_name() {
        return 'wp_create_menu_item';
    }

    public function get_description() {
        return 'Creates a new item in a WordPress navigation menu. Required: `menu_id` (the parent menu\'s ID), `title`. Optional: `url` (for custom URL links), `object_type` (post_type/taxonomy/custom), `object` (post type or taxonomy slug, e.g. "post"/"page"/"category"), `object_id` (ID of the linked post or term), `parent` (parent menu_item ID, 0 = top-level — use to nest into submenus), `position` (1-based menu order; auto-appended if omitted). Returns { id, title, url } (a minimal object — use `wp_list_menu_items` or `wp_update_menu_item` for the full item shape). Use `wp_list_menu_items` to find existing item IDs and object_ids.';
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
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'menu_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to add the item to.',
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'The title for the menu item.',
                ),
                'url'         => array(
                    'type'        => 'string',
                    'description' => 'The URL for the menu item.',
                ),
                'object_type' => array(
                    'type'        => 'string',
                    'description' => 'The type of object for the menu item.',
                    'enum'        => array( 'custom', 'post_type', 'taxonomy', 'post_type_archive' ),
                ),
                'object'      => array(
                    'type'        => 'string',
                    'description' => 'The slug of the post type or taxonomy the menu item links to (e.g. "post", "page", "category"). Required when object_type is post_type or taxonomy.',
                ),
                'object_id'   => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the object the menu item refers to.',
                ),
                'parent'      => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent menu item.',
                ),
                'position'    => array(
                    'type'        => 'integer',
                    'description' => 'The position (menu order) of the menu item.',
                ),
            ),
            'required'   => array( 'menu_id', 'title' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id', 'title' ) );

        $this->validate_title_length( $arguments['title'] );

        $params = array(
            'menus'  => $this->parse_required_id( $arguments['menu_id'], 'menu_id' ),
            'title'  => sanitize_text_field( $arguments['title'] ),
            'type'   => 'custom',
            'status' => 'publish',
        );

        if ( isset( $arguments['url'] ) ) {
            $params['url'] = esc_url_raw( $arguments['url'] );
        }

        if ( ! empty( $arguments['object_type'] ) ) {
            $params['type'] = $arguments['object_type'];
        }

        if ( isset( $arguments['object'] ) ) {
            $params['object'] = sanitize_key( $arguments['object'] );
        }

        if ( isset( $arguments['object_id'] ) ) {
            $params['object_id'] = absint( $arguments['object_id'] );
        }

        if ( isset( $arguments['parent'] ) ) {
            $params['parent'] = absint( $arguments['parent'] );
        }

        if ( isset( $arguments['position'] ) ) {
            $params['menu_order'] = absint( $arguments['position'] );
        }

        $data = $this->rest_request( 'POST', '/wp/v2/menu-items', $params );

        return array(
            'id'    => $data['id'],
            'title' => wp_strip_all_tags( $data['title']['rendered'] ),
            'url'   => $data['url'],
        );
    }
}
