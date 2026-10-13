<?php
namespace Easy_MCP_AI\Tools\WooCommerce;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Create_Webhook extends Base_Tool {

    public function get_name() {
        return 'wp_wc_create_webhook';
    }

    public function get_description() {
        return 'Creates a WooCommerce webhook. Required: name, topic (e.g. order.created, product.updated, customer.created), delivery_url. The webhook fires when the topic event occurs.';
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
                'name'         => array(
                    'type'        => 'string',
                    'description' => 'Webhook name.',
                ),
                'topic'        => array(
                    'type'        => 'string',
                    'description' => 'Event topic. Examples: order.created, order.updated, order.deleted, product.created, product.updated, product.deleted, customer.created, customer.updated.',
                ),
                'delivery_url' => array(
                    'type'        => 'string',
                    'description' => 'URL where the webhook will deliver its payload.',
                ),
                'secret'       => array(
                    'type'        => 'string',
                    'description' => 'Secret key for HMAC-SHA256 signature verification.',
                ),
                'status'       => array(
                    'type'        => 'string',
                    'description' => 'Webhook status.',
                    'enum'        => array( 'active', 'paused', 'disabled' ),
                    'default'     => 'active',
                ),
            ),
            'required'   => array( 'name', 'topic', 'delivery_url' ),
        );
    }

    public function execute( array $arguments ) {
        $this->refuse_webhook_api_version( $arguments );
        if ( ! class_exists( 'WooCommerce' ) ) {
            throw new \RuntimeException( 'WooCommerce is not active.' );
        }

        $this->validate_required( $arguments, array( 'name', 'topic', 'delivery_url' ) );

        $params = array(
            'name'         => sanitize_text_field( $arguments['name'] ),
            'topic'        => sanitize_text_field( $arguments['topic'] ),
            'delivery_url' => $this->validate_webhook_url( $arguments['delivery_url'] ),
        );

        if ( isset( $arguments['secret'] ) ) {
            $params['secret'] = sanitize_text_field( $arguments['secret'] );
        }
        if ( isset( $arguments['status'] ) ) {
            $params['status'] = sanitize_text_field( $arguments['status'] );
        }

        $data = $this->rest_request( 'POST', '/wc/v3/webhooks', $params );

        return array(
            'id'           => $data['id'],
            'name'         => $data['name'],
            'topic'        => $data['topic'],
            'delivery_url' => $data['delivery_url'],
            'status'       => $data['status'],
        );
    }
}
