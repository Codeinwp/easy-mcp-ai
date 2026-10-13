<?php
namespace Easy_MCP_AI\Tools\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




























trait Widget_Rest {

    





    protected static function block_theme_hint(): string {
        return 'This theme has no classic widget areas (sidebars). On a block theme, headers, footers and sidebars are template parts: use wp_list_templates with type "template_part" to find them, wp_get_template to read one and wp_update_template to change it.';
    }

    






    protected function theme_supports_widgets(): bool {
        return (bool) current_theme_supports( 'widgets' );
    }

    










    protected function require_widget_support(): void {
        if ( ! $this->theme_supports_widgets() ) {
            throw new \RuntimeException( 'The active theme does not support widgets, so there are no widget areas to manage (Appearance > Widgets refuses the same way). ' . self::block_theme_hint() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    










    protected function widget_type_status( string $id_base ): string {
        global $wp_widget_factory, $wp_registered_widget_updates;

        $object = null;
        if ( is_object( $wp_widget_factory ) && method_exists( $wp_widget_factory, 'get_widget_object' ) ) {
            $object = $wp_widget_factory->get_widget_object( $id_base );
        }
        if ( $object ) {
            return ! empty( $object->widget_options['show_instance_in_rest'] ) ? 'editable' : 'legacy';
        }
        if ( is_array( $wp_registered_widget_updates ) && isset( $wp_registered_widget_updates[ $id_base ] ) ) {
            return 'legacy';
        }
        return 'unknown';
    }

    









    protected function legacy_widget_error( string $id_base ): \RuntimeException {
        return new \RuntimeException(
            sprintf(
                'Read-only over MCP (no show_instance_in_rest): edit it in Appearance > Widgets, or use a settings_editable type such as "block". Moving and deleting still work. Widget type: %s',
                $id_base // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            )
        );
    }

    






    protected function position_argument( array $arguments ): ?int {
        if ( ! isset( $arguments['position'] ) || '' === $arguments['position'] ) {
            return null;
        }
        $value = $arguments['position'];
        if ( is_int( $value ) ) {
            $position = $value;
        } elseif ( is_string( $value ) && preg_match( '/^\d+$/', $value ) ) {
            $position = (int) $value;
        } else {
            $position = -1;
        }
        if ( $position < 0 ) {
            throw new \InvalidArgumentException( '`position` must be a non-negative integer (0 = first widget in the sidebar).' );
        }
        return $position;
    }

    






    protected function instance_argument( $value ): array {
        if ( is_object( $value ) ) {
            $value = (array) $value;
        }
        $settings = $this->parse_json_param( $value, '`instance`' );
        if ( ! empty( $settings ) && array_keys( $settings ) === range( 0, count( $settings ) - 1 ) ) {
            throw new \InvalidArgumentException( '`instance` must be an object of widget settings keyed by name (for a block widget: {"content": "<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->"}), not a list.' );
        }
        return $settings;
    }

    






    protected function raw_instance( array $settings ): array {
        return array( 'raw' => empty( $settings ) ? new \stdClass() : $settings );
    }

    







    protected function sidebar_widget_ids( string $sidebar_id ): array {
        $data = $this->rest_request( 'GET', '/wp/v2/sidebars/' . $sidebar_id, array( 'context' => 'edit' ), 'id,widgets' );
        return $this->widget_ids( isset( $data['widgets'] ) ? $data['widgets'] : array() );
    }

    







    protected function widget_ids( $widgets ): array {
        $ids = array();
        foreach ( is_array( $widgets ) ? $widgets : array() as $widget ) {
            if ( is_string( $widget ) ) {
                $ids[] = $widget;
            } elseif ( is_array( $widget ) && isset( $widget['id'] ) ) {
                $ids[] = (string) $widget['id'];
            }
        }
        return $ids;
    }

    




















    protected function apply_widget_position( string $widget_id, string $sidebar_id, int $position ): array {
        try {
            $current = $this->sidebar_widget_ids( $sidebar_id );
            $order   = array_values( array_diff( $current, array( $widget_id ) ) );
            $index   = min( $position, count( $order ) );
            array_splice( $order, $index, 0, array( $widget_id ) );

            if ( $order !== $current ) {
                $saved = $this->rest_request( 'PUT', '/wp/v2/sidebars/' . $sidebar_id, array( 'widgets' => $order ), 'id,widgets' );
                if ( isset( $saved['widgets'] ) && is_array( $saved['widgets'] ) ) {
                    $order = $this->widget_ids( $saved['widgets'] );
                }
            }

            $at = array_search( $widget_id, $order, true );
            return array(
                'applied'         => true,
                'position'        => false === $at ? $index : (int) $at,
                'sidebar_widgets' => $order,
            );
        } catch ( \Exception $e ) {
            return array(
                'applied' => false,
                'error'   => $e->getMessage(),
            );
        }
    }

    







    protected function with_position_result( array $result, array $position, string $verb ): array {
        if ( $position['applied'] ) {
            $result['position']        = $position['position'];
            $result['sidebar_widgets'] = $position['sidebar_widgets'];
            return $result;
        }
        $result['position_applied'] = false;
        $result['warning']          = sprintf(
            'The widget was %1$s, but moving it to the requested position failed: %2$s. It is at the end of sidebar "%3$s". Retry with wp_update_widget and only widget_id + position.',
            $verb,
            $position['error'],
            isset( $result['sidebar'] ) ? $result['sidebar'] : ''
        );
        return $result;
    }

    










    protected function shape_widget( array $data, bool $with_rendered ): array {
        $editable = isset( $data['instance'] ) && is_array( $data['instance'] ) && array_key_exists( 'raw', $data['instance'] );
        $settings = null;
        if ( $editable ) {
            $settings = $data['instance']['raw'];
            if ( is_object( $settings ) ) {
                $settings = (array) $settings;
            }
            if ( empty( $settings ) ) {
                $settings = new \stdClass();
            }
        }

        $shaped = array(
            'id'                => isset( $data['id'] ) ? (string) $data['id'] : '',
            'id_base'           => isset( $data['id_base'] ) ? (string) $data['id_base'] : '',
            'sidebar'           => isset( $data['sidebar'] ) ? (string) $data['sidebar'] : '',
            'settings_editable' => $editable,
            'settings'          => $settings,
        );
        if ( $with_rendered ) {
            $shaped['rendered'] = isset( $data['rendered'] ) ? (string) $data['rendered'] : '';
        }
        return $shaped;
    }
}
