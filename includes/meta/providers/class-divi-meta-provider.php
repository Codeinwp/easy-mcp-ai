<?php

namespace Easy_MCP_AI\Meta\Providers;

use Easy_MCP_AI\Meta\Meta_Field_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Divi_Meta_Provider extends Meta_Field_Provider {

    const DETECT_FUNCTION = 'et_divi_get_content_width';

    public function get_group_slug() {
        return 'divi';
    }

    public function get_label() {
        return 'Divi';
    }

    public function get_key_prefixes() {
        return array( '_et_pb_', '_et_post_' );
    }

    public function is_active() {
        return function_exists( self::DETECT_FUNCTION );
    }

    public function get_fields() {
        return array(
            '_et_pb_page_layout'   => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'et_right_sidebar', 'et_left_sidebar', 'et_no_sidebar', 'et_full_width_page' ),
                'description' => 'Divi sidebar layout of a post or a page not built with the Divi Builder: et_right_sidebar, et_left_sidebar, et_no_sidebar, or et_full_width_page. Divi shows a page built with its builder without a sidebar whatever this holds.',
            ),
            '_et_pb_side_nav'      => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'on', 'off' ),
                'description' => 'Divi dot navigation on the page: on or off.',
            ),
            '_et_pb_post_hide_nav' => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'default', 'no', 'on' ),
                'description' => 'Divi "hide navigation before scroll": default (the theme setting), no (off) or on.',
            ),
            '_et_pb_show_title'    => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'on', 'off' ),
                'description' => 'Whether Divi shows the post title above a post built with the Divi Builder: on or off.',
            ),
            '_et_pb_project_nav'   => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'on', 'off' ),
                'description' => 'Whether a Divi project shows its previous and next links: on or off.',
            ),
            '_et_post_use_bg_color' => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'flag:1',
                'description' => 'Whether Divi uses _et_post_bg_color as the post\'s background: "1" on, "" off.',
            ),
            '_et_post_bg_color'    => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => __CLASS__ . '::hex_colour',
                'description' => 'Divi post background colour, a hex value such as #ffffff; it applies while _et_post_use_bg_color is on.',
            ),
            '_et_post_bg_layout'   => array(
                'auth'        => __CLASS__ . '::page_options_allowed',
                'type'        => 'string',
                'sanitize'    => 'enum',
                'enum'        => array( 'light', 'dark' ),
                'description' => 'Divi post text colour for its background: light or dark.',
            ),
        );
    }

    public static function page_options_allowed( $user_id, $post_id, $key ) {
        unset( $post_id, $key );
        if ( ! function_exists( 'et_pb_is_allowed' ) || (int) $user_id !== get_current_user_id() ) {
            return false;
        }
        return (bool) et_pb_is_allowed( 'page_options' );
    }

    public static function hex_colour( $value ) {
        $value = is_scalar( $value ) ? trim( (string) $value ) : '';
        return 1 === preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value ) ? $value : '';
    }
}
