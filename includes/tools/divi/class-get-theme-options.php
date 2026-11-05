<?php
namespace Easy_MCP_AI\Tools\Divi;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Theme_Options extends Base_Tool {

    public function get_name() {
        return 'wp_divi_get_theme_options';
    }

    public function get_description() {
        return 'Reads the Divi theme\'s performance settings (Divi › Theme Options › General › Performance): dynamic module framework, dynamic CSS, dynamic icons, critical CSS and its threshold, deferred block CSS, Google Fonts loading, emoji script and jQuery loading. Only the settings the running Divi shows are listed (Divi 5 hides some). No arguments. Returns { settings: [{ name, label, type (boolean or select), value, default, values? }] }; change them with wp_divi_update_theme_options. Requires the Divi theme active, and Divi\'s Theme Options permission.';
    }

    public function get_category() {
        return 'divi';
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
            'properties' => new \stdClass(),
            'required'   => array(),
        );
    }

    public function execute( array $arguments ) {
        $refusal = Divi_Theme_Options::unavailable();
        if ( null !== $refusal ) {
            throw new \RuntimeException( $refusal ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $settings = array();
        foreach ( Divi_Theme_Options::settings() as $setting ) {
            $settings[] = array_merge( $setting, array( 'value' => Divi_Theme_Options::value( $setting ) ) );
        }
        return array( 'settings' => $settings );
    }
}
