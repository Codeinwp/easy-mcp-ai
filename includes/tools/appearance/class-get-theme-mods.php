<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Theme_Mods extends Base_Tool {

    public function get_name() {
        return 'wp_get_theme_mods';
    }

    public function get_description() {
        return 'Reads the active theme\'s stored theme mods (the Customizer values classic themes keep: logo, colours, layout). Returns { stylesheet, mods }. `keys` limits the result; a key with nothing stored is null (the theme uses its default), and `foo[bar]` reads inside the `foo` mod. `include_editable: true` also returns `editable`: the settings wp_update_theme_mod and wp_delete_theme_mod accept, as { key, default, label, control_type, choices }; it runs the theme\'s Customizer code, so it is slower. Additional CSS is read with wp_get_custom_css.';
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
                'keys'             => array(
                    'type'        => 'array',
                    'description' => 'Theme mod names to return. Omit for every stored mod.',
                    'items'       => array( 'type' => 'string' ),
                ),
                'include_editable' => array(
                    'type'        => 'boolean',
                    'description' => 'Also list the Customizer settings you may change, with labels, control types and choices. Slower: loads the Customizer.',
                    'default'     => false,
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $mods = get_theme_mods();
        if ( ! is_array( $mods ) ) {
            $mods = array();
        }

        if ( isset( $arguments['keys'] ) ) {
            if ( ! is_array( $arguments['keys'] ) ) {
                throw new \InvalidArgumentException( '"keys" must be an array of theme mod names.' );
            }
            $selected = array();
            foreach ( $arguments['keys'] as $key ) {
                if ( ! is_string( $key ) || '' === trim( $key ) ) {
                    throw new \InvalidArgumentException( 'Every entry in "keys" must be a non-empty string.' );
                }
                $key              = trim( $key );
                $selected[ $key ] = $this->lookup( $mods, $key );
            }
            $mods = $selected;
        }

        $result = array(
            'stylesheet' => get_stylesheet(),
            'mods'       => empty( $mods ) ? new \stdClass() : $mods,
        );

        if ( ! empty( $arguments['include_editable'] ) && rest_sanitize_boolean( $arguments['include_editable'] ) ) {
            Customizer_Settings::assert_can_customize();
            $result['editable'] = Customizer_Settings::with_manager(
                static function ( $manager ) {
                    return Customizer_Settings::describe_editable( $manager );
                }
            );
        }

        return $result;
    }

    private function lookup( array $mods, string $key ) {
        $base = Customizer_Settings::base_of( $key );
        if ( ! array_key_exists( $base, $mods ) ) {
            return null;
        }
        $node = $mods[ $base ];
        if ( $base === $key ) {
            return $node;
        }
        $path = explode( '[', str_replace( ']', '', $key ) );
        array_shift( $path );
        foreach ( $path as $segment ) {
            if ( is_array( $node ) && array_key_exists( $segment, $node ) ) {
                $node = $node[ $segment ];
            } elseif ( is_object( $node ) && property_exists( $node, $segment ) ) {
                $node = $node->$segment;
            } else {
                return null;
            }
        }
        return $node;
    }
}
