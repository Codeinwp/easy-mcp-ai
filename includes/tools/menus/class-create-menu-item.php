<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Menu_Item extends Base_Tool {

    use Menu_Item_Refusal;

    
    const IDS_IN_MESSAGE = 8;

    public function get_name() {
        return 'wp_create_menu_item';
    }

    public function get_description() {
        return 'Adds an item to a navigation menu. Required: `menu_id`. `title` is required for a custom link (the default `object_type`); a post_type, taxonomy or post_type_archive item may omit it and then shows the linked object\'s own title, following renames. Without `position` (or with 0 or null) the item goes at the end of the menu; with `position` it takes that place and the later items move down one (`shift_following: false` sets only its own order). Nest it with `parent`. Returns { id, title, url, parent, menu_order }, plus `shifted` (the ids moved down) when a `position` was given and `shift_following` is on. Use `wp_list_menu_items` to find item IDs, positions and object_ids.';
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
                'menu_id'         => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to add the item to.',
                ),
                'title'           => array(
                    'type'        => 'string',
                    'description' => 'The title for the menu item. Required for a custom link; optional for post_type, taxonomy and post_type_archive items, which otherwise show the linked object\'s title.',
                ),
                'url'             => array(
                    'type'        => 'string',
                    'description' => 'The URL of a custom link. Other item types link to their object\'s own URL, so a URL sent for one is not shown.',
                ),
                'object_type'     => array(
                    'type'        => 'string',
                    'description' => 'The type of object for the menu item. Default: custom.',
                    'enum'        => array( 'custom', 'post_type', 'taxonomy', 'post_type_archive' ),
                ),
                'object'          => array(
                    'type'        => 'string',
                    'description' => 'The slug of the post type or taxonomy the menu item links to (e.g. "post", "page", "category"). Required when object_type is post_type or taxonomy.',
                ),
                'object_id'       => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the linked post or term (post_type and taxonomy items).',
                ),
                'parent'          => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the parent menu item; 0 or omitted for the top level.',
                ),
                'position'        => array(
                    'type'        => 'integer',
                    'description' => '1-based menu_order as wp_list_menu_items shows it. The new item takes this place and every item at or after it moves down one. Omit it, or send 0 or null, to add the item at the end. A position after the last item is stored as sent.',
                ),
                'shift_following' => array(
                    'type'        => 'boolean',
                    'description' => 'Default true: with `position`, every item at or after it moves down one, so the new item has the place alone. false sets only the new item\'s order, so it can share the place. No effect without `position`.',
                    'default'     => true,
                ),
                'description'     => array(
                    'type'        => 'string',
                    'description' => 'Description text for the menu item (shown by some themes).',
                ),
                'attr_title'      => array(
                    'type'        => 'string',
                    'description' => 'The title attribute of the link (the tooltip some browsers show on hover).',
                ),
                'target'          => array(
                    'type'        => 'string',
                    'description' => 'Link target: "_blank" to open in a new tab, or "_self" for the same tab (the default).',
                    'enum'        => array( '_blank', '_self' ),
                ),
                'classes'         => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'CSS class names to apply to the menu item (array of strings).',
                ),
            ),
            'required'   => array( 'menu_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );

        $menu_id   = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $has_title = isset( $arguments['title'] ) && '' !== $arguments['title'];
        if ( $has_title ) {
            $this->validate_title_length( $arguments['title'] );
        }

        $params   = array_merge( self::link_params( $arguments, $menu_id, $has_title ), $this->display_params( $arguments ) );
        $position = self::position_argument( $arguments );
        $shift    = 0 < $position && self::shift_requested( $arguments );

        
        
        $this->assert_menu_exists( $menu_id );

        if ( 'custom' === $params['type'] && ! $has_title ) {
            throw new \InvalidArgumentException( 'Missing required parameters: title (a custom link needs a title; it is optional only for post_type, taxonomy and post_type_archive items, which show the linked object\'s title).' );
        }

        
        $orders               = ( 0 === $position || $shift ) ? self::stored_orders( $menu_id ) : array();
        $params['menu_order'] = 0 === $position ? self::end_position( $orders ) : $position;
        $to_shift             = $shift ? $this->items_to_shift( $menu_id, $orders, $position ) : array();

        
        
        
        $data = $this->rest_request( 'POST', '/wp/v2/menu-items', $params );

        $result = array(
            'id'         => $data['id'],
            'title'      => wp_strip_all_tags( $data['title']['rendered'] ),
            'url'        => $data['url'],
            'parent'     => (int) $data['parent'],
            'menu_order' => (int) $data['menu_order'],
        );
        if ( ! $shift ) {
            return $result;
        }
        return $this->shift_items( $result, $to_shift, $position );
    }

    









    private static function link_params( array $arguments, int $menu_id, bool $has_title ): array {
        $params = array(
            'menus'  => $menu_id,
            'type'   => 'custom',
            'status' => 'publish',
        );
        if ( $has_title ) {
            $params['title'] = sanitize_text_field( $arguments['title'] );
        }
        if ( isset( $arguments['url'] ) ) {
            $params['url'] = esc_url_raw( $arguments['url'] );
        }
        if ( ! empty( $arguments['object_type'] ) ) {
            $params['type'] = $arguments['object_type'];
        }
        if ( isset( $arguments['object'] ) ) {
            $params['object'] = sanitize_key( $arguments['object'] );
        }
        foreach ( array( 'object_id', 'parent' ) as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = absint( $arguments[ $field ] );
            }
        }
        return $params;
    }

    







    private function display_params( array $arguments ): array {
        $params = array();
        foreach ( array( 'description', 'attr_title' ) as $field ) {
            if ( isset( $arguments[ $field ] ) ) {
                $params[ $field ] = sanitize_text_field( $arguments[ $field ] );
            }
        }
        if ( isset( $arguments['target'] ) ) {
            
            
            
            
            $this->refuse_unless_one_of( 'target', $arguments['target'], array( '_blank', '_self', '' ), $arguments['target'], '"_blank" (new tab) or "_self" (same tab)' );
            $params['target'] = '_blank' === $arguments['target'] ? '_blank' : '';
        }
        if ( isset( $arguments['classes'] ) ) {
            $params['classes'] = $this->classes_argument( $arguments['classes'] );
        }
        return $params;
    }

    











    private static function position_argument( array $arguments ): int {
        if ( ! isset( $arguments['position'] ) ) {
            return 0;
        }
        $value = $arguments['position'];
        if ( is_int( $value ) && $value >= 0 ) {
            return $value;
        }
        if ( is_string( $value ) && ctype_digit( $value ) ) {
            return (int) $value;
        }
        
        if ( is_float( $value ) && $value >= 0 && $value < PHP_INT_MAX && floor( $value ) === $value ) {
            return (int) $value;
        }
        $shown = wp_json_encode( $value );
        throw new \InvalidArgumentException( sprintf( 'Invalid position: must be 0 or a positive integer (0 or omitted adds the item at the end), got: %s', false === $shown ? gettype( $value ) : $shown ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    






    private static function shift_requested( array $arguments ): bool {
        return ! isset( $arguments['shift_following'] ) || rest_sanitize_boolean( $arguments['shift_following'] );
    }

    














    private static function end_position( array $orders ): int {
        
        
        return max( 1, ( $orders ? max( $orders ) : 0 ) + 1 );
    }

    







    private static function stored_orders( int $menu_id ): array {
        return array_map(
            static function ( array $item ): int {
                return $item['menu_order'];
            },
            List_Menu_Items::stored_items( $menu_id )
        );
    }

    








    private function shift_items( array $result, array $to_shift, int $position ): array {
        $shifted = array();
        foreach ( $to_shift as $index => $row ) {
            try {
                $this->rest_request( 'POST', '/wp/v2/menu-items/' . $row['id'], array( 'menu_order' => $row['menu_order'] + 1 ), array( 'id' ) );
                $shifted[] = $row['id'];
            } catch ( \RuntimeException $e ) {
                return $this->partial_shift_report( $result, $shifted, $row['id'], $e->getMessage(), array_slice( $to_shift, $index + 1 ), $position );
            }
        }
        $result['shifted'] = $shifted;
        return $result;
    }

    










    private function classes_argument( $value ): array {
        if ( is_string( $value ) ) {
            $value = preg_split( '/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( '`classes` must be an array of CSS class names.' );
        }
        $classes = array();
        foreach ( $value as $class ) {
            if ( ! is_scalar( $class ) ) {
                throw new \InvalidArgumentException( '`classes` must be an array of CSS class names (strings).' );
            }
            $classes[] = sanitize_html_class( (string) $class );
        }
        return $classes;
    }

    






    private function assert_menu_exists( int $menu_id ): void {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menus/' . $menu_id );
        $request->set_param( 'context', 'edit' );
        $request->set_param( '_fields', 'id' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $error = $response->as_error();
            if ( 'rest_no_route' === $error->get_error_code() ) {
                throw new \RuntimeException( 'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.' );
            }
            throw new \RuntimeException( sprintf( 'Menu %d could not be loaded: %s', $menu_id, self::rest_error_message( $error ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    




















    private function items_to_shift( int $menu_id, array $orders, int $position ): array {
        $moving = array_filter(
            $orders,
            static function ( int $order ) use ( $position ): bool {
                return $order >= $position;
            }
        );
        if ( ! $moving ) {
            return array();
        }
        $items      = List_Menu_Items::every_item( $menu_id );
        $rows       = array();
        $denied     = array();
        $unread     = array();
        $unsaveable = array();
        foreach ( $moving as $id => $order ) {
            if ( ! current_user_can( 'edit_post', $id ) ) {
                $denied[] = $id;
            } elseif ( ! isset( $items[ $id ] ) ) {
                $unread[] = $id;
            } elseif ( List_Menu_Items::unsaveable( $items[ $id ] ) ) {
                $unsaveable[] = $id;
            }
            $rows[] = array(
                'id'         => $id,
                'menu_order' => $order,
            );
        }
        if ( $denied ) {
            $this->refuse(
                'you may not edit item(s) %s, which must move down to make room.',
                $denied,
                'To add the item anyway, omit position (it goes at the end) or send shift_following: false (it shares the position with the item already there).'
            );
        }
        if ( $unread ) {
            $this->refuse(
                'REST did not return item(s) %s, which must move down to make room.',
                $unread,
                'WordPress\'s menu-items collection left them out although you may edit them, so they could not be checked before the write; a plugin may filter that collection (rest_nav_menu_item_query). Omit position to add the item at the end, or send shift_following: false.'
            );
        }
        if ( $unsaveable ) {
            $this->refuse(
                'item(s) %s must move down, but WordPress will not re-save them as stored.',
                $unsaveable,
                'WordPress refuses to re-save a custom link with an empty title or URL, or an archive link to a post type that is no longer registered. Fix those items with wp_update_menu_item first, omit position to add the item at the end, or send shift_following: false to set only its own order.'
            );
        }
        usort(
            $rows,
            static function ( array $a, array $b ): int {
                $order = $b['menu_order'] - $a['menu_order'];
                return 0 !== $order ? $order : $b['id'] - $a['id'];
            }
        );
        return $rows;
    }

    












    private function partial_shift_report( array $result, array $shifted, int $failed_id, string $error, array $remaining, int $position ): array {
        return array_merge(
            array(
                'error' => sprintf(
                    'Item %1$d was created at position %2$d, but moving the later items stopped at item %3$d, so some share position %2$d. Do not retry the create; fix the order with wp_reorder_menu_items.',
                    (int) $result['id'],
                    $position,
                    $failed_id
                ),
            ),
            $result,
            array(
                'shifted'     => $shifted,
                'failed'      => array(
                    'id'    => $failed_id,
                    'error' => $error,
                ),
                'not_shifted' => array_map(
                    static function ( array $row ): int {
                        return $row['id'];
                    },
                    $remaining
                ),
            )
        );
    }
}
