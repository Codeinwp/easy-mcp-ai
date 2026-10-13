<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Widgets extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_list_widgets';
    }

    public function get_description() {
        return 'Lists the widgets placed in a classic theme\'s widget areas, with their settings. Optional: `sidebar` (a sidebar id from wp_list_sidebars, e.g. "sidebar-1" or "wp_inactive_widgets") to list one area only. Returns { widgets: [{ id, id_base, sidebar, position, settings_editable, settings }], total }, grouped by sidebar in display order; `position` is the 0-based index inside its sidebar (the value wp_create_widget / wp_update_widget accept). `settings` is the widget\'s settings object (e.g. { title, content } for a custom_html widget, { content } for a block widget); it is null and `settings_editable` is false for a legacy widget type without a REST representation — those are read-only over MCP. Use wp_get_widget for one widget with its rendered HTML. Refused when the theme has no widget areas (a block theme): use wp_list_templates instead. Requires the edit_theme_options capability, the same as Appearance > Widgets.';
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
            'properties' => array(
                'sidebar' => array(
                    'type'        => 'string',
                    'description' => 'Only list widgets in this sidebar (an id from wp_list_sidebars). Omit to list every area, including wp_inactive_widgets.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_widget_support();

        $params = array( 'context' => 'edit' );
        if ( isset( $arguments['sidebar'] ) && '' !== $arguments['sidebar'] ) {
            $params['sidebar'] = $this->validate_rest_route_segment( $arguments['sidebar'], 'sidebar' );
        }

        
        
        
        $data = $this->rest_request( 'GET', '/wp/v2/widgets', $params, 'id,id_base,sidebar,instance' );

        $widgets = array();
        $counter = array();
        foreach ( (array) $data as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }
            $widget  = $this->shape_widget( $item, false );
            $sidebar = $widget['sidebar'];
            
            
            $counter[ $sidebar ] = isset( $counter[ $sidebar ] ) ? $counter[ $sidebar ] + 1 : 0;
            $widgets[]           = array(
                'id'                => $widget['id'],
                'id_base'           => $widget['id_base'],
                'sidebar'           => $sidebar,
                'position'          => $counter[ $sidebar ],
                'settings_editable' => $widget['settings_editable'],
                'settings'          => $widget['settings'],
            );
        }

        return array(
            'widgets' => $widgets,
            'total'   => count( $widgets ),
        );
    }
}
