<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Widget extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_get_widget';
    }

    public function get_description() {
        return 'Gets one widget by id, with its settings and a rendered HTML preview. Required: `widget_id` (e.g. "block-3", "text-2", "recent-posts-4" — from wp_list_widgets or wp_list_sidebars). Returns { id, id_base, sidebar, settings_editable, settings, rendered }. `settings` is the settings object wp_update_widget accepts as `instance`; it is null and `settings_editable` is false for a legacy widget type without a REST representation (read-only over MCP). `rendered` is the widget\'s front-end HTML ("" while it sits in wp_inactive_widgets). Refused when the theme has no widget areas (a block theme): use wp_list_templates instead. Requires the edit_theme_options capability, the same as Appearance > Widgets.';
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
                'widget_id' => array(
                    'type'        => 'string',
                    'description' => 'The widget id, e.g. "block-3" (id_base and instance number).',
                ),
            ),
            'required'   => array( 'widget_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_widget_support();
        $this->validate_required( $arguments, array( 'widget_id' ) );
        $widget_id = $this->validate_rest_route_segment( $arguments['widget_id'], 'widget_id' );

        
        $data = $this->rest_request( 'GET', '/wp/v2/widgets/' . $widget_id, array( 'context' => 'edit' ), 'id,id_base,sidebar,rendered,instance' );

        return $this->shape_widget( (array) $data, true );
    }
}
