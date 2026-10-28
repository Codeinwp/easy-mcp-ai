<?php
namespace Easy_MCP_AI\Tools\Widgets;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Widget extends Base_Tool {

    use Widget_Rest {
        sidebar_widget_ids as private rest_sidebar_widget_ids;
    }

    private $record_order = false;

    private $order_before = null;

    public function get_name() {
        return 'wp_update_widget';
    }

    public function get_description() {
        return 'Updates a widget\'s settings, moves it to another widget area, or reorders it. Required: `widget_id` (from wp_list_widgets), plus at least one of: `instance` (settings to change — merged over the current settings, so keys you omit keep their values; e.g. {"content": "<!-- wp:paragraph --><p>New footer text</p><!-- /wp:paragraph -->"} for a block widget, {"title": "Latest"} for recent-posts), `sidebar` (move to this area, from wp_list_sidebars; it is added last unless `position` is given) and `position` (0-based index inside its sidebar; 0 = first). Read the current settings with wp_get_widget first. HTML in block, custom_html and text content is filtered with wp_kses_post unless your user has the unfiltered_html capability, exactly as in Appearance > Widgets. A legacy widget without a REST representation (settings_editable: false) can be moved or reordered but its `instance` is refused (read-only over MCP). Returns { id, id_base, sidebar, settings_editable, settings, rendered, changed } plus { position, sidebar_widgets } when `position` was given; if the other changes were saved but the reorder failed, { position_applied: false, warning } instead. `changed` is false when the call left the widget exactly as it was — same stored settings, same sidebar, same place in it (settings you send are still saved through the widget\'s own update callback, so they are written even then). The change is visible on every page that shows the sidebar. To take a widget off the site use wp_delete_widget. Requires the edit_theme_options capability.';
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

        $settings = $has_instance ? $this->instance_argument( $arguments['instance'] ) : null;
        $sidebar  = $has_sidebar ? $this->validate_rest_route_segment( $arguments['sidebar'], 'sidebar' ) : null;

        $shaped  = $this->current_widget( $widget_id, $has_instance );
        $result  = $this->save_changes( $widget_id, $shaped, $settings, $sidebar );
        $changed = self::widget_changed( $shaped, $result, $has_instance );

        if ( null !== $position ) {
            return $this->with_placement( $widget_id, $result, $position, $changed );
        }

        $result['changed'] = $changed;
        return $result;
    }

    private function current_widget( string $widget_id, bool $has_instance ): array {
        $current = (array) $this->rest_request( 'GET', '/wp/v2/widgets/' . $widget_id, array( 'context' => 'edit' ), 'id,id_base,sidebar,rendered,instance' );
        $shaped  = $this->shape_widget( $current, true );

        if ( $has_instance && ! $shaped['settings_editable'] ) {
            throw $this->legacy_widget_error( '' !== $shaped['id_base'] ? $shaped['id_base'] : $widget_id );
        }
        return $shaped;
    }

    private function save_changes( string $widget_id, array $shaped, ?array $settings, ?string $sidebar ): array {
        if ( null !== $sidebar && $sidebar !== $shaped['sidebar'] ) {
            $this->sidebar_widget_ids( $sidebar );
        }

        $params = array();
        if ( null !== $settings ) {
            $merged             = array_merge( is_array( $shaped['settings'] ) ? $shaped['settings'] : (array) $shaped['settings'], $settings );
            $params['instance'] = $this->raw_instance( $merged );
        }
        if ( null !== $sidebar ) {
            $params['sidebar'] = $sidebar;
        }

        if ( empty( $params ) ) {
            return $shaped;
        }
        $data = $this->rest_request( 'PUT', '/wp/v2/widgets/' . $widget_id, $params, 'id,id_base,sidebar,rendered,instance' );
        return $this->shape_widget( (array) $data, true );
    }

    private static function widget_changed( array $before, array $after, bool $has_instance ): bool {
        return $after['sidebar'] !== $before['sidebar']
            || ( $has_instance && self::comparable_settings( $after['settings'] ) !== self::comparable_settings( $before['settings'] ) );
    }

    private function with_placement( string $widget_id, array $result, int $position, bool $changed ): array {
        $this->order_before = null;
        $this->record_order = true;
        try {
            $placed = $this->apply_widget_position( $widget_id, $result['sidebar'], $position );
        } finally {
            $this->record_order = false;
        }
        if ( $placed['applied'] && $this->order_before !== $placed['sidebar_widgets'] ) {
            $changed = true;
        }
        $result            = $this->with_position_result( $result, $placed, 'updated' );
        $result['changed'] = $changed;
        return $result;
    }

    protected function sidebar_widget_ids( string $sidebar_id ): array {
        $ids = $this->rest_sidebar_widget_ids( $sidebar_id );
        if ( $this->record_order && null === $this->order_before ) {
            $this->order_before = $ids;
        }
        return $ids;
    }

    private static function comparable_settings( $settings ) {
        if ( is_object( $settings ) ) {
            $settings = (array) $settings;
        }
        if ( ! is_array( $settings ) ) {
            return $settings;
        }
        foreach ( $settings as $key => $value ) {
            $settings[ $key ] = self::comparable_settings( $value );
        }
        if ( array_values( $settings ) !== $settings ) {
            ksort( $settings );
        }
        return $settings;
    }
}
