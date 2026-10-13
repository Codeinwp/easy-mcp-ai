<?php
namespace Easy_MCP_AI\Tools\Plugins;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Plugins extends Base_Tool {

    public function get_name() {
        return 'wp_list_plugins';
    }

    public function get_description() {
        return 'Lists installed WordPress plugins. Optional: `status` (filter by activation status: "active" | "inactive" — omit for all). Returns { plugins: [{ plugin (folder/file.php identifier), name, description, status, version, author }], total }. Requires administrator access. The `plugin` field is the identifier used by WordPress plugin functions.';
    }

    public function get_category() {
        return 'plugins';
    }

    public function get_required_capability() {
        return 'activate_plugins';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'status' => array(
                    'type'        => 'string',
                    'description' => 'Filter plugins by status.',
                    'enum'        => array( 'active', 'inactive' ),
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $params = array(
            'context' => 'edit',
        );

        if ( ! empty( $arguments['status'] ) ) {
            $params['status'] = $arguments['status'];
        }

        $request = new \WP_REST_Request( 'GET', '/wp/v2/plugins' );
        foreach ( $params as $key => $value ) {
            $request->set_param( $key, $value );
        }

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $error = $response->as_error();
            throw new \RuntimeException( $error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $plugins = $response->get_data();
        $total   = count( $plugins );

        $result = array();
        foreach ( $plugins as $plugin ) {
            
            $plugin_id = $plugin['plugin'];
            if ( substr( $plugin_id, -4 ) !== '.php' ) {
                $plugin_id .= '.php';
            }
            $result[] = array(
                'plugin'      => $plugin_id,
                'name'        => $plugin['name'],
                'description' => is_array( $plugin['description'] ) ? ( $plugin['description']['raw'] ?? '' ) : ( (string) $plugin['description'] ),
                'status'      => $plugin['status'],
                'version'     => $plugin['version'],
                'author'      => $plugin['author'],
            );
        }

        return array(
            'plugins' => $result,
            'total'   => $total,
        );
    }
}
