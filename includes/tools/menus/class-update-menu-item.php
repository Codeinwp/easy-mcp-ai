<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Menu_Item extends Base_Tool {

    const UPDATE_FIELDS = array(
        'title'       => array( 'title', 'text' ),
        'url'         => array( 'url', 'url' ),
        'position'    => array( 'menu_order', 'int' ),
        'parent'      => array( 'parent', 'int' ),
        'menu_id'     => array( 'menus', 'int' ),
        'target'      => array( 'target', 'target' ),
        'classes'     => array( 'classes', 'classes' ),
        'description' => array( 'description', 'text' ),
        'attr_title'  => array( 'attr_title', 'text' ),
        'object_type' => array( 'type', 'text' ),
        'object'      => array( 'object', 'text' ),
        'object_id'   => array( 'object_id', 'int' ),
    );

    const RESULT_DEFAULTS = array(
        'url'         => '',
        'status'      => '',
        'parent'      => 0,
        'menu_order'  => 0,
        'target'      => '',
        'classes'     => array(),
        'description' => '',
        'type'        => '',
        'object'      => '',
        'object_id'   => 0,
        'menus'       => null,
        'attr_title'  => '',
    );

    public function get_name() {
        return 'wp_update_menu_item';
    }

    public function get_description() {
        return 'Updates a menu item; only the fields you send change. Required: `item_id`, the menu item\'s own ID from `wp_list_menu_items` (not the linked post or term ID). `position` sets only this item\'s menu_order and moves no other item, so it can share a place with one; to move items, use `wp_reorder_menu_items`. To re-link the item, send `object_type`, `object` and `object_id` together. Returns the saved item { id, title, url, status, parent, menu_order, target, classes, description, type, object, object_id, menus, attr_title } and `changed`: false when nothing differed (WordPress still re-saves it, so its modified date moves). A same-tab `target` reads back as "", not "_self".';
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
                    'description' => 'New URL of a custom link. Other item types link to their object\'s own URL, so a URL sent for one is not shown.',
                ),
                'position' => array(
                    'type'        => 'integer',
                    'description' => 'New position (menu order) for this item only; siblings are ordered by menu order ascending. No other item moves, so it can share the position with the item already there; to move an item among the others, use wp_reorder_menu_items.',
                ),
                'parent'   => array(
                    'type'        => 'integer',
                    'description' => 'New parent menu item ID; 0 for the top level.',
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
                'attr_title'  => array(
                    'type'        => 'string',
                    'description' => 'New title attribute of the link (the tooltip some browsers show on hover). "" clears it.',
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

        $before = $this->read_item( $item_id );

        $request = new \WP_REST_Request( 'POST', '/wp/v2/menu-items/' . $item_id );
        foreach ( self::UPDATE_FIELDS as $argument => list( $param, $kind ) ) {
            if ( isset( $arguments[ $argument ] ) ) {
                $request->set_param( $param, self::param_value( $kind, $arguments[ $argument ] ) );
            }
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            self::throw_rest_error( $response );
        }

        $data = $response->get_data();
        return self::item_result( is_array( $data ) ? $data : array(), $before );
    }

    private static function item_result( array $data, array $before ): array {
        $result = array(
            'id'    => $data['id'],
            'title' => $data['title']['raw'] ?? wp_strip_all_tags( $data['title']['rendered'] ?? '' ),
        );
        foreach ( self::RESULT_DEFAULTS as $field => $default ) {
            $result[ $field ] = $data[ $field ] ?? $default;
        }
        $result['changed'] = self::comparable( $before ) !== self::comparable( $data );
        return $result;
    }

    private static function param_value( string $kind, $value ) {
        switch ( $kind ) {
            case 'url':
                return esc_url_raw( $value );
            case 'int':
                return absint( $value );
            case 'target':
                $target = sanitize_text_field( $value );
                return '_self' === $target ? '' : $target;
            case 'classes':
                return array_map( 'sanitize_html_class', is_array( $value ) ? $value : array( $value ) );
            default:
                return sanitize_text_field( $value );
        }
    }

    private function read_item( int $item_id ): array {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menu-items/' . $item_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            self::throw_rest_error( $response );
        }
        $data = $response->get_data();
        return is_array( $data ) ? $data : array();
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

    private static function comparable( array $data ): array {
        $title = $data['title'] ?? '';
        if ( is_array( $title ) ) {
            $title = $title['raw'] ?? ( $title['rendered'] ?? '' );
        }
        $out = array( 'title' => (string) $title );
        foreach ( array( 'url', 'status', 'parent', 'menu_order', 'target', 'classes', 'description', 'attr_title', 'type', 'object', 'object_id', 'menus', 'xfn' ) as $key ) {
            $out[ $key ] = $data[ $key ] ?? null;
        }
        return $out;
    }
}
