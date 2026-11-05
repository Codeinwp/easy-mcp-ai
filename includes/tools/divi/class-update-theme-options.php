<?php
namespace Easy_MCP_AI\Tools\Divi;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Theme_Options extends Base_Tool {

    public function get_name() {
        return 'wp_divi_update_theme_options';
    }

    public function get_description() {
        return 'Changes Divi theme performance settings, as Divi › Theme Options › General › Performance does. Required: `settings`, an object of setting name to value, for settings wp_divi_get_theme_options lists: a boolean for a switch, one of the listed values for a select (divi_critical_threshold_height: Low, Medium or High). A setting this Divi does not show, or a wrong value, refuses the call and nothing is changed. After a change, Divi\'s cached CSS and JavaScript is cleared for every page, so the next visit rebuilds it. Returns { updated, changed: [{ name, before, after }], unchanged: [names], settings }: updated is false when every value sent was already stored. Requires the Divi theme active, and Divi\'s Theme Options permission.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'settings' => array(
                    'type'        => 'object',
                    'description' => 'Setting name => new value: true or false for a switch, a listed value for a select. Names from wp_divi_get_theme_options.',
                ),
            ),
            'required'   => array( 'settings' ),
        );
    }

    public function describe( array $arguments ) {
        $names = isset( $arguments['settings'] ) && is_array( $arguments['settings'] ) ? array_map( 'strval', array_keys( $arguments['settings'] ) ) : array();
        return sprintf( 'Change Divi performance settings: %s', array() === $names ? 'none' : implode( ', ', $names ) );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'settings' ) );
        if ( ! is_array( $arguments['settings'] ) || array() === $arguments['settings'] ) {
            throw new \InvalidArgumentException( '`settings` must be an object of setting name to value.' );
        }
        $refusal = Divi_Theme_Options::unavailable();
        if ( null !== $refusal ) {
            throw new \RuntimeException( $refusal ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $settings = Divi_Theme_Options::settings();
        $problems = array();
        $stored   = array();
        foreach ( $arguments['settings'] as $name => $value ) {
            $name = (string) $name;
            if ( ! isset( $settings[ $name ] ) ) {
                $problems[] = sprintf( '"%s" is not a performance setting this Divi shows (wp_divi_get_theme_options lists them)', $name );
                continue;
            }
            $checked = Divi_Theme_Options::stored( $settings[ $name ], $value );
            if ( is_array( $checked ) ) {
                $problems[] = sprintf( '"%s" %s', $name, $checked['error'] );
                continue;
            }
            $stored[ $name ] = $checked;
        }
        if ( array() !== $problems ) {
            throw new \InvalidArgumentException( 'Nothing was changed: ' . implode( '; ', $problems ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $before = Divi_Theme_Options::write( $settings, $stored );
        $changed = array();
        foreach ( $before as $name => $was ) {
            $now = Divi_Theme_Options::value( $settings[ $name ] );
            if ( $now === $was ) {
                throw new \RuntimeException( sprintf( 'Divi did not store the new value of "%s"; nothing may have been saved. Read the settings again with wp_divi_get_theme_options.', $name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $changed[] = array(
                'name'   => $name,
                'before' => $was,
                'after'  => $now,
            );
        }
        $all = array();
        foreach ( $settings as $setting ) {
            $all[] = array_merge( $setting, array( 'value' => Divi_Theme_Options::value( $setting ) ) );
        }
        return array(
            'updated'   => array() !== $changed,
            'changed'   => $changed,
            'unchanged' => array_values( array_diff( array_map( 'strval', array_keys( $arguments['settings'] ) ), array_keys( $before ) ) ),
            'settings'  => $all,
        );
    }
}
