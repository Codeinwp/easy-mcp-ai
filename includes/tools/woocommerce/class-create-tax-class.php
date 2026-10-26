<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Create_Tax_Class extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_create_tax_class';
    }

    public function get_description() {
        return 'Creates a WooCommerce tax class, e.g. "Reduced rate", for products taxed differently. Required: `name`; the slug is made from it. A class that already exists is refused. Tax classes cannot be renamed. Add its rates with wp_wc_create_tax_rate `class` = the returned slug. Returns { slug, name }. Requires WooCommerce active.';
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
                'name' => array(
                    'type'        => 'string',
                    'description' => 'Class name, e.g. "Reduced rate".',
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

        $class = $this->rest_request( 'POST', '/wc/v3/taxes/classes', array( 'name' => sanitize_text_field( $name ) ) );

        return array(
            'slug' => (string) ( $class['slug'] ?? '' ),
            'name' => (string) ( $class['name'] ?? '' ),
        );
    }
}
