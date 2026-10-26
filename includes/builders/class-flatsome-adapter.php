<?php






namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


























final class Flatsome_Adapter extends Shortcode_Adapter {

    
    const TESTED_VERSIONS = array( '3.16.1', '3.20.11' );

    



    const FIELD_KINDS = array( 'text', 'select', 'number', 'color', 'size', 'spacing' );

    
    const BLOCKS_TYPE = 'blocks';

    






    const MAX_INNER = 9;

    





    const ELEMENTS = array(
        'section'      => array(
            'label'  => 'Section',
            'roles'  => array( 'image' => 'bg' ),
            'fields' => 'label:text bg:image bg_size:select bg_color:color bg_overlay:color* effect:select dark:select sticky:select mask:select* padding:size* height:size* margin:size divider_top:select divider:select border:spacing border_color:color* youtube:text video_mp4:text class:text visibility:select',
        ),
        'row'          => array(
            'label'  => 'Row',
            'roles'  => array(),
            'fields' => 'label:text style:select col_style:select col_bg:color col_bg_radius:number width:select custom_width:size v_align:select h_align:select padding:spacing* depth:number depth_hover:number class:text visibility:select',
        ),
        'col'          => array(
            'label'  => 'Column',
            'roles'  => array(),
            'fields' => 'label:text span:number* force_first:select divider:boolean* padding:spacing* margin:spacing* align:select bg_color:color bg_radius:number color:select sticky:select max_width:size* animate:select hover:select tooltip:text border:spacing border_color:color* class:text visibility:select',
        ),
        'ux_banner'    => array(
            'label'  => 'Banner',
            'roles'  => array( 'image' => 'bg', 'link' => 'link' ),
            'fields' => 'label:text height:size* slide_effect:select bg:image bg_size:select bg_color:color bg_overlay:color* bg_pos:text effect:select link:link target:select rel:text link_aria_label:text divider_top:select divider:select border:spacing border_color:color* youtube:text video_mp4:text sticky:select class:text visibility:select',
        ),
        'text_box'     => array(
            'label'  => 'Text Box',
            'roles'  => array( 'text' => 'content' ),
            'fields' => 'style:select text_color:select hover:select width:number* scale:number* margin:spacing* padding:spacing* rotate:number animate:select parallax:number position_x:number* position_y:number* text_align:select text_depth:number bg:color radius:number depth:number depth_hover:number border:spacing border_color:color* class:text visibility:select',
        ),
        'ux_slider'    => array(
            'label'  => 'Slider',
            'roles'  => array(),
            'fields' => 'label:text type:select style:select slide_width:size* slide_align:select bg_color:color margin:size* infinitive:select freescroll:select draggable:select parallax:number mobile:select hide_nav:select nav_pos:select nav_size:select arrows:select nav_style:select nav_color:select bullets:select bullet_style:select auto_slide:select timer:text pause_hover:select class:text visibility:select',
        ),
        'ux_image'     => array(
            'label'  => 'Image',
            'roles'  => array( 'image' => 'id', 'link' => 'link' ),
            'fields' => 'id:image image_size:select width:number* height:size margin:spacing lightbox:select lightbox_image_size:select caption:select image_overlay:color image_hover:select animate:select link:link target:select rel:text class:text visibility:select',
        ),
        'button'       => array(
            'label'  => 'Button',
            'roles'  => array( 'text' => 'text', 'link' => 'link' ),
            'fields' => 'text:text letter_case:select color:select style:select size:select animate:select padding:spacing radius:number depth:number depth_hover:number expand:boolean icon:select link:link target:select rel:text class:text visibility:select',
        ),
        'title'        => array(
            'label'  => 'Title',
            'roles'  => array( 'text' => 'text', 'link' => 'link' ),
            'fields' => 'style:select text:text tag_name:select color:color icon:select width:size margin_top:size margin_bottom:size size:number link:link link_text:text target:select rel:text class:text visibility:select',
        ),
        'ux_text'      => array(
            'label'  => 'Text',
            'roles'  => array( 'text' => 'content' ),
            'fields' => 'font_size:number* line_height:number* text_align:select* text_color:color class:text visibility:select',
        ),
        'block'        => array(
            'label'  => 'UX Block',
            'roles'  => array(),
            'fields' => 'id:select',
        ),
        'ux_html'      => array(
            'label'  => 'HTML',
            'roles'  => array(),
            'fields' => 'class:text visibility:select',
            'code'   => true,
        ),
        'message_box'  => array(
            'label'  => 'Message Box',
            'roles'  => array( 'text' => 'content', 'image' => 'bg' ),
            'fields' => 'bg:image bg_color:color text_color:select padding:number class:text visibility:select',
        ),
        'featured_box' => array(
            'label'  => 'Icon Box',
            'roles'  => array( 'text' => 'content', 'image' => 'img', 'link' => 'link' ),
            'fields' => 'img:image inline_svg:boolean img_width:number pos:select title:text title_small:text tooltip:text font_size:select margin:spacing icon_border:number icon_color:color link:link target:select rel:text class:text visibility:select',
        ),
    );

    





    const ADDED_IN = array(
        'title'     => array( 'rel' => '3.20.5' ),
        'ux_banner' => array( 'link_aria_label' => '3.20.5' ),
    );

    
    const OTHER_TAGS = array(
        'accordion', 'accordion-item', 'blog_posts', 'bullet_item', 'col_grid', 'divider',
        'follow', 'gap', 'logo', 'map', 'page_header', 'scroll_to', 'search', 'share', 'tab',
        'tabgroup', 'team_member', 'testimonial', 'ux_banner_grid', 'ux_countdown', 'ux_gallery',
        'ux_hotspot', 'ux_image_box', 'ux_instagram_feed', 'ux_lottie', 'ux_menu', 'ux_menu_link',
        'ux_menu_title', 'ux_nav', 'ux_pages', 'ux_payment_icons', 'ux_portfolio', 'ux_price_table',
        'ux_product_categories', 'ux_product_flip', 'ux_products', 'ux_products_list', 'ux_sidebar',
        'ux_stack', 'ux_video', 'video_button', 'ux_gutenberg', 'ux_shortcode',
    );

    public function id(): string {
        return 'flatsome';
    }

    public function label(): string {
        return 'Flatsome UX Builder';
    }

    public function is_present(): bool {
        return function_exists( 'get_template' ) && 'flatsome' === get_template();
    }

    public function version(): string {
        if ( defined( 'UX_BUILDER_VERSION' ) ) {
            return (string) constant( 'UX_BUILDER_VERSION' );
        }
        $theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( 'flatsome' ) : null;
        return is_object( $theme ) && method_exists( $theme, 'get' ) ? (string) $theme->get( 'Version' ) : '';
    }

    public function owns( object $post ): bool {
        if ( self::BLOCKS_TYPE === $post->post_type ) {
            return true;
        }
        
        
        foreach ( $this->layout_items( $this->source( $post ) ) as $item ) {
            if ( Shortcode_Tree::TEXT !== $item['tag'] ) {
                return true;
            }
        }
        return false;
    }

    protected function mask( string $source ): string {
        
        
        
        
        return (string) preg_replace_callback(
            '#<!--\s*/?wp:flatsome/uxbuilder\b[^>]*-->#',
            static function ( array $match ): string {
                return str_repeat( ' ', strlen( $match[0] ) );
            },
            $source
        );
    }

    protected function tags(): array {
        $tags = array();
        foreach ( array_merge( array_keys( self::ELEMENTS ), self::OTHER_TAGS ) as $tag ) {
            $tags[] = $tag;
            $tags[] = $tag . '_inner';
            for ( $i = 1; $i <= self::MAX_INNER; $i++ ) {
                $tags[] = $tag . '_inner_' . $i;
            }
        }
        return $tags;
    }

    public function tested_versions(): array {
        return self::TESTED_VERSIONS;
    }

    public function field_kinds(): array {
        return self::FIELD_KINDS;
    }

    
    public function encode_attribute( string $tag, string $name, array $spec, string $value ): string {
        unset( $tag, $name, $spec );
        return str_replace( array( '"', '[', ']' ), array( '&quot;', '&#91;', '&#93;' ), $value );
    }

    




    public function link_attributes( string $tag, string $field, array $spec, array $decoded, string $url, ?bool $new_tab, ?bool $nofollow, array $fields ) {
        $names   = array_column( $fields, 'name' );
        $changes = array( $field => '' === $url ? null : $this->encode_attribute( $tag, $field, $spec, $url ) );
        if ( null !== $new_tab ) {
            if ( ! in_array( 'target', $names, true ) ) {
                return sprintf( '%s elements have no target field, so new_tab cannot be set.', $tag );
            }
            $changes['target'] = $new_tab ? '_blank' : null;
        }
        if ( null !== $nofollow ) {
            if ( ! in_array( 'rel', $names, true ) ) {
                return sprintf( '%s elements have no rel field, so nofollow cannot be set.', $tag );
            }
            $rel = preg_split( '/\s+/', (string) ( $decoded['rel'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
            $rel = array_values( array_diff( is_array( $rel ) ? $rel : array(), array( 'nofollow' ) ) );
            if ( $nofollow ) {
                $rel[] = 'nofollow';
            }
            $changes['rel'] = array() === $rel ? null : implode( ' ', $rel );
        }
        return $changes;
    }

    




    public function link_view( string $tag, string $field, array $decoded ) {
        unset( $tag );
        return array(
            'url'    => (string) ( $decoded[ $field ] ?? '' ),
            'target' => (string) ( $decoded['target'] ?? '' ),
            'rel'    => (string) ( $decoded['rel'] ?? '' ),
        );
    }

    protected function code_tags(): array {
        $tags = array();
        foreach ( self::ELEMENTS as $tag => $element ) {
            if ( ! empty( $element['code'] ) ) {
                $tags[] = $tag;
            }
        }
        return $tags;
    }

    protected function roles( string $tag ): array {
        return self::ELEMENTS[ self::base_tag( $tag ) ]['roles'] ?? array();
    }

    protected function tag_label( string $tag ): string {
        return self::ELEMENTS[ self::base_tag( $tag ) ]['label'] ?? $tag;
    }

    protected function decode_atts( string $tag, array $atts ): array {
        unset( $tag );
        return $atts;
    }

    





    public function references( array $nodes ): array {
        $refs = array();
        foreach ( $nodes as $node ) {
            if ( 'block' === self::base_tag( $node['type'] ) && isset( $node['settings']['id'] ) && '' !== $node['settings']['id'] ) {
                $refs[] = array(
                    'address' => $node['address'],
                    'type'    => 'block',
                    'ref'     => (string) $node['settings']['id'],
                    'note'    => 'A UX Block (post type `blocks`), referenced by ID or slug; read it with wp_get_page_layout on that post.',
                );
            }
            $refs = array_merge( $refs, $this->references( $node['children'] ) );
        }
        return $refs;
    }

    public function element_types(): array {
        $types = array();
        foreach ( self::ELEMENTS as $tag => $element ) {
            $entry = array(
                'type'     => $tag,
                'label'    => $element['label'],
                'category' => '',
            );
            if ( ! empty( $element['code'] ) ) {
                $entry['code'] = true;
            }
            $types[] = $entry;
        }
        return $types;
    }

    public function element_fields( string $type ): ?array {
        $element = self::ELEMENTS[ self::base_tag( $type ) ] ?? null;
        if ( null === $element ) {
            return null;
        }
        $fields = array();
        if ( 'content' === ( $element['roles']['text'] ?? '' ) ) {
            $fields[] = array(
                'name'  => 'content',
                'kind'  => 'html',
                'label' => 'Enclosed content',
            );
        }
        $version = $this->version();
        $added   = self::ADDED_IN[ self::base_tag( $type ) ] ?? array();
        foreach ( preg_split( '/\s+/', trim( $element['fields'] ), -1, PREG_SPLIT_NO_EMPTY ) as $spec ) {
            list( $name, $kind ) = explode( ':', $spec, 2 );
            if ( isset( $added[ $name ] ) && '' !== $version && version_compare( $version, $added[ $name ], '<' ) ) {
                continue;
            }
            $field = array(
                'name'  => $name,
                'kind'  => rtrim( $kind, '*' ),
                'label' => '',
            );
            if ( '*' === substr( $kind, -1 ) ) {
                $field['responsive'] = true;
                $field['devices']    = array( $name, $name . '__md', $name . '__sm' );
            }
            $fields[] = $field;
        }
        return $fields;
    }

    





    public static function base_tag( string $tag ): string {
        return (string) preg_replace( '/_inner(?:_\d+)?$/', '', $tag );
    }
}
