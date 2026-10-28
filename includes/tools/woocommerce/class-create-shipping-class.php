<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Shipping_Class extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_create_shipping_class';
    }

    public function get_description() {
        return 'Creates a WooCommerce shipping class, used to give a group of products its own shipping cost (a flat rate method then gets a cost setting for the class). Required: `name`. Optional: `slug`, `description`. A name or slug that already exists is refused (find it with wp_wc_list_shipping_classes). Returns { id, name, slug, description, count }. Requires WooCommerce active.';
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
                'name'        => array(
                    'type'        => 'string',
                    'description' => 'Class name, e.g. "Bulky items".',
                ),
                'slug'        => array(
                    'type'        => 'string',
                    'description' => 'URL-friendly identifier; made from the name when omitted.',
                ),
                'description' => array(
                    'type'        => 'string',
                    'description' => 'Description shown in the admin.',
                ),
            ),
            'required'   => array( 'name' ),
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $name = self::text_arg( $arguments, 'name' );
        if ( '' === $name ) {
            throw new \InvalidArgumentException( 'Missing required parameters: name' );
        }
        $this->refuse_backslash( $name, 'name' );
        $params = array( 'name' => sanitize_text_field( $name ) );
        if ( '' !== self::text_arg( $arguments, 'slug' ) ) {
            $params['slug'] = sanitize_title( self::text_arg( $arguments, 'slug' ) );
            $this->refuse_taken_slug( $params['slug'] );
        }
        if ( isset( $arguments['description'] ) && is_string( $arguments['description'] ) ) {
            $this->refuse_backslash( $arguments['description'], 'description' );
            $params['description'] = sanitize_textarea_field( $arguments['description'] );
        }

        $class = self::present_shipping_class( $this->rest_request( 'POST', '/wc/v3/products/shipping_classes', $params ) );
        if ( isset( $params['slug'] ) && $class['slug'] !== $params['slug'] ) {
            throw new \RuntimeException( sprintf( 'Shipping class %1$d "%2$s" was created with the slug "%3$s", not "%4$s": WordPress gave it another slug. Delete it with wp_wc_delete_shipping_class or keep it.', $class['id'], $class['name'], $class['slug'], $params['slug'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $class;
    }

    private function refuse_taken_slug( string $slug ): void {
        foreach ( (array) $this->rest_request( 'GET', '/wc/v3/products/shipping_classes', array( 'slug' => $slug ) ) as $class ) {
            if ( is_array( $class ) && $slug === ( $class['slug'] ?? '' ) ) {
                throw new \InvalidArgumentException( sprintf( 'Shipping class %1$d "%2$s" already has the slug "%3$s". Choose another slug, or change that class with wp_wc_update_shipping_class.', (int) ( $class['id'] ?? 0 ), (string) ( $class['name'] ?? '' ), $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
    }
}
