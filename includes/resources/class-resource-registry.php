<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Resource_Registry {

    private $resources = array();

    public function register( Base_Resource $resource ) {
        $this->resources[ $resource->get_uri() ] = $resource;
    }

    public function get_resource( $uri ) {
        return isset( $this->resources[ $uri ] ) ? $this->resources[ $uri ] : null;
    }

    public function get_all_definitions() {
        $definitions = array();
        foreach ( $this->resources as $resource ) {
            $definitions[] = $resource->get_definition();
        }
        return $definitions;
    }

    public function auto_discover() {
        $resource_classes = array(
            
            'Easy_MCP_AI\\Resources\\Site_Info_Resource',
            'Easy_MCP_AI\\Resources\\Recent_Posts_Resource',
            'Easy_MCP_AI\\Resources\\Site_Stats_Resource',
            
            'Easy_MCP_AI\\Resources\\Reading_Settings_Resource',
            'Easy_MCP_AI\\Resources\\Discussion_Settings_Resource',
            'Easy_MCP_AI\\Resources\\Active_Plugins_Resource',
            'Easy_MCP_AI\\Resources\\Post_Types_Resource',
            'Easy_MCP_AI\\Resources\\Taxonomies_Resource',
            'Easy_MCP_AI\\Resources\\Authors_Resource',
            'Easy_MCP_AI\\Resources\\Theme_Templates_Resource',
            'Easy_MCP_AI\\Resources\\Menus_Resource',
            'Easy_MCP_AI\\Resources\\Draft_Posts_Resource',
            'Easy_MCP_AI\\Resources\\Scheduled_Posts_Resource',
            'Easy_MCP_AI\\Resources\\Recent_Media_Resource',
            
            'Easy_MCP_AI\\Resources\\Approval_Card_Resource',
        );
        foreach ( $resource_classes as $class ) {
            if ( class_exists( $class ) ) {
                $this->register( new $class() );
            }
        }
    }
}
