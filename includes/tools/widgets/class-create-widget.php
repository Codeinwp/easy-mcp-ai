<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Widget extends Base_Tool {

    use Widget_Rest;

    public function get_name() {
        return 'wp_create_widget';
    }

    public function get_description() {
        return 'Creates a widget in a classic theme\'s widget area. Required: `sidebar` (a sidebar id from wp_list_sidebars) and `id_base` (the widget type, from wp_list_widget_types — must have settings_editable: true). Optional: `instance` (the widget\'s settings object; omitted = the type\'s defaults) and `position` (0-based index in the sidebar; 0 = first; omitted or past the end = last). Settings by type: block { "content": "<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->" } (any block markup — the recommended type for free-form content), custom_html { "title", "content" }, text { "title", "text", "filter": true for automatic paragraphs }, recent-posts { "title", "number", "show_date" }, search { "title" }, nav_menu { "title", "nav_menu": menu id from wp_list_menus }. HTML in block, custom_html and text content is filtered with wp_kses_post unless your user has the unfiltered_html capability, exactly as in Appearance > Widgets. Legacy widget types without a REST representation are refused (read-only over MCP). Returns { id, id_base, sidebar, settings_editable, settings, rendered } plus, when `position` was given, { position, sidebar_widgets }; if the widget was created but could not be moved, { position_applied: false, warning } instead. The change is visible on every page that shows the sidebar. Refused when the theme has no widget areas (a block theme): use wp_update_template instead. Requires the edit_theme_options capability.';
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
                'sidebar'  => array(
                    'type'        => 'string',
                    'description' => 'Id of the widget area to add the widget to, from wp_list_sidebars (e.g. "sidebar-1").',
                ),
                'id_base'  => array(
                    'type'        => 'string',
                    'description' => 'Widget type, from wp_list_widget_types (e.g. "block", "custom_html", "recent-posts").',
                ),
                'instance' => array(
                    'type'        => 'object',
                    'description' => 'The widget settings, keyed by setting name; arbitrary keys allowed, as each widget type defines its own. For a block widget: {"content": "<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->"}. Omit to use the type\'s defaults.',
                ),
                'position' => array(
                    'type'        => 'integer',
                    'description' => '0-based position inside the sidebar (0 = first). Omit, or pass a number past the end, to add it last.',
                    'minimum'     => 0,
                ),
            ),
            'required'   => array( 'sidebar', 'id_base' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_widget_support();
        $this->validate_required( $arguments, array( 'sidebar', 'id_base' ) );

        $sidebar  = $this->validate_rest_route_segment( $arguments['sidebar'], 'sidebar' );
        $id_base  = $this->validate_rest_route_segment( $arguments['id_base'], 'id_base' );
        $position = $this->position_argument( $arguments );
        $settings = isset( $arguments['instance'] ) ? $this->instance_argument( $arguments['instance'] ) : array();

        
        
        
        
        $status = $this->widget_type_status( $id_base );
        if ( 'legacy' === $status ) {
            throw $this->legacy_widget_error( $id_base );
        }
        if ( 'unknown' === $status ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown widget type "%s". Call wp_list_widget_types for the registered id_base values.', $id_base ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        
        $this->sidebar_widget_ids( $sidebar );

        $data = $this->rest_request(
            'POST',
            '/wp/v2/widgets',
            array(
                'id_base'  => $id_base,
                'sidebar'  => $sidebar,
                'instance' => $this->raw_instance( $settings ),
            ),
            'id,id_base,sidebar,rendered,instance'
        );

        $result = $this->shape_widget( (array) $data, true );

        if ( null !== $position && '' !== $result['id'] ) {
            $result = $this->with_position_result( $result, $this->apply_widget_position( $result['id'], $result['sidebar'], $position ), 'created' );
        }

        return $result;
    }
}
