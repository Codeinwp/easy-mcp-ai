<?php
namespace Easy_MCP_AI\Tools\Appearance;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Theme_Mod extends Base_Tool {

    const NOT_A_VALUE = '"value" must be a string, number, boolean, object or list; null is not accepted. Use wp_delete_theme_mod to go back to the default.';

    public function get_name() {
        return 'wp_update_theme_mod';
    }

    public function get_description() {
        return 'Changes one theme mod of the active theme, as publishing it in Appearance → Customize does: the setting\'s own validation and sanitizing run. `key` must be a Customizer setting the theme or a plugin registers (wp_get_theme_mods with include_editable: true lists them, with defaults and choices); send `value` in the shape the setting stores (see its default): an object or list when the default is one (it replaces the stored value whole), otherwise a string, number or boolean. Refused, with nothing written: unknown keys, site options, menu locations, custom_css_post_id, Additional CSS (use wp_update_custom_css), values of another shape and values the setting rejects. Live: { published: true, changed, key, previous, value, stored, warning? } — `changed` is false when nothing stored changed. Not live: { published: false, staged: true, key, value, checked, preview_url, share_url, message, reason? } — with publish: false, or when this site stops the Customizer outside its own screen (`reason`), the change waits in the Customizer draft for a person to publish at preview_url (login required); share_url shows it on the site to anyone with the link, until it is published.';
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
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'key'     => array(
                    'type'        => 'string',
                    'description' => 'Customizer setting id of the theme mod to change.',
                ),
                'value'   => array(
                    'description' => 'New value, in the shape the setting stores (see its default): an object or list when the default is one, otherwise a string, number or boolean.',
                    'oneOf'       => array(
                        array( 'type' => 'string' ),
                        array( 'type' => 'number' ),
                        array( 'type' => 'boolean' ),
                        array( 'type' => 'object' ),
                        array(
                            'type'  => 'array',
                            'items' => new \stdClass(),
                        ),
                    ),
                ),
                'publish' => array(
                    'type'        => 'boolean',
                    'description' => 'Make the change live (default true). false stages it in the Customizer draft for a person to review and publish.',
                ),
            ),
            'required'   => array( 'key', 'value' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'key' ) );
        if ( ! array_key_exists( 'value', $arguments ) ) {
            throw new \InvalidArgumentException( 'Missing required parameter: value' );
        }

        $key   = Customizer_Settings::parse_key( $arguments['key'] );
        $value = $arguments['value'];
        if ( ! is_scalar( $value ) && ! is_array( $value ) ) {
            throw new \InvalidArgumentException( self::NOT_A_VALUE );
        }

        $publish = ! array_key_exists( 'publish', $arguments ) || rest_sanitize_boolean( $arguments['publish'] );

        $denied = Customizer_Settings::denied_reason( $key );
        if ( null !== $denied ) {
            throw new \InvalidArgumentException( $denied ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        Customizer_Settings::assert_can_customize();
        if ( ! Customizer_Settings::is_customizer_available() || ! Customizer_Changeset::is_available() ) {
            throw new \RuntimeException( Customizer_Settings::NO_CUSTOMIZER );
        }

        $entered = false;
        $checked = null;
        $unknown = false;
        try {
            return Customizer_Settings::with_manager(
                static function ( $manager ) use ( $key, &$value, $publish, &$entered, &$checked, &$unknown ) {
                    $entered = true;
                    $setting = Customizer_Settings::resolve_writable_setting( $manager, $key );
                    $value = Theme_Mod_Value::conform( (string) $setting->id, $setting->default, $value );
                    Customizer_Settings::validate_value( $manager, $setting, $value );
                    $checked = $setting;

                    return $publish
                        ? self::publish( $manager, $setting, $value, $unknown )
                        : self::staged( (string) $setting->id, $value, true, null );
                }
            );
        } catch ( \Throwable $e ) {
            if ( $unknown || ( $entered && null === $checked ) ) {
                throw $e;
            }
            return self::staged( null === $checked ? $key : (string) $checked->id, $value, null !== $checked, $e );
        }
    }

    private static function publish( object $manager, object $setting, $value, bool &$unknown ): array {
        $id        = (string) $setting->id;
        $previous  = Customizer_Settings::effective_value( $setting );
        $before    = Customizer_Settings::stored_value( $setting );
        $changeset = Customizer_Changeset::create( $id, $value );
        $warnings  = array();
        $saved     = false;
        try {
            if ( ! Customizer_Settings::publish_changeset( $manager, $changeset, $setting, array( $id => $value ), $saved ) ) {
                throw new \RuntimeException( sprintf( 'WordPress did not save "%s" (the value was invalid after sanitizing, or the setting refused the write).', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        } catch ( \Throwable $e ) {
            Customizer_Changeset::discard( $changeset );
            try {
                $after = Customizer_Settings::stored_value( $setting );
                $live  = $after !== $before || ( $saved && null !== $after && $setting->sanitize( $value ) === $after );
            } catch ( \Throwable $read ) {
                $unknown = true;
                throw new \Easy_MCP_AI\MCP\Detailed_Tool_Error(
                    'Whether this change is live is unknown: publishing it failed, and reading it back failed too. Check with wp_get_theme_mods before trying again.',
                    sprintf( 'Publishing "%1$s" failed: %2$s Reading it back failed: %3$s', $id, $e->getMessage(), $read->getMessage() )
                );
            }
            if ( ! $live ) {
                throw $e;
            }
            $warnings[] = 'The value was saved, but code on this site failed after the save: ' . $e->getMessage();
        }

        $result = array(
            'published' => true,
            'changed'   => null,
            'key'       => $id,
            'previous'  => $previous,
            'value'     => null,
            'stored'    => null,
        );
        try {
            $result['stored'] = Customizer_Settings::stored_value( $setting );
            $result['changed'] = $result['stored'] !== $before;
            $result['value']   = Customizer_Settings::effective_value( $setting );
        } catch ( \Throwable $e ) {
            $warnings[] = 'The value was saved, but reading it back failed: ' . $e->getMessage();
        }
        if ( ! empty( $warnings ) ) {
            $result['warning'] = implode( ' ', $warnings );
        }
        return $result;
    }

    private static function staged( string $key, $value, bool $checked, ?\Throwable $why ): array {
        $uuid   = Customizer_Changeset::stage( $key, $value );
        $result = array(
            'published'   => false,
            'staged'      => true,
            'key'         => $key,
            'value'       => $value,
            'checked'     => $checked,
            'preview_url' => Customizer_Changeset::preview_url( $uuid ),
            'share_url'   => Customizer_Changeset::share_url( $uuid ),
            'message'     => null === $why
                ? 'Staged in the Customizer, not published. Open preview_url to review and publish it, or call again with publish: true.'
                : 'Not published (see reason). The change is staged in the Customizer instead; an administrator can review and publish it at preview_url.',
        );
        if ( null !== $why ) {
            $result['reason'] = Customizer_Settings::cause_of( $why );
        }
        return $result;
    }
}
