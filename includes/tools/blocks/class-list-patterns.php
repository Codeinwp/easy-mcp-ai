<?php
namespace Easy_MCP_AI\Tools\Blocks;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Patterns extends Base_Tool {

    public function get_name() {
        return 'wp_list_patterns';
    }

    public function get_description() {
        return 'Lists the block patterns registered on the site — the ready-made block layouts from core, the active theme and plugins that the editor offers in its Patterns inserter (not synced patterns / reusable blocks, which wp_list_blocks covers). Optional filters, all applied locally: `category` (a pattern category slug such as `featured`, `text`, `call-to-action`, `banner`, `posts`), `keyword` (case-insensitive match on title, description and keywords), `source` (where the pattern comes from — `core`, `theme`, `plugin`, `pattern-directory/core`, `pattern-directory/theme`, `pattern-directory/featured`; WordPress records a source only for core and pattern-directory patterns, so for the rest it is derived from the slug prefix: the active theme or its parent → `theme`, anything else → `plugin`), `per_page` (default 20, max 100), `page`. Returns { patterns: [{ slug, title, description, categories, block_types, post_types, source }], total, total_pages, page, per_page } without the block markup. Pass `slug` (a pattern `name`, e.g. `twentytwentyfour/banner-hero`) to get that one pattern with its `content` (block markup ready to insert into a post, page or template) and `required_blocks: [{ name, registered }]` — every block type the markup uses and whether it is registered on this site, so a pattern that depends on a plugin block can be spotted before it is inserted.';
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
                'slug'     => array(
                    'type'        => 'string',
                    'description' => 'A pattern name (e.g. `core/query-standard-posts`, `twentytwentyfour/banner-hero`). Returns that single pattern with its content and required_blocks instead of a list.',
                ),
                'category' => array(
                    'type'        => 'string',
                    'description' => 'Keep patterns in this category slug (e.g. `featured`, `text`, `call-to-action`, `banner`, `posts`).',
                ),
                'keyword'  => array(
                    'type'        => 'string',
                    'description' => 'Case-insensitive match on title, description and keywords.',
                ),
                'source'   => array(
                    'type'        => 'string',
                    'description' => 'Keep patterns from this source: `core`, `theme`, `plugin`, `pattern-directory/core`, `pattern-directory/theme` or `pattern-directory/featured`. Derived from the slug prefix for patterns that do not declare one (theme patterns → `theme`, others → `plugin`).',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Items per page (1-100).',
                    'default'     => 20,
                    'minimum'     => 1,
                    'maximum'     => 100,
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
        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 20;
        $page     = isset( $arguments['page'] ) ? max( 1, absint( $arguments['page'] ) ) : 1;

        
        
        
        
        $patterns = $this->rest_request( 'GET', '/wp/v2/block-patterns/patterns' );
        if ( ! is_array( $patterns ) ) {
            $patterns = array();
        }

        if ( isset( $arguments['slug'] ) && '' !== $arguments['slug'] ) {
            $slug = sanitize_text_field( (string) $arguments['slug'] );
            foreach ( $patterns as $pattern ) {
                if ( is_array( $pattern ) && isset( $pattern['name'] ) && (string) $pattern['name'] === $slug ) {
                    return $this->format_single( $pattern );
                }
            }
            throw new \InvalidArgumentException(
                sprintf( 'Unknown pattern slug "%s". Call wp_list_patterns without `slug` to see the registered patterns.', $slug ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $category = isset( $arguments['category'] ) && '' !== $arguments['category'] ? sanitize_text_field( (string) $arguments['category'] ) : '';
        $source   = isset( $arguments['source'] ) && '' !== $arguments['source'] ? sanitize_text_field( (string) $arguments['source'] ) : '';
        $keyword  = isset( $arguments['keyword'] ) && '' !== $arguments['keyword'] ? strtolower( sanitize_text_field( (string) $arguments['keyword'] ) ) : '';

        $result = array();
        foreach ( $patterns as $pattern ) {
            if ( ! is_array( $pattern ) || empty( $pattern['name'] ) ) {
                continue;
            }
            $item = $this->format_summary( $pattern );

            if ( '' !== $category && ! in_array( $category, $item['categories'], true ) ) {
                continue;
            }
            if ( '' !== $source && $item['source'] !== $source ) {
                continue;
            }
            if ( '' !== $keyword && ! $this->matches_keyword( $pattern, $item, $keyword ) ) {
                continue;
            }
            $result[] = $item;
        }

        $total       = count( $result );
        $total_pages = (int) ceil( $total / $per_page );
        $offset      = ( $page - 1 ) * $per_page;

        return array(
            'patterns'    => array_values( array_slice( $result, $offset, $per_page ) ),
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
        );
    }

    





    private function format_summary( array $pattern ) {
        return array(
            'slug'        => (string) $pattern['name'],
            'title'       => isset( $pattern['title'] ) ? wp_strip_all_tags( (string) $pattern['title'] ) : '',
            'description' => isset( $pattern['description'] ) ? wp_strip_all_tags( (string) $pattern['description'] ) : '',
            'categories'  => $this->string_list( $pattern['categories'] ?? array() ),
            'block_types' => $this->string_list( $pattern['block_types'] ?? array() ),
            'post_types'  => $this->string_list( $pattern['post_types'] ?? array() ),
            'source'      => $this->pattern_source( $pattern ),
        );
    }

    














    private function pattern_source( array $pattern ) {
        if ( isset( $pattern['source'] ) && is_string( $pattern['source'] ) && '' !== $pattern['source'] ) {
            return $pattern['source'];
        }

        $name   = isset( $pattern['name'] ) ? (string) $pattern['name'] : '';
        $prefix = false !== strpos( $name, '/' ) ? substr( $name, 0, strpos( $name, '/' ) ) : '';
        if ( '' === $prefix ) {
            return 'plugin';
        }

        $theme_dirs = array( get_stylesheet() );
        if ( function_exists( 'get_template' ) ) {
            $theme_dirs[] = get_template();
        }
        return in_array( $prefix, $theme_dirs, true ) ? 'theme' : 'plugin';
    }

    






    private function format_single( array $pattern ) {
        $content = isset( $pattern['content'] ) ? (string) $pattern['content'] : '';

        return array_merge(
            $this->format_summary( $pattern ),
            array(
                'keywords'        => $this->string_list( $pattern['keywords'] ?? array() ),
                'inserter'        => ! isset( $pattern['inserter'] ) || (bool) $pattern['inserter'],
                'content'         => $content,
                'required_blocks' => $this->required_blocks( $content ),
            )
        );
    }

    







    private function required_blocks( $content ) {
        if ( '' === $content || ! function_exists( 'parse_blocks' ) ) {
            return array();
        }

        $names = array();
        $this->collect_block_names( parse_blocks( $content ), $names );

        $registry = class_exists( 'WP_Block_Type_Registry' ) ? \WP_Block_Type_Registry::get_instance() : null;

        $out = array();
        foreach ( array_keys( $names ) as $name ) {
            $out[] = array(
                'name'       => $name,
                'registered' => $registry ? (bool) $registry->is_registered( $name ) : false,
            );
        }
        return $out;
    }

    private function collect_block_names( array $blocks, array &$names ) {
        foreach ( $blocks as $block ) {
            if ( ! is_array( $block ) ) {
                continue;
            }
            if ( ! empty( $block['blockName'] ) && is_string( $block['blockName'] ) ) {
                $names[ $block['blockName'] ] = true;
            }
            if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
                $this->collect_block_names( $block['innerBlocks'], $names );
            }
        }
    }

    private function matches_keyword( array $pattern, array $item, $needle ) {
        $haystacks = array( $item['title'], $item['description'] );
        foreach ( $this->string_list( $pattern['keywords'] ?? array() ) as $keyword ) {
            $haystacks[] = $keyword;
        }
        foreach ( $haystacks as $haystack ) {
            if ( false !== strpos( strtolower( (string) $haystack ), $needle ) ) {
                return true;
            }
        }
        return false;
    }

    
    private function string_list( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }
        $out = array();
        foreach ( $value as $entry ) {
            if ( is_scalar( $entry ) && '' !== (string) $entry ) {
                $out[] = (string) $entry;
            }
        }
        return array_values( $out );
    }
}
