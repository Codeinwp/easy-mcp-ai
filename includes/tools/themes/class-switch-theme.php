<?php
namespace Easy_MCP_AI\Tools\Themes;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}











class Switch_Theme extends Base_Tool {

    public function get_name() {
        return 'wp_switch_theme';
    }

    public function get_description() {
        return 'Switches the active theme (changes the site\'s appearance immediately), exactly as the Activate button on Appearance → Themes does. Required: `stylesheet` (the theme folder name — the `stylesheet` field from wp_list_themes). Returns { stylesheet, template, name, version, parent (the parent theme\'s stylesheet for a child theme, else null), previous: { stylesheet, name }, changed (false when it was already active) }. Refused when the theme is not installed, is broken (missing files or a missing parent theme), does not meet its Requires WP / Requires PHP headers, or — on multisite — is not enabled for this site by the network. Switch back with the previous stylesheet from the result. Use wp_get_active_theme to see the current theme.';
    }

    public function get_category() {
        return 'themes';
    }

    public function get_required_capability() {
        return 'switch_themes';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'stylesheet' => array(
                    'type'        => 'string',
                    'description' => 'Theme folder name, e.g. "twentytwentyfive" (the `stylesheet` field from wp_list_themes).',
                ),
            ),
            'required'   => array( 'stylesheet' ),
        );
    }

    public function execute( array $arguments ) {
        $stylesheet = $this->parse_stylesheet( $arguments );
        $theme      = wp_get_theme( $stylesheet );
        $this->assert_activatable( $theme, $stylesheet );

        $previous = wp_get_theme();
        $changed  = $previous->get_stylesheet() !== $theme->get_stylesheet();

        if ( $changed ) {
            
            switch_theme( $theme->get_stylesheet() );
        }

        $parent = $theme->parent();

        return array(
            'stylesheet' => $theme->get_stylesheet(),
            'template'   => $theme->get_template(),
            'name'       => (string) $theme->get( 'Name' ),
            'version'    => (string) $theme->get( 'Version' ),
            'parent'     => $parent ? $parent->get_stylesheet() : null,
            'previous'   => array(
                'stylesheet' => $previous->get_stylesheet(),
                'name'       => (string) $previous->get( 'Name' ),
            ),
            'changed'    => $changed,
        );
    }

    






    private function parse_stylesheet( array $arguments ): string {
        $stylesheet = isset( $arguments['stylesheet'] ) && is_string( $arguments['stylesheet'] ) ? trim( $arguments['stylesheet'] ) : '';
        if ( '' === $stylesheet ) {
            throw new \InvalidArgumentException( 'Missing required parameters: stylesheet' );
        }
        
        
        
        if ( 0 !== validate_file( $stylesheet ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid stylesheet "%s". Pass a theme folder name as returned by wp_list_themes.', $stylesheet ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $stylesheet;
    }

    






    private function assert_activatable( $theme, string $stylesheet ) {
        
        
        if ( ! $theme->exists() ) {
            throw new \RuntimeException(
                sprintf( 'Theme "%s" is not installed. Use wp_list_themes to see installed themes.', $stylesheet ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        if ( ! $theme->is_allowed() ) {
            throw new \RuntimeException(
                sprintf( 'Theme "%s" is not enabled for this site. A network administrator must enable it under Network Admin → Themes first.', $stylesheet ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        
        
        
        
        
        $errors = $theme->errors();
        if ( $errors ) {
            throw new \RuntimeException(
                sprintf( 'Theme "%s" is broken and cannot be activated: %s', $stylesheet, wp_strip_all_tags( $errors->get_error_message() ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        
        
        
        
        
        $requirements = validate_theme_requirements( $stylesheet );
        if ( is_wp_error( $requirements ) ) {
            throw new \RuntimeException( wp_strip_all_tags( $requirements->get_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }
}
