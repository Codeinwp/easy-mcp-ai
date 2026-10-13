<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Lifecycle_Guard;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




class List_Themes extends Base_Tool {

    use Lifecycle_Guard;

    public function get_name() {
        return 'wp_list_themes';
    }

    public function get_description() {
        return 'Lists all installed WordPress themes. Returns { themes: [{ stylesheet (theme folder name / identifier), name, version, status ("active"/"inactive"), author, update_available (boolean), new_version (only when an update is available) }], total }. The active theme has `status="active"`. Update availability comes from WordPress\'s stored update data (the same data Appearance → Themes shows); this tool does not contact wordpress.org. Use `wp_get_active_theme` if you only need the current theme\'s details, `wp_switch_theme` to activate one and `wp_update_theme` to update one. Requires administrator access.';
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
        $data   = $this->rest_request( 'GET', '/wp/v2/themes' );
        $themes = array();

        
        
        
        $response = $this->update_response( get_site_transient( 'update_themes' ) );

        foreach ( $data as $theme ) {
            $themes[] = $this->theme_item( $theme, $response );
        }

        return array( 'themes' => $themes, 'total' => count( $themes ) );
    }

    







    private function theme_item( $theme, array $response ): array {
        $stylesheet = isset( $theme['stylesheet'] ) ? $theme['stylesheet'] : '';
        $item       = array(
            'stylesheet'       => $stylesheet,
            'name'             => isset( $theme['name']['rendered'] ) ? wp_strip_all_tags( $theme['name']['rendered'] ) : '',
            'version'          => isset( $theme['version'] ) ? $theme['version'] : '',
            'status'           => isset( $theme['status'] ) ? $theme['status'] : '',
            'author'           => isset( $theme['author']['rendered'] ) ? wp_strip_all_tags( $theme['author']['rendered'] ) : '',
            'update_available' => false,
        );
        if ( '' !== $stylesheet && isset( $response[ $stylesheet ] ) ) {
            $item = $this->with_update( $item, $response[ $stylesheet ] );
        }
        return $item;
    }

    






    private function with_update( array $item, $entry ): array {
        $new_version              = (string) $this->update_field( $entry, 'new_version' );
        $item['update_available'] = true;
        if ( '' !== $new_version ) {
            $item['new_version'] = $new_version;
        }
        return $item;
    }
}
