<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Post_Blocks extends Base_Tool {

    use Block_Tree;

    public function get_name() {
        return 'wp_get_post_blocks';
    }

    public function get_description() {
        return 'Returns the Gutenberg block tree of a post, page or custom post type item as structured data instead of raw markup — read this before editing blocks with `wp_update_post_blocks`. Required: `post_id`. Optional: `post_type` (REST base, default `posts`; e.g. `pages`), `depth` (levels to expand, default 2, max 10), `path` (dot-joined zero-based indexes such as `0.2.1`; returns only that subtree). Paths count addressable blocks only: named blocks and non-empty classic HTML (reported as `core/freeform`); the whitespace gaps between blocks are skipped, so three paragraphs are `0`, `1`, `2`. Returns { post_id, block_count (root blocks), blocks: [{ path, name, attrs, inner_html_preview (first 160 chars of the block\'s own HTML), children_count, children | has_children }] } — `children` is present within `depth`, beyond it `has_children` is set instead. Reads the raw content with edit context, so the same `edit_post` check as the editor applies. For synced patterns (wp_block posts) use `wp_get_block`.';
    }

    public function get_category() {
        return 'blocks';
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
                'post_id'   => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the post whose blocks to read.',
                ),
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'REST base of the post type (default "posts"; e.g. "pages", "products").',
                    'default'     => 'posts',
                ),
                'depth'     => array(
                    'type'        => 'integer',
                    'description' => 'How many levels of nested blocks to expand (1 = root blocks only). Default 2, max 10.',
                    'default'     => 2,
                    'minimum'     => 1,
                    'maximum'     => 10,
                ),
                'path'      => array(
                    'type'        => 'string',
                    'description' => 'Dot-joined zero-based path of one block, e.g. "0.2.1". Returns only that block and its subtree; depth counts from it.',
                ),
            ),
            'required'   => array( 'post_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'post_id' ) );

        $post_id   = $this->parse_required_id( $arguments['post_id'], 'post_id' );
        $post_type = ! empty( $arguments['post_type'] )
            ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' )
            : 'posts';
        $depth     = isset( $arguments['depth'] ) ? min( 10, max( 1, absint( $arguments['depth'] ) ) ) : 2;
        $segments  = ( isset( $arguments['path'] ) && '' !== $arguments['path'] )
            ? $this->parse_block_path( $arguments['path'] )
            : array();

        
        $data = $this->rest_request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id, array( 'context' => 'edit' ) );
        if ( ! isset( $data['content']['raw'] ) ) {
            throw new \RuntimeException( 'The post content could not be read in edit context.' );
        }

        $root_blocks = parse_blocks( (string) $data['content']['raw'] );
        $root        = array(
            'blockName'    => null,
            'attrs'        => array(),
            'innerBlocks'  => $root_blocks,
            'innerHTML'    => '',
            'innerContent' => null,
        );
        $block_count = count( $this->addressable_indexes( $root_blocks ) );

        if ( empty( $segments ) ) {
            $blocks = $this->build_block_tree( $root_blocks, array(), 1, $depth );
        } else {
            $path_label = implode( '.', $segments );
            $container  = &$this->resolve_container( $root, $segments, $path_label );
            $raw        = $this->raw_index_at( $container['innerBlocks'], end( $segments ), $path_label, count( $segments ) - 1 );
            $parent     = array_slice( $segments, 0, -1 );
            
            
            $subtree = $this->build_block_tree( array( $container['innerBlocks'][ $raw ] ), $parent, 1, $depth );
            $subtree[0]['path'] = $path_label;
            $this->rebase_child_paths( $subtree[0], $path_label );
            $blocks = $subtree;
        }

        return array(
            'post_id'     => (int) $data['id'],
            'block_count' => $block_count,
            'blocks'      => $blocks,
        );
    }

    







    private function rebase_child_paths( array &$item, $prefix ) {
        if ( empty( $item['children'] ) || ! is_array( $item['children'] ) ) {
            return;
        }
        foreach ( $item['children'] as $i => &$child ) {
            $child['path'] = $prefix . '.' . $i;
            $this->rebase_child_paths( $child, $child['path'] );
        }
        unset( $child );
    }
}
