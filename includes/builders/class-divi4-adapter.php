<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi4_Adapter extends Shortcode_Adapter {

    const TESTED_VERSIONS = array( '4.27.9' );

    const FIELD_KINDS = array( 'text', 'select', 'number', 'color', 'boolean', 'size' );

    const USE_BUILDER_KEY = '_et_pb_use_builder';

    const CODE_TAGS = array( 'et_pb_code', 'et_pb_fullwidth_code' );

    const URL_ATTRIBUTES = array( 'url', 'button_link', 'button_url', 'image_src', 'redirect_url' );

    const ATTRIBUTE_ESCAPES = array(
        '%22' => '"',
        '%92' => '\\',
        '%91' => '[',
        '%93' => ']',
        '%5c' => '\\',
    );

    const CORE_TAGS = array(
        'et_pb_section', 'et_pb_row', 'et_pb_row_inner', 'et_pb_column', 'et_pb_column_inner',
        'et_pb_accordion', 'et_pb_accordion_item', 'et_pb_audio', 'et_pb_blog', 'et_pb_blurb', 'et_pb_button',
        'et_pb_circle_counter', 'et_pb_code', 'et_pb_comments', 'et_pb_contact_form', 'et_pb_contact_field',
        'et_pb_countdown_timer', 'et_pb_counters', 'et_pb_counter', 'et_pb_cta', 'et_pb_divider',
        'et_pb_filterable_portfolio', 'et_pb_fullwidth_code', 'et_pb_fullwidth_header', 'et_pb_fullwidth_image',
        'et_pb_fullwidth_map', 'et_pb_fullwidth_menu', 'et_pb_fullwidth_portfolio', 'et_pb_fullwidth_post_content',
        'et_pb_fullwidth_post_slider', 'et_pb_fullwidth_post_title', 'et_pb_fullwidth_slider', 'et_pb_gallery',
        'et_pb_heading', 'et_pb_icon', 'et_pb_image', 'et_pb_login', 'et_pb_map', 'et_pb_map_pin', 'et_pb_menu',
        'et_pb_number_counter', 'et_pb_portfolio', 'et_pb_post_content', 'et_pb_post_nav', 'et_pb_post_slider',
        'et_pb_post_title', 'et_pb_pricing_tables', 'et_pb_pricing_table', 'et_pb_search', 'et_pb_shop',
        'et_pb_sidebar', 'et_pb_signup', 'et_pb_signup_custom_field', 'et_pb_slider', 'et_pb_slide',
        'et_pb_social_media_follow', 'et_pb_social_media_follow_network', 'et_pb_tabs', 'et_pb_tab',
        'et_pb_team_member', 'et_pb_testimonial', 'et_pb_text', 'et_pb_toggle', 'et_pb_video',
        'et_pb_video_slider', 'et_pb_video_slider_item',
    );

    const ROLES = array(
        'et_pb_text'                        => array( 'text' => 'content' ),
        'et_pb_blurb'                       => array( 'text' => 'title', 'link' => 'url', 'image' => 'image' ),
        'et_pb_button'                      => array( 'text' => 'button_text', 'link' => 'button_url' ),
        'et_pb_image'                       => array( 'image' => 'src', 'link' => 'url' ),
        'et_pb_fullwidth_image'             => array( 'image' => 'src', 'link' => 'url' ),
        'et_pb_cta'                         => array( 'text' => 'title', 'link' => 'button_url' ),
        'et_pb_heading'                     => array( 'text' => 'title', 'link' => 'url' ),
        'et_pb_slide'                       => array( 'text' => 'heading', 'link' => 'button_link', 'image' => 'image' ),
        'et_pb_fullwidth_header'            => array( 'text' => 'title', 'link' => 'button_one_url', 'image' => 'logo_image_url' ),
        'et_pb_toggle'                      => array( 'text' => 'title' ),
        'et_pb_accordion_item'              => array( 'text' => 'title' ),
        'et_pb_tab'                         => array( 'text' => 'title' ),
        'et_pb_testimonial'                 => array( 'text' => 'content', 'link' => 'url', 'image' => 'portrait_url' ),
        'et_pb_team_member'                 => array( 'text' => 'name', 'image' => 'image_url' ),
        'et_pb_pricing_table'               => array( 'text' => 'title', 'link' => 'button_url' ),
        'et_pb_number_counter'              => array( 'text' => 'title' ),
        'et_pb_circle_counter'              => array( 'text' => 'title' ),
        'et_pb_counter'                     => array( 'text' => 'content' ),
        'et_pb_icon'                        => array( 'link' => 'url' ),
        'et_pb_login'                       => array( 'text' => 'title' ),
        'et_pb_map_pin'                     => array( 'text' => 'title' ),
        'et_pb_contact_field'               => array( 'text' => 'field_title' ),
        'et_pb_social_media_follow_network' => array( 'link' => 'url' ),
    );

    const TYPE_KINDS = array(
        'text'               => 'text',
        'textarea'           => 'text',
        'tiny_mce'           => 'html',
        'codemirror'         => 'code',
        'upload'             => 'image',
        'select'             => 'select',
        'yes_no_button'      => 'boolean',
        'color'              => 'color',
        'color-alpha'        => 'color',
        'range'              => 'size',
        'custom_margin'      => 'spacing',
        'custom_padding'     => 'spacing',
        'select_icon'        => 'icon',
        'upload-gallery'     => 'list',
        'options_list'       => 'list',
        'sortable_list'      => 'list',
        'date_picker'        => 'text',
    );

    const HIDDEN_TYPES = array( 'computed', 'skip', 'hidden', 'warning', 'font', 'composite', 'background-field', 'divider' );

    private $module_reader;

    private ?array $modules = null;

    public function __construct( ?callable $module_reader = null, ?callable $save_filter = null ) {
        parent::__construct( $save_filter ?? array( __CLASS__, 'predicted' ) );
        $this->module_reader = $module_reader;
    }

    public static function predicted( string $content ): string {
        $stored = Shortcode_Adapter::saved_content( $content );
        if ( function_exists( 'et_builder_sanitize_dynamic_content_fields' ) ) {
            $data   = et_builder_sanitize_dynamic_content_fields( array( 'post_content' => wp_slash( $stored ) ) );
            $stored = is_array( $data ) && isset( $data['post_content'] ) ? wp_unslash( (string) $data['post_content'] ) : $stored;
        }
        return $stored;
    }

    public function id(): string {
        return 'divi4';
    }

    public function label(): string {
        return 'Divi 4';
    }

    public function is_present(): bool {
        return defined( 'ET_BUILDER_PRODUCT_VERSION' );
    }

    public function version(): string {
        return defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? (string) constant( 'ET_BUILDER_PRODUCT_VERSION' ) : '';
    }

    public function owns( object $post ): bool {
        if ( ! Divi_Access::is_divi_type( $post ) && 'on' !== get_post_meta( (int) $post->ID, self::USE_BUILDER_KEY, true ) ) {
            return false;
        }
        if ( Divi5_Adapter::is_divi5( $post ) ) {
            return false;
        }
        foreach ( $this->layout_items( $this->source( $post ) ) as $item ) {
            if ( Shortcode_Tree::TEXT !== $item['tag'] ) {
                return true;
            }
        }
        return false;
    }

    protected function tags(): array {
        return array_values( array_unique( array_merge( array_keys( $this->modules() ), self::CORE_TAGS ) ) );
    }

    protected function code_tags(): array {
        return self::CODE_TAGS;
    }

    public function tested_versions(): array {
        return self::TESTED_VERSIONS;
    }

    public function field_kinds(): array {
        return self::FIELD_KINDS;
    }

    public function structure_rules(): ?Structure_Rules {
        $modules = $this->modules();
        return array() === $modules ? null : new Divi4_Rules( $modules, $this->version() );
    }

    protected function builder_access_refusal( object $post ): ?string {
        if ( array() === $this->modules() ) {
            return 'Divi\'s module registry could not be loaded on this site, so this layout can be read but not edited.';
        }
        return Divi_Access::refusal( $post );
    }

    public function layout_refusal( array $item, string $role ): ?string {
        $atts   = isset( $item['atts'] ) && is_array( $item['atts'] ) ? $item['atts'] : array();
        $inside = (string) ( $atts['global_parent'] ?? '' );
        if ( '' !== $inside ) {
            return sprintf( 'it is part of a global Divi element, which renders from Divi Library item %1$s; edit that item (wp_get_page_layout with post_id %1$s).', $inside );
        }
        $global = (string) ( $atts['global_module'] ?? '' );
        if ( '' !== $global && 'own' !== $role ) {
            return sprintf( 'it is %1$s global Divi element, which renders from Divi Library item %2$s; edit that item (wp_get_page_layout with post_id %2$s).', 'inside' === $role ? 'inside a' : 'a', $global );
        }
        $locked = 'on' === ( $atts['locked'] ?? '' ) ? Divi_Access::lock_refusal( 'the ' . (string) $item['tag'] ) : null;
        if ( null === $locked && 'edit' === $role && '' === (string) ( $atts['_builder_version'] ?? '' ) ) {
            return 'it has no _builder_version, so Divi takes it for 3.0.47 data and runs every later settings migration over what is written; open and save the page in the Divi Builder first, which records the version on every element.';
        }
        return $locked;
    }

    public function op_refusal( string $op, string $type ): ?string {
        return Divi_Access::op_refusal( $op, $type );
    }

    public function field_refusal( array $item, string $field ): ?string {
        $listed = isset( $item['atts']['_dynamic_attributes'] ) ? explode( ',', (string) $item['atts']['_dynamic_attributes'] ) : array();
        if ( in_array( $field, $listed, true ) ) {
            return sprintf( 'field "%s" is filled by Divi dynamic content; change that binding in the Divi Builder.', $field );
        }
        $tag = (string) $item['tag'];
        return Divi_Access::module_refusal( $tag ) ?? Divi_Access::field_refusal( $field, (string) ( $this->modules()[ $tag ]['fields'][ $field ]['option_category'] ?? '' ) );
    }

    public function references( array $nodes ): array {
        $references = array();
        foreach ( $nodes as $node ) {
            $global = isset( $node['settings']['global_module'] ) && is_scalar( $node['settings']['global_module'] ) ? (string) $node['settings']['global_module'] : '';
            if ( '' !== $global && ctype_digit( $global ) ) {
                $references[] = array(
                    'address' => $node['address'],
                    'type'    => $node['type'],
                    'ref'     => 'post:' . $global,
                    'note'    => 'A global Divi element: the page renders Divi Library item ' . $global . ', so edit that item with wp_get_page_layout and wp_update_page_layout.',
                );
                continue;
            }
            $references = array_merge( $references, $this->references( $node['children'] ) );
        }
        return $references;
    }

    public function encode_attribute( string $tag, string $name, array $spec, string $value ): string {
        unset( $tag, $spec );
        if ( in_array( $name, self::URL_ATTRIBUTES, true ) ) {
            return str_replace( array( '"', '[', ']', '\\' ), array( '%22', '%5B', '%5D', '%5C' ), $value );
        }
        return str_replace( array( '\\', '"', '[', ']' ), array( '%92', '%22', '%91', '%93' ), $value );
    }

    public function value_refusal( string $tag, string $name, string $value ): ?string {
        unset( $tag );
        if ( in_array( $name, self::URL_ATTRIBUTES, true ) ) {
            $query = (string) wp_parse_url( $value, PHP_URL_QUERY );
            foreach ( array( '%91', '%93' ) as $escape ) {
                if ( false !== strpos( $query, $escape ) ) {
                    return sprintf( 'holds "%s" in its query, which Divi turns into %s before it checks the URL, so it was not written', $escape, '%91' === $escape ? '&#91;' : '&#93;' );
                }
            }
            return null;
        }
        foreach ( self::ATTRIBUTE_ESCAPES as $escape => $character ) {
            if ( false !== strpos( $value, $escape ) ) {
                return sprintf( 'holds "%s", which Divi shows as %s (it has no way to store "%s" itself), so it was not written', $escape, $character, $escape );
            }
        }
        return null;
    }

    public function field_warning( array $item, string $field ): ?string {
        $atts = isset( $item['atts'] ) && is_array( $item['atts'] ) ? $item['atts'] : array();
        if ( 'on' !== explode( '|', (string) ( $atts[ $field . '_last_edited' ] ?? '' ) )[0] ) {
            return null;
        }
        $tablet = array_key_exists( $field . '_tablet', $atts );
        $phone  = array_key_exists( $field . '_phone', $atts );
        if ( ! $tablet && ! $phone ) {
            return null;
        }
        $kept = array_keys( array_filter( array( $field . '_tablet' => $tablet, $field . '_phone' => $phone ) ) );
        return sprintf( 'field "%1$s" also has %2$s, which Divi still shows on %3$s (%1$s_last_edited is on).', $field, implode( ' and ', $kept ), $tablet ? 'tablets and phones' : 'phones' );
    }

    public function link_attributes( string $tag, string $field, array $spec, array $decoded, string $url, ?bool $new_tab, ?bool $nofollow, array $fields ) {
        unset( $decoded );
        if ( null !== $nofollow ) {
            return sprintf( 'Divi has no nofollow switch on "%s"; a button keeps its rel values in button_rel (set_fields).', $field );
        }
        $refusal = $this->value_refusal( $tag, $field, $url );
        if ( null !== $refusal ) {
            return sprintf( 'field "%s" %s.', $field, $refusal );
        }
        $changes = array( $field => $this->encode_attribute( $tag, $field, $spec, $url ) );
        if ( null === $new_tab ) {
            return $changes;
        }
        $switch = '';
        foreach ( array( $field . '_new_window', 'url_new_window' ) as $name ) {
            foreach ( $fields as $candidate ) {
                if ( $name === ( $candidate['name'] ?? '' ) ) {
                    $switch = $name;
                    break 2;
                }
            }
        }
        if ( '' === $switch ) {
            return sprintf( '%s elements have no new-tab setting for "%s".', $tag, $field );
        }
        $changes[ $switch ] = $new_tab ? 'on' : 'off';
        return $changes;
    }

    public function image_attributes( string $tag, string $field, array $spec, array $media ): array {
        return array( $field => $this->encode_attribute( $tag, $field, $spec, $media['url'] ) );
    }

    protected function after_save( int $post_id ): void {
        Divi_Access::after_save( $post_id );
    }

    protected function roles( string $tag ): array {
        if ( isset( self::ROLES[ $tag ] ) ) {
            return self::ROLES[ $tag ];
        }
        return ! empty( $this->modules()[ $tag ]['content'] ) ? array( 'text' => 'content' ) : array();
    }

    protected function tag_label( string $tag ): string {
        $label = $this->modules()[ $tag ]['label'] ?? '';
        return '' !== $label ? $label : $tag;
    }

    protected function decode_atts( string $tag, array $atts ): array {
        unset( $tag );
        $decoded = array();
        foreach ( $atts as $name => $value ) {
            $value            = (string) $value;
            $decoded[ $name ] = in_array( (string) $name, self::URL_ATTRIBUTES, true ) ? $value : str_replace( array( '%22', '%92', '%91', '%93', '%5c' ), array( '"', '\\', '[', ']', '\\' ), $value );
        }
        return $decoded;
    }

    protected function decode_content( string $tag, string $content ): string {
        if ( in_array( $tag, Divi4_Rules::STRUCTURE, true ) || '' !== ( $this->modules()[ $tag ]['child_slug'] ?? '' ) ) {
            return $content;
        }
        return 0 === strpos( $content, "\n\n" ) && "\n\n" === substr( $content, -2 ) && strlen( $content ) >= 4 ? substr( $content, 2, -2 ) : $content;
    }

    public function element_types(): array {
        $types = array();
        foreach ( $this->modules() as $slug => $module ) {
            $entry = array(
                'type'     => (string) $slug,
                'label'    => $module['label'],
                'category' => in_array( $slug, Divi4_Rules::STRUCTURE, true ) ? 'structure' : ( $module['child'] ? 'child' : ( $module['fullwidth'] ? 'fullwidth' : 'module' ) ),
            );
            if ( in_array( $slug, self::CODE_TAGS, true ) ) {
                $entry['code'] = true;
            }
            $types[] = $entry;
        }
        return $types;
    }

    public function element_fields( string $type ): ?array {
        $modules = $this->modules();
        if ( ! isset( $modules[ $type ] ) ) {
            return null;
        }
        $fields = array();
        foreach ( $modules[ $type ]['fields'] as $name => $field ) {
            $spec = self::field( (string) $name, $field );
            if ( null !== $spec ) {
                $fields[] = $spec;
            }
        }
        return $fields;
    }

    private static function field( string $name, array $field ): ?array {
        $type = (string) ( $field['type'] ?? '' );
        $tab  = (string) ( $field['tab_slug'] ?? 'general' );
        if ( 'general' !== $tab || in_array( $type, self::HIDDEN_TYPES, true ) || 1 === preg_match( '/(?:_tablet|_phone|_last_edited|__hover|__hover_enabled|__sticky|__sticky_enabled)$/', $name ) ) {
            return null;
        }
        $kind = self::TYPE_KINDS[ $type ] ?? 'other';
        if ( 'text' === $kind && 'url' === ( $field['dynamic_content'] ?? '' ) ) {
            $kind = 'link';
        }
        if ( 'image' === $kind && ! in_array( (string) ( $field['data_type'] ?? 'image' ), array( '', 'image' ), true ) ) {
            $kind = 'other';
        }
        if ( 0 === strpos( $name, 'custom_css_' ) ) {
            $kind = 'code';
        }
        $spec = array(
            'name'  => $name,
            'kind'  => $kind,
            'label' => isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : '',
        );
        if ( isset( $field['options'] ) && is_array( $field['options'] ) && in_array( $kind, array( 'select', 'boolean' ), true ) ) {
            $spec['options'] = array_map( 'strval', array_keys( $field['options'] ) );
        }
        if ( 'boolean' === $kind ) {
            $spec['on_value']  = 'on';
            $spec['off_value'] = 'off';
        }
        if ( ! empty( $field['mobile_options'] ) ) {
            $spec['responsive'] = true;
        }
        $default = $field['default_on_front'] ?? ( $field['default'] ?? null );
        if ( is_scalar( $default ) && '' !== (string) $default ) {
            $spec['default'] = $default;
        }
        return $spec;
    }

    private function modules(): array {
        if ( null === $this->modules ) {
            $all           = null !== $this->module_reader ? call_user_func( $this->module_reader ) : self::module_reader();
            $this->modules = is_array( $all ) ? $all : array();
        }
        return $this->modules;
    }

    public static function module_reader(): array {
        if ( Divi5_Adapter::divi5_active() || ! class_exists( 'ET_Builder_Element' ) || ! method_exists( 'ET_Builder_Element', 'get_modules' ) ) {
            return array();
        }
        try {
            if ( array() === (array) \ET_Builder_Element::get_modules() && function_exists( 'et_builder_add_main_elements' ) ) {
                add_filter( 'et_builder_should_load_all_module_data', '__return_true' );
                if ( function_exists( 'et_builder_init_global_settings' ) ) {
                    et_builder_init_global_settings();
                }
                et_builder_add_main_elements();
            }
            $registry = array();
            foreach ( (array) \ET_Builder_Element::get_modules() as $slug => $module ) {
                if ( ! is_object( $module ) ) {
                    continue;
                }
                $fields              = isset( $module->fields_unprocessed ) && is_array( $module->fields_unprocessed ) ? $module->fields_unprocessed : array();
                $registry[ $slug ] = array(
                    'label'      => isset( $module->name ) && is_string( $module->name ) ? $module->name : (string) $slug,
                    'child_slug' => isset( $module->child_slug ) && is_string( $module->child_slug ) ? $module->child_slug : '',
                    'child'      => isset( $module->type ) && 'child' === $module->type,
                    'fullwidth'  => ! empty( $module->fullwidth ),
                    'content'    => isset( $fields['content'] ) || isset( $fields['raw_content'] ),
                    'fields'     => array_filter( $fields, 'is_array' ),
                );
            }
            return $registry;
        } catch ( \Throwable $e ) {
            return array();
        }
    }
}
