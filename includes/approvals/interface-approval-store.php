<?php
namespace Easy_MCP_AI\Approvals;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







interface Approval_Store {
    
    public function create( array $row );

    
    public function find( $approval_id );

    







    public function find_latest( array $identity, $tool_name, $args_hash, $status );

    





    public function decide( $approval_id, $status, $decided_by, $decision_via, $now );

    





    public function claim( $approval_id, $now );

    
    public function update( $approval_id, array $fields );

    
    public function expire_pending( $now );

    
    public function delete_old( $cutoff, $limit = 1000 );
}
