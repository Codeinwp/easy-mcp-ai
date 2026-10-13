<?php
namespace Easy_MCP_AI\Tools\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





require_once __DIR__ . '/class-approve-operation.php';





class Deny_Operation extends Approve_Operation {

    public function get_name() {
        return 'wp_deny_operation';
    }

    public function get_description() {
        return 'Deny a paused destructive operation from the approval card. Requires the approval id and the card secret from the paused result. Not for models.';
    }

    protected function approves() {
        return false;
    }
}
