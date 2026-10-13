<?php




























namespace Easy_MCP_AI\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Access_Presets {

    const READ   = 'read';
    const WRITE  = 'write';
    const FULL   = 'full';
    const ROLE   = 'role';
    const CUSTOM = 'custom';

    
    const ROLES = array( 'editor', 'author' );

    
    private $tool_registry;

    
    private $lists = null;

    




    public function __construct( $tool_registry = null ) {
        $this->tool_registry = $tool_registry;
    }

    




    public static function ids() {
        $ids = array( self::READ, self::WRITE, self::FULL );
        foreach ( self::ROLES as $role ) {
            $ids[] = self::ROLE . ':' . $role;
        }
        $ids[] = self::CUSTOM;
        return $ids;
    }

    







    public static function split( $preset, $role = null ) {
        $preset = is_string( $preset ) ? $preset : '';
        if ( 0 === strpos( $preset, self::ROLE . ':' ) ) {
            $role   = substr( $preset, strlen( self::ROLE ) + 1 );
            $preset = self::ROLE;
        }
        if ( self::ROLE === $preset ) {
            return in_array( $role, self::ROLES, true ) ? array( self::ROLE, $role ) : array( self::CUSTOM, null );
        }
        if ( in_array( $preset, array( self::READ, self::WRITE, self::FULL ), true ) ) {
            return array( $preset, null );
        }
        return array( self::CUSTOM, null );
    }

    






    public function to_allowed_tools( $preset, $role = null ) {
        list( $preset, $role ) = self::split( $preset, $role );
        if ( self::CUSTOM === $preset ) {
            return null;
        }
        $lists = $this->lists();
        $id    = self::ROLE === $preset ? self::ROLE . ':' . $role : $preset;
        return isset( $lists[ $id ] ) ? $lists[ $id ] : null;
    }

    





    public function from_allowed_tools( array $list ) {
        $needle = self::normalize( $list );
        if ( empty( $needle ) ) {
            return self::CUSTOM;
        }
        foreach ( $this->lists() as $id => $tools ) {
            if ( $tools === $needle ) {
                return $id;
            }
        }
        return self::CUSTOM;
    }

    





    public static function normalize( array $list ) {
        $out = array();
        foreach ( $list as $item ) {
            if ( is_string( $item ) && '' !== trim( $item ) ) {
                $out[ trim( $item ) ] = true;
            }
        }
        $out = array_keys( $out );
        sort( $out, SORT_STRING );
        return $out;
    }

    
    private function lists() {
        if ( null !== $this->lists ) {
            return $this->lists;
        }
        self::load_scope_map();
        $lists = array(
            self::READ  => self::normalize( \Easy_MCP_AI\OAuth\Scope_Map::resolve_allowed_tools( implode( ' ', \Easy_MCP_AI\OAuth\Scope_Map::get_read_scopes() ) ) ),
            self::WRITE => self::normalize( \Easy_MCP_AI\OAuth\Scope_Map::resolve_allowed_tools( \Easy_MCP_AI\OAuth\Scope_Map::get_default_scope() ) ),
            self::FULL  => array( '*' ),
        );
        foreach ( self::ROLES as $role ) {
            $lists[ self::ROLE . ':' . $role ] = $this->role_tools( $role );
        }
        $this->lists = $lists;
        return $lists;
    }

    






    private function role_tools( $role ) {
        $wp_role  = \get_role( $role );
        $registry = $this->registry();
        if ( ! $wp_role || ! $registry ) {
            return array();
        }
        $tools = array();
        foreach ( (array) $registry->get_all_tool_names() as $name ) {
            $tool = $registry->get_tool( $name );
            if ( ! $tool ) {
                continue;
            }
            
            
            
            if ( $tool instanceof \Easy_MCP_AI\Tools\Privileged_Tool ) {
                continue;
            }
            $cap = (string) $tool->get_required_capability();
            if ( class_exists( '\\Easy_MCP_AI\\MCP\\Server' ) ) {
                $cap = (string) \Easy_MCP_AI\MCP\Server::effective_required_capability( $tool->get_category(), $cap );
            }
            if ( '' === $cap || $wp_role->has_cap( $cap ) ) {
                $tools[] = (string) $name;
            }
        }
        return self::normalize( $tools );
    }

    
    private function registry() {
        if ( $this->tool_registry ) {
            return $this->tool_registry;
        }
        
        
        if ( class_exists( '\\Easy_MCP_AI\\Plugin' ) && method_exists( '\\Easy_MCP_AI\\Plugin', 'instance' ) ) {
            $registry = \Easy_MCP_AI\Plugin::instance()->get_tool_registry();
            if ( $registry ) {
                $this->tool_registry = $registry;
            }
        }
        return $this->tool_registry;
    }

    private static function load_scope_map() {
        if ( ! class_exists( '\\Easy_MCP_AI\\OAuth\\Scope_Map' ) ) {
            require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/oauth/class-scope-map.php';
        }
    }
}
