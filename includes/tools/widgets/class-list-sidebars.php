<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Sidebars extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_list_sidebars';
    }

    public function get_description() {
        return 'Lists the widget areas (sidebars) of a classic theme — sidebars, footers and other areas the theme registers — with the ids of the widgets in each, in display order. No arguments. Returns { sidebars: [{ id, name, description, status, widgets: [widget ids] }], total }. `status` is "active" for an area the theme registers and "inactive" for `wp_inactive_widgets` (widgets removed from every area but kept with their settings). When the theme has no widget areas — a block theme — returns { sidebars: [], total: 0, hint }: the hint points to wp_list_templates / wp_get_template, because a block theme builds headers and footers from template parts instead. Next steps: wp_list_widgets (settings of each widget), wp_create_widget, wp_update_widget. Requires the edit_theme_options capability, the same as Appearance > Widgets.';
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
        
        
        
        if ( ! $this->theme_supports_widgets() ) {
            return $this->empty_result();
        }

        $data = $this->rest_request( 'GET', '/wp/v2/sidebars', array( 'context' => 'edit' ), 'id,name,description,status,widgets' );

        $sidebars   = array();
        $registered = 0;
        $active     = 0;
        foreach ( (array) $data as $sidebar ) {
            if ( ! is_array( $sidebar ) || ! isset( $sidebar['id'] ) ) {
                continue;
            }
            $id      = (string) $sidebar['id'];
            $widgets = $this->widget_ids( isset( $sidebar['widgets'] ) ? $sidebar['widgets'] : array() );
            $status  = isset( $sidebar['status'] ) ? (string) $sidebar['status'] : '';
            if ( 'wp_inactive_widgets' !== $id ) {
                ++$registered;
                if ( 'active' === $status ) {
                    ++$active;
                }
            }
            $sidebars[] = array(
                'id'          => $id,
                'name'        => isset( $sidebar['name'] ) ? (string) $sidebar['name'] : '',
                'description' => isset( $sidebar['description'] ) ? (string) $sidebar['description'] : '',
                'status'      => $status,
                'widgets'     => $widgets,
            );
        }

        
        
        
        if ( 0 === $registered ) {
            return $this->empty_result();
        }

        $result = array(
            'sidebars' => $sidebars,
            'total'    => count( $sidebars ),
        );
        
        
        
        if ( 0 === $active ) {
            $result['hint'] = 'None of these widget areas is active: the theme registers them but does not display them. ' . self::block_theme_hint();
        }
        return $result;
    }

    




    private function empty_result(): array {
        return array(
            'sidebars' => array(),
            'total'    => 0,
            'hint'     => self::block_theme_hint(),
        );
    }
}
