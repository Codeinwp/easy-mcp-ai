<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Delete_Tax_Rate extends Base_Tool {

    use Store_Setup;

    public function get_name() {
        return 'wp_wc_delete_tax_rate';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce tax rate with its postcodes and cities. Required: `id` (wp_wc_list_tax_rates). Orders already placed keep the tax they were charged. There is no trash and no undo. Returns { deleted: true, id, name, rate, country }. Requires WooCommerce active.';
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
                'id' => array(
                    'type'        => 'integer',
                    'description' => 'Tax rate id.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id   = isset( $arguments['id'] ) ? (int) $arguments['id'] : 0;
        $rate = $id > 0 ? $this->peek( '/wc/v3/taxes/' . $id, 'id,name,rate,country,state,class' ) : null;
        if ( ! $rate ) {
            return '';
        }
        return sprintf(
            /* translators: 1: rate name, 2: percentage, 3: country or "every country", 4: tax class, 5: rate id */
            __( "Permanently delete tax rate '%1\$s' (%2\$s%% in %3\$s, class %4\$s, ID %5\$d).", 'easy-mcp-ai' ),
            (string) ( $rate['name'] ?? '' ),
            (string) ( $rate['rate'] ?? '' ),
            '' === (string) ( $rate['country'] ?? '' ) ? __( 'every country', 'easy-mcp-ai' ) : trim( $rate['country'] . ' ' . ( $rate['state'] ?? '' ) ),
            (string) ( $rate['class'] ?? 'standard' ),
            $id
        );
    }

    public function execute( array $arguments ) {
        $this->require_woocommerce();
        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $rate = $this->rest_request( 'DELETE', '/wc/v3/taxes/' . $id, array( 'force' => true ) );

        return array(
            'deleted' => true,
            'id'      => $id,
            'name'    => (string) ( $rate['name'] ?? '' ),
            'rate'    => (string) ( $rate['rate'] ?? '' ),
            'country' => (string) ( $rate['country'] ?? '' ),
        );
    }
}
