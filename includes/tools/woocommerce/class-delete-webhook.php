<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Delete_Webhook extends Base_Tool {

    public function get_name() {
        return 'wp_wc_delete_webhook';
    }

    public function get_description() {
        return 'Permanently deletes a WooCommerce webhook by ID. Required: `id`. Deletion stops all future deliveries immediately and is irreversible. To temporarily stop deliveries without deleting, use `wp_wc_update_webhook` with status="paused" instead. Returns { deleted: true, id }. Requires WooCommerce active.';
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
                    'description' => 'The ID of the webhook to delete.',
                ),
            ),
            'required'   => array( 'id' ),
        );
    }

    public function execute( array $arguments ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'id' ) );
        $id = $this->parse_required_id( $arguments['id'], 'id' );

        $this->rest_request( 'DELETE', '/wc/v3/webhooks/' . $id, array( 'force' => true ) );

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }
}
