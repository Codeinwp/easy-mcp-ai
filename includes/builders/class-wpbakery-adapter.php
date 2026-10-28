<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class WPBakery_Adapter extends Shortcode_Adapter {

    const TESTED_VERSIONS = array( '6.0.5', '9.0.1' );

    const LINK_TYPES = array( 'vc_link', 'link' );

    const SETTINGS_TAGS = array( 'vc_basic_grid', 'vc_masonry_grid', 'vc_media_grid', 'vc_masonry_media_grid' );

    const FIELD_KINDS = array( 'text', 'select', 'number', 'color', 'boolean' );

    const STATUS_KEY = '_wpb_vc_js_status';

    const CORE_TAGS = array(
        'vc_section', 'vc_row', 'vc_column', 'vc_row_inner', 'vc_column_inner',
        'vc_column_text', 'vc_custom_heading', 'vc_btn', 'vc_single_image', 'vc_cta',
        'vc_message', 'vc_separator', 'vc_text_separator', 'vc_empty_space', 'vc_icon',
        'vc_toggle', 'vc_tta_tabs', 'vc_tta_accordion', 'vc_tta_tour', 'vc_tta_section',
        'vc_gallery', 'vc_images_carousel', 'vc_video', 'vc_gmaps', 'vc_raw_html', 'vc_raw_js',
        'vc_widget_sidebar', 'vc_basic_grid', 'vc_masonry_grid',
    );

    const CODE_TAGS = array( 'vc_raw_html', 'vc_raw_js', 'vc_gmaps' );

    const ENCODED_TYPES = array( 'textarea_raw_html', 'textarea_ace' );

    const ENCODED_CONTENT_TAGS = array( 'vc_raw_html', 'vc_raw_js' );

    const ROLES = array(
        'vc_column_text'    => array( 'text' => 'content' ),
        'vc_custom_heading' => array( 'text' => 'text', 'link' => 'link' ),
        'vc_btn'            => array( 'text' => 'title', 'link' => 'link' ),
        'vc_single_image'   => array( 'image' => 'image', 'link' => 'link' ),
        'vc_cta'            => array( 'text' => 'h2', 'link' => 'btn_link' ),
        'vc_message'        => array( 'text' => 'content' ),
        'vc_toggle'         => array( 'text' => 'title' ),
        'vc_tta_section'    => array( 'text' => 'title' ),
        'vc_text_separator' => array( 'text' => 'title' ),
    );

    const PARAM_KINDS = array(
        'textfield'         => 'text',
        'textarea'          => 'text',
        'textarea_safe'     => 'text',
        'textarea_html'     => 'html',
        'textarea_raw_html' => 'code',
        'textarea_ace'      => 'code',
        'vc_link'           => 'link',
        'link'              => 'link',
        'href'              => 'link',
        'attach_image'      => 'image',
        'attach_images'     => 'list',
        'dropdown'          => 'select',
        'button_group'      => 'select',
        'checkbox'          => 'boolean',
        'toggle'            => 'boolean',
        'number'            => 'number',
        'colorpicker'       => 'color',
        'iconpicker'        => 'icon',
        'param_group'       => 'list',
        'css_editor'        => 'spacing',
        'el_id'             => 'text',
    );

    private $map_reader;

    private ?array $map = null;

    public function __construct( ?callable $map_reader = null, ?callable $save_filter = null ) {
        parent::__construct( $save_filter );
        $this->map_reader = $map_reader;
    }

    public function id(): string {
        return 'wpbakery';
    }

    public function label(): string {
        return 'WPBakery Page Builder';
    }

    public function is_present(): bool {
        return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' );
    }

    public function version(): string {
        return defined( 'WPB_VC_VERSION' ) ? (string) constant( 'WPB_VC_VERSION' ) : '';
    }

    public function owns( object $post ): bool {
        return 'true' === get_post_meta( $post->ID, self::STATUS_KEY, true )
            || 1 === preg_match( '/^\s*\[vc_(?:row|section)[\s\]]/', (string) $post->post_content );
    }

    protected function tags(): array {
        $map = $this->map();
        return array() === $map ? self::CORE_TAGS : array_values( array_unique( array_merge( array_keys( $map ), self::CORE_TAGS ) ) );
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

    public function element_refusal( string $tag ): ?string {
        if ( in_array( $tag, self::SETTINGS_TAGS, true ) ) {
            return 'WPBakery keeps this grid\'s query in the post\'s settings, which only its own editor updates; change it in WPBakery.';
        }
        return parent::element_refusal( $tag );
    }

    protected function builder_access_refusal( object $post ): ?string {
        if ( ! function_exists( 'vc_user_access' ) || ! function_exists( 'vc_check_post_type' ) || ! function_exists( 'vc_user_access_check_shortcode_edit' ) ) {
            return 'WPBakery\'s Role Manager cannot be read on this site, so this layout can be read but not edited.';
        }
        try {
            if ( ! vc_check_post_type( (string) $post->post_type ) ) {
                return sprintf( 'WPBakery is not enabled for the post type "%s" (WPBakery Page Builder › Role Manager › Post types), so this layout can be read but not edited.', (string) $post->post_type );
            }
            if ( ! vc_user_access()->part( 'backend_editor' )->can()->get() ) {
                return 'WPBakery\'s Role Manager does not give this account the backend editor, so this layout can be read but not edited.';
            }
        } catch ( \Throwable $e ) {
            return 'WPBakery\'s Role Manager could not be read, so this layout can be read but not edited.';
        }
        return null;
    }

    public function element_access_refusal( string $tag ): ?string {
        if ( ! function_exists( 'vc_user_access_check_shortcode_edit' ) ) {
            return null;
        }
        try {
            $allowed = vc_user_access_check_shortcode_edit( $tag );
        } catch ( \Throwable $e ) {
            $allowed = false;
        }
        return $allowed ? null : sprintf( 'WPBakery\'s Role Manager does not let this account edit %s elements.', $tag );
    }

    public function encode_attribute( string $tag, string $name, array $spec, string $value ): string {
        unset( $tag, $name );
        if ( 'textarea_safe' === ( $spec['param'] ?? '' ) && 1 === preg_match( '/"|http/', $value ) ) {
            return '#E-8_' . base64_encode( rawurlencode( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WPBakery's own storage format.
        }
        return self::to_safe( $value );
    }

    public static function to_safe( string $value ): string {
        return str_replace( array( '"', '[', ']' ), array( '``', '`{`', '`}`' ), $value );
    }

    public function link_attributes( string $tag, string $field, array $spec, array $decoded, string $url, ?bool $new_tab, ?bool $nofollow, array $fields ) {
        unset( $fields );
        $param = (string) ( $spec['param'] ?? '' );
        if ( ! in_array( $param, self::LINK_TYPES, true ) ) {
            if ( null !== $new_tab || null !== $nofollow ) {
                return sprintf( 'field "%s" stores only a URL, so it has no new-tab or nofollow setting.', $field );
            }
            return array( $field => self::to_safe( $url ) );
        }
        $link        = isset( $decoded[ $field ] ) && is_array( $decoded[ $field ] ) ? $decoded[ $field ] : array();
        $link['url'] = $url;
        if ( null !== $new_tab ) {
            $link['target'] = $new_tab ? '_blank' : '';
        }
        if ( null !== $nofollow ) {
            $link['rel'] = $nofollow ? 'nofollow' : '';
        }
        return array( $field => self::build_link( $link ) );
    }

    public static function build_link( array $link ): string {
        $parts = array();
        foreach ( array( 'url', 'title', 'target', 'rel' ) as $key ) {
            $value = isset( $link[ $key ] ) && is_scalar( $link[ $key ] ) ? (string) $link[ $key ] : '';
            if ( '' !== $value ) {
                $parts[] = $key . ':' . rawurlencode( $value );
            }
        }
        return implode( '|', $parts );
    }

    protected function roles( string $tag ): array {
        return self::ROLES[ $tag ] ?? array( 'text' => 'content' );
    }

    protected function tag_label( string $tag ): string {
        $map = $this->map();
        return isset( $map[ $tag ]['name'] ) && is_string( $map[ $tag ]['name'] ) ? $map[ $tag ]['name'] : $tag;
    }

    protected function decode_atts( string $tag, array $atts ): array {
        $types   = $this->param_types( $tag );
        $decoded = array();
        foreach ( $atts as $name => $value ) {
            $value = self::from_safe( (string) $value );
            $type  = $types[ $name ] ?? '';
            if ( in_array( $type, self::LINK_TYPES, true ) ) {
                $value = self::decode_link( $tag, $value, $atts );
            } elseif ( ! isset( $this->map()[ $tag ] ) && 0 === strpos( $value, 'url:' ) ) {
                $value = self::parse_link( $value );
            } elseif ( 'param_group' === $type ) {
                $value = self::parse_group( $value );
            } elseif ( in_array( $type, self::ENCODED_TYPES, true ) ) {
                $value = self::decode_code( $value );
            }
            $decoded[ $name ] = $value;
        }
        return $decoded;
    }

    protected function decode_content( string $tag, string $content ): string {
        $type = $this->param_types( $tag )['content'] ?? '';
        $raw  = '' === $type ? in_array( $tag, self::ENCODED_CONTENT_TAGS, true ) : in_array( $type, self::ENCODED_TYPES, true );
        return $raw ? self::decode_code( $content ) : $content;
    }

    public static function decode_code( string $value ): string {
        $decoded = base64_decode( \wp_strip_all_tags( $value ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- WPBakery's own storage format.
        if ( false === $decoded || 1 !== preg_match( '/^[^\x00-\x08\x0B\x0C\x0E-\x1F\x7F]*\z/u', $decoded ) ) {
            return $value;
        }
        return rawurldecode( $decoded );
    }

    public static function parse_group( string $value ) {
        $rows = json_decode( urldecode( $value ), true );
        return is_array( $rows ) ? $rows : $value;
    }

    public static function from_safe( string $value ): string {
        if ( 0 === strpos( $value, '#E-8_' ) ) {
            $decoded = base64_decode( substr( $value, 5 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- WPBakery's own storage format.
            $value   = false === $decoded ? $value : rawurldecode( $decoded );
        }
        return str_replace( array( '`{`', '`}`', '``' ), array( '[', ']', '"' ), $value );
    }

    private static function decode_link( string $tag, string $value, array $atts ): array {
        if ( '' === $value || self::is_packed_link( $value ) ) {
            return self::parse_link( $value );
        }
        $link = array( 'url' => $value );
        if ( 'vc_single_image' === $tag && '_blank' === self::from_safe( (string) ( $atts['img_link_target'] ?? '' ) ) ) {
            $link['target'] = '_blank';
        }
        return $link;
    }

    public static function is_packed_link( string $value ): bool {
        return 1 === preg_match( '/url:|target:|rel:|title:/', $value );
    }

    public static function parse_link( string $value ): array {
        $link = array();
        foreach ( explode( '|', $value ) as $pair ) {
            $parts = explode( ':', $pair );
            if ( '' !== $parts[0] && isset( $parts[1] ) ) {
                $link[ $parts[0] ] = trim( rawurldecode( $parts[1] ) );
            }
        }
        return $link;
    }

    public function element_types(): array {
        $types = array();
        foreach ( $this->map() as $tag => $element ) {
            $entry = array(
                'type'     => (string) $tag,
                'label'    => isset( $element['name'] ) && is_string( $element['name'] ) ? $element['name'] : (string) $tag,
                'category' => isset( $element['category'] ) && is_string( $element['category'] ) ? $element['category'] : '',
            );
            if ( in_array( $tag, self::CODE_TAGS, true ) ) {
                $entry['code'] = true;
            }
            $types[] = $entry;
        }
        return $types;
    }

    public function element_fields( string $type ): ?array {
        $map = $this->map();
        if ( ! isset( $map[ $type ] ) ) {
            return null;
        }
        $fields = array();
        foreach ( (array) ( $map[ $type ]['params'] ?? array() ) as $param ) {
            if ( ! is_array( $param ) || empty( $param['param_name'] ) || empty( $param['type'] ) ) {
                continue;
            }
            $field = array(
                'name'  => (string) $param['param_name'],
                'kind'  => 1 === preg_match( '/(?:^|_)custom_onclick(?:_code)?$/', (string) $param['param_name'] ) ? 'code' : ( self::PARAM_KINDS[ $param['type'] ] ?? 'other' ),
                'label' => isset( $param['heading'] ) && is_string( $param['heading'] ) ? $param['heading'] : '',
                'param' => (string) $param['type'],
            );
            if ( 'dropdown' === $param['type'] && isset( $param['value'] ) && is_array( $param['value'] ) ) {
                $field['options'] = self::dropdown_values( $param['value'] );
            }
            if ( 'button_group' === $param['type'] && isset( $param['value'] ) && is_array( $param['value'] ) ) {
                $field['options'] = array_map( 'strval', array_keys( $param['value'] ) );
            }
            if ( 'checkbox' === $param['type'] && isset( $param['value'] ) && is_array( $param['value'] ) && 1 === count( $param['value'] ) && is_scalar( reset( $param['value'] ) ) ) {
                $field['on_value'] = (string) reset( $param['value'] );
            }
            if ( 'number' === $param['type'] && ! empty( $param['settings']['units'] ) ) {
                $field['units'] = self::css_units();
            }
            if ( 'toggle' === $param['type'] ) {
                $value = $param['value'] ?? 'true';
                $value = is_array( $value ) ? reset( $value ) : $value;
                if ( is_scalar( $value ) ) {
                    $field['on_value'] = (string) $value;
                }
            }
            if ( isset( $param['std'] ) && is_scalar( $param['std'] ) && '' !== $param['std'] ) {
                $field['default'] = $param['std'];
            }
            $fields[] = $field;
        }
        return $fields;
    }

    private static function css_units(): array {
        $units = function_exists( 'vc_get_shared' ) ? vc_get_shared( 'css-units' ) : null;
        return is_array( $units ) && array() !== $units ? array_values( array_map( 'strval', $units ) ) : array( 'px', '%', 'in', 'cm', 'mm', 'em', 'rem', 'ex', 'pt', 'pc', 'vw', 'vh', 'vmin', 'vmax' );
    }

    public static function dropdown_values( array $choices ): array {
        $values = array();
        foreach ( $choices as $choice ) {
            if ( is_array( $choice ) ) {
                $choice = array_key_exists( 'value', $choice ) ? $choice['value'] : reset( $choice );
            }
            if ( is_scalar( $choice ) ) {
                $values[] = (string) $choice;
            }
        }
        return $values;
    }

    private function param_types( string $tag ): array {
        $types = array();
        foreach ( (array) ( $this->map()[ $tag ]['params'] ?? array() ) as $param ) {
            if ( is_array( $param ) && ! empty( $param['param_name'] ) && isset( $param['type'] ) ) {
                $types[ (string) $param['param_name'] ] = (string) $param['type'];
            }
        }
        return $types;
    }

    private function map(): array {
        if ( null !== $this->map ) {
            return $this->map;
        }
        $all = array();
        if ( null !== $this->map_reader ) {
            $all = call_user_func( $this->map_reader );
        } elseif ( class_exists( 'WPBMap' ) && method_exists( 'WPBMap', 'getAllShortCodes' ) ) {
            $all = \WPBMap::getAllShortCodes();
        }
        $this->map = is_array( $all ) ? array_filter( $all, 'is_array' ) : array();
        return $this->map;
    }
}
