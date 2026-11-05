<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Elementor_Adapter extends Layout_Adapter {

    const EDIT_MODE_KEY = '_elementor_edit_mode';

    const DATA_KEY = '_elementor_data';

    const LIBRARY_TYPE = 'elementor_library';

    const V4_ABILITY = 'elementor/manage-elements';

    const ROLES = array(
        'heading'        => array( 'text' => 'title', 'link' => 'link' ),
        'text-editor'    => array( 'text' => 'editor' ),
        'button'         => array( 'text' => 'text', 'link' => 'link' ),
        'image'          => array( 'image' => 'image', 'link' => 'link' ),
        'icon-box'       => array( 'text' => 'title_text', 'link' => 'link' ),
        'image-box'      => array( 'text' => 'title_text', 'image' => 'image', 'link' => 'link' ),
        'call-to-action' => array( 'text' => 'title', 'image' => 'bg_image', 'link' => 'link' ),
        'testimonial'    => array( 'text' => 'testimonial_content', 'image' => 'testimonial_image' ),
        'e-heading'      => array( 'text' => 'title', 'link' => 'link' ),
        'e-paragraph'    => array( 'text' => 'paragraph', 'link' => 'link' ),
        'e-button'       => array( 'text' => 'text', 'link' => 'link' ),
        'e-image'        => array( 'image' => 'image', 'link' => 'link' ),
    );

    const ALL_FIELDS = '*';

    const FALLBACK_TEXT = array( 'title', 'text', 'editor', 'title_text', 'description_text', 'description', 'content', 'caption' );

    const UI_CONTROLS = array( 'section', 'tab', 'tabs', 'heading', 'notice', 'raw_html', 'deprecated_notice', 'alert', 'divider', 'button', 'hidden' );

    const CONTROL_KINDS = array(
        'text'     => 'text',
        'textarea' => 'text',
        'wysiwyg'  => 'html',
        'code'     => 'code',
        'url'      => 'link',
        'media'    => 'image',
        'gallery'  => 'list',
        'select'   => 'select',
        'select2'  => 'select',
        'choose'   => 'select',
        'number'   => 'number',
        'slider'   => 'size',
        'switcher' => 'boolean',
        'color'    => 'color',
        'icons'    => 'icon',
        'icon'     => 'icon',
    );

    private $widgets_manager;

    private $documents;

    public function __construct( ?callable $widgets_manager = null, ?callable $documents = null ) {
        $this->widgets_manager = $widgets_manager;
        $this->documents       = $documents;
    }

    public function id(): string {
        return 'elementor';
    }

    public function label(): string {
        return 'Elementor';
    }

    public function is_present(): bool {
        return function_exists( 'did_action' ) && did_action( 'elementor/loaded' ) > 0 && class_exists( '\\Elementor\\Plugin' );
    }

    public function version(): string {
        return defined( 'ELEMENTOR_VERSION' ) ? (string) constant( 'ELEMENTOR_VERSION' ) : '';
    }

    public function owns( object $post ): bool {
        return self::LIBRARY_TYPE === $post->post_type
            || 'builder' === get_post_meta( $post->ID, self::EDIT_MODE_KEY, true );
    }

    public function renders_post_content( object $post ): bool {
        try {
            return array() === self::tree( (int) $post->ID );
        } catch ( \RuntimeException $e ) {
            return true;
        }
    }

    public function released_by( array $meta ): bool {
        return array_key_exists( self::EDIT_MODE_KEY, $meta ) && is_scalar( $meta[ self::EDIT_MODE_KEY ] ) && '' === (string) $meta[ self::EDIT_MODE_KEY ];
    }

    public function layout_meta_keys(): array {
        return array( self::DATA_KEY );
    }

    public function source( object $post ): string {
        $raw = get_post_meta( $post->ID, self::DATA_KEY, true );
        return is_string( $raw ) ? $raw : (string) wp_json_encode( $raw );
    }

    public function nodes( object $post ): array {
        return $this->build( self::tree( (int) $post->ID ) );
    }

    private static function tree( int $post_id ): array {
        $raw = get_post_meta( $post_id, self::DATA_KEY, true );
        if ( '' === $raw || array() === $raw ) {
            return array();
        }
        $tree = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
        if ( ! is_array( $tree ) ) {
            throw new \RuntimeException( sprintf( 'Post %d is built with Elementor, but its layout (%s) is not valid JSON, so it cannot be read.', $post_id, self::DATA_KEY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $tree;
    }

    private function build( array $elements ): array {
        $nodes = array();
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $el_type  = isset( $element['elType'] ) ? (string) $element['elType'] : '';
            $type     = 'widget' === $el_type && isset( $element['widgetType'] ) ? (string) $element['widgetType'] : $el_type;
            $settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
            $atomic   = 0 === strpos( $type, 'e-' ) || self::has_transformables( $settings );
            $roles    = self::ROLES[ $type ] ?? array();
            $flags    = $atomic ? array( 'atomic' ) : array();
            if ( 'html' === $type ) {
                $flags[] = 'code';
            }
            $nodes[] = self::node(
                array(
                    'address'  => isset( $element['id'] ) ? (string) $element['id'] : '',
                    'type'     => $type,
                    'text'     => self::excerpt( self::plain( $settings[ $roles['text'] ?? '' ] ?? self::fallback_text( $settings ) ) ),
                    'image'    => isset( $roles['image'] ) ? self::image( self::plain( $settings[ $roles['image'] ] ?? null ) ) : null,
                    'link'     => isset( $roles['link'] ) ? self::link( self::plain( $settings[ $roles['link'] ] ?? null ) ) : null,
                    'settings' => $settings,
                    'flags'    => $flags,
                    'lists'    => 'widget' === $el_type && ! $atomic ? $this->lists( $type, $settings ) : array(),
                    'children' => isset( $element['elements'] ) && is_array( $element['elements'] ) ? $this->build( $element['elements'] ) : array(),
                )
            );
        }
        return $nodes;
    }

    private static function fallback_text( array $settings ): ?string {
        foreach ( self::FALLBACK_TEXT as $key ) {
            $value = self::plain( $settings[ $key ] ?? null );
            if ( is_string( $value ) && '' !== trim( $value ) ) {
                return $value;
            }
        }
        return null;
    }

    private static function plain( $value ) {
        if ( is_array( $value ) && array_key_exists( '$$type', $value ) && array_key_exists( 'value', $value ) ) {
            return self::plain( $value['value'] );
        }
        if ( is_array( $value ) ) {
            return array_map( array( __CLASS__, 'plain' ), $value );
        }
        return $value;
    }

    private static function has_transformables( array $settings ): bool {
        foreach ( $settings as $value ) {
            if ( is_array( $value ) && array_key_exists( '$$type', $value ) ) {
                return true;
            }
        }
        return false;
    }

    private static function image( $media ): ?array {
        if ( ! is_array( $media ) ) {
            return null;
        }
        if ( isset( $media['src'] ) && is_array( $media['src'] ) ) {
            $media = $media['src'];
        }
        $image = array();
        if ( ! empty( $media['id'] ) && is_numeric( $media['id'] ) ) {
            $image['id'] = (int) $media['id'];
        }
        if ( ! empty( $media['url'] ) && is_string( $media['url'] ) ) {
            $image['url'] = $media['url'];
        }
        return array() === $image ? null : $image;
    }

    private static function link( $link ): ?string {
        if ( is_string( $link ) && '' !== $link ) {
            return $link;
        }
        if ( ! is_array( $link ) ) {
            return null;
        }
        foreach ( array( 'url', 'destination', 'href' ) as $key ) {
            if ( ! empty( $link[ $key ] ) && is_string( $link[ $key ] ) ) {
                return $link[ $key ];
            }
        }
        return null;
    }

    public function element_types(): array {
        $types = array();
        foreach ( $this->widgets() as $slug => $widget ) {
            if ( ! is_object( $widget ) ) {
                continue;
            }
            $categories = method_exists( $widget, 'get_categories' ) ? (array) $widget->get_categories() : array();
            $entry      = array(
                'type'     => (string) $slug,
                'label'    => method_exists( $widget, 'get_title' ) ? (string) $widget->get_title() : (string) $slug,
                'category' => isset( $categories[0] ) ? (string) $categories[0] : '',
            );
            if ( method_exists( $widget, 'get_props_schema' ) ) {
                $entry['atomic'] = true;
            }
            $types[] = $entry;
        }
        return $types;
    }

    public function element_fields( string $type ): ?array {
        $widget = $this->widget( $type );
        if ( null === $widget ) {
            return null;
        }
        self::refuse_front_end_controls();
        return self::content_fields( (array) $widget->get_controls(), true );
    }

    private static function refuse_front_end_controls(): void {
        $performance = 'Elementor\\Core\\Frontend\\Performance';
        if ( ! is_callable( array( $performance, 'should_optimize_controls' ) ) ) {
            return;
        }
        if ( true === $performance::should_optimize_controls() ) {
            throw new \RuntimeException( 'Elementor is treating this request as a front-end page view, where it labels every widget control (the CSS ID too) as Content, so the fields cannot be told apart. Nothing was changed.' );
        }
    }

    private static function content_fields( array $controls, bool $lists ): array {
        $fields = array();
        foreach ( $controls as $control ) {
            $field = self::content_field( $control, $lists );
            if ( null !== $field ) {
                $fields[] = $field;
            }
        }
        return $fields;
    }

    private static function content_field( $control, bool $lists ): ?array {
        if ( ! is_array( $control ) || empty( $control['name'] ) || empty( $control['type'] ) ) {
            return null;
        }
        if ( in_array( $control['type'], self::UI_CONTROLS, true ) || 'content' !== ( $control['tab'] ?? 'content' ) ) {
            return null;
        }
        $repeater = self::is_repeater( (string) $control['type'] );
        $field    = array(
            'name'    => (string) $control['name'],
            'kind'    => $repeater ? 'list' : ( self::CONTROL_KINDS[ $control['type'] ] ?? 'other' ),
            'label'   => isset( $control['label'] ) && is_string( $control['label'] ) ? $control['label'] : '',
            'control' => (string) $control['type'],
        );
        if ( ! empty( $control['responsive'] ) ) {
            $field['responsive'] = true;
        }
        if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
            $field['options'] = array_values( array_map( 'strval', array_keys( $control['options'] ) ) );
        }
        if ( 'select2' === $control['type'] && ! empty( $control['multiple'] ) ) {
            $field['multiple'] = true;
        }
        if ( 'switcher' === $control['type'] && isset( $control['return_value'] ) && is_scalar( $control['return_value'] ) && 'yes' !== (string) $control['return_value'] ) {
            $field['on_value'] = (string) $control['return_value'];
        }
        if ( isset( $control['default'] ) && is_scalar( $control['default'] ) && '' !== $control['default'] ) {
            $field['default'] = $control['default'];
        }
        if ( $lists && $repeater && isset( $control['fields'] ) && is_array( $control['fields'] ) ) {
            $field['item_fields'] = self::content_fields( $control['fields'], false );
            $min                  = isset( $control['min_items'] ) && is_int( $control['min_items'] ) && $control['min_items'] > 0 ? $control['min_items'] : 0;
            $field['min_items']   = $min > 0 ? $min : ( false === ( $control['prevent_empty'] ?? true ) ? 0 : 1 );
            if ( isset( $control['max_items'] ) && is_int( $control['max_items'] ) && $control['max_items'] > 0 ) {
                $field['max_items'] = $control['max_items'];
            }
        }
        return $field;
    }

    private static function is_repeater( string $type ): bool {
        if ( in_array( $type, array( 'repeater', 'nested-elements-repeater' ), true ) ) {
            return true;
        }
        $controls = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->controls_manager ) ? \Elementor\Plugin::$instance->controls_manager : null;
        return is_object( $controls ) && method_exists( $controls, 'get_control' ) && is_a( $controls->get_control( $type ), 'Elementor\\Control_Repeater' );
    }

    public function is_nested_widget( string $type ): bool {
        $widget = $this->widget( $type );
        return null !== $widget && is_a( $widget, 'Elementor\\Modules\\NestedElements\\Base\\Widget_Nested_Base' );
    }

    private function widget( string $type ): ?object {
        $widgets = $this->widgets();
        return isset( $widgets[ $type ] ) && is_object( $widgets[ $type ] ) && method_exists( $widgets[ $type ], 'get_controls' ) ? $widgets[ $type ] : null;
    }

    private function lists( string $type, array $settings ): array {
        $widget = $this->widget( $type );
        if ( null === $widget ) {
            return array();
        }
        $controls = (array) $widget->get_controls();
        $lists    = array();
        foreach ( $controls as $control ) {
            $field = self::content_field( $control, true );
            if ( null === $field || ! isset( $field['item_fields'] ) ) {
                continue;
            }
            $entry = array(
                'list'  => $field['name'],
                'label' => $field['label'],
                'items' => array(),
            );
            $flags  = array();
            $stored = $settings[ $field['name'] ] ?? null;
            if ( ! is_array( $stored ) ) {
                $flags[] = 'not_stored';
            } else {
                $ids = array();
                foreach ( $stored as $row ) {
                    $row = is_array( $row ) ? $row : array();
                    $id  = isset( $row[ Elementor_Editor::ITEM_ID ] ) && is_scalar( $row[ Elementor_Editor::ITEM_ID ] ) ? (string) $row[ Elementor_Editor::ITEM_ID ] : '';
                    if ( '' === $id || isset( $ids[ $id ] ) ) {
                        $flags[] = 'no_ids';
                    }
                    $ids[ $id ]       = true;
                    $entry['items'][] = array(
                        'item'  => $id,
                        'label' => self::row_label( $row, isset( $control['title_field'] ) && is_string( $control['title_field'] ) ? $control['title_field'] : '', $field['item_fields'] ),
                    );
                }
            }
            if ( ! self::list_visible( $widget, $control, $controls, $settings ) ) {
                $flags[] = 'hidden';
            }
            if ( array() !== $flags ) {
                $entry['flags'] = array_values( array_unique( $flags ) );
            }
            $lists[] = $entry;
        }
        return $lists;
    }

    private static function row_label( array $row, string $template, array $item_fields ): string {
        $text = array();
        foreach ( $item_fields as $spec ) {
            if ( in_array( $spec['kind'], Elementor_Editor::TEXT_KINDS, true ) && isset( $row[ $spec['name'] ] ) && is_string( $row[ $spec['name'] ] ) && '' !== trim( $row[ $spec['name'] ] ) ) {
                $text[ $spec['name'] ] = $row[ $spec['name'] ];
            }
        }
        $named = array();
        preg_match_all( '/\{\{\{?\s*([A-Za-z0-9_]+)\s*\}?\}\}/', $template, $matches );
        foreach ( $matches[1] as $name ) {
            if ( isset( $text[ $name ] ) ) {
                $named[] = $text[ $name ];
            }
        }
        $label = self::excerpt( array() !== $named ? implode( ' ', $named ) : reset( $text ) );
        return null === $label ? '' : $label;
    }

    private static function list_visible( object $widget, array $control, array $controls, array $settings ): bool {
        if ( ! method_exists( $widget, 'is_control_visible' ) ) {
            return true;
        }
        $values = $settings;
        foreach ( $controls as $other ) {
            if ( is_array( $other ) && isset( $other['name'] ) && ! array_key_exists( $other['name'], $values ) && array_key_exists( 'default', $other ) ) {
                $values[ $other['name'] ] = $other['default'];
            }
        }
        try {
            return (bool) $widget->is_control_visible( $control, $values, $controls );
        } catch ( \Throwable $e ) {
            return true;
        }
    }

    public function edit_refusal( object $post ): ?string {
        if ( ! $this->is_present() ) {
            return 'Elementor is not active, so this layout can be read but not saved.';
        }
        $document = $this->document( (int) $post->ID );
        if ( null === $document ) {
            return sprintf( 'Elementor cannot open post %d as an Elementor document, so its layout cannot be saved.', (int) $post->ID );
        }
        if ( ! $document->is_editable_by_current_user() ) {
            return 'Elementor does not let this account edit this item. Elementor refuses a trashed item, the Posts page, the WooCommerce Shop page, a post type not enabled under Elementor › Settings, and a role excluded under Elementor › Role Manager.';
        }
        $repeated = Elementor_Editor::repeated_ids( self::tree( (int) $post->ID ) );
        return array() === $repeated ? null : Elementor_Editor::repeated_message( $repeated );
    }

    public function edit( object $post, array $ops, bool $dry_run, ?callable $fence = null ): array {
        $post_id = (int) $post->ID;
        $refusal = $this->edit_refusal( $post );
        if ( null !== $refusal ) {
            throw self::refusal( array( $refusal ) );
        }
        $document = $this->document( $post_id );
        if ( null === $document ) {
            throw self::refusal( array( sprintf( 'Elementor cannot open post %d as an Elementor document.', $post_id ) ) );
        }
        $original = array_values( self::tree( $post_id ) );
        $editor   = new Elementor_Editor( array( $this, 'element_fields' ), array( __CLASS__, 'library_image' ), array( $this, 'is_nested_widget' ), current_user_can( 'unfiltered_html' ), self::containers_active() );
        $plan     = $editor->plan( $original, $ops );
        if ( array() !== $plan['errors'] ) {
            throw self::refusal( $plan['errors'] );
        }
        $encoded = wp_json_encode( $plan['data'] );
        if ( false === $encoded ) {
            throw self::refusal( array( 'The edited layout cannot be stored as JSON (a value is not a finite number or not valid UTF-8), so nothing was saved.' ) );
        }
        $warnings = array();
        $problems = self::save_problems( $document, $original, $plan['data'], $plan['results'], current_user_can( 'unfiltered_html' ), $warnings );
        if ( array() !== $problems ) {
            throw self::refusal( $problems );
        }

        $warnings = array_merge( $warnings, $this->hidden_list_warnings( $plan['data'], $plan['results'] ) );
        $editing  = self::editing_user( $post_id );
        if ( null !== $editing ) {
            $open = sprintf( '%s has post %d open in the editor; their next save there replaces the whole layout, this change included.', $editing, $post_id );
            if ( ! $dry_run ) {
                throw self::refusal( array( $open . ' Try again once they close it.' ) );
            }
            $warnings[] = $open;
        }

        $changed = wp_json_encode( $original ) !== $encoded;
        if ( $dry_run || ! $changed ) {
            return array(
                'changed'  => $changed,
                'saved'    => false,
                'results'  => $plan['results'],
                'warnings' => $warnings,
            );
        }

        if ( null !== $fence ) {
            $fence();
        }
        wp_cache_delete( $post_id, 'post_meta' );
        if ( wp_json_encode( array_values( self::tree( $post_id ) ) ) !== wp_json_encode( $original ) ) {
            throw new \RuntimeException( sprintf( 'The layout of post %d changed while this edit was being prepared, so nothing was saved. Read it again with wp_get_page_layout and redo the edit.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( true !== $document->save( array( 'elements' => $plan['data'] ) ) ) {
            throw new \RuntimeException( sprintf( 'Elementor did not save post %d: Document::save() refused it.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $stored = array_values( self::tree( $post_id ) );
        if ( wp_json_encode( $stored ) === wp_json_encode( $original ) ) {
            throw new \RuntimeException( sprintf( 'Elementor reported post %d saved, but its stored layout did not change: something stopped the write of %s (a plugin filter or a database error).', $post_id, self::DATA_KEY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $results = self::read_back( $plan['results'], $stored, $plan['data'], $warnings );
        return array(
            'changed'  => true,
            'saved'    => true,
            'results'  => $results,
            'warnings' => $warnings,
        );
    }

    public function replace_problems( object $original, object $copy, array &$warnings ): array {
        if ( ! $this->is_present() ) {
            return array( sprintf( 'Post %1$d is built with Elementor, which is not active, so its layout cannot be saved onto post %2$d.', (int) $copy->ID, (int) $original->ID ) );
        }
        $document = $this->document( (int) $original->ID );
        if ( null === $document ) {
            return array( sprintf( 'Elementor cannot open post %d as an Elementor document, so the copy\'s layout cannot be saved onto it.', (int) $original->ID ) );
        }
        if ( ! $document->is_editable_by_current_user() ) {
            return array( sprintf( 'Elementor does not let this account edit post %d. Elementor refuses a trashed item, the Posts page, the WooCommerce Shop page, a post type not enabled under Elementor › Settings, and a role excluded under Elementor › Role Manager.', (int) $original->ID ) );
        }
        try {
            $layout = array_values( self::tree( (int) $copy->ID ) );
        } catch ( \RuntimeException $e ) {
            return array( $e->getMessage() );
        }
        $repeated = Elementor_Editor::repeated_ids( $layout );
        if ( array() !== $repeated ) {
            return array( Elementor_Editor::repeated_message( $repeated ) );
        }
        return self::save_problems( $document, $layout, $layout, array(), current_user_can( 'unfiltered_html' ), $warnings );
    }

    public function replace_layout( object $original, object $copy ): void {
        $original_id = (int) $original->ID;
        $document    = $this->document( $original_id );
        if ( null === $document ) {
            throw new \RuntimeException( sprintf( 'Elementor cannot open post %d as an Elementor document, so the copy\'s layout was not saved onto it.', $original_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $layout   = array_values( self::tree( (int) $copy->ID ) );
        $expected = self::predicted_save( $document, $layout, current_user_can( 'unfiltered_html' ) );
        $before   = wp_json_encode( array_values( self::tree( $original_id ) ) );
        if ( true !== $document->save( array( 'elements' => $layout ) ) ) {
            throw new \RuntimeException( sprintf( 'Elementor did not save the copy\'s layout onto post %d: Document::save() refused it.', $original_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        wp_cache_delete( $original_id, 'post_meta' );
        $after = wp_json_encode( array_values( self::tree( $original_id ) ) );
        $emptied = array() !== $layout && '[]' === $after;
        if ( $emptied || ( $after === $before && ( ! is_array( $expected ) || wp_json_encode( array_values( $expected ) ) !== $before ) ) ) {
            throw new \RuntimeException( sprintf( 'Elementor reported post %1$d saved, but its stored layout %2$s: something stopped the write of %3$s (a plugin filter or a database error).', $original_id, $emptied ? 'is empty' : 'did not change', self::DATA_KEY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    public function release( object $post ): void {
        $post_id  = (int) $post->ID;
        $document = $this->is_present() ? $this->document( $post_id ) : null;
        if ( null === $document || ! method_exists( $document, 'set_is_built_with_elementor' ) ) {
            delete_post_meta( $post_id, self::EDIT_MODE_KEY );
        } else {
            $document->set_is_built_with_elementor( false );
            if ( class_exists( '\\Elementor\\Core\\Files\\CSS\\Post' ) ) {
                \Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
            }
        }
        wp_cache_delete( $post_id, 'post_meta' );
        if ( 'builder' === get_post_meta( $post_id, self::EDIT_MODE_KEY, true ) ) {
            throw new \RuntimeException( sprintf( 'Post %1$d is still marked as built with Elementor: something stopped the delete of %2$s (a plugin filter or a database error).', $post_id, self::EDIT_MODE_KEY ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    private static function containers_active(): bool {
        $experiments = class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->experiments ) ? \Elementor\Plugin::$instance->experiments : null;
        return is_object( $experiments ) && method_exists( $experiments, 'is_feature_active' ) && (bool) $experiments->is_feature_active( 'container' );
    }

    private function hidden_list_warnings( array $data, array $results ): array {
        $warnings = array();
        foreach ( $results as $result ) {
            if ( ! isset( $result['list'] ) ) {
                continue;
            }
            $path = Elementor_Tree::path( $data, (string) $result['element'] );
            if ( null === $path ) {
                continue;
            }
            $element = Elementor_Tree::at( $data, $path );
            foreach ( $this->lists( (string) $result['type'], $element['settings'] ) as $entry ) {
                if ( $entry['list'] === $result['list'] && in_array( 'hidden', $entry['flags'] ?? array(), true ) ) {
                    $warnings[] = sprintf( 'Elementor does not show list "%s" of "%s" while the widget\'s own settings hide it, so the page will not show this change until they do.', $result['list'], $result['element'] );
                }
            }
        }
        return array_values( array_unique( $warnings ) );
    }

    private static function read_back( array $results, array $stored, array $planned, array &$warnings ): array {
        $settings = self::settings_by_id( $stored );
        $intended = self::settings_by_id( $planned );
        $last = array();
        foreach ( $results as $index => $result ) {
            foreach ( self::read_keys( $result ) as $key ) {
                $last[ $key ] = $index;
            }
        }
        foreach ( $results as $index => $result ) {
            if ( 'add_item' !== $result['op'] && ! in_array( $result['op'], Elementor_Editor::SET_OPS, true ) ) {
                continue;
            }
            $address = (string) $result['element'];
            $source  = self::target( $settings, $result );
            if ( null === $source ) {
                if ( null === self::target( $intended, $result ) ) {
                    continue;
                }
                $source = array();
            }
            if ( 'add_item' === $result['op'] ) {
                if ( $last[ self::item_key( $result ) ] === $index ) {
                    $row = array() === $source ? null : $source;
                    self::compare( $result['after'], $row, sprintf( 'item "%s" of list "%s" in "%s"', $result['item'], $result['list'], $address ), $warnings );
                    $results[ $index ]['after']   = $row;
                    $results[ $index ]['changed'] = null !== $row;
                }
                continue;
            }
            $values = self::asked( $result );
            foreach ( $values as $field => $value ) {
                if ( $last[ self::field_key( $result, (string) $field ) ] !== $index ) {
                    continue;
                }
                $values[ $field ] = $source[ $field ] ?? null;
                self::compare( $value, $values[ $field ], sprintf( 'field "%s" of %s', $field, isset( $result['item'] ) ? sprintf( 'item "%s" in "%s"', $result['item'], $address ) : '"' . $address . '"' ), $warnings );
            }
            $results[ $index ]['after']   = 'set_fields' === $result['op'] ? $values : reset( $values );
            $results[ $index ]['changed'] = wp_json_encode( $result['before'] ) !== wp_json_encode( $results[ $index ]['after'] );
        }
        return $results;
    }

    private static function compare( $sent, $stored, string $what, array &$warnings ): void {
        if ( json_decode( (string) wp_json_encode( $sent ), true ) !== $stored ) {
            $warnings[] = sprintf( 'Elementor stored a different value for %s than was sent; `after` shows what it stored.', $what );
        }
    }

    private static function settings_by_id( array $elements ): array {
        $settings = array();
        foreach ( self::flatten( $elements ) as $element ) {
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
                $settings[ (string) $element['id'] ] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
            }
        }
        return $settings;
    }

    private static function target( array $settings, array $result ): ?array {
        $source = $settings[ (string) $result['element'] ] ?? null;
        if ( null !== $source && isset( $result['item'] ) ) {
            return self::stored_row( $source, (string) $result['list'], (string) $result['item'] );
        }
        return $source;
    }

    private static function stored_row( array $settings, string $list, string $item ): ?array {
        foreach ( is_array( $settings[ $list ] ?? null ) ? $settings[ $list ] : array() as $row ) {
            if ( is_array( $row ) && isset( $row[ Elementor_Editor::ITEM_ID ] ) && is_scalar( $row[ Elementor_Editor::ITEM_ID ] ) && (string) $row[ Elementor_Editor::ITEM_ID ] === $item ) {
                return $row;
            }
        }
        return null;
    }

    private static function read_keys( array $result ): array {
        $keys = isset( $result['item'] ) ? array( self::item_key( $result ) ) : array();
        if ( in_array( $result['op'], Elementor_Editor::SET_OPS, true ) ) {
            foreach ( array_keys( self::asked( $result ) ) as $field ) {
                $keys[] = self::field_key( $result, (string) $field );
            }
        }
        return $keys;
    }

    private static function item_key( array $result ): string {
        return $result['element'] . '|' . ( $result['list'] ?? '' ) . '|' . ( $result['item'] ?? '' );
    }

    private static function field_key( array $result, string $field ): string {
        return ( isset( $result['item'] ) ? self::item_key( $result ) : $result['element'] ) . '|' . $field;
    }

    private static function asked( array $result ): array {
        return 'set_fields' === $result['op'] ? (array) $result['after'] : array( (string) $result['field'] => $result['after'] );
    }

    private static function save_problems( object $document, array $original, array $data, array $results, bool $unfiltered, array &$warnings ): array {
        $predicted = self::predicted_save( $document, $data, $unfiltered );
        if ( is_string( $predicted ) ) {
            return array( $predicted );
        }
        $planned   = self::inventory( $data );
        $kept      = self::inventory( $predicted );
        $written   = self::written_fields( $results );
        $problems  = array();
        $untouched = array();
        foreach ( $planned as $id => $entry ) {
            if ( ! isset( $kept[ $id ] ) ) {
                if ( '' === $entry['parent'] || isset( $kept[ $entry['parent'] ] ) ) {
                    $problems[] = sprintf( 'Element "%s" (%s) needs a plugin that is not active; Elementor\'s save would remove it.', $id, $entry['type'] );
                }
                continue;
            }
            if ( $kept[ $id ]['parent'] !== $entry['parent'] || $kept[ $id ]['type'] !== $entry['type'] ) {
                $problems[] = sprintf( 'Elementor\'s save would move element "%s" or change its type.', $id );
                continue;
            }
            $differences = self::differences( $entry['leaves'], $kept[ $id ]['leaves'] );
            if ( array() !== $differences['tags'] ) {
                $problems[] = sprintf( 'Element "%s" uses a dynamic tag from a plugin that is not active (on %s); Elementor\'s save would remove it.', $id, implode( ', ', $differences['tags'] ) );
            }
            foreach ( $differences['fields'] as $field => $path ) {
                if ( array() !== array_intersect( array( (string) $field, self::ALL_FIELDS ), $written[ $id ] ?? array() ) ) {
                    $stored     = $kept[ $id ]['leaves'][ $path ] ?? null;
                    $problems[] = sprintf( 'Elementor would not store field "%s" of "%s" as sent; it would store %s.', $field, $id, is_string( $stored ) ? '"' . $stored . '"' : (string) wp_json_encode( $stored ) );
                } else {
                    $untouched[ $id ][] = (string) $field;
                }
            }
        }
        foreach ( array_diff_key( $kept, $planned ) as $id => $entry ) {
            $problems[] = sprintf( 'Elementor\'s save would add element "%s" (%s).', $id, $entry['type'] );
        }
        foreach ( self::reordered( $data, $predicted, array_map( 'strval', array_keys( $planned ) ) ) as $parent ) {
            $problems[] = '' === $parent ? 'Elementor\'s save would reorder the top-level elements.' : sprintf( 'Elementor\'s save would reorder the elements inside "%s".', $parent );
        }
        if ( array() !== $untouched ) {
            $untouched = self::resave_removals( $document, $original, $unfiltered, $untouched, $kept, $warnings );
        }
        if ( array() !== $untouched ) {
            $problems[] = self::untouched_problem( $untouched, $kept, $unfiltered );
        }
        return $problems;
    }

    private static function resave_removals( object $document, array $original, bool $unfiltered, array $untouched, array $kept, array &$warnings ): array {
        $resaved = self::predicted_save( $document, $original, $unfiltered );
        if ( is_string( $resaved ) ) {
            return $untouched;
        }
        $resaved = self::inventory( $resaved );
        $removed = array();
        foreach ( $untouched as $id => $fields ) {
            $left = array();
            foreach ( $fields as $field ) {
                if ( self::holds( $kept[ $id ]['leaves'], $field ) || ! isset( $resaved[ $id ] ) || self::holds( $resaved[ $id ]['leaves'], $field ) ) {
                    $left[] = $field;
                    continue;
                }
                $removed[ $field ] = ( $removed[ $field ] ?? 0 ) + 1;
            }
            if ( array() === $left ) {
                unset( $untouched[ $id ] );
            } else {
                $untouched[ $id ] = $left;
            }
        }
        if ( array() !== $removed ) {
            $counts = array();
            foreach ( $removed as $field => $count ) {
                $counts[] = sprintf( '%s (%d %s)', $field, $count, 1 === $count ? 'element' : 'elements' );
            }
            $warnings[] = sprintf( 'Elementor\'s save also removes fields this call does not write, as every save of this page does (Elementor\'s editor included): %s.', implode( ', ', $counts ) );
        }
        return $untouched;
    }

    private static function holds( array $leaves, string $field ): bool {
        foreach ( array_keys( $leaves ) as $path ) {
            if ( (string) $path === $field || 0 === strpos( (string) $path, $field . '.' ) ) {
                return true;
            }
        }
        return false;
    }

    private static function untouched_problem( array $untouched, array $kept, bool $unfiltered ): string {
        $named   = array();
        $heading = false;
        foreach ( $untouched as $id => $fields ) {
            foreach ( $fields as $field ) {
                $named[] = sprintf( '%s (%s)', $id, $field );
                $heading = $heading || ( 'title' === $field && 'heading' === $kept[ $id ]['type'] );
            }
        }
        $problem   = sprintf( 'Elementor\'s save would also change fields this call does not write: %s.', implode( ', ', $named ) );
        $admin     = current_user_can( 'manage_options' );
        $sanitized = $heading && ! $admin;
        if ( $unfiltered && ! $sanitized ) {
            $problem .= ' Elementor\'s own filters do not run on these fields for this account, so a plugin hooked into Elementor\'s save, or the element\'s own save step, makes this change.';
            return $admin ? $problem : $problem . ' Some plugins filter what an account that is not an administrator saves, so an administrator may be able to make this change.';
        }
        $filters = array_merge(
            $unfiltered ? array() : array( 'its HTML filter over the whole layout' ),
            $sanitized ? array( 'its sanitizer over heading titles' ) : array()
        );
        $problem .= sprintf( ' For this account Elementor\'s save runs %s, and a plugin hooked into the save can change fields too.', implode( ' and ', $filters ) );
        if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
            return $problem . ' No account on this site may post unfiltered HTML (DISALLOW_UNFILTERED_HTML), so if the HTML filter makes the change, every save of this page makes it, Elementor\'s editor included.';
        }
        return $problem . sprintf( ' Elementor\'s save runs neither of its filters on these fields for %s.', $heading ? 'an administrator who may post unfiltered HTML' : 'an account that may post unfiltered HTML' );
    }

    private static function differences( array $planned, array $stored ): array {
        $tags   = array();
        $fields = array();
        foreach ( $planned + $stored as $path => $unused ) {
            $in_plan  = array_key_exists( $path, $planned );
            $in_store = array_key_exists( $path, $stored );
            if ( $in_plan && $in_store && self::same_value( $planned[ $path ], $stored[ $path ] ) ) {
                continue;
            }
            $segments = explode( '.', (string) $path );
            if ( $in_plan && ! $in_store && in_array( Elementor_Editor::DYNAMIC_KEY, $segments, true ) ) {
                $tags[] = implode( '.', array_diff( $segments, array( Elementor_Editor::DYNAMIC_KEY ) ) );
                continue;
            }
            if ( ! isset( $fields[ $segments[0] ] ) ) {
                $fields[ $segments[0] ] = (string) $path;
            }
        }
        return array(
            'tags'   => $tags,
            'fields' => $fields,
        );
    }

    private static function reordered( array $planned, array $predicted, array $ids ): array {
        $before = self::child_order( $planned );
        $after  = self::child_order( $predicted );
        $moved  = array();
        foreach ( $before as $parent => $children ) {
            $common_before = array_values( array_intersect( $children, $after[ $parent ] ?? array() ) );
            $common_after  = array_values( array_intersect( $after[ $parent ] ?? array(), $children ) );
            if ( $common_before !== $common_after && ( '' === $parent || in_array( (string) $parent, $ids, true ) ) ) {
                $moved[] = (string) $parent;
            }
        }
        return $moved;
    }

    private static function child_order( array $elements, string $parent = '' ): array {
        $order = array( $parent => array() );
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) || ! isset( $element['id'] ) || ! is_scalar( $element['id'] ) ) {
                continue;
            }
            $id               = (string) $element['id'];
            $order[ $parent ][] = $id;
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $order += self::child_order( $element['elements'], $id );
            }
        }
        return $order;
    }

    private static function predicted_save( object $document, array $data, bool $unfiltered ) {
        if ( ! method_exists( $document, 'get_elements_raw_data' ) || ! method_exists( $document, 'set_is_saving' ) ) {
            return 'This Elementor version cannot report what its save keeps (Document::get_elements_raw_data() is missing), so nothing was saved.';
        }
        try {
            $filtered = apply_filters( 'elementor/document/save/data', array( 'elements' => $data ), $document );
            $filtered = is_array( $filtered ) ? $filtered : array();
            if ( ! $unfiltered ) {
                $filtered = self::kses_deep( $filtered );
            }
            $elements = isset( $filtered['elements'] ) && is_array( $filtered['elements'] ) ? $filtered['elements'] : array();
            $document->set_is_saving( true );
            try {
                $kept = $document->get_elements_raw_data( $elements );
            } finally {
                $document->set_is_saving( false );
            }
        } catch ( \Throwable $e ) {
            return 'Elementor refused this layout before saving it: ' . $e->getMessage();
        }
        return is_array( $kept ) ? $kept : array();
    }

    private static function kses_deep( array $data ): array {
        if ( is_callable( array( 'Elementor\\Utils', 'kses_post_deep' ) ) ) {
            $filtered = \Elementor\Utils::kses_post_deep( $data );
            return is_array( $filtered ) ? $filtered : array();
        }
        array_walk_recursive(
            $data,
            static function ( &$value ): void {
                if ( is_string( $value ) ) {
                    $value = wp_kses_post( $value );
                }
            }
        );
        return $data;
    }

    private static function same_value( $planned, $stored ): bool {
        if ( is_string( $planned ) && is_string( $stored ) ) {
            return html_entity_decode( $planned, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === html_entity_decode( $stored, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        }
        return wp_json_encode( $planned ) === wp_json_encode( $stored );
    }

    private static function written_fields( array $results ): array {
        $written = array();
        foreach ( $results as $result ) {
            foreach ( $result['created'] ?? array() as $id ) {
                $written[ $id ] = array( self::ALL_FIELDS );
            }
            if ( isset( $result['list'] ) ) {
                $fields = array( (string) $result['list'] );
            } elseif ( in_array( $result['op'], Elementor_Editor::SET_OPS, true ) ) {
                $fields = 'set_fields' === $result['op'] && is_array( $result['after'] ) ? array_map( 'strval', array_keys( $result['after'] ) ) : array( (string) $result['field'] );
            } else {
                continue;
            }
            $id             = (string) $result['element'];
            $written[ $id ] = array_merge( $written[ $id ] ?? array(), $fields );
        }
        return $written;
    }

    private static function inventory( array $elements, string $parent = '' ): array {
        $all = array();
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) || ! isset( $element['id'] ) || ! is_scalar( $element['id'] ) ) {
                continue;
            }
            $id         = (string) $element['id'];
            $el_type    = isset( $element['elType'] ) ? (string) $element['elType'] : '';
            $all[ $id ] = array(
                'type'   => 'widget' === $el_type && isset( $element['widgetType'] ) ? (string) $element['widgetType'] : $el_type,
                'parent' => $parent,
                'leaves' => self::leaves( isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array(), '' ),
            );
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $all += self::inventory( $element['elements'], $id );
            }
        }
        return $all;
    }

    private static function leaves( array $settings, string $prefix ): array {
        $leaves = array();
        foreach ( $settings as $key => $value ) {
            $path = $prefix . $key;
            if ( is_array( $value ) && array() !== $value ) {
                $leaves += self::leaves( $value, $path . '.' );
            } else {
                $leaves[ $path ] = $value;
            }
        }
        return $leaves;
    }

    private static function flatten( array $elements ): array {
        $all = array();
        foreach ( $elements as $element ) {
            if ( ! is_array( $element ) ) {
                continue;
            }
            $all[] = $element;
            if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
                $all = array_merge( $all, self::flatten( $element['elements'] ) );
            }
        }
        return $all;
    }

    private function document( int $post_id ): ?object {
        $manager = null;
        if ( null !== $this->documents ) {
            $manager = call_user_func( $this->documents );
        } elseif ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->documents ) ) {
            $manager = \Elementor\Plugin::$instance->documents;
        }
        if ( ! is_object( $manager ) || ! method_exists( $manager, 'get' ) ) {
            return null;
        }
        $document = $manager->get( $post_id, false );
        if ( ! is_object( $document ) || ! method_exists( $document, 'is_editable_by_current_user' ) || ! method_exists( $document, 'save' ) ) {
            return null;
        }
        $opened = method_exists( $document, 'get_post' ) ? $document->get_post() : null;
        return is_object( $opened ) && isset( $opened->ID ) && (int) $opened->ID === $post_id ? $document : null;
    }

    public function vendor_route( object $post, array $nodes ): ?array {
        unset( $post );
        if ( ! self::any_atomic( $nodes ) ) {
            return null;
        }
        $off = array();
        if ( ! self::ability_registered( self::V4_ABILITY ) ) {
            $off[] = 'Elementor’s abilities are not registered: they need Elementor 4.3 or later on WordPress 6.9 or later, and a change in Elementor’s code or an error while registering them also stops them (an error is written to the PHP error log)';
        }
        $enabled = array_map( 'strval', array_filter( (array) get_option( 'easy_mcp_ai_enabled_abilities', array() ), 'is_scalar' ) );
        if ( ! in_array( self::V4_ABILITY, $enabled, true ) ) {
            $off[] = 'Elementor’s abilities are not switched on in Easy MCP AI › Tools';
        }
        return array(
            'elements'  => 'atomic',
            'tool'      => 'wp_ability_elementor_manage_elements',
            'ability'   => self::V4_ABILITY,
            'available' => array() === $off,
            'switch_on' => $off,
            'note'      => 'Elements flagged `atomic` are Elementor V4 elements; Elementor’s own abilities read and edit them.',
        );
    }

    private static function ability_registered( string $name ): bool {
        return function_exists( 'wp_get_abilities' ) && isset( ( (array) wp_get_abilities() )[ $name ] );
    }

    private static function any_atomic( array $nodes ): bool {
        foreach ( $nodes as $node ) {
            if ( in_array( 'atomic', $node['flags'], true ) || self::any_atomic( $node['children'] ) ) {
                return true;
            }
        }
        return false;
    }

    private function widgets(): array {
        $manager = null;
        if ( null !== $this->widgets_manager ) {
            $manager = call_user_func( $this->widgets_manager );
        } elseif ( class_exists( '\\Elementor\\Plugin' ) && isset( \Elementor\Plugin::$instance->widgets_manager ) ) {
            $manager = \Elementor\Plugin::$instance->widgets_manager;
        }
        if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_widget_types' ) ) {
            return array();
        }
        $types = $manager->get_widget_types();
        return is_array( $types ) ? $types : array();
    }
}
