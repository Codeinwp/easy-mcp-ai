<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Custom_Css extends Base_Tool {

    public function get_name() {
        return 'wp_get_custom_css';
    }

    public function get_description() {
        return 'Reads a theme\'s Additional CSS (Appearance → Customize → Additional CSS). `stylesheet` defaults to the active theme; each theme keeps its own. Returns { stylesheet, active, css, post_id } (post_id is null when there is none). Change it with wp_update_custom_css; block-theme global styles CSS is separate (wp_get_global_styles).';
    }

    public function get_category() {
        return 'appearance';
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
                'stylesheet' => array(
                    'type'        => 'string',
                    'description' => 'Theme folder name (stylesheet). Defaults to the active theme.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $active     = get_stylesheet();
        $stylesheet = $active;
        if ( isset( $arguments['stylesheet'] ) && '' !== $arguments['stylesheet'] ) {
            if ( ! is_string( $arguments['stylesheet'] ) ) {
                throw new \InvalidArgumentException( '"stylesheet" must be a theme folder name.' );
            }
            $stylesheet = trim( $arguments['stylesheet'] );
            if ( ! $this->is_installed_theme( $stylesheet ) ) {
                throw new \InvalidArgumentException( sprintf( 'Theme "%s" is not installed. Use wp_list_themes to see installed themes.', $stylesheet ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }

        $post = wp_get_custom_css_post( $stylesheet );

        return array(
            'stylesheet' => $stylesheet,
            'active'     => $stylesheet === $active,
            'css'        => (string) wp_get_custom_css( $stylesheet ),
            'post_id'    => $post ? (int) $post->ID : null,
        );
    }

    











    private function is_installed_theme( string $stylesheet ): bool {
        return array_key_exists( $stylesheet, wp_get_themes( array( 'errors' => null ) ) );
    }
}
