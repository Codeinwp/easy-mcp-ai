<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menus_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://menus/all';
    }

    public function get_name() {
        return 'Navigation Menus';
    }

    public function get_description() {
        return 'All registered navigation menus with their assigned theme locations.';
    }

    public function read() {
        $menus     = wp_get_nav_menus();
        $locations = get_registered_nav_menus();
        $assigned  = get_nav_menu_locations();

        $menu_list = array();
        if ( is_array( $menus ) ) {
            foreach ( $menus as $menu ) {
                $menu_locations = array();
                if ( is_array( $assigned ) ) {
                    foreach ( $assigned as $loc => $menu_id ) {
                        if ( (int) $menu_id === (int) $menu->term_id ) {
                            $menu_locations[] = $loc;
                        }
                    }
                }
                $menu_list[] = array(
                    'id'        => (int) $menu->term_id,
                    'name'      => $menu->name,
                    'slug'      => $menu->slug,
                    'count'     => (int) $menu->count,
                    'locations' => $menu_locations,
                );
            }
        }

        return array(
            'menus'                => $menu_list,
            
            
            
            
            
            
            
            
            'registered_locations' => (object) ( is_array( $locations ) ? $locations : array() ),
        );
    }
}
