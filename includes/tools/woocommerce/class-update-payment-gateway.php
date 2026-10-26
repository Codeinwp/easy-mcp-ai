<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}









class Update_Payment_Gateway extends Base_Tool {

    use Store_Setup;

    const FIELDS = array( 'enabled', 'title', 'description', 'order' );

    public function get_name() {
        return 'wp_wc_update_payment_gateway';
    }

    public function get_description() {
        return 'Switches a WooCommerce payment method on or off, or changes the title and description customers see at checkout, or its position. Required: `id`, the gateway id from wp_wc_list_payment_gateways (e.g. "bacs", "cheque", "cod"). Optional: `enabled` (true/false), `title`, `description`, `order` (integer; lower shows first). Gateway settings such as API keys, accounts or modes are not changed by this tool; set those in WooCommerce › Settings › Payments. WooCommerce does not check that a gateway\'s account is set up before switching it on. Returns { id, title, description, enabled, order, changed }, where `changed` lists the fields that differ from before (empty when the gateway already had these values). Requires WooCommerce active.';
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
                'id'          => array(
                    'type'        => 'string',
                    'description' => 'Gateway id, e.g. "cod".',
                ),
                'enabled'     => array(
                    'type'        => 'boolean',
                    'description' => 'Whether customers can pay with it.',
                ),
                'title'       => array(
                    'type'        => 'string',
                    'description' => 'Name shown at checkout.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Text shown at checkout under the name.',
                ),
                'order'       => array(
                    'type'        => 'integer',
                    'minimum'     => 0,
                    'description' => 'Position at checkout; lower shows first.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $id = self::text_arg( $arguments, 'id' );
        if ( '' === $id ) {
            throw new \InvalidArgumentException( 'Missing required parameters: id' );
        }
        if ( ! preg_match( '/^[\w-]+$/', $id ) ) {
            throw new \InvalidArgumentException( 'Invalid id: a gateway id holds letters, digits, "_" and "-" only, e.g. "cod". Find it with wp_wc_list_payment_gateways.' );
        }

        if ( array_key_exists( 'settings', $arguments ) ) {
            throw new \InvalidArgumentException( 'This tool does not change gateway settings (API keys, accounts, modes): only enabled, title, description and order. Set the rest in WooCommerce › Settings › Payments.' );
        }

        $params = array();
        if ( array_key_exists( 'enabled', $arguments ) ) {
            $params['enabled'] = $this->parse_bool( $arguments['enabled'], 'enabled' );
        }
        foreach ( array( 'title', 'description' ) as $field ) {
            if ( array_key_exists( $field, $arguments ) ) {
                if ( ! is_string( $arguments[ $field ] ) ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: must be a string.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $this->refuse_backslash( $arguments[ $field ], $field );
                $params[ $field ] = $arguments[ $field ];
            }
        }
        if ( isset( $arguments['order'] ) ) {
            $params['order'] = $this->parse_non_negative_int( $arguments['order'], 'order' );
        }
        $this->refuse_empty_update( $params, self::FIELDS );

        $route  = '/wc/v3/payment_gateways/' . $id;
        $before = self::present_gateway( $this->rest_request( 'GET', $route ) );
        $after  = self::present_gateway( $this->rest_request( 'PUT', $route, $params ) );

        $after['changed'] = self::changed_fields( $before, $after, self::FIELDS );
        return $after;
    }

    






    private static function present_gateway( $gateway ): array {
        $gateway = is_array( $gateway ) ? $gateway : array();
        return array(
            'id'          => (string) ( $gateway['id'] ?? '' ),
            'title'       => (string) ( $gateway['title'] ?? '' ),
            'description' => (string) ( $gateway['description'] ?? '' ),
            'enabled'     => (bool) ( $gateway['enabled'] ?? false ),
            'order'       => (int) ( $gateway['order'] ?? 0 ),
        );
    }
}
