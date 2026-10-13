<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Active_Plugins_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/active-plugins';
    }

    public function get_name() {
        return 'Active Plugins';
    }

    public function get_description() {
        return 'List of all active WordPress plugins with name, version, and description.';
    }

    public function read() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return array( 'plugins' => array() );
        }

        
        
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active      = array();

        foreach ( $all_plugins as $file => $data ) {
            if ( is_plugin_active( $file ) ) {
                $active[] = array(
                    'file'        => $file,
                    'name'        => isset( $data['Name'] ) ? $data['Name'] : '',
                    'version'     => isset( $data['Version'] ) ? $data['Version'] : '',
                    'description' => isset( $data['Description'] ) ? $data['Description'] : '',
                    'author'      => isset( $data['Author'] ) ? $data['Author'] : '',
                );
            }
        }

        return array( 'plugins' => $active );
    }
}
