<?php

namespace Easy_MCP_AI\Compat\Shims;

use Easy_MCP_AI\Compat\Compat_Shim;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Astra_Customizer_Loader extends Compat_Shim {

    const TOOLS = array( 'wp_get_theme_mods', 'wp_update_theme_mod', 'wp_delete_theme_mod', 'wp_update_custom_css' );

    const PRIORITY = 1;

    const CONFIG_BASE_FILE = 'inc/customizer/configurations/class-astra-customizer-config-base.php';

    const PANEL_FILES = array(
        'inc/customizer/extend-customizer/class-astra-wp-customize-panel.php',
        'inc/customizer/extend-customizer/class-astra-wp-customize-section.php',
        'inc/customizer/extend-customizer/class-astra-wp-customize-separator.php',
    );

    private $customizer;

    private $theme_dir;

    private $callback = null;

    public function __construct( string $customizer = 'Astra_Customizer', ?string $theme_dir = null ) {
        $this->customizer = $customizer;
        $this->theme_dir  = $theme_dir;
    }

    public function id(): string {
        return 'astra-customizer-loader';
    }

    public function applies_to( $tool ): bool {
        return is_object( $tool ) && method_exists( $tool, 'get_name' ) && in_array( $tool->get_name(), self::TOOLS, true );
    }

    public function is_needed(): bool {
        return '' !== $this->theme_dir() && ! $this->astra_hooked( array( 'include_configurations', 'include_config_base' ) );
    }

    public function enter( array $arguments = array(), $tool = null ): ?\stdClass {
        if ( null !== $this->callback ) {
            return null;
        }
        $this->callback = function (): void {
            $this->load();
        };
        add_action( 'customize_register', $this->callback, self::PRIORITY );
        return new \stdClass();
    }

    public function leave( $state = null ): void {
        if ( null === $this->callback || ! $state instanceof \stdClass ) {
            return;
        }
        remove_action( 'customize_register', $this->callback, self::PRIORITY );
        $this->callback = null;
    }

    private function load(): void {
        if ( ! $this->is_needed() ) {
            return;
        }
        $files = array( self::CONFIG_BASE_FILE );
        if ( ! $this->astra_hooked( array( 'customize_register_panel' ) ) ) {
            $files = array_merge( $files, self::PANEL_FILES );
        }
        foreach ( $files as $file ) {
            $path = $this->theme_dir() . $file;
            if ( is_readable( $path ) ) {
                require_once $path;
            }
        }
    }

    private function theme_dir(): string {
        if ( ! class_exists( $this->customizer, false ) ) {
            return '';
        }
        $dir = null !== $this->theme_dir ? $this->theme_dir : ( defined( 'ASTRA_THEME_DIR' ) ? (string) constant( 'ASTRA_THEME_DIR' ) : '' );
        return '' === $dir ? '' : trailingslashit( $dir );
    }

    private function astra_hooked( array $methods ): bool {
        $hook = $GLOBALS['wp_filter']['customize_register'] ?? null;
        if ( ! is_object( $hook ) || ! isset( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
            return false;
        }
        foreach ( $hook->callbacks as $callbacks ) {
            foreach ( (array) $callbacks as $entry ) {
                if ( in_array( $this->astra_method( $entry ), $methods, true ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    private function astra_method( $entry ): ?string {
        $function = is_array( $entry ) && isset( $entry['function'] ) ? $entry['function'] : null;
        if ( ! is_array( $function ) || ! isset( $function[0], $function[1] ) || ! is_string( $function[1] ) ) {
            return null;
        }
        return $function[0] instanceof $this->customizer ? strtolower( $function[1] ) : null;
    }
}
