<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Delete_Tax_Class extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_delete_tax_class';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce tax class AND every tax rate in it. Required: `slug` (wp_wc_list_tax_classes). The "standard" class cannot be deleted. There is no trash and no undo. Returns { deleted: true, slug, name, rates_deleted }. Requires WooCommerce active.';
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
            'destructiveHint' => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'slug' => array(
                    'type'        => 'string',
                    'description' => 'Tax class slug, e.g. "reduced-rate".',
                ),
            ),
            'required'   => array( 'slug' ),
        );
    }

    
    public function describe( array $arguments ) {
        try {
            $slug = $this->parse_slug( $arguments );
            return sprintf(
                /* translators: 1: tax class slug, 2: number of tax rates */
                __( "Permanently delete tax class '%1\$s' and its %2\$d tax rate(s).", 'easy-mcp-ai' ),
                $slug,
                $this->count_rates( $slug )
            );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $slug  = $this->parse_slug( $arguments );
        $rates = $this->count_rates( $slug );

        $class = $this->rest_request( 'DELETE', '/wc/v3/taxes/classes/' . rawurlencode( $slug ), array( 'force' => true ) );

        return array(
            'deleted'       => true,
            'slug'          => $slug,
            'name'          => (string) ( $class['name'] ?? '' ),
            'rates_deleted' => $rates,
        );
    }

    






    private function parse_slug( array $arguments ): string {
        $slug = sanitize_title( self::text_arg( $arguments, 'slug' ) );
        if ( '' === $slug ) {
            throw new \InvalidArgumentException( 'Missing required parameters: slug' );
        }
        if ( 'standard' === $slug ) {
            throw new \InvalidArgumentException( 'The "standard" tax class is built into WooCommerce and cannot be deleted. Delete its rates one by one with wp_wc_delete_tax_rate.' );
        }
        return $slug;
    }

    








    private function count_rates( string $slug ): int {
        $request  = self::build_rest_request( 'GET', '/wc/v3/taxes', array( 'class' => $slug, 'per_page' => 1 ) );
        $response = rest_do_request( $request );
        if ( $response->is_error() ) {
            $message = self::rest_error_message_or_empty( $response );
            throw new \RuntimeException( '' === $message ? self::rest_status_message( 'GET', '/wc/v3/taxes', $response ) : $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $headers = $response->get_headers();
        $total   = $headers['X-WP-Total'] ?? null;
        if ( ! is_numeric( $total ) || (int) $total < 0 ) {
            throw new \RuntimeException( sprintf( 'WooCommerce did not say how many tax rates the class "%s" holds, and deleting the class deletes them all; nothing was deleted. List them with wp_wc_list_tax_rates `class`.', $slug ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return (int) $total;
    }
}
