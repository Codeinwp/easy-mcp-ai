<?php

namespace Easy_MCP_AI\Tools\Divi;

use Easy_MCP_AI\Option_Lock;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Divi_Theme_Options {

    const SETTINGS = array(
        'divi_dynamic_module_framework'                       => array( 'type' => 'checkbox' ),
        'divi_dynamic_css'                                    => array( 'type' => 'checkbox' ),
        'divi_dynamic_icons'                                  => array( 'type' => 'checkbox' ),
        'divi_dynamic_icons_child_theme'                      => array( 'type' => 'checkbox' ),
        'divi_inline_stylesheet'                              => array( 'type' => 'checkbox' ),
        'divi_critical_css'                                   => array( 'type' => 'checkbox' ),
        'divi_critical_threshold_height'                      => array(
            'type'   => 'select',
            'values' => array( 'Low', 'Medium', 'High' ),
        ),
        'divi_dynamic_js_libraries'                           => array( 'type' => 'checkbox' ),
        'divi_disable_emojis'                                 => array( 'type' => 'checkbox' ),
        'divi_defer_block_css'                                => array( 'type' => 'checkbox' ),
        'divi_google_fonts_inline'                            => array( 'type' => 'checkbox' ),
        'divi_limit_google_fonts_support_for_legacy_browsers' => array( 'type' => 'checkbox' ),
        'divi_enable_jquery_body'                             => array( 'type' => 'checkbox' ),
        'divi_enable_jquery_compatibility'                    => array( 'type' => 'checkbox' ),
        'divi_enable_jquery_body_super'                       => array( 'type' => 'checkbox' ),
    );

    const LOCK = 'easy_mcp_ai_divi_options_lock';

    const LOCK_TTL = 30;

    const LOCK_WAIT = 10;

    public static function unavailable(): ?string {
        if ( ! function_exists( 'et_get_option' ) || ! function_exists( 'et_update_option' ) || ! function_exists( 'et_load_core_options' ) ) {
            return 'The Divi theme is not active, so its Theme Options cannot be read here.';
        }
        if ( function_exists( 'et_pb_is_allowed' ) && ! et_pb_is_allowed( 'theme_options' ) ) {
            return 'Divi\'s Role Editor does not give this account Theme Options.';
        }
        return null;
    }

    public static function settings(): array {
        $settings = array();
        foreach ( self::definitions() as $definition ) {
            $id = isset( $definition['id'] ) && is_string( $definition['id'] ) ? $definition['id'] : '';
            if ( ! isset( self::SETTINGS[ $id ] ) || self::SETTINGS[ $id ]['type'] !== ( $definition['type'] ?? '' ) ) {
                continue;
            }
            $setting = array(
                'name'    => $id,
                'label'   => isset( $definition['name'] ) && is_string( $definition['name'] ) ? $definition['name'] : $id,
                'type'    => 'checkbox' === self::SETTINGS[ $id ]['type'] ? 'boolean' : 'select',
                'default' => 'checkbox' === self::SETTINGS[ $id ]['type'] ? 'on' === ( $definition['std'] ?? '' ) : (string) ( $definition['std'] ?? '' ),
            );
            if ( isset( self::SETTINGS[ $id ]['values'] ) ) {
                $setting['values'] = self::SETTINGS[ $id ]['values'];
            }
            $settings[ $id ] = $setting;
        }
        return $settings;
    }

    public static function value( array $setting ) {
        $stored = et_get_option( $setting['name'], '' );
        if ( 'boolean' === $setting['type'] ) {
            return '' === $stored || false === $stored ? (bool) $setting['default'] : 'on' === $stored;
        }
        return is_string( $stored ) && '' !== $stored ? $stored : (string) $setting['default'];
    }

    public static function stored( array $setting, $value ) {
        if ( 'boolean' === $setting['type'] ) {
            return is_bool( $value ) ? ( $value ? 'on' : 'false' ) : array( 'error' => 'must be true or false' );
        }
        $values = $setting['values'] ?? array();
        return is_string( $value ) && in_array( $value, $values, true ) ? $value : array( 'error' => sprintf( 'must be one of: %s', implode( ', ', $values ) ) );
    }

    public static function write( array $settings, array $stored ): array {
        $lock   = self::lock();
        $before = array();
        try {
            self::refresh();
            foreach ( $stored as $name => $value ) {
                $current = self::value( $settings[ $name ] );
                if ( $current === ( 'boolean' === $settings[ $name ]['type'] ? 'on' === $value : $value ) ) {
                    continue;
                }
                $before[ $name ] = $current;
                et_update_option( $name, $value );
            }
        } finally {
            Option_Lock::release( self::LOCK, $lock );
        }
        if ( array() !== $before && class_exists( '\\ET_Core_PageResource' ) && method_exists( '\\ET_Core_PageResource', 'remove_static_resources' ) ) {
            \ET_Core_PageResource::remove_static_resources( 'all', 'all' );
        }
        return $before;
    }

    private static function refresh(): void {
        global $et_theme_options, $shortname;
        if ( ! function_exists( 'et_options_stored_in_one_row' ) || ! et_options_stored_in_one_row() || ! is_string( $shortname ) || '' === $shortname ) {
            return;
        }
        $name = 'et_' . $shortname;
        wp_cache_delete( 'alloptions', 'options' );
        wp_cache_delete( 'notoptions', 'options' );
        wp_cache_delete( $name, 'options' );
        $et_theme_options = get_option( $name ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Divi's own cache of its options, refreshed as et_get_option() fills it.
    }

    private static function definitions(): array {
        global $options;
        if ( ! is_array( $options ) || array() === $options ) {
            try {
                ob_start();
                et_load_core_options();
            } catch ( \Throwable $e ) {
                throw new \RuntimeException( 'Divi\'s Theme Options definitions could not be loaded on this site: ' . $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            } finally {
                ob_end_clean();
            }
        }
        return is_array( $options ) ? array_values( array_filter( $options, 'is_array' ) ) : array();
    }

    private static function lock(): string {
        $deadline = microtime( true ) + self::LOCK_WAIT;
        do {
            $lock = Option_Lock::acquire( self::LOCK, self::LOCK_TTL );
            if ( '' !== $lock ) {
                return $lock;
            }
            usleep( 100000 );
        } while ( microtime( true ) < $deadline );
        throw new \RuntimeException( sprintf( 'Another Divi Theme Options update has been saving for more than %d seconds, so nothing was changed. Call again.', self::LOCK_WAIT ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }
}
