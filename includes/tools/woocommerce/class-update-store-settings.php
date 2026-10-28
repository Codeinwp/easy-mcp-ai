<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Update_Store_Settings extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_update_store_settings';
    }

    public function get_description() {
        return 'Changes WooCommerce store settings in one group. Required: `group` ("general", "tax" or "shipping") and `settings`, an object of setting id to new value, e.g. { "woocommerce_currency": "EUR", "woocommerce_price_num_decimals": "2" }. Find the ids, types and allowed values with wp_wc_get_store_settings. Checkboxes take true/false or "yes"/"no"; a select takes one of its options; a multiselect (e.g. woocommerce_specific_allowed_countries) takes the full list of codes and replaces the current one. Every value is checked before anything is saved, and an unknown id or value is refused. Changing woocommerce_currency does not convert any price: products keep their numbers in the new currency. Returns { group, changed, unchanged, settings: [{ id, value }], adjusted? }, read back after saving: `adjusted` lists each setting WooCommerce saved differently from what was sent (e.g. a negative number of decimals saved as positive), as { id, sent, saved }. Requires WooCommerce active.';
    }

    public function get_category() {
        return 'woocommerce';
    }

    public function get_required_capability() {
        return 'manage_woocommerce';
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
                'group'    => array(
                    'type'        => 'string',
                    'description' => 'Settings group.',
                    'enum'        => Get_Store_Settings::GROUPS,
                ),
                'settings' => array(
                    'type'        => 'object',
                    'description' => 'Setting id to new value. Any setting id of the group (wp_wc_get_store_settings); values are strings, true/false for a checkbox, or a list of codes for a multiselect.',
                ),
            ),
            'required'   => array( 'group', 'settings' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $group = self::text_arg( $arguments, 'group' );
        $this->refuse_unless_one_of( 'group', $group, Get_Store_Settings::GROUPS, $arguments['group'] ?? '', implode( ', ', Get_Store_Settings::GROUPS ) );
        $sent = $arguments['settings'] ?? null;
        if ( ! is_array( $sent ) || empty( $sent ) || array_values( $sent ) === $sent ) {
            throw new \InvalidArgumentException( 'Invalid settings: send an object of setting id to value, e.g. { "woocommerce_currency": "EUR" }.' );
        }

        foreach ( array_keys( $sent ) as $id ) {
            if ( isset( Get_Store_Settings::UNUSED[ $id ] ) ) {
                throw new \InvalidArgumentException( Get_Store_Settings::UNUSED[ $id ] );
            }
        }
        $before = Get_Store_Settings::settings_of( $this->rest_request( 'GET', '/wc/v3/settings/' . $group ) );
        $this->refuse_unknown_keys( $sent, array_keys( $before ), 'setting in group ' . $group );

        $update = array();
        foreach ( $sent as $id => $value ) {
            $update[] = array(
                'id'    => (string) $id,
                'value' => $this->normalize_setting_value( (string) $id, $value, $before[ $id ], 'wp_wc_get_store_settings { group, id: "%s" }' ),
            );
        }

        $reply = $this->rest_request( 'POST', '/wc/v3/settings/' . $group . '/batch', array( 'update' => $update ) );
        try {
            $after = Get_Store_Settings::settings_of( $this->rest_request( 'GET', '/wc/v3/settings/' . $group ) );
        } catch ( \RuntimeException $e ) {
            throw new \RuntimeException( sprintf( 'WooCommerce took the settings, but reading group %1$s back failed, so what was saved is not confirmed: %2$s Read them with wp_wc_get_store_settings.', $group, $e->getMessage() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $this->report( $group, $update, $before, $after, is_array( $reply ) && isset( $reply['update'] ) ? (array) $reply['update'] : array() );
    }

    private function report( string $group, array $update, array $before, array $after, array $items ): array {
        $errors = array();
        foreach ( $items as $item ) {
            if ( is_array( $item ) && isset( $item['error'] ) ) {
                $errors[] = sprintf( '%s: %s', $item['id'] ?? '?', is_array( $item['error'] ) ? ( $item['error']['message'] ?? 'refused' ) : 'refused' );
            }
        }

        $changed   = array();
        $unchanged = array();
        $settings  = array();
        $adjusted  = array();
        $unread = array_diff( array_column( $update, 'id' ), array_keys( $after ) );
        if ( ! empty( $unread ) ) {
            throw new \RuntimeException( sprintf( 'WooCommerce took the settings, but %1$s did not come back when group %2$s was read again, so what was saved is not confirmed. Read them with wp_wc_get_store_settings.', implode( ', ', $unread ), $group ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        foreach ( $update as $row ) {
            $id    = $row['id'];
            $old   = self::stored_value( $before[ $id ] ?? array() );
            $saved = self::stored_value( $after[ $id ] ?? array() );
            if ( self::changed_fields( array( 'v' => $old ), array( 'v' => $saved ), array( 'v' ) ) ) {
                $changed[] = $id;
            } else {
                $unchanged[] = $id;
            }
            $settings[] = array(
                'id'    => $id,
                'value' => $saved,
            );
            if ( self::changed_fields( array( 'v' => $row['value'] ), array( 'v' => $saved ), array( 'v' ) ) ) {
                $adjusted[] = array(
                    'id'    => $id,
                    'sent'  => $row['value'],
                    'saved' => $saved,
                );
            }
        }

        if ( ! empty( $errors ) ) {
            $message = sprintf( 'WooCommerce refused %1$s in group %2$s.', implode( '; ', $errors ), $group );
            if ( ! empty( $changed ) ) {
                $message .= sprintf( ' Saved before the refusal: %s.', implode( ', ', $changed ) );
            }
            throw new \RuntimeException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $result = array(
            'group'     => $group,
            'changed'   => $changed,
            'unchanged' => $unchanged,
            'settings'  => $settings,
        );
        if ( ! empty( $adjusted ) ) {
            $result['adjusted'] = $adjusted;
        }
        if ( in_array( 'woocommerce_currency', $changed, true ) ) {
            $result['note'] = 'The store currency changed. No price was converted: every product, coupon and shipping cost keeps its number, now in the new currency.';
        }
        return $result;
    }

    private static function stored_value( array $setting ) {
        $value = $setting['value'] ?? null;
        return 'multiselect' === ( $setting['type'] ?? '' ) && '' === $value ? array() : $value;
    }
}
