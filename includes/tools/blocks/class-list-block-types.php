<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Block_Types extends Base_Tool {

    use Block_Tree;

    public function get_name() {
        return 'wp_list_block_types';
    }

    public function get_description() {
        return 'Lists the block types registered on the server (core, theme and plugin blocks) so an agent knows which block names and attributes `wp_update_post_blocks` will accept. Optional: `search` (case-insensitive match on name or title), `name` (exact block name such as `core/paragraph` — returns that type with its full `attributes` schema and `supports`), `category`, `per_page` (default 50, max 200), `page`. Without `name` the list omits `attributes` and `supports` to stay small. Returns { block_types: [{ name, title, description, category, parent, ancestor, attributes?, supports? }], total, total_pages, page, per_page }. Blocks registered only in the editor JavaScript are not listed; the `easy_mcp_ai_block_attribute_schema` filter lets a plugin add their attributes. Synced patterns (wp_block posts) are content, not block types — see `wp_list_blocks`.';
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
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Case-insensitive match on the block name or title.',
                ),
                'name'     => array(
                    'type'        => 'string',
                    'description' => 'Exact block name (e.g. "core/paragraph"). Returns that type with attributes and supports.',
                ),
                'category' => array(
                    'type'        => 'string',
                    'description' => 'Block category slug (e.g. "text", "media", "design", "widgets", "theme", "embed").',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Items per page (1-200).',
                    'default'     => 50,
                    'minimum'     => 1,
                    'maximum'     => 200,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WP_Block_Type_Registry' ) ) {
            throw new \RuntimeException( 'The block type registry is not available on this WordPress install.' );
        }

        $per_page = isset( $arguments['per_page'] ) ? min( 200, max( 1, absint( $arguments['per_page'] ) ) ) : 50;
        $page     = isset( $arguments['page'] ) ? max( 1, absint( $arguments['page'] ) ) : 1;
        $name     = isset( $arguments['name'] ) && is_string( $arguments['name'] ) ? trim( $arguments['name'] ) : '';
        $search   = isset( $arguments['search'] ) && is_string( $arguments['search'] ) ? strtolower( trim( $arguments['search'] ) ) : '';
        $category = isset( $arguments['category'] ) && is_string( $arguments['category'] ) ? trim( $arguments['category'] ) : '';

        $registered = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        if ( ! is_array( $registered ) ) {
            $registered = array();
        }
        ksort( $registered, SORT_STRING );

        $items = array();
        foreach ( $registered as $type_name => $type ) {
            $type_name = (string) $type_name;
            $title     = isset( $type->title ) ? (string) $type->title : '';
            if ( '' !== $name && $type_name !== $name ) {
                continue;
            }
            if ( '' !== $category && ( ! isset( $type->category ) || (string) $type->category !== $category ) ) {
                continue;
            }
            if ( '' !== $search
                && false === strpos( strtolower( $type_name ), $search )
                && false === strpos( strtolower( $title ), $search ) ) {
                continue;
            }

            $item = array(
                'name'        => $type_name,
                'title'       => $title,
                'description' => isset( $type->description ) ? (string) $type->description : '',
                'category'    => isset( $type->category ) ? $type->category : null,
                'parent'      => ( isset( $type->parent ) && is_array( $type->parent ) ) ? array_values( $type->parent ) : null,
                'ancestor'    => ( isset( $type->ancestor ) && is_array( $type->ancestor ) ) ? array_values( $type->ancestor ) : null,
            );

            if ( '' !== $name ) {
                $attributes         = $this->block_attribute_schema( $type_name );
                $supports           = ( isset( $type->supports ) && is_array( $type->supports ) ) ? $type->supports : array();
                $item['attributes'] = empty( $attributes ) ? new \stdClass() : $attributes;
                $item['supports']   = empty( $supports ) ? new \stdClass() : $supports;
            }

            $items[] = $item;
        }

        $total       = count( $items );
        $total_pages = (int) ceil( $total / $per_page );
        $paged       = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

        return array(
            'block_types' => array_values( $paged ),
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
        );
    }
}
