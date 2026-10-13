<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Menu_Item extends Base_Tool {

    public function get_name() {
        return 'wp_delete_menu_item';
    }

    public function get_description() {
        return 'Deletes a single menu item from a navigation menu. Required: `item_id` (the menu_item id, NOT the linked post/term id — get it from `wp_list_menu_items`). Deletion is always permanent — WordPress has no trash for menu items, so this is irreversible. Deleting a parent item leaves its children orphaned at top-level (`parent` becomes 0); re-parent them first if you want them moved. Does NOT delete the linked post or term — only the menu entry.';
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
                'item_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu item to delete.',
                ),
            ),
            'required'   => array( 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'item_id' ) );

        $item_id = $this->parse_required_id( $arguments['item_id'], 'item_id' );
        $request = new \WP_REST_Request( 'DELETE', '/wp/v2/menu-items/' . $item_id );
        
        
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
            'id'      => $item_id,
        );
    }
}
