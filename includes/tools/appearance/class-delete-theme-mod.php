<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Theme_Mod extends Base_Tool {

    const MAX_KEYS = 50;

    public function get_name() {
        return 'wp_delete_theme_mod';
    }

    public function get_description() {
        return 'Removes theme mods\' stored values so the theme falls back to its defaults: `key` for one, or `keys` for several in one call (the Customizer loads once per request). Accepts and refuses the same keys as wp_update_theme_mod; for `foo[bar]` only that entry is removed. With `include_unregistered: true`, a name that is no Customizer setting is removed from the stored theme mods as it is, as `wp theme mod remove` does, instead of refused. Every key is checked before anything is removed. Returns { key, deleted, previous, value } for `key`, and { removed: [{ key, deleted, previous, value, customizer }], deleted } for `keys`: `deleted` is false when nothing was stored (nothing is written), `value` is what the theme now reads, and `customizer` is false for a name removed as it is.';
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
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'key'                  => array(
                    'type'        => 'string',
                    'description' => 'Customizer setting id of the theme mod to reset to its default.',
                ),
                'keys'                 => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Several Customizer setting ids to reset in one call, instead of key (at most 50).',
                ),
                'include_unregistered' => array(
                    'type'        => 'boolean',
                    'description' => 'Remove a name that is no Customizer setting from the stored theme mods as it is, instead of refusing it (default false).',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $many = array_key_exists( 'keys', $arguments );
        if ( $many === array_key_exists( 'key', $arguments ) ) {
            throw new \InvalidArgumentException( $many ? 'Pass key for one theme mod or keys for several, not both.' : 'Missing required parameter: key (or keys, for several).' );
        }
        $keys   = $many ? self::keys_of( $arguments['keys'] ) : array( Customizer_Settings::parse_key( $arguments['key'] ) );
        $raw_ok = isset( $arguments['include_unregistered'] ) && rest_sanitize_boolean( $arguments['include_unregistered'] );

        foreach ( $keys as $key ) {
            $denied = Customizer_Settings::denied_reason( $key );
            if ( null !== $denied ) {
                throw new \InvalidArgumentException( $denied ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        Customizer_Settings::assert_can_customize();

        $removed = Customizer_Settings::with_manager(
            static function ( object $manager ) use ( $keys, $raw_ok ): array {
                $targets = array();
                foreach ( $keys as $key ) {
                    $targets[ $key ] = $raw_ok && ! self::is_registered( $manager, $key ) ? null : Customizer_Settings::resolve_writable_setting( $manager, $key );
                }
                $removed = array();
                foreach ( $targets as $key => $setting ) {
                    $removed[] = null === $setting ? self::remove_stored( (string) $key ) : self::remove_setting( $setting );
                }
                return $removed;
            }
        );

        if ( $many ) {
            return array(
                'removed' => $removed,
                'deleted' => in_array( true, array_column( $removed, 'deleted' ), true ),
            );
        }
        unset( $removed[0]['customizer'] );
        return $removed[0];
    }

    private static function keys_of( $raw ): array {
        if ( ! is_array( $raw ) || array() === $raw ) {
            throw new \InvalidArgumentException( 'keys must be a non-empty list of theme mod names.' );
        }
        if ( count( $raw ) > self::MAX_KEYS ) {
            throw new \InvalidArgumentException( sprintf( 'keys lists %1$d theme mods; at most %2$d can be removed in one call.', count( $raw ), self::MAX_KEYS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $keys = array();
        foreach ( array_values( $raw ) as $key ) {
            $keys[] = Customizer_Settings::parse_key( $key );
        }
        return $keys;
    }

    private static function is_registered( object $manager, string $key ): bool {
        if ( $manager->get_setting( $key ) ) {
            return true;
        }
        $manager->add_dynamic_settings( array( $key ) );
        return (bool) $manager->get_setting( $key );
    }

    private static function remove_setting( object $setting ): array {
        $previous = Customizer_Settings::effective_value( $setting );
        if ( ! Customizer_Settings::is_stored( $setting ) ) {
            return array(
                'key'        => (string) $setting->id,
                'deleted'    => false,
                'previous'   => $previous,
                'value'      => $previous,
                'customizer' => true,
            );
        }

        $data = $setting->id_data();
        if ( empty( $data['keys'] ) ) {
            remove_theme_mod( $data['base'] );
        } else {
            $mods = get_theme_mods();
            $root = $mods[ $data['base'] ];
            self::unset_path( $root, $data['keys'] );
            set_theme_mod( $data['base'], $root );
        }

        return array(
            'key'        => (string) $setting->id,
            'deleted'    => true,
            'previous'   => $previous,
            'value'      => Customizer_Settings::effective_value( $setting ),
            'customizer' => true,
        );
    }

    private static function remove_stored( string $name ): array {
        $mods     = get_theme_mods();
        $stored   = is_array( $mods ) && array_key_exists( $name, $mods );
        $previous = get_theme_mod( $name );
        if ( $stored ) {
            remove_theme_mod( $name );
        }
        return array(
            'key'        => $name,
            'deleted'    => $stored,
            'previous'   => $previous,
            'value'      => $stored ? get_theme_mod( $name ) : $previous,
            'customizer' => false,
        );
    }

    private static function unset_path( array &$root, array $keys ): void {
        $last = array_pop( $keys );
        $node = &$root;
        foreach ( $keys as $k ) {
            $node = &$node[ $k ];
        }
        unset( $node[ $last ] );
    }
}
