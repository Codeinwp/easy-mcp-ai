<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Flatsome_Registry implements Structure_Rules {

    const ROOT = '_root';

    const COPY_DROPS = array( '_id' );

    const ELEMENTS = array(
        '_root'             => 'container hidden',
        'accordion'         => 'container allow:accordion-item',
        'accordion-item'    => 'container hidden require:accordion',
        'block'             => 'normal text',
        'blog_posts'        => 'normal',
        'bullet_item'       => 'normal hidden require:ux_price_table',
        'button'            => 'normal',
        'col'               => 'container nested require:row',
        'col_grid'          => 'container nested allow:ux_banner,ux_slider,ux_image,ux_lottie require:row',
        'divider'           => 'normal',
        'featured_box'      => 'container',
        'follow'            => 'normal',
        'gallery'           => 'normal',
        'gap'               => 'normal',
        'logo'              => 'normal',
        'map'               => 'container',
        'message_box'       => 'container',
        'page_header'       => 'normal',
        'row'               => 'container nested allow:col',
        'scroll_to'         => 'normal',
        'search'            => 'normal',
        'section'           => 'container',
        'share'             => 'normal',
        'tab'               => 'container hidden require:tabgroup',
        'tabgroup'          => 'container allow:tab',
        'team_member'       => 'container',
        'testimonial'       => 'container',
        'text'              => 'container text',
        'text_box'          => 'container allow:ux_breadcrumbs,ux_the_title,ux_image,ux_lottie,ux_stack,ux_html,text,divider,button,title,video_button,row,follow,share,gap,testimonial,ux_countdown,search,ux_video require:ux_banner',
        'title'             => 'normal',
        'ux_banner'         => 'container allow:text_box,ux_image,ux_lottie,ux_hotspot',
        'ux_banner_grid'    => 'container allow:col_grid',
        'ux_countdown'      => 'normal',
        'ux_gallery'        => 'normal',
        'ux_gutenberg'      => 'container text require:_root',
        'ux_hotspot'        => 'normal require:ux_banner',
        'ux_html'           => 'container text',
        'ux_image'          => 'normal',
        'ux_image_box'      => 'container',
        'ux_instagram_feed' => 'normal',
        'ux_lottie'         => 'normal',
        'ux_menu'           => 'container allow:ux_menu_link,ux_menu_title',
        'ux_menu_link'      => 'normal require:ux_menu',
        'ux_menu_title'     => 'normal require:ux_menu',
        'ux_nav'            => 'normal',
        'ux_pages'          => 'normal',
        'ux_portfolio'      => 'normal',
        'ux_price_table'    => 'container allow:text,bullet_item,button',
        'ux_sidebar'        => 'normal',
        'ux_slider'         => 'container allow:ux_banner,ux_image,ux_lottie,section,row,ux_banner_grid,logo',
        'ux_stack'          => 'container nested',
        'ux_video'          => 'normal',
        'video_button'      => 'normal',
    );

    const ADDED_IN = array( 'ux_lottie' => '3.20.11' );

    const ALWAYS_SAVED = array(
        'text_box'   => array(
            'position_x' => '50',
            'position_y' => '50',
        ),
        'ux_image'   => array(
            'position_x' => '50',
            'position_y' => '50',
        ),
        'ux_hotspot' => array(
            'position_x' => '50',
            'position_y' => '50',
        ),
    );

    private $elements = array();

    public function __construct( string $version ) {
        $missing = array();
        foreach ( self::ADDED_IN as $tag => $since ) {
            if ( version_compare( $version, $since, '<' ) ) {
                $missing[] = $tag;
            }
        }
        foreach ( self::ELEMENTS as $tag => $spec ) {
            if ( in_array( $tag, $missing, true ) ) {
                continue;
            }
            $this->elements[ $tag ] = self::parse_spec( $spec, $missing );
        }
    }

    public function dump(): array {
        $dump = array();
        foreach ( $this->elements as $tag => $element ) {
            $dump[ $tag ] = array(
                'type'    => $element['kind'],
                'hidden'  => $element['hidden'],
                'nested'  => $element['nested'],
                'compile' => ! $element['text'],
                'allow'   => $element['allow'],
                'require' => $element['require'],
            );
        }
        return $dump;
    }

    public function name(): string {
        return 'UX Builder';
    }

    public function base_tag( string $tag ): string {
        return Flatsome_Adapter::base_tag( $tag );
    }

    public function tag_at( string $tag, array $ancestors ): string {
        $base = Flatsome_Adapter::base_tag( $tag );
        $same = 0;
        foreach ( $ancestors as $ancestor ) {
            $same += Flatsome_Adapter::base_tag( $ancestor['tag'] ) === $base ? 1 : 0;
        }
        return self::nested_name( $tag, $same );
    }

    public function copy_drops(): array {
        return self::COPY_DROPS;
    }

    public function fixed_refusal( string $tag ): ?string {
        unset( $tag );
        return null;
    }

    public static function element_of( string $tag ): string {
        $base = Flatsome_Adapter::base_tag( $tag );
        return 'ux_text' === $base ? 'text' : $base;
    }

    public function insertable( string $tag ): bool {
        $name = self::element_of( $tag );
        return isset( $this->elements[ $name ] ) && 'text' !== $name;
    }

    public function holds_elements( string $tag ): bool {
        $element = $this->elements[ self::element_of( $tag ) ] ?? null;
        return null !== $element && 'container' === $element['kind'] && ! $element['text'];
    }

    public function placement_refusal( string $child, array $chain, array $descendants ): ?string {
        $chain  = array_values( array_map( 'strval', array_column( $chain, 'tag' ) ) );
        $parent = array() === $chain ? self::ROOT : self::element_of( (string) end( $chain ) );
        $name   = self::element_of( $child );
        if ( ! isset( $this->elements[ $name ] ) ) {
            return sprintf( 'UX Builder does not register a %s element, so where it may go is not known here.', $child );
        }
        if ( ! isset( $this->elements[ $parent ] ) ) {
            return sprintf( 'UX Builder does not register a %s element, so what it may hold is not known here.', (string) end( $chain ) );
        }
        $accepted = $this->accepted( $parent, array_map( array( __CLASS__, 'element_of' ), $chain ) );
        if ( 'text' === $child && ! in_array( $name, $accepted, true ) ) {
            return sprintf( 'UX Builder would turn it into a Text element, which %s does not take (in a slider, an extra slide).', self::ROOT === $parent ? 'the page' : 'a ' . $parent );
        }
        if ( ! in_array( $name, $accepted, true ) ) {
            return sprintf( '%s cannot hold a %s in UX Builder; it holds %s.', self::ROOT === $parent ? 'The page' : 'A ' . $parent, $name, array() === $accepted ? 'no elements' : implode( ', ', $accepted ) );
        }
        foreach ( $descendants as $inner ) {
            $inner = self::element_of( $inner );
            if ( $inner === $parent && ! $this->elements[ $inner ]['nested'] ) {
                return sprintf( 'The %s holds a %s, which may not sit inside another %s in UX Builder.', $name, $inner, $parent );
            }
        }
        return null;
    }

    private function is_container( string $tag ): bool {
        return 'container' === ( $this->elements[ self::element_of( $tag ) ]['kind'] ?? '' );
    }

    public static function nested_name( string $tag, int $same ): string {
        $base = Flatsome_Adapter::base_tag( $tag );
        if ( $same < 1 ) {
            return $base;
        }
        return $base . '_inner' . ( $same > 1 ? '_' . ( $same - 1 ) : '' );
    }

    public function markup( string $tag, string $atts ): string {
        return $this->is_container( $tag ) ? '[' . $tag . $atts . "]\n\n\n[/" . $tag . "]\n" : '[' . $tag . $atts . "]\n\n";
    }

    public function trailing_breaks( string $tag ): int {
        return Shortcode_Tree::TEXT !== $tag && ! $this->is_container( $tag ) ? 2 : 1;
    }

    public function always_saved( string $tag, string $parent ): array {
        if ( 'ux_banner' !== Flatsome_Adapter::base_tag( $parent ) ) {
            return array();
        }
        return self::ALWAYS_SAVED[ Flatsome_Adapter::base_tag( $tag ) ] ?? array();
    }

    private function accepted( string $parent, array $chain ): array {
        $allow = $this->elements[ $parent ]['allow'];
        if ( array() !== $allow ) {
            return array_values( array_filter( $allow, array( $this, 'is_registered' ) ) );
        }
        $accepted = array();
        foreach ( $this->elements as $tag => $element ) {
            if ( $element['hidden'] || ( array() !== $element['require'] && ! in_array( $parent, $element['require'], true ) ) ) {
                continue;
            }
            if ( ! $element['nested'] && in_array( $tag, $chain, true ) ) {
                continue;
            }
            $accepted[] = $tag;
        }
        return $accepted;
    }

    private function is_registered( string $tag ): bool {
        return isset( $this->elements[ $tag ] );
    }

    private static function parse_spec( string $spec, array $missing ): array {
        $words   = explode( ' ', $spec );
        $element = array(
            'kind'    => (string) array_shift( $words ),
            'hidden'  => false,
            'nested'  => false,
            'text'    => false,
            'allow'   => array(),
            'require' => array(),
        );
        foreach ( $words as $word ) {
            if ( false === strpos( $word, ':' ) ) {
                $element[ $word ] = true;
                continue;
            }
            list( $key, $list ) = explode( ':', $word, 2 );
            $element[ $key ]    = array_values( array_diff( explode( ',', $list ), $missing ) );
        }
        return $element;
    }
}
