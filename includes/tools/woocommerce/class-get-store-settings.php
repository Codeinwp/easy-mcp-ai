<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Store_Settings extends Base_Tool {

    use Store_Setup;

    const GROUPS = array( 'general', 'tax', 'shipping' );

    const OPTIONS_LIMIT = 25;

    const UNUSED = array(
        'woocommerce_tax_classes' => 'Tax classes are not a setting since WooCommerce 3.7: use wp_wc_list_tax_classes, wp_wc_create_tax_class and wp_wc_delete_tax_class.',
    );

    public function get_name() {
        return 'wp_wc_get_store_settings';
    }

    public function get_description() {
        return 'Reads WooCommerce store settings. Required: `group`: "general" (store address, selling and shipping countries, taxes and coupons on or off, currency, currency position, thousand and decimal separators, number of decimals), "tax" (prices entered with tax, tax based on, display) or "shipping" (shipping calculator, hide rates when free shipping is available, ship to billing or shipping address). Optional: `id` to read one setting with all its allowed values. Returns { group, settings: [{ id, label, description, type, value, options }] }; a select with more than 25 choices (currencies, countries) shows `options_count` instead of `options` unless `id` names it. Checkbox values are "yes"/"no". Change them with wp_wc_update_store_settings. Requires WooCommerce active.';
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
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'group' => array(
                    'type'        => 'string',
                    'description' => 'Settings group.',
                    'enum'        => self::GROUPS,
                ),
                'id'    => array(
                    'type'        => 'string',
                    'description' => 'One setting id, e.g. "woocommerce_currency", to read it with every allowed value.',
                ),
            ),
            'required'   => array( 'group' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $group = self::text_arg( $arguments, 'group' );
        $this->refuse_unless_one_of( 'group', $group, self::GROUPS, $arguments['group'] ?? '', implode( ', ', self::GROUPS ) );
        $id = self::text_arg( $arguments, 'id' );

        $settings = self::settings_of( $this->rest_request( 'GET', '/wc/v3/settings/' . $group ) );
        if ( '' !== $id ) {
            if ( ! isset( $settings[ $id ] ) ) {
                throw new \InvalidArgumentException( sprintf( 'Unknown setting "%1$s" in group %2$s. Settings: %3$s.', $id, $group, implode( ', ', array_keys( $settings ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            $settings = array( $id => $settings[ $id ] );
        }

        $out = array();
        foreach ( $settings as $setting ) {
            $out[] = self::present( $setting, '' !== $id );
        }
        return array(
            'group'    => $group,
            'settings' => $out,
        );
    }

    public static function settings_of( $data ): array {
        $settings = array();
        foreach ( is_array( $data ) ? $data : array() as $setting ) {
            if ( ! is_array( $setting ) || empty( $setting['id'] ) || isset( self::UNUSED[ $setting['id'] ] ) || in_array( $setting['type'] ?? '', array( 'title', 'sectionend' ), true ) ) {
                continue;
            }
            $settings[ (string) $setting['id'] ] = $setting;
        }
        return $settings;
    }

    private static function present( array $setting, bool $all_options ): array {
        $row = array(
            'id'          => $setting['id'],
            'label'       => $setting['label'] ?? '',
            'description' => $setting['description'] ?? '',
            'type'        => $setting['type'] ?? '',
            'value'       => $setting['value'] ?? null,
        );
        if ( isset( $setting['options'] ) && is_array( $setting['options'] ) ) {
            if ( $all_options || count( $setting['options'] ) <= self::OPTIONS_LIMIT ) {
                $row['options'] = $setting['options'];
            } else {
                $row['options_count'] = count( $setting['options'] );
            }
        }
        return $row;
    }
}
