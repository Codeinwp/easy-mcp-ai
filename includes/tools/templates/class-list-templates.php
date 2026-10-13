<?php
namespace Easy_MCP_AI\Tools\Templates;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Templates extends Base_Tool {

    public function get_name() {
        return 'wp_list_templates';
    }

    public function get_description() {
        return 'Lists block templates or template parts. Optional: `type` (`template` — default — or `template_part`; template parts are the header, footer and other reusable areas of a block theme), `area` (template parts only: `header`, `footer` or `uncategorized`, exact match applied locally), `search` (case-insensitive match on title, slug, or description, applied locally), `per_page` (default 10, max 100), `page` (pagination applied locally). Returns { templates: [{ id, slug, title, description, type, area, status, has_theme_file }], total, total_pages, page, per_page }. `area` is an empty string for templates. IDs are `theme-slug//slug` for both types and are accepted by wp_get_template / wp_update_template with the same `type`. Requires an active block theme (Full Site Editing).';
    }

    public function get_category() {
        return 'templates';
    }

    public function get_required_capability() {
        return 'edit_theme_options';
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
                'type'     => array(
                    'type'        => 'string',
                    'description' => 'What to list: `template` (page templates such as index, single, archive) or `template_part` (header, footer and other reusable areas).',
                    'enum'        => array( 'template', 'template_part' ),
                    'default'     => 'template',
                ),
                'area'     => array(
                    'type'        => 'string',
                    'description' => 'Template parts only: keep parts whose area equals this value (`header`, `footer`, `uncategorized`). Applied locally.',
                ),
                'per_page' => array(
                    'type'        => 'integer',
                    'description' => 'Items per page (1-100).',
                    'default'     => 10,
                    'minimum'     => 1,
                    'maximum'     => 100,
                ),
                'page'     => array(
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'default'     => 1,
                ),
                'search'   => array(
                    'type'        => 'string',
                    'description' => 'Search by keyword.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $type  = Template_Type::resolve( $arguments );
        $label = Template_Type::label( $type );

        if ( ! wp_is_block_theme() ) {
            throw new \RuntimeException( sprintf( '%s are not available. This requires an active block theme (Full Site Editing). The current theme is a classic theme.', $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $area = '';
        if ( isset( $arguments['area'] ) && '' !== $arguments['area'] ) {
            if ( Template_Type::TEMPLATE_PART !== $type ) {
                throw new \InvalidArgumentException( '`area` only applies to template parts. Pass `type: "template_part"` to filter by area.' );
            }
            $area = strtolower( sanitize_text_field( (string) $arguments['area'] ) );
        }

        $per_page = isset( $arguments['per_page'] ) ? min( 100, max( 1, absint( $arguments['per_page'] ) ) ) : 10;
        $page     = isset( $arguments['page'] ) ? absint( $arguments['page'] ) : 1;

        
        
        
        
        $request = new \WP_REST_Request( 'GET', '/wp/v2/' . Template_Type::rest_base( $type ) );
        $request->set_param( 'context', 'edit' );

        $response = rest_do_request( $request );

        if ( $response->is_error() ) {
            $wp_error     = $response->as_error();
            $block_theme_codes = array( 'rest_no_route', 'rest_cannot_manage_templates' );
            if ( in_array( $wp_error->get_error_code(), $block_theme_codes, true ) ) {
                throw new \RuntimeException(
                    sprintf( '%s endpoint is not available. This requires an active block theme (Full Site Editing). The current theme appears to be a classic theme.', $label ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException( $wp_error->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $templates = $response->get_data();

        $result = array();
        foreach ( $templates as $template ) {
            $result[] = array(
                'id'             => $template['id'],
                'slug'           => $template['slug'],
                'title'          => $template['title']['raw'] ?? wp_strip_all_tags( $template['title']['rendered'] ?? '' ),
                'description'    => $template['description'] ?? '',
                'type'           => $template['type'] ?? '',
                'area'           => Template_Type::area_of( $template ),
                'status'         => $template['status'],
                'has_theme_file' => $template['has_theme_file'] ?? false,
            );
        }

        
        
        if ( ! empty( $arguments['search'] ) ) {
            $needle = strtolower( sanitize_text_field( $arguments['search'] ) );
            $result = array_values( array_filter(
                $result,
                function ( $tpl ) use ( $needle ) {
                    return false !== strpos( strtolower( (string) $tpl['title'] ), $needle )
                        || false !== strpos( strtolower( (string) $tpl['slug'] ), $needle )
                        || false !== strpos( strtolower( (string) $tpl['description'] ), $needle );
                }
            ) );
        }

        
        
        
        if ( '' !== $area ) {
            $result = array_values( array_filter(
                $result,
                function ( $tpl ) use ( $area ) {
                    return strtolower( (string) $tpl['area'] ) === $area;
                }
            ) );
        }

        $total       = count( $result );
        $total_pages = (int) ceil( $total / $per_page );
        $offset      = ( max( 1, $page ) - 1 ) * $per_page;
        $paged       = array_slice( $result, $offset, $per_page );

        return array(
            'templates'   => array_values( $paged ),
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => (int) $page,
            'per_page'    => (int) $per_page,
        );
    }
}
