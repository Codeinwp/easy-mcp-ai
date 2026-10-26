<?php
namespace Easy_MCP_AI\Tools\Builders;

use Easy_MCP_AI\Builders\Builder_Registry;
use Easy_MCP_AI\Builders\Layout_Adapter;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Get_Page_Layout extends Base_Tool {

    
    const DEFAULT_DEPTH = 4;
    const MAX_DEPTH     = 12;

    public function get_name() {
        return 'wp_get_page_layout';
    }

    public function get_description() {
        return 'Reads the layout of a post, page, template or UX Block built with a page builder (Elementor, WPBakery or Flatsome UX Builder) from the builder\'s own stored data, which post tools do not show. Detects the builder per item. Required: `post_id`. Optional: `element` (an address from a previous read; returns that element with its settings), `depth` (levels to expand, default 4, max 12), `include_settings` (each element\'s stored settings; default false). Returns { post_id, builder, builder_version, version, editable, element_count, elements: [{ address, type, label, text (excerpt), image, link, flags, children_count, children | has_children }] }; `editable` says whether wp_update_page_layout can change the item (`edit_note` says why not), and `version` is what that edit sends. Addresses are Elementor element ids, or dot-joined zero-based paths for shortcode builders. Flag `atomic` marks Elementor V4 elements, which Elementor\'s own abilities edit (see `vendor_route`); `code` marks elements that run HTML or script. For a Gutenberg or classic post `builder` is null and `use` names the tool to read it. Requires edit access to the item.';
    }

    public function get_category() {
        return 'builders';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'post_id'          => array(
                    'type'        => 'integer',
                    'description' => 'ID of the post, page, template or UX Block.',
                ),
                'element'          => array(
                    'type'        => 'string',
                    'description' => 'Address of one element, as a previous read returned it (an Elementor id such as "a1b2c3d", or a path such as "0.1.2"). Returns that element with its settings and its children.',
                ),
                'depth'            => array(
                    'type'        => 'integer',
                    'description' => 'How many levels to expand (1 = root elements only). Default 4, max 12.',
                    'default'     => self::DEFAULT_DEPTH,
                    'minimum'     => 1,
                    'maximum'     => self::MAX_DEPTH,
                ),
                'include_settings' => array(
                    'type'        => 'boolean',
                    'description' => 'Include each element\'s stored settings or attributes. Default false (an element read with `element` always has them).',
                    'default'     => false,
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );
        $post_id = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $depth   = isset( $arguments['depth'] ) ? min( self::MAX_DEPTH, max( 1, absint( $arguments['depth'] ) ) ) : self::DEFAULT_DEPTH;
        $element = isset( $arguments['element'] ) ? trim( (string) $arguments['element'] ) : '';
        $with    = ! empty( $arguments['include_settings'] );

        $post = get_post( $post_id );
        if ( ! is_object( $post ) || empty( $post->ID ) || 'revision' === ( $post->post_type ?? '' ) ) {
            throw new \RuntimeException( sprintf( 'No post with ID %d.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( sprintf( 'You cannot edit post %d, so its layout cannot be read.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $adapter = Builder_Registry::for_post( $post );
        if ( null === $adapter ) {
            return $this->not_a_builder_page( $post );
        }

        $nodes = $adapter->nodes( $post );
        $reply = array(
            'post_id'         => $post_id,
            'post_type'       => $post->post_type,
            'title'           => $post->post_title,
            'builder'         => $adapter->id(),
            'builder_label'   => $adapter->label(),
            'builder_version' => $adapter->version(),
            'version'         => $adapter->version_token( $post ),
            'element_count'   => self::count_nodes( $nodes ),
        );
        $refusal           = $adapter->edit_refusal( $post );
        $reply['editable'] = null === $refusal;
        if ( null !== $refusal ) {
            $reply['edit_note'] = $refusal;
        }

        if ( '' !== $element ) {
            $node = self::find( $nodes, $element );
            if ( null === $node ) {
                throw new \InvalidArgumentException( sprintf( 'No element "%s" in post %d. Read the layout without `element` to see the addresses.', $element, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $reply['element'] = self::item( $node, 1, $depth, true );
        } else {
            $reply['elements'] = array_map(
                static function ( array $node ) use ( $depth, $with ): array {
                    return self::item( $node, 1, $depth, $with );
                },
                $nodes
            );
        }

        $references = $adapter->references( $nodes );
        if ( array() !== $references ) {
            $reply['references'] = $references;
        }
        $route = $adapter->vendor_route( $post, $nodes );
        if ( null !== $route ) {
            $reply['vendor_route'] = $route;
        }
        return $reply;
    }

    





    private function not_a_builder_page( object $post ): array {
        $present = array_map(
            static function ( Layout_Adapter $adapter ): string {
                return $adapter->label();
            },
            Builder_Registry::present()
        );
        $blocks  = function_exists( 'has_blocks' ) && has_blocks( $post->post_content );
        return array(
            'post_id'          => (int) $post->ID,
            'post_type'        => $post->post_type,
            'title'            => $post->post_title,
            'builder'          => null,
            'builders_on_site' => $present,
            'use'              => $blocks ? 'wp_get_post_blocks' : 'wp_get_post',
            'note'             => $blocks
                ? 'This item is not built with a supported page builder; its content is Gutenberg blocks.'
                : 'This item is not built with a supported page builder; its content is classic post content.',
        );
    }

    








    private static function item( array $node, int $level, int $depth, bool $settings ): array {
        $item = array(
            'address' => $node['address'],
            'type'    => $node['type'],
        );
        foreach ( array( 'label', 'text', 'image', 'link', 'flags' ) as $key ) {
            if ( null !== $node[ $key ] && '' !== $node[ $key ] && array() !== $node[ $key ] ) {
                $item[ $key ] = $node[ $key ];
            }
        }
        if ( $settings && array() !== $node['settings'] ) {
            $item['settings'] = $node['settings'];
        }
        $item['children_count'] = count( $node['children'] );
        if ( array() === $node['children'] ) {
            return $item;
        }
        if ( $level >= $depth ) {
            $item['has_children'] = true;
            return $item;
        }
        $item['children'] = array();
        foreach ( $node['children'] as $child ) {
            $item['children'][] = self::item( $child, $level + 1, $depth, $settings );
        }
        return $item;
    }

    






    private static function find( array $nodes, string $address ): ?array {
        foreach ( $nodes as $node ) {
            if ( $node['address'] === $address ) {
                return $node;
            }
            $found = self::find( $node['children'], $address );
            if ( null !== $found ) {
                return $found;
            }
        }
        return null;
    }

    





    private static function count_nodes( array $nodes ): int {
        $count = 0;
        foreach ( $nodes as $node ) {
            $count += 1 + self::count_nodes( $node['children'] );
        }
        return $count;
    }
}
