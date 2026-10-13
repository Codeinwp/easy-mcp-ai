<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Widget extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_update_widget';
    }

    public function get_description() {
        return 'Updates a widget\'s settings, moves it to another widget area, or reorders it. Required: `widget_id` (from wp_list_widgets), plus at least one of: `instance` (settings to change — merged over the current settings, so keys you omit keep their values; e.g. {"content": "<!-- wp:paragraph --><p>New footer text</p><!-- /wp:paragraph -->"} for a block widget, {"title": "Latest"} for recent-posts), `sidebar` (move to this area, from wp_list_sidebars; it is added last unless `position` is given) and `position` (0-based index inside its sidebar; 0 = first). Read the current settings with wp_get_widget first. HTML in block, custom_html and text content is filtered with wp_kses_post unless your user has the unfiltered_html capability, exactly as in Appearance > Widgets. A legacy widget without a REST representation (settings_editable: false) can be moved or reordered but its `instance` is refused (read-only over MCP). Returns { id, id_base, sidebar, settings_editable, settings, rendered } plus { position, sidebar_widgets } when `position` was given; if the other changes were saved but the reorder failed, { position_applied: false, warning } instead. The change is visible on every page that shows the sidebar. To take a widget off the site use wp_delete_widget. Requires the edit_theme_options capability.';
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
            'readOnlyHint'    => false,
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
                    'description' => 'The widget id, e.g. "block-3".',
                ),
                'instance'  => array(
                    'type'        => 'object',
                    'description' => 'Settings to change, keyed by setting name; arbitrary keys allowed, as each widget type defines its own. Merged over the current settings. For a block widget: {"content": "<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->"}.',
                ),
                'sidebar'   => array(
                    'type'        => 'string',
                    'description' => 'Move the widget to this widget area (an id from wp_list_sidebars). "wp_inactive_widgets" takes it off the site but keeps its settings.',
                ),
                'position'  => array(
                    'type'        => 'integer',
                    'description' => '0-based position inside the sidebar (0 = first). A number past the end puts it last.',
                    'minimum'     => 0,
                ),
            ),
            'required'   => array( 'widget_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_widget_support();
        $this->validate_required( $arguments, array( 'widget_id' ) );

        $widget_id    = $this->validate_rest_route_segment( $arguments['widget_id'], 'widget_id' );
        $has_instance = isset( $arguments['instance'] );
        $has_sidebar  = isset( $arguments['sidebar'] ) && '' !== $arguments['sidebar'];
        $position     = $this->position_argument( $arguments );

        if ( ! $has_instance && ! $has_sidebar && null === $position ) {
            throw new \InvalidArgumentException( 'Nothing to update: pass `instance` (settings), `sidebar` (move) and/or `position` (reorder).' );
        }

        $settings = $has_instance ? $this->instance_argument( $arguments['instance'] ) : array();
        $sidebar  = $has_sidebar ? $this->validate_rest_route_segment( $arguments['sidebar'], 'sidebar' ) : '';

        
        
        
        
        $current = (array) $this->rest_request( 'GET', '/wp/v2/widgets/' . $widget_id, array( 'context' => 'edit' ), 'id,id_base,sidebar,rendered,instance' );
        $shaped  = $this->shape_widget( $current, true );

        if ( $has_instance && ! $shaped['settings_editable'] ) {
            throw $this->legacy_widget_error( '' !== $shaped['id_base'] ? $shaped['id_base'] : $widget_id );
        }

        
        
        if ( $has_sidebar && $sidebar !== $shaped['sidebar'] ) {
            $this->sidebar_widget_ids( $sidebar );
        }

        $params = array();
        if ( $has_instance ) {
            
            
            
            
            
            
            
            $merged             = array_merge( is_array( $shaped['settings'] ) ? $shaped['settings'] : (array) $shaped['settings'], $settings );
            $params['instance'] = $this->raw_instance( $merged );
        }
        if ( $has_sidebar ) {
            $params['sidebar'] = $sidebar;
        }

        if ( ! empty( $params ) ) {
            $data   = $this->rest_request( 'PUT', '/wp/v2/widgets/' . $widget_id, $params, 'id,id_base,sidebar,rendered,instance' );
            $result = $this->shape_widget( (array) $data, true );
        } else {
            $result = $shaped;
        }

        if ( null !== $position ) {
            $result = $this->with_position_result( $result, $this->apply_widget_position( $widget_id, $result['sidebar'], $position ), 'updated' );
        }

        return $result;
    }
}
