<?php
namespace Easy_MCP_AI\Tools\Divi;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Theme_Builder extends Base_Tool {

    public function get_name() {
        return 'wp_divi_list_theme_builder';
    }

    public function get_description() {
        return 'Lists the Divi Theme Builder\'s templates: where each applies and which header, body and footer layout it uses. Optional `live` (default true): the published Theme Builder; false reads the unpublished draft Divi keeps while someone edits it. Returns { live, theme_builder_id, templates: [{ id, title, default, enabled, use_on, exclude_from, layouts: { header, body, footer: { id, enabled, global, title } } }] }: `use_on` and `exclude_from` are Divi\'s conditions (e.g. "singular:post_type:page:all", "homepage"); a layout id of 0 means the template uses the theme\'s own header, body or footer, and enabled false hides that area. Read and edit a layout with wp_get_page_layout and wp_update_page_layout, passing its id as post_id. Requires the Divi theme, and Divi\'s Theme Builder permission with the right to edit others\' posts, as Divi\'s own Theme Builder.';
    }

    public function get_category() {
        return 'divi';
    }

    public function get_required_capability() {
        return 'edit_others_posts';
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
                'live' => array(
                    'type'        => 'boolean',
                    'description' => 'The published Theme Builder (true, default) or the draft (false).',
                    'default'     => true,
                ),
            ),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        if ( ! function_exists( 'et_theme_builder_get_theme_builder_post_id' ) || ! function_exists( 'et_theme_builder_get_theme_builder_templates' ) ) {
            throw new \RuntimeException( 'Divi\'s Theme Builder is not available on this site.' );
        }
        if ( ! function_exists( 'et_pb_is_allowed' ) || ! et_pb_is_allowed( 'theme_builder' ) ) {
            throw new \RuntimeException( 'Divi\'s Role Editor does not give this account the Theme Builder (Divi › Role Editor › Theme Builder).' );
        }
        $live = ! isset( $arguments['live'] ) || rest_sanitize_boolean( $arguments['live'] );
        $id   = (int) et_theme_builder_get_theme_builder_post_id( $live, false );
        if ( $id <= 0 ) {
            return array(
                'live'             => $live,
                'theme_builder_id' => 0,
                'templates'        => array(),
                'note'             => $live ? 'This site has no Divi Theme Builder templates yet; every page uses the theme\'s own header, body and footer.' : 'No unpublished Theme Builder draft is open.',
            );
        }
        $templates = array();
        foreach ( (array) et_theme_builder_get_theme_builder_templates( $live, $id ) as $template ) {
            if ( is_array( $template ) ) {
                $templates[] = self::template( $template );
            }
        }
        return array(
            'live'             => $live,
            'theme_builder_id' => $id,
            'templates'        => $templates,
        );
    }

    private static function template( array $template ): array {
        $layouts = array();
        foreach ( array( 'header', 'body', 'footer' ) as $area ) {
            $layout           = isset( $template['layouts'][ $area ] ) && is_array( $template['layouts'][ $area ] ) ? $template['layouts'][ $area ] : array();
            $id               = (int) ( $layout['id'] ?? 0 );
            $layouts[ $area ] = array(
                'id'      => $id,
                'enabled' => ! empty( $layout['enabled'] ),
                'global'  => ! empty( $layout['global'] ),
                'title'   => $id > 0 ? get_the_title( $id ) : '',
            );
        }
        return array(
            'id'           => (int) ( $template['id'] ?? 0 ),
            'title'        => (string) ( $template['title'] ?? '' ),
            'default'      => ! empty( $template['default'] ),
            'enabled'      => ! empty( $template['enabled'] ),
            'use_on'       => array_values( array_map( 'strval', (array) ( $template['use_on'] ?? array() ) ) ),
            'exclude_from' => array_values( array_map( 'strval', (array) ( $template['exclude_from'] ?? array() ) ) ),
            'layouts'      => $layouts,
        );
    }
}
