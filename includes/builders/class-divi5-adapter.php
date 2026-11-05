<?php

namespace Easy_MCP_AI\Builders;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi5_Adapter extends Layout_Adapter {

    const TESTED_VERSIONS = array( '5.18.1' );

    const DIVI5_KEY = '_et_pb_use_divi_5';

    const ROLES = array(
        'divi/text'             => array( 'text' => 'content.innerContent' ),
        'divi/heading'          => array( 'text' => 'title.innerContent' ),
        'divi/button'           => array( 'text' => 'button.innerContent.text', 'link' => 'button.innerContent.linkUrl' ),
        'divi/image'            => array( 'image' => 'image.innerContent.src', 'link' => 'image.innerContent.linkUrl' ),
        'divi/blurb'            => array( 'text' => 'title.innerContent.text', 'link' => 'title.innerContent.url', 'image' => 'imageIcon.innerContent.src' ),
        'divi/cta'              => array( 'text' => 'title.innerContent', 'link' => 'button.innerContent.linkUrl' ),
        'divi/slide'            => array( 'text' => 'title.innerContent', 'link' => 'button.innerContent.linkUrl', 'image' => 'image.innerContent.src' ),
        'divi/toggle'           => array( 'text' => 'title.innerContent' ),
        'divi/accordion-item'   => array( 'text' => 'title.innerContent' ),
        'divi/tab'              => array( 'text' => 'title.innerContent' ),
        'divi/testimonial'      => array( 'text' => 'content.innerContent', 'image' => 'portrait.innerContent.src' ),
        'divi/team-member'      => array( 'text' => 'name.innerContent', 'image' => 'image.innerContent.url' ),
        'divi/pricing-table'    => array( 'text' => 'title.innerContent', 'link' => 'button.innerContent.linkUrl' ),
        'divi/fullwidth-header' => array( 'text' => 'title.innerContent', 'link' => 'buttonOne.innerContent.linkUrl', 'image' => 'logo.innerContent.src' ),
        'divi/number-counter'   => array( 'text' => 'title.innerContent' ),
        'divi/circle-counter'   => array( 'text' => 'title.innerContent' ),
    );

    const COMPONENT_KINDS = array(
        'divi/text'        => 'text',
        'divi/textarea'    => 'text',
        'divi/richtext'    => 'html',
        'divi/code'        => 'code',
        'divi/upload'      => 'image',
        'divi/select'      => 'select',
        'divi/toggle'      => 'boolean',
        'divi/range'       => 'size',
        'divi/icon-picker' => 'icon',
        'divi/checkboxes'  => 'list',
    );

    private $module_reader;

    private $save_filter;

    private ?array $modules = null;

    public function __construct( ?callable $module_reader = null, ?callable $save_filter = null ) {
        $this->module_reader = $module_reader;
        $this->save_filter   = $save_filter;
    }

    public function id(): string {
        return 'divi5';
    }

    public function label(): string {
        return 'Divi 5';
    }

    public function is_present(): bool {
        return self::divi5_active();
    }

    public static function divi5_active(): bool {
        return function_exists( 'et_builder_d5_enabled' ) && (bool) et_builder_d5_enabled();
    }

    public function version(): string {
        return defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? (string) constant( 'ET_BUILDER_PRODUCT_VERSION' ) : '';
    }

    public static function is_divi5( object $post ): bool {
        return 'on' === get_post_meta( (int) $post->ID, self::DIVI5_KEY, true ) || false !== strpos( (string) $post->post_content, '<!-- wp:divi/' );
    }

    public function owns( object $post ): bool {
        if ( ! self::is_divi5( $post ) ) {
            return false;
        }
        $tree = Divi5_Tree::parse( (string) $post->post_content );
        if ( null === $tree ) {
            return false;
        }
        foreach ( $tree->items() as $block ) {
            if ( ! $block->is_text() ) {
                return true;
            }
        }
        return false;
    }

    public function source( object $post ): string {
        return (string) $post->post_content;
    }

    public function nodes( object $post ): array {
        $tree = Divi5_Tree::parse( $this->source( $post ) );
        if ( null === $tree ) {
            throw new \RuntimeException( 'The stored Divi 5 layout is not well-formed block markup, so it cannot be read here.' );
        }
        return $this->build( $tree->items(), '' );
    }

    private function build( array $blocks, string $prefix ): array {
        $nodes = array();
        foreach ( array_values( $blocks ) as $index => $block ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            if ( $block->is_text() ) {
                $nodes[] = self::node(
                    array(
                        'address'  => $address,
                        'type'     => Shortcode_Tree::TEXT,
                        'label'    => 'Text',
                        'text'     => self::excerpt( $block->text ),
                        'settings' => array( 'content' => $block->text ),
                    )
                );
                continue;
            }
            $roles   = $this->roles( $block->name );
            $label   = $block->get( array( 'module', 'meta', 'adminLabel', 'desktop', 'value' ) );
            $image   = isset( $roles['image'] ) ? $block->get( Divi5_Editor::value_path( $roles['image'] ) ) : null;
            $link    = isset( $roles['link'] ) ? $block->get( Divi5_Editor::value_path( $roles['link'] ) ) : null;
            $global  = Divi5_Rules::GLOBAL_LAYOUT === $block->name ? $block->get( array( 'globalModule' ) ) : null;
            $flags   = in_array( $block->name, Divi5_Editor::CODE_MODULES, true ) ? array( 'code' ) : array();
            $nodes[] = self::node(
                array(
                    'address'  => $address,
                    'type'     => $block->name,
                    'label'    => is_string( $label ) && '' !== $label ? $label : ( null !== $global ? 'Global element (Library item ' . ( is_scalar( $global ) ? (string) $global : '?' ) . ')' : $this->title( $block->name ) ),
                    'text'     => isset( $roles['text'] ) ? self::excerpt( $block->get( Divi5_Editor::value_path( $roles['text'] ) ) ) : null,
                    'image'    => is_string( $image ) && '' !== $image ? array( 'url' => $image ) : null,
                    'link'     => is_string( $link ) && '' !== $link ? $link : null,
                    'settings' => (array) json_decode( (string) wp_json_encode( $block->attrs ), true ),
                    'flags'    => $flags,
                    'children' => $this->build( $block->children, $address ),
                )
            );
        }
        return $nodes;
    }

    public function references( array $nodes ): array {
        $references = array();
        foreach ( $nodes as $node ) {
            if ( Divi5_Rules::GLOBAL_LAYOUT === $node['type'] ) {
                $id = $node['settings']['globalModule'] ?? '';
                if ( is_scalar( $id ) && ctype_digit( (string) $id ) ) {
                    $references[] = array(
                        'address' => $node['address'],
                        'type'    => $node['type'],
                        'ref'     => 'post:' . (string) $id,
                        'note'    => 'A global Divi element: the page renders Divi Library item ' . (string) $id . ', so edit that item with wp_get_page_layout and wp_update_page_layout.',
                    );
                }
                continue;
            }
            $references = array_merge( $references, $this->references( $node['children'] ) );
        }
        return $references;
    }

    public function element_types(): array {
        $types = array();
        foreach ( $this->modules() as $name => $module ) {
            if ( Divi5_Tree::PLACEHOLDER === $name ) {
                continue;
            }
            $entry = array(
                'type'     => (string) $name,
                'label'    => $module['title'],
                'category' => $module['category'],
            );
            if ( in_array( $name, Divi5_Editor::CODE_MODULES, true ) ) {
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
        $fields = $modules[ $type ]['fields'];
        $named  = array_column( $fields, 'name' );
        foreach ( $this->roles( $type ) as $role => $name ) {
            if ( ! in_array( $name, $named, true ) ) {
                $fields[] = array(
                    'name'     => $name,
                    'kind'     => 'text' === $role ? ( 'content.innerContent' === $name ? 'html' : 'text' ) : $role,
                    'label'    => ucfirst( $role ),
                    'category' => 'basic_option',
                );
            }
        }
        return $fields;
    }

    public function edit_refusal( object $post ): ?string {
        if ( ! $this->is_present() ) {
            return 'Divi 5 is not active, so this layout can be read but not saved.';
        }
        $version = $this->version();
        if ( ! in_array( $version, self::TESTED_VERSIONS, true ) ) {
            return sprintf( 'Editing Divi 5 layouts is measured on version %1$s; this site runs %2$s, so its layouts can be read but not edited.', implode( ', ', self::TESTED_VERSIONS ), '' === $version ? 'an unknown version' : $version );
        }
        if ( array() === $this->modules() ) {
            return 'Divi 5\'s module metadata could not be read on this site, so this layout can be read but not edited.';
        }
        return Divi_Access::refusal( $post );
    }

    public function edit( object $post, array $ops, bool $dry_run, ?callable $fence = null ): array {
        $post_id = (int) $post->ID;
        $refusal = $this->edit_refusal( $post );
        if ( null !== $refusal ) {
            throw self::refusal( array( $refusal ) );
        }
        $original = (string) $post->post_content;
        $editor   = new Divi5_Editor(
            new Divi5_Rules( $this->modules() ),
            array( $this, 'element_fields' ),
            array( $this, 'roles' ),
            array( Layout_Adapter::class, 'library_image' ),
            current_user_can( 'unfiltered_html' ),
            $this->version()
        );
        $plan     = $editor->plan( $original, $ops );
        if ( array() !== $plan['errors'] ) {
            throw self::refusal( $plan['errors'] );
        }
        $problems = $this->save_problems( $plan['content'] );
        if ( array() !== $problems ) {
            throw self::refusal( $problems );
        }
        $warnings = $plan['warnings'];
        $editing  = self::editing_user( $post_id );
        if ( null !== $editing ) {
            $open = sprintf( '%s has post %d open in the editor; their next save there replaces the whole layout, this change included.', $editing, $post_id );
            if ( ! $dry_run ) {
                throw self::refusal( array( $open . ' Try again once they close it.' ) );
            }
            $warnings[] = $open;
        }
        $changed = $plan['content'] !== $original;
        $extra   = array() === $plan['parents'] ? array() : array( 'parents' => $this->parents( $plan['parents'], $editor, $plan['content'] ) );
        if ( $dry_run || ! $changed ) {
            return array(
                'changed'  => $changed,
                'saved'    => false,
                'results'  => $plan['results'],
                'warnings' => $warnings,
            ) + $extra;
        }

        if ( null !== $fence ) {
            $fence();
        }
        if ( self::stored_content( $post_id ) !== $original ) {
            throw new \RuntimeException( sprintf( 'The layout of post %d changed while this edit was being prepared, so nothing was saved. Read it again with wp_get_page_layout and redo the edit.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $saved = wp_update_post(
            wp_slash(
                array(
                    'ID'           => $post_id,
                    'post_content' => $plan['content'],
                )
            ),
            true
        );
        if ( is_wp_error( $saved ) || ! $saved ) {
            throw new \RuntimeException( sprintf( 'WordPress did not save post %d: %s', $post_id, is_wp_error( $saved ) ? $saved->get_error_message() : 'wp_update_post() returned 0.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        update_post_meta( $post_id, self::DIVI5_KEY, 'on' );
        update_post_meta( $post_id, Divi4_Adapter::USE_BUILDER_KEY, 'on' );
        Divi_Access::after_save( $post_id );
        $stored = self::stored_content( $post_id );
        if ( $stored === $original ) {
            throw new \RuntimeException( sprintf( 'WordPress reported post %d saved, but its content did not change: something stopped the write (a plugin filter or a database error).', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $differ = self::differences( $plan['content'], $stored );
        if ( array() !== $differ ) {
            $warnings[] = sprintf( 'WordPress stored %s differently from the plan; read the layout again to see what it holds.', implode( ', ', array_slice( $differ, 0, 10 ) ) );
        }
        return array(
            'changed'  => true,
            'saved'    => true,
            'results'  => $plan['results'],
            'warnings' => $warnings,
        ) + $extra;
    }

    private function save_problems( string $content ): array {
        $predicted = null !== $this->save_filter ? (string) ( $this->save_filter )( $content ) : self::predicted( $content );
        $differ    = self::differences( $content, $predicted );
        if ( array() === $differ ) {
            return array();
        }
        $where = implode( ', ', array_slice( $differ, 0, 10 ) ) . ( count( $differ ) > 10 ? sprintf( ' and %d more', count( $differ ) - 10 ) : '' );
        return array( sprintf( 'When this account saves, Divi\'s and WordPress\'s save filters would store %s differently from the plan (HTML or attributes the account may not post are removed). Ask someone whose account may post that HTML to make this edit, or change those elements in the Divi Builder first.', $where ) );
    }

    public static function predicted( string $content ): string {
        $stored   = Shortcode_Adapter::saved_content( $content );
        $security = '\\ET\\Builder\\Security\\Security';
        if ( class_exists( $security ) && method_exists( $security, 'apply_post_content_save_transforms' ) ) {
            $data   = call_user_func( array( $security, 'apply_post_content_save_transforms' ), array( 'post_content' => wp_slash( $stored ) ) );
            $stored = is_array( $data ) && isset( $data['post_content'] ) ? wp_unslash( (string) $data['post_content'] ) : $stored;
        }
        return $stored;
    }

    public static function differences( string $planned, string $other ): array {
        $a = Divi5_Tree::parse( $planned );
        $b = Divi5_Tree::parse( $other );
        if ( null === $a || null === $b ) {
            return null === $b ? array( 'the page' ) : array();
        }
        $differ = array();
        $wrap   = $a->placeholder();
        if ( null === $wrap ) {
            self::compare( $a->top, $b->top, '', $differ );
            return $differ;
        }
        $theirs = $b->placeholder();
        if ( null === $theirs ) {
            return array( 'the ' . Divi5_Tree::PLACEHOLDER . ' block' );
        }
        if ( self::canonical( $theirs->attrs ) !== self::canonical( $wrap->attrs ) ) {
            $differ[] = 'the ' . Divi5_Tree::PLACEHOLDER . ' block';
        }
        self::compare( $a->items(), $b->items(), '', $differ );
        if ( self::loose_text( $a->top ) !== self::loose_text( $b->top ) ) {
            $differ[] = 'text outside the ' . Divi5_Tree::PLACEHOLDER . ' block';
        }
        return $differ;
    }

    private static function loose_text( array $blocks ): array {
        $text = array();
        foreach ( $blocks as $block ) {
            if ( $block->is_text() ) {
                $text[] = trim( $block->text );
            }
        }
        return $text;
    }

    private static function compare( array $a, array $b, string $prefix, array &$differ ): void {
        $a = array_values( $a );
        $b = array_values( $b );
        foreach ( $a as $index => $block ) {
            $address = '' === $prefix ? (string) $index : $prefix . '.' . $index;
            $other   = $b[ $index ] ?? null;
            if ( null === $other || $other->name !== $block->name || trim( $other->text ) !== trim( $block->text ) || self::canonical( $other->attrs ) !== self::canonical( $block->attrs ) || count( $other->children ) !== count( $block->children ) ) {
                $differ[] = sprintf( '%s (%s)', $address, $block->is_text() ? 'text' : $block->name );
                continue;
            }
            self::compare( $block->children, $other->children, $address, $differ );
        }
        if ( count( $b ) > count( $a ) ) {
            $differ[] = sprintf( '%s more elements under "%s"', count( $b ) - count( $a ), '' === $prefix ? 'the page' : $prefix );
        }
    }

    private static function canonical( $value ) {
        $object = $value instanceof \stdClass;
        if ( $object ) {
            $value = get_object_vars( $value );
        }
        if ( ! is_array( $value ) ) {
            return $value;
        }
        $out = array();
        foreach ( $value as $key => $inner ) {
            $inner = self::canonical( $inner );
            if ( array() !== $inner ) {
                $out[ $key ] = $inner;
            }
        }
        if ( $object ) {
            ksort( $out );
        }
        return $out;
    }

    private function parents( array $parents, Divi5_Editor $editor, string $content ): array {
        $where = $editor->where();
        $nodes = $this->nodes( (object) array( 'post_content' => $content ) );
        $out   = array();
        $seen  = array();
        foreach ( $parents as $parent ) {
            $address = null === $parent ? '' : ( $where[ spl_object_id( $parent ) ] ?? null );
            if ( null === $address || isset( $seen[ $address ] ) ) {
                continue;
            }
            $seen[ $address ] = true;
            $node             = '' === $address ? array( 'children' => $nodes, 'type' => 'page' ) : self::node_at( $nodes, $address );
            $out[]            = array(
                'address'  => $address,
                'type'     => (string) ( $node['type'] ?? '' ),
                'children' => array_map(
                    static function ( array $child ): array {
                        return array_intersect_key( $child, array_flip( array( 'address', 'type', 'label', 'text', 'image', 'link' ) ) );
                    },
                    (array) ( $node['children'] ?? array() )
                ),
            );
        }
        return $out;
    }

    private static function node_at( array $nodes, string $address ): ?array {
        $node = null;
        foreach ( explode( '.', $address ) as $index ) {
            $node  = $nodes[ (int) $index ] ?? null;
            $nodes = is_array( $node ) ? (array) ( $node['children'] ?? array() ) : array();
        }
        return $node;
    }

    public function roles( string $name ): array {
        if ( isset( self::ROLES[ $name ] ) ) {
            return self::ROLES[ $name ];
        }
        $named = array_column( $this->modules()[ $name ]['fields'] ?? array(), 'name' );
        foreach ( array( 'content.innerContent', 'title.innerContent' ) as $field ) {
            if ( in_array( $field, $named, true ) ) {
                return array( 'text' => $field );
            }
        }
        return array();
    }

    private function title( string $name ): string {
        $title = $this->modules()[ $name ]['title'] ?? '';
        return '' !== $title ? $title : $name;
    }

    private static function stored_content( int $post_id ): string {
        wp_cache_delete( $post_id, 'posts' );
        $post = get_post( $post_id );
        return is_object( $post ) ? (string) $post->post_content : '';
    }

    private function modules(): array {
        if ( null === $this->modules ) {
            $all           = null !== $this->module_reader ? call_user_func( $this->module_reader ) : self::module_reader();
            $this->modules = is_array( $all ) ? $all : array();
        }
        return $this->modules;
    }

    public static function module_reader(): array {
        $registration = '\\ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration';
        if ( ! self::divi5_active() || ! class_exists( $registration ) || ! method_exists( $registration, 'get_all_core_modules_metadata' ) ) {
            return array();
        }
        try {
            $all = call_user_func( array( $registration, 'get_all_core_modules_metadata' ) );
        } catch ( \Throwable $e ) {
            return array();
        }
        $modules = array();
        foreach ( is_array( $all ) ? $all : array() as $meta ) {
            if ( is_array( $meta ) && isset( $meta['name'] ) && is_string( $meta['name'] ) ) {
                $modules[ $meta['name'] ] = self::meta( $meta );
            }
        }
        return $modules;
    }

    public static function meta( array $meta ): array {
        $fields = array();
        foreach ( (array) ( $meta['attributes'] ?? array() ) as $group => $attribute ) {
            $inner = is_array( $attribute ) ? ( $attribute['settings']['innerContent'] ?? null ) : null;
            if ( is_array( $inner ) ) {
                self::collect_fields( $inner, (string) $group, $fields );
            }
        }
        return array(
            'title'    => isset( $meta['title'] ) && is_string( $meta['title'] ) ? $meta['title'] : '',
            'category' => isset( $meta['category'] ) && is_string( $meta['category'] ) ? $meta['category'] : '',
            'child'    => isset( $meta['childModuleName'] ) && is_string( $meta['childModuleName'] ) ? $meta['childModuleName'] : '',
            'd4'       => isset( $meta['d4Shortcode'] ) && is_string( $meta['d4Shortcode'] ) ? $meta['d4Shortcode'] : '',
            'fields'   => $fields,
        );
    }

    private static function collect_fields( array $node, string $group, array &$fields ): void {
        $component = isset( $node['component'] ) && is_array( $node['component'] ) ? $node['component'] : null;
        if ( null === $component && isset( $node['item'] ) && is_array( $node['item'] ) && ! isset( $node['item']['component'] ) && ! isset( $node['item']['attrName'] ) ) {
            $fields[] = self::with_category(
                array(
                    'name'  => $group . '.innerContent',
                    'kind'  => 'content' === $group ? 'html' : 'text',
                    'label' => ucfirst( $group ),
                ),
                $node['item']
            );
            return;
        }
        if ( null === $component ) {
            foreach ( $node as $inner ) {
                if ( is_array( $inner ) ) {
                    self::collect_fields( $inner, $group, $fields );
                }
            }
            return;
        }
        $props = isset( $component['props'] ) && is_array( $component['props'] ) ? $component['props'] : array();
        $attr  = (string) ( $node['attrName'] ?? ( $props['attrName'] ?? $group . '.innerContent' ) );
        $label = (string) ( $node['label'] ?? ( $props['fieldLabel'] ?? '' ) );
        $name  = (string) ( $component['name'] ?? '' );
        if ( 'group' === ( $component['type'] ?? '' ) ) {
            if ( 'divi/button-link' === $name ) {
                $fields[] = array(
                    'name'     => $attr . '.linkUrl',
                    'kind'     => 'link',
                    'label'    => trim( $label . ' Link URL' ),
                    'category' => 'basic_option',
                );
                $fields[] = array(
                    'name'     => $attr . '.linkTarget',
                    'kind'     => 'select',
                    'label'    => trim( $label . ' Link Target' ),
                    'options'  => array( 'off', 'on' ),
                    'category' => 'configuration',
                );
            }
            return;
        }
        $sub  = isset( $node['subName'] ) && is_string( $node['subName'] ) ? $node['subName'] : '';
        $kind = self::COMPONENT_KINDS[ $name ] ?? 'other';
        $type = $node['features']['dynamicContent']['type'] ?? '';
        if ( 'text' === $kind && 'url' === $type ) {
            $kind = 'link';
        }
        if ( 'image' === $kind && ! in_array( (string) ( $props['dataType'] ?? 'image' ), array( '', 'image' ), true ) ) {
            $kind = 'other';
        }
        $field = array(
            'name'  => '' === $sub ? $attr : $attr . '.' . $sub,
            'kind'  => $kind,
            'label' => $label,
        );
        if ( 'select' === $kind && isset( $props['options'] ) && is_array( $props['options'] ) ) {
            $field['options'] = array_map( 'strval', array_keys( $props['options'] ) );
        }
        if ( 'boolean' === $kind ) {
            $field['on_value']  = 'on';
            $field['off_value'] = 'off';
        }
        if ( ! empty( $node['features']['responsive'] ) ) {
            $field['responsive'] = true;
        }
        $fields[] = self::with_category( $field, $node );
    }

    private static function with_category( array $field, array $node ): array {
        if ( isset( $node['category'] ) && is_string( $node['category'] ) && '' !== $node['category'] ) {
            $field['category'] = $node['category'];
        }
        return $field;
    }
}
