<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Widget_Types extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_list_widget_types';
    }

    public function get_description() {
        return 'Lists the widget types registered on the site — the valid `id_base` values for wp_create_widget. No arguments. Returns { widget_types: [{ id, name, description, is_multi, settings_editable }], total }. `id` is the id_base. Only types with `settings_editable: true` can be created or have their settings changed over MCP; the others are legacy widgets without a REST representation. WordPress publishes no settings schema per type; the core ones are: block { content: block markup }, text { title, text, filter }, custom_html { title, content }, recent-posts { title, number, show_date }, search { title }, categories { title, count, hierarchical, dropdown }, archives { title, count, dropdown }, nav_menu { title, nav_menu: menu id }. Read an existing widget with wp_get_widget to see a type\'s settings. Refused when the theme has no widget areas (a block theme). Requires the edit_theme_options capability, the same as Appearance > Widgets.';
    }

    public function get_category() {
        return 'widgets';
    }

    public function get_required_capability() {
        return 'edit_theme_options';
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
        $this->require_widget_support();

        $data = $this->rest_request( 'GET', '/wp/v2/widget-types', array(), 'id,name,description,is_multi' );

        $types = array();
        foreach ( (array) $data as $type ) {
            if ( ! is_array( $type ) || ! isset( $type['id'] ) ) {
                continue;
            }
            $id      = (string) $type['id'];
            $types[] = array(
                'id'                => $id,
                'name'              => isset( $type['name'] ) ? (string) $type['name'] : '',
                'description'       => isset( $type['description'] ) ? (string) $type['description'] : '',
                'is_multi'          => ! empty( $type['is_multi'] ),
                
                
                
                'settings_editable' => 'editable' === $this->widget_type_status( $id ),
            );
        }

        return array(
            'widget_types' => $types,
            'total'        => count( $types ),
        );
    }
}
