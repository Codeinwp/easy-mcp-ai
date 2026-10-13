<?php
namespace Easy_MCP_AI\Tools\Approvals;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













class Approve_Operation extends Base_Tool {

    
    protected $identity = null;

    public function get_name() {
        return 'wp_approve_operation';
    }

    public function get_description() {
        return 'Approve a paused destructive operation from the approval card. Requires the approval id and the card secret from the paused result. Not for models: the operation itself runs when the original tool is called again.';
    }

    public function get_category() {
        return 'approvals';
    }

    public function get_required_capability() {
        return 'read';
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'id'     => array( 'type' => 'string', 'description' => 'The approval id from the paused result.' ),
                'secret' => array( 'type' => 'string', 'description' => 'The card secret from the paused result.' ),
            ),
            'required'   => array( 'id', 'secret' ),
        );
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        );
    }

    public function get_redacted_arguments() {
        return array( 'secret' );
    }

    
    public function get_ui_meta() {
        return array( 'visibility' => array( 'app' ) );
    }

    public function set_request_identity( array $identity ) {
        $this->identity = $identity;
    }

    protected function approves() {
        return true;
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'id', 'secret' ) );
        if ( ! is_array( $this->identity ) ) {
            throw new \RuntimeException( 'No request identity.' );
        }
        \Easy_MCP_AI\MCP\Server::load_approval_classes();
        $gate    = new \Easy_MCP_AI\Approvals\Approval_Gate( new \Easy_MCP_AI\Approvals\Wpdb_Approval_Store() );
        $outcome = $gate->decide_from_card( (string) $arguments['id'], (string) $arguments['secret'], $this->identity, $this->approves() );
        if ( ! $outcome['ok'] ) {
            throw new \RuntimeException( $outcome['message'] );
        }
        return array( 'id' => (string) $arguments['id'], 'status' => $this->approves() ? 'approved' : 'denied' );
    }
}
