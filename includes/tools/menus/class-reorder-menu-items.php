<?php
namespace Easy_MCP_AI\Tools\Menus;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Reorder_Menu_Items extends Base_Tool {

    
    const PER_PAGE = 100;

    




    const MAX_PAGES = 100;

    
    const IDS_IN_MESSAGE = 8;

    public function get_name() {
        return 'wp_reorder_menu_items';
    }

    public function get_description() {
        return 'Reorders a navigation menu and sets its nesting in one call. Required: `menu_id`, and `items` — an array of { id, parent } objects in the order you want them, where `id` is a menu_item id from `wp_list_menu_items` (NOT the linked post/term id) and `parent` is another item of the same menu, or 0 for top level. Items that share a parent appear in the order they are listed. `strict` (default true) requires every item of the menu to be listed exactly once; with `strict: false`, unlisted items keep their current parent and follow the listed items under it, in their current relative order. The whole request is checked before anything is written and refused with the offending ids if any item or parent belongs to another menu, an id repeats, an item is its own parent, or the result would contain a cycle (an item nested under its own descendant) — nothing is changed in that case. Positions are stored the way the wp-admin menu editor stores them: `menu_order` is each item\'s 1-based place in the whole menu read top to bottom, children directly after their parent. Only items whose parent or position actually changes are saved. Returns { menu_id, changed, items } where items has the `wp_list_menu_items` fields plus `status`. If a save fails part-way the call stops and returns { error, applied, failed: { id, error }, not_applied } — calling again with the same arguments finishes the job, since items already in place are skipped. Requires edit_theme_options, the capability the Menus screen requires; pending (draft) items that wp-admin added but has not saved yet are part of the menu too. To move an item to another menu, use `wp_update_menu_item` with `menu_id`.';
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
                'menu_id' => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the menu to reorder (from `wp_list_menus`).',
                ),
                'items'   => array(
                    'type'        => 'array',
                    'description' => 'The menu items in the desired order. Each entry is { "id": menu item id, "parent": parent menu item id, or 0 for top level }. Siblings (items with the same parent) are ordered by their position in this array.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'id'     => array(
                                'type'        => 'integer',
                                'description' => 'The menu item id (from `wp_list_menu_items`), not the linked post or term id.',
                                'minimum'     => 1,
                            ),
                            'parent' => array(
                                'type'        => 'integer',
                                'description' => 'The id of the menu item this one nests under, or 0 for top level. Must be an item of the same menu.',
                                'minimum'     => 0,
                            ),
                        ),
                        'required'   => array( 'id', 'parent' ),
                    ),
                ),
                'strict'  => array(
                    'type'        => 'boolean',
                    'description' => 'When true (default), every item currently in the menu must be listed exactly once or the call is refused. When false, unlisted items keep their current parent and are placed after the listed items that share it.',
                    'default'     => true,
                ),
            ),
            'required'   => array( 'menu_id', 'items' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'menu_id' ) );
        if ( ! array_key_exists( 'items', $arguments ) || null === $arguments['items'] ) {
            throw new \InvalidArgumentException( 'Missing required parameters: items' );
        }

        $menu_id = $this->parse_required_id( $arguments['menu_id'], 'menu_id' );
        $strict  = ( ! array_key_exists( 'strict', $arguments ) || null === $arguments['strict'] )
            ? true
            : rest_sanitize_boolean( $arguments['strict'] );
        $entries = $this->parse_entries( $arguments['items'] );

        $this->assert_menu_exists( $menu_id );
        $current = $this->load_items( $menu_id );

        
        
        $writes = $this->changed_rows( $this->plan( $menu_id, $entries, $current, $strict ), $current );
        $this->assert_writable( $writes, $current );

        $applied = array();
        foreach ( $writes as $index => $row ) {
            try {
                $this->rest_request(
                    'POST',
                    '/wp/v2/menu-items/' . $row['id'],
                    array(
                        'parent'     => $row['parent'],
                        'menu_order' => $row['menu_order'],
                    ),
                    array( 'id' )
                );
                $applied[] = $row['id'];
            } catch ( \RuntimeException $e ) {
                return $this->partial_failure_report( $menu_id, $applied, $row['id'], $e->getMessage(), array_slice( $writes, $index + 1 ) );
            }
        }

        return array(
            'menu_id' => $menu_id,
            'changed' => count( $applied ),
            'items'   => $this->shape_items( $this->load_items( $menu_id ) ),
        );
    }

    









    private function parse_entries( $raw ) {
        $list = $this->parse_json_param( $raw, 'items' );
        if ( array_values( $list ) !== $list ) {
            throw new \InvalidArgumentException( 'items must be a JSON array of { "id", "parent" } objects, not an object keyed by id.' );
        }

        $entries = array();
        foreach ( $list as $i => $entry ) {
            if ( ! is_array( $entry ) || ! array_key_exists( 'id', $entry ) || ! array_key_exists( 'parent', $entry ) ) {
                throw new \InvalidArgumentException( sprintf( 'items[%d] must be an object with both "id" and "parent" (0 for top level).', $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $entries[] = array(
                'id'     => $this->parse_required_id( $entry['id'], sprintf( 'items[%d].id', $i ) ),
                'parent' => $this->parse_parent( $entry['parent'], $i ),
            );
        }
        return $entries;
    }

    






    private function parse_parent( $value, $index ) {
        if ( 0 === $value || '0' === $value ) {
            return 0;
        }
        return $this->parse_required_id( $value, sprintf( 'items[%d].parent', $index ) );
    }

    










    private function assert_menu_exists( $menu_id ) {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menus/' . $menu_id );
        $request->set_param( 'context', 'edit' );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $error = $response->as_error();
            self::throw_if_no_route( $error );
            throw new \RuntimeException( sprintf( 'Menu %d could not be loaded: %s', $menu_id, self::rest_error_message( $error ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    







    private static function throw_if_no_route( $error ) {
        if ( 'rest_no_route' === $error->get_error_code() ) {
            throw new \RuntimeException( 'Menu endpoints are not available. This requires WordPress 5.9 or later with navigation menu support.' );
        }
    }

    















    private function load_items( $menu_id ) {
        $items = array();
        $page  = 1;
        do {
            $result = $this->fetch_page( $menu_id, $page );
            foreach ( $result['items'] as $item ) {
                if ( is_array( $item ) && isset( $item['id'] ) ) {
                    $items[ (int) $item['id'] ] = $item;
                }
            }
            $more = $result['more'];
            ++$page;
        } while ( $more && $page <= self::MAX_PAGES );

        if ( $more ) {
            throw new \RuntimeException( sprintf( 'Menu %d has more than %d items; refusing to reorder a menu that could not be read in full.', $menu_id, self::PER_PAGE * self::MAX_PAGES ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        uasort(
            $items,
            static function ( $a, $b ) {
                $order = (int) $a['menu_order'] - (int) $b['menu_order'];
                return 0 !== $order ? $order : (int) $a['id'] - (int) $b['id'];
            }
        );
        return $items;
    }

    














    private function fetch_page( $menu_id, $page ) {
        $request = new \WP_REST_Request( 'GET', '/wp/v2/menu-items' );
        $request->set_param( 'menus', $menu_id );
        $request->set_param( 'per_page', self::PER_PAGE );
        $request->set_param( 'page', $page );
        $request->set_param( 'status', array( 'publish', 'draft' ) );
        $request->set_param( 'orderby', 'id' );
        $request->set_param( 'order', 'asc' );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $error = $response->as_error();
            self::throw_if_no_route( $error );
            throw new \RuntimeException( self::rest_error_message( $error ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $batch       = (array) $response->get_data();
        $headers     = $response->get_headers();
        $total_pages = isset( $headers['X-WP-TotalPages'] ) ? (int) $headers['X-WP-TotalPages'] : null;

        return array(
            'items' => $batch,
            'more'  => null !== $total_pages ? $page < $total_pages : count( $batch ) >= self::PER_PAGE,
        );
    }

    




























    private function plan( $menu_id, array $entries, array $current, $strict ) {
        $listed  = $this->validate_entries( $menu_id, $entries, $current, $strict );
        $parents = $this->final_parents( $listed, $current );
        $this->refuse_cycles( $parents['layout'], $listed );

        return $this->flatten( $this->sibling_order( $entries, $listed, $parents['layout'], $current ), $parents['final'] );
    }

    








    private function validate_entries( $menu_id, array $entries, array $current, $strict ) {
        $listed = $this->listed_parents( $entries );
        $this->refuse_outside_menu( $menu_id, $listed, $current );
        if ( $strict ) {
            $this->refuse_unlisted( $menu_id, $listed, $current );
        }
        return $listed;
    }

    





    private function listed_parents( array $entries ) {
        $listed     = array();
        $duplicates = array();
        foreach ( $entries as $entry ) {
            if ( isset( $listed[ $entry['id'] ] ) ) {
                $duplicates[ $entry['id'] ] = $entry['id'];
                continue;
            }
            $listed[ $entry['id'] ] = $entry['parent'];
        }
        if ( $duplicates ) {
            $this->refuse( 'items lists the same id more than once: %s. List each menu item exactly once.', array_values( $duplicates ) );
        }
        return $listed;
    }

    











    private function refuse_outside_menu( $menu_id, array $listed, array $current ) {
        $foreign    = array();
        $self       = array();
        $bad_parent = array();
        foreach ( $listed as $id => $parent ) {
            if ( ! isset( $current[ $id ] ) ) {
                $foreign[] = $id;
            }
            if ( $id === $parent ) {
                $self[] = $id;
            } elseif ( 0 !== $parent && ! isset( $current[ $parent ] ) ) {
                $bad_parent[] = $parent;
            }
        }

        if ( $foreign ) {
            $this->refuse( 'these ids are not items of menu ' . $menu_id . ': %s. Use menu_item ids from wp_list_menu_items for this menu.', $foreign );
        }
        if ( $self ) {
            $this->refuse( 'an item cannot be its own parent: %s.', $self );
        }
        if ( $bad_parent ) {
            $this->refuse( 'these parents are not items of menu ' . $menu_id . ': %s. A parent must be an item of the same menu, or 0 for top level.', array_values( array_unique( $bad_parent ) ) );
        }
    }

    






    private function refuse_unlisted( $menu_id, array $listed, array $current ) {
        $missing = array_diff( array_keys( $current ), array_keys( $listed ) );
        if ( ! $missing ) {
            return;
        }
        $labels = array();
        foreach ( $missing as $id ) {
            $labels[] = ( isset( $current[ $id ]['status'] ) && 'draft' === $current[ $id ]['status'] ) ? $id . ' (draft)' : (string) $id;
        }
        $this->refuse( sprintf( 'strict mode, %d item(s) of menu %d not listed: %%s. List every item, or pass strict=false.', count( $missing ), $menu_id ), $labels );
    }

    











    private function final_parents( array $listed, array $current ) {
        $final  = array();
        $layout = array();
        foreach ( $current as $id => $item ) {
            $final[ $id ]  = isset( $listed[ $id ] ) ? $listed[ $id ] : (int) $item['parent'];
            $layout[ $id ] = isset( $current[ $final[ $id ] ] ) ? $final[ $id ] : 0;
        }
        return array(
            'final'  => $final,
            'layout' => $layout,
        );
    }

    









    private function sibling_order( array $entries, array $listed, array $layout, array $current ) {
        $children = array( 0 => array() );
        foreach ( $entries as $entry ) {
            $children[ $layout[ $entry['id'] ] ][] = $entry['id'];
        }
        foreach ( array_keys( $current ) as $id ) {
            if ( ! isset( $listed[ $id ] ) ) {
                $children[ $layout[ $id ] ][] = $id;
            }
        }
        return $children;
    }

    







    private function flatten( array $children, array $final ) {
        $plan  = array();
        $stack = array_reverse( $children[0] );
        while ( $stack ) {
            $id     = array_pop( $stack );
            $plan[] = array(
                'id'         => $id,
                'parent'     => $final[ $id ],
                'menu_order' => count( $plan ) + 1,
            );
            if ( isset( $children[ $id ] ) ) {
                foreach ( array_reverse( $children[ $id ] ) as $child ) {
                    $stack[] = $child;
                }
            }
        }
        return $plan;
    }

    






    private function changed_rows( array $plan, array $current ) {
        $writes = array();
        foreach ( $plan as $row ) {
            $before = $current[ $row['id'] ];
            if ( (int) $before['parent'] !== $row['parent'] || (int) $before['menu_order'] !== $row['menu_order'] ) {
                $writes[] = $row;
            }
        }
        return $writes;
    }

    









    private function refuse_cycles( array $layout, array $listed ) {
        $done   = array();
        $cycles = array();
        foreach ( array_keys( $layout ) as $start ) {
            $path = array();
            $pos  = array();
            $cur  = $start;
            while ( 0 !== $cur && ! isset( $done[ $cur ] ) ) {
                if ( isset( $pos[ $cur ] ) ) {
                    $cycles[] = array_merge( array_slice( $path, $pos[ $cur ] ), array( $cur ) );
                    break;
                }
                $pos[ $cur ] = count( $path );
                $path[]      = $cur;
                $cur         = $layout[ $cur ];
            }
            foreach ( $path as $id ) {
                $done[ $id ] = true;
            }
        }

        if ( $cycles ) {
            throw new \InvalidArgumentException( $this->describe_cycles( $cycles, $listed ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private function describe_cycles( array $cycles, array $listed ) {
        $described = array();
        $unlisted  = array();
        foreach ( $cycles as $loop ) {
            $described[] = implode( '→', $loop );
            foreach ( $loop as $id ) {
                if ( ! isset( $listed[ $id ] ) ) {
                    $unlisted[ $id ] = $id;
                }
            }
        }
        $message = 'Refused, nothing was changed: cycle (child→parent) ' . implode( '; ', $described ) . '.';
        if ( $unlisted ) {
            $message .= ' Unlisted ' . implode( ', ', $unlisted ) . ' kept its current parent.';
        }
        return $message;
    }

    
















    private function assert_writable( array $writes, array $current ) {
        $denied     = array();
        $unsaveable = array();
        foreach ( $writes as $row ) {
            if ( ! current_user_can( 'edit_post', $row['id'] ) ) {
                $denied[] = $row['id'];
            } elseif ( $this->is_unsaveable( $current[ $row['id'] ] ) ) {
                $unsaveable[] = $row['id'];
            }
        }

        if ( $denied ) {
            $this->refuse( 'you are not allowed to edit menu item(s) %s.', $denied );
        }
        if ( $unsaveable ) {
            $this->refuse( 'WordPress will not re-save menu item(s) %s: a custom link with an empty title or URL, or an archive link to a post type that is no longer registered. Fix them with wp_update_menu_item first.', $unsaveable );
        }
    }

    









    private function is_unsaveable( array $item ) {
        $type = isset( $item['type'] ) ? (string) $item['type'] : '';
        if ( 'custom' === $type ) {
            return '' === $this->item_title( $item, 'raw' ) || empty( $item['url'] );
        }
        if ( 'post_type_archive' === $type ) {
            return ! post_type_exists( isset( $item['object'] ) ? (string) $item['object'] : '' );
        }
        return false;
    }

    






    private function item_title( array $item, string $field ): string {
        if ( ! isset( $item['title'] ) ) {
            return '';
        }
        return is_array( $item['title'] ) ? (string) ( $item['title'][ $field ] ?? '' ) : (string) $item['title'];
    }

    











    private function partial_failure_report( $menu_id, array $applied, $failed_id, $error, array $remaining ) {
        $report = array(
            'error'       => sprintf(
                'Reorder of menu %d stopped at item %d after %d of %d save(s); the menu is partly reordered. Call again with the same arguments to finish: items already in place are skipped.',
                $menu_id,
                $failed_id,
                count( $applied ),
                count( $applied ) + 1 + count( $remaining )
            ),
            'menu_id'     => $menu_id,
            'changed'     => count( $applied ),
            'applied'     => $applied,
            'failed'      => array(
                'id'    => $failed_id,
                'error' => $error,
            ),
            'not_applied' => array_map(
                static function ( $row ) {
                    return $row['id'];
                },
                $remaining
            ),
        );

        try {
            $report['items'] = $this->shape_items( $this->load_items( $menu_id ) );
        } catch ( \RuntimeException $e ) {
            
            unset( $e );
        }
        return $report;
    }

    






    private function shape_items( array $items ) {
        $out = array();
        foreach ( $items as $item ) {
            $out[] = array(
                'id'         => (int) $item['id'],
                'title'      => wp_strip_all_tags( $this->item_title( $item, 'rendered' ) ),
                'url'        => $item['url'] ?? '',
                'parent'     => (int) $item['parent'],
                'menu_order' => (int) $item['menu_order'],
                'type'       => $item['type'] ?? '',
                'object'     => $item['object'] ?? '',
                'object_id'  => (int) ( $item['object_id'] ?? 0 ),
                'status'     => $item['status'] ?? '',
            );
        }
        return $out;
    }

    






    private function refuse( $format, array $ids ) {
        $shown = array_slice( $ids, 0, self::IDS_IN_MESSAGE );
        $list  = implode( ', ', $shown );
        if ( count( $ids ) > count( $shown ) ) {
            $list .= sprintf( ' (+%d more)', count( $ids ) - count( $shown ) );
        }
        throw new \InvalidArgumentException( 'Refused, nothing was changed: ' . sprintf( $format, $list ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
