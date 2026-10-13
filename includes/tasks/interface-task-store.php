<?php
namespace Easy_MCP_AI\Tasks;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





interface Task_Store {
    
    public function create( array $row );

    
    public function find( $task_id );

    
    public function update( $task_id, array $fields );

    






    public function update_working( $task_id, array $fields, $lock_token = null );

    





    public function claim( $task_id, $lock_token, $now, $locked_until );

    
    public function release( $task_id, $lock_token );

    
    public function count_working( $auth_source, $wp_user_id, $token_id, $oauth_client_id );

    
    public function count_running( $now );

    
    public function fail_stale( $cutoff, $now, $expires_at, $message );

    
    public function delete_expired( $now, $limit = 1000 );
}
