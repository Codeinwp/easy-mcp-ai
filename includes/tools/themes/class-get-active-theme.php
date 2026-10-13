<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Active_Theme extends Base_Tool {

    public function get_name() {
        return 'wp_get_active_theme';
    }

    public function get_description() {
        return 'Gets the currently active WordPress theme. Returns { stylesheet (theme folder identifier), name, version, author, description }. No parameters required. Use `wp_list_themes` to see all installed themes.';
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'switch_themes';
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
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $data = $this->rest_request( 'GET', '/wp/v2/themes', array( 'status' => 'active' ) );

        if ( empty( $data ) ) {
            throw new \RuntimeException( 'Could not determine active theme.' );
        }

        $theme = $data[0];

        return array(
            'stylesheet'  => isset( $theme['stylesheet'] ) ? $theme['stylesheet'] : '',
            'name'        => isset( $theme['name']['rendered'] ) ? wp_strip_all_tags( $theme['name']['rendered'] ) : '',
            'version'     => isset( $theme['version'] ) ? $theme['version'] : '',
            'author'      => isset( $theme['author']['rendered'] ) ? wp_strip_all_tags( $theme['author']['rendered'] ) : '',
            'description' => isset( $theme['description']['rendered'] ) ? wp_strip_all_tags( $theme['description']['rendered'] ) : '',
        );
    }
}
