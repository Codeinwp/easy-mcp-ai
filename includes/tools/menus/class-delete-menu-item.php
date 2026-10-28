<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Menu_Item extends Base_Tool {

    use Menu_Item_Refusal;

    const IDS_IN_MESSAGE = 8;

    public function get_name() {
        return 'wp_delete_menu_item';
    }

    public function get_description() {
        return 'Deletes a single menu item from a navigation menu. Required: `item_id` (the menu_item id, NOT the linked post/term id — get it from `wp_list_menu_items`). Deletion is always permanent — WordPress has no trash for menu items, so this is irreversible. By default the item\'s children keep pointing at the deleted id as their parent (`reparent_children` false): themes render them as top-level items, and `wp_list_menu_items` still reports the deleted id as their `parent`. With `reparent_children: true` each child is moved to the deleted item\'s own parent (0 = top level), as the Menus screen and WP-CLI do; the children are checked before the delete, and the call is refused with nothing deleted if one of them cannot be saved or you may not edit it. Returns { deleted, id }, plus `reparented` (child ids moved) when reparent_children was used. Does NOT delete the linked post or term — only the menu entry.';
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
                'item_id'           => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu item to delete.',
                ),
                'reparent_children' => array(
                    'type'        => 'boolean',
                    'description' => 'Move the item\'s children to the deleted item\'s own parent (0 = top level) after deleting it, so none is left pointing at a deleted id. Default false.',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'item_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'item_id' ) );

        $item_id  = $this->parse_required_id( $arguments['item_id'], 'item_id' );
        $reparent = isset( $arguments['reparent_children'] ) && rest_sanitize_boolean( $arguments['reparent_children'] );

        $plan = $reparent ? $this->plan_reparent( $item_id ) : null;

        $request = new \WP_REST_Request( 'DELETE', '/wp/v2/menu-items/' . $item_id );
        $request->set_param( 'force', true );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            self::throw_rest_error( $response );
        }

        $result = array(
            'deleted' => true,
            'id'      => $item_id,
        );
        if ( null === $plan ) {
            return $result;
        }

        $moved = array();
        foreach ( $plan['children'] as $index => $child ) {
            try {
                $this->rest_request( 'POST', '/wp/v2/menu-items/' . $child, array( 'parent' => $plan['parent'] ), array( 'id' ) );
                $moved[] = $child;
            } catch ( \RuntimeException $e ) {
                return array_merge(
                    array(
                        'error' => sprintf(
                            'Item %1$d was deleted, but moving its children to parent %2$d stopped at child %3$d. Move the rest with wp_update_menu_item (parent %2$d).',
                            $item_id,
                            $plan['parent'],
                            $child
                        ),
                    ),
                    $result,
                    array(
                        'reparented'     => $moved,
                        'failed'         => array(
                            'id'    => $child,
                            'error' => $e->getMessage(),
                        ),
                        'not_reparented' => array_slice( $plan['children'], $index + 1 ),
                    )
                );
            }
        }
        $result['reparented'] = $moved;
        return $result;
    }

    private function plan_reparent( int $item_id ): array {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menu-items/' . $item_id );
        $request->set_param( 'context', 'edit' );
        $request->set_param( '_fields', 'id,parent,menus' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            self::throw_rest_error( $response );
        }
        $item    = (array) $response->get_data();
        $parent  = isset( $item['parent'] ) ? (int) $item['parent'] : 0;
        $menu_id = isset( $item['menus'] ) ? (int) $item['menus'] : 0;

        if ( $menu_id < 1 ) {
            return array(
                'parent'   => $parent,
                'children' => array(),
            );
        }

        list( $children, $denied, $unread, $unsaveable ) = self::children_of( $menu_id, $item_id );
        if ( $denied ) {
            $this->refuse( 'you may not edit child item(s) %s, which must move.', $denied, 'To delete the item anyway, send reparent_children: false; its children then keep pointing at the deleted id.' );
        }
        if ( $unread ) {
            $this->refuse( 'REST did not return child item(s) %s that must move.', $unread, 'WordPress\'s menu-items collection left them out although you may edit them, so they could not be checked before the write; a plugin may filter that collection (rest_nav_menu_item_query). Delete without reparent_children, or move those items first.' );
        }
        if ( $unsaveable ) {
            $this->refuse(
                'child item(s) %s must move, but WordPress will not re-save them as stored.',
                $unsaveable,
                'WordPress refuses to re-save a custom link with an empty title or URL, or an archive link to a post type that is no longer registered. Fix those items with wp_update_menu_item first, or delete without reparent_children.'
            );
        }
        return array(
            'parent'   => $parent,
            'children' => $children,
        );
    }

    private static function children_of( int $menu_id, int $item_id ): array {
        $found = array();
        foreach ( List_Menu_Items::stored_items( $menu_id ) as $id => $stored ) {
            if ( $id !== $item_id && $stored['parent'] === $item_id ) {
                $found[] = array(
                    'id'         => $id,
                    'menu_order' => $stored['menu_order'],
                );
            }
        }
        if ( ! $found ) {
            return array( array(), array(), array(), array() );
        }
        usort( $found, array( List_Menu_Items::class, 'by_menu_order_then_id' ) );

        $items      = List_Menu_Items::every_item( $menu_id );
        $children   = array();
        $denied     = array();
        $unread     = array();
        $unsaveable = array();
        foreach ( $found as $child ) {
            $id = $child['id'];
            if ( ! current_user_can( 'edit_post', $id ) ) {
                $denied[] = $id;
            } elseif ( ! isset( $items[ $id ] ) ) {
                $unread[] = $id;
            } elseif ( List_Menu_Items::unsaveable( $items[ $id ] ) ) {
                $unsaveable[] = $id;
            }
            $children[] = $id;
        }
        return array( $children, $denied, $unread, $unsaveable );
    }

    private static function throw_rest_error( \WP_REST_Response $response ): void {
        $wp_error = $response->as_error();
        if ( 'rest_no_route' === $wp_error->get_error_code() ) {
            throw new \RuntimeException(
                'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.'
            );
        }
        throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
