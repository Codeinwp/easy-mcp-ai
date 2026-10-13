<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Menu_Item extends Base_Tool {

    public function get_name() {
        return 'wp_update_menu_item';
    }

    public function get_description() {
        return 'Updates an existing menu item. Required: `item_id` (the menu_item id — get it from `wp_list_menu_items`; NOT the linked post/term id). Only fields you provide are changed. Common updates: `title`, `parent` (move under a different parent — 0 = top-level), `position` (re-position; sibling order is by menu_order ascending), `url` (for custom URL items), `target` ("_blank" / "_self"), `classes` (array of CSS class strings), `description`, `menu_id` (move to a different menu). To re-link an item to a different post/term, set `object_type` (post_type/taxonomy/post_type_archive/custom) + `object` (e.g. page/category) + `object_id` together. Returns { id, title, url, status, parent, menu_order, target, classes, description, type, object, object_id, menus } — note the returned `target` (and any later read via `wp_list_menu_items`) reports "" rather than "_self" for same-tab items, since that is WordPress core\'s own internal representation.';
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
                'item_id'  => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu item to update.',
                ),
                'title'    => array(
                    'type'        => 'string',
                    'description' => 'New title for the menu item.',
                ),
                'url'      => array(
                    'type'        => 'string',
                    'description' => 'New URL for the menu item.',
                ),
                'position' => array(
                    'type'        => 'integer',
                    'description' => 'New position (menu order) for the item.',
                ),
                'parent'   => array(
                    'type'        => 'integer',
                    'description' => 'New parent menu item ID.',
                ),
                'menu_id'  => array(
                    'type'        => 'integer',
                    'description' => 'Move the item to a different menu.',
                ),
                'target'   => array(
                    'type'        => 'string',
                    'description' => 'Link target: "_blank" to open in a new tab, or "_self" for the same tab.',
                    'enum'        => array( '_blank', '_self' ),
                ),
                'classes'  => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'CSS class names to apply to the menu item (array of strings).',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Description text for the menu item (shown by some themes).',
                ),
                'object_type' => array(
                    'type'        => 'string',
                    'description' => 'When re-linking the item, the family of object it represents: post_type, taxonomy, post_type_archive, or custom. Set together with `object` and `object_id`.',
                ),
                'object'   => array(
                    'type'        => 'string',
                    'description' => 'When re-linking, the specific object type (e.g. page, post, category). Set together with `object_type` and `object_id`.',
                ),
                'object_id' => array(
                    'type'        => 'integer',
                    'description' => 'When re-linking, the database ID of the object this item points to. Set together with `object_type` and `object`.',
                ),
            ),
            'required'   => array( 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'item_id' ) );
        $this->validate_title_length( isset( $arguments['title'] ) ? $arguments['title'] : null );

        $item_id = $this->parse_required_id( $arguments['item_id'], 'item_id' );
        $request = new \WP_REST_Request( 'POST', '/wp/v2/menu-items/' . $item_id );

        if ( isset( $arguments['title'] ) ) {
            $request->set_param( 'title', sanitize_text_field( $arguments['title'] ) );
        }
        if ( isset( $arguments['url'] ) ) {
            $request->set_param( 'url', esc_url_raw( $arguments['url'] ) );
        }
        if ( isset( $arguments['position'] ) ) {
            $request->set_param( 'menu_order', absint( $arguments['position'] ) );
        }
        if ( isset( $arguments['parent'] ) ) {
            $request->set_param( 'parent', absint( $arguments['parent'] ) );
        }
        if ( isset( $arguments['menu_id'] ) ) {
            $request->set_param( 'menus', absint( $arguments['menu_id'] ) );
        }
        if ( isset( $arguments['target'] ) ) {
            $target = sanitize_text_field( $arguments['target'] );
            
            
            
            if ( '_self' === $target ) {
                $target = '';
            }
            $request->set_param( 'target', $target );
        }
        if ( isset( $arguments['classes'] ) ) {
            $classes = is_array( $arguments['classes'] ) ? $arguments['classes'] : array( $arguments['classes'] );
            $request->set_param( 'classes', array_map( 'sanitize_html_class', $classes ) );
        }
        if ( isset( $arguments['description'] ) ) {
            $request->set_param( 'description', sanitize_text_field( $arguments['description'] ) );
        }
        if ( isset( $arguments['object_type'] ) ) {
            $request->set_param( 'type', sanitize_text_field( $arguments['object_type'] ) );
        }
        if ( isset( $arguments['object'] ) ) {
            $request->set_param( 'object', sanitize_text_field( $arguments['object'] ) );
        }
        if ( isset( $arguments['object_id'] ) ) {
            $request->set_param( 'object_id', absint( $arguments['object_id'] ) );
        }

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

        $data = $response->get_data();

        return array(
            'id'          => $data['id'],
            'title'       => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
            'url'         => $data['url'] ?? '',
            'status'      => $data['status'] ?? '',
            'parent'      => $data['parent'] ?? 0,
            'menu_order'  => $data['menu_order'] ?? 0,
            'target'      => $data['target'] ?? '',
            'classes'     => $data['classes'] ?? array(),
            'description' => $data['description'] ?? '',
            'type'        => $data['type'] ?? '',
            'object'      => $data['object'] ?? '',
            'object_id'   => $data['object_id'] ?? 0,
            'menus'       => $data['menus'] ?? null,
        );
    }
}
