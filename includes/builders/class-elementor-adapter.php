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
        'repeater' => 'list',
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
        $widgets = $this->widgets();
        if ( ! isset( $widgets[ $type ] ) || ! is_object( $widgets[ $type ] ) || ! method_exists( $widgets[ $type ], 'get_controls' ) ) {
            return null;
        }
        $fields = array();
        foreach ( (array) $widgets[ $type ]->get_controls() as $control ) {
            if ( ! is_array( $control ) || empty( $control['name'] ) || empty( $control['type'] ) ) {
                continue;
            }
            if ( in_array( $control['type'], self::UI_CONTROLS, true ) || 'content' !== ( $control['tab'] ?? 'content' ) ) {
                continue;
            }
            $field = array(
                'name'    => (string) $control['name'],
                'kind'    => self::CONTROL_KINDS[ $control['type'] ] ?? 'other',
                'label'   => isset( $control['label'] ) && is_string( $control['label'] ) ? $control['label'] : '',
                'control' => (string) $control['type'],
            );
            if ( ! empty( $control['responsive'] ) ) {
                $field['responsive'] = true;
            }
            if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
                $field['options'] = array_values( array_map( 'strval', array_keys( $control['options'] ) ) );
            }
            
            if ( 'switcher' === $control['type'] && isset( $control['return_value'] ) && is_scalar( $control['return_value'] ) && 'yes' !== (string) $control['return_value'] ) {
                $field['on_value'] = (string) $control['return_value'];
            }
            if ( isset( $control['default'] ) && is_scalar( $control['default'] ) && '' !== $control['default'] ) {
                $field['default'] = $control['default'];
            }
            $fields[] = $field;
        }
        return $fields;
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
        $editor   = new Elementor_Editor( array( $this, 'element_fields' ), array( __CLASS__, 'library_image' ), current_user_can( 'unfiltered_html' ) );
        $plan     = $editor->plan( $original, $ops );
        if ( array() !== $plan['errors'] ) {
            throw self::refusal( $plan['errors'] );
        }
        
        
        $encoded = wp_json_encode( $plan['data'] );
        if ( false === $encoded ) {
            throw self::refusal( array( 'The edited layout cannot be stored as JSON (a value is not a finite number or not valid UTF-8), so nothing was saved.' ) );
        }
        $problems = self::save_problems( $document, $plan['data'], $plan['results'], current_user_can( 'unfiltered_html' ) );
        if ( array() !== $problems ) {
            throw self::refusal( $problems );
        }

        $warnings = array();
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
        $results = self::read_back( $plan['results'], $stored, $warnings );
        return array(
            'changed'  => true,
            'saved'    => true,
            'results'  => $results,
            'warnings' => $warnings,
        );
    }

    










    private static function read_back( array $results, array $stored, array &$warnings ): array {
        $settings = array();
        foreach ( self::flatten( $stored ) as $element ) {
            if ( isset( $element['id'] ) && is_scalar( $element['id'] ) ) {
                $settings[ (string) $element['id'] ] = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
            }
        }
        
        
        $last = array();
        foreach ( $results as $index => $result ) {
            foreach ( array_keys( self::asked( $result ) ) as $field ) {
                $last[ $result['element'] . '|' . $field ] = $index;
            }
        }
        foreach ( $results as $index => $result ) {
            $address = (string) $result['element'];
            $values  = self::asked( $result );
            foreach ( $values as $field => $value ) {
                if ( $last[ $address . '|' . $field ] !== $index ) {
                    continue;
                }
                $values[ $field ] = $settings[ $address ][ $field ] ?? null;
                if ( json_decode( (string) wp_json_encode( $value ), true ) !== $values[ $field ] ) {
                    $warnings[] = sprintf( 'Elementor stored a different value for field "%s" of "%s" than was sent; `after` shows what it stored.', $field, $address );
                }
            }
            $results[ $index ]['after']   = 'set_fields' === $result['op'] ? $values : reset( $values );
            $results[ $index ]['changed'] = wp_json_encode( $result['before'] ) !== wp_json_encode( $results[ $index ]['after'] );
        }
        return $results;
    }

    





    private static function asked( array $result ): array {
        return 'set_fields' === $result['op'] ? (array) $result['after'] : array( (string) $result['field'] => $result['after'] );
    }

    















    private static function save_problems( object $document, array $data, array $results, bool $unfiltered ): array {
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
                if ( in_array( (string) $field, $written[ $id ] ?? array(), true ) ) {
                    $stored     = $kept[ $id ]['leaves'][ $path ] ?? null;
                    $problems[] = sprintf( 'Elementor would not store field "%s" of "%s" as sent; it would store %s.', $field, $id, is_string( $stored ) ? '"' . $stored . '"' : (string) wp_json_encode( $stored ) );
                } else {
                    $untouched[] = sprintf( '%s (%s)', $id, $field );
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
            $problems[] = sprintf( 'Elementor\'s save would also change elements this call does not touch: %s. Elementor filters the HTML of the whole layout when an account that may not post unfiltered HTML saves, and every heading title when an account that is not an administrator saves. Ask an administrator to make this change.', implode( ', ', $untouched ) );
        }
        return $problems;
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
            $fields = 'set_fields' === $result['op'] && is_array( $result['after'] ) ? array_map( 'strval', array_keys( $result['after'] ) ) : array( (string) $result['field'] );
            $id     = (string) $result['element'];
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
            $off[] = \Easy_MCP_AI\Compat\Elementor_Abilities::group_enabled()
                ? 'Elementor’s abilities are not registered although Elementor is switched on in Easy MCP AI › Tools › Abilities: they need Elementor 4.3 or later on WordPress 6.9 or later, and a change in Elementor’s code or an error while registering them also stops them (an error is written to the PHP error log)'
                : 'Elementor’s abilities are not registered: switch on Elementor in Easy MCP AI › Tools › Abilities, which registers them without Elementor’s own MCP setting';
        }
        $enabled = array_map( 'strval', array_filter( (array) get_option( 'easy_mcp_ai_enabled_abilities', array() ), 'is_scalar' ) );
        if ( ! in_array( self::V4_ABILITY, $enabled, true ) ) {
            $off[] = 'Elementor’s abilities are not switched on in Easy MCP AI › Tools › Abilities';
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
