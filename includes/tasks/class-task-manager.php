<?php
namespace Easy_MCP_AI\Tasks;

use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\MCP\JSON_RPC;
use Easy_MCP_AI\MCP\Error_Codes;
use Easy_MCP_AI\Tools\Tool_Registry;
use Easy_MCP_AI\Tools\Dynamic_Tool_Registrar;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Task_Manager {
    const EXTENSION = 'io.modelcontextprotocol/tasks';
    const TICK_HOOK = Task_Scheduler::HOOK;

    const POLL_INTERVAL_MS      = 5000;
    const WORKING_TTL_SECONDS   = 86400; 
    const STATUS_MIN_INTERVAL   = 3;     
    const STALL_SECONDS         = 30;    
    const LOCK_MARGIN_SECONDS   = 30;
    const MAX_RESULT_BYTES      = 1048576;
    const DEFAULT_BUDGET        = 20;

    private $server;
    private $registry;
    private $store;
    
    private $clock;

    public function __construct( Server $server, Tool_Registry $registry, Task_Store $store, $clock = null ) {
        $this->server   = $server;
        $this->registry = $registry;
        $this->store    = $store;
        $this->clock    = is_callable( $clock ) ? $clock : 'time';
    }

    
    
    

    
    public function contract_for( $tool_name ) {
        $tool = is_string( $tool_name ) && '' !== $tool_name ? $this->registry->get_tool( $tool_name ) : null;
        if ( ! $tool || ! method_exists( $tool, 'get_task_contract' ) ) {
            return null;
        }
        $contract = $tool->get_task_contract();
        return is_array( $contract ) ? $contract : null;
    }

    
    
    

    





    public function call( $id, $params, $token_id, $allowed_tools ) {
        $tool_name = isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : '';
        $contract  = $this->contract_for( $tool_name );
        if ( null === $contract ) {
            return null;
        }
        $arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();
        if ( ! is_array( $arguments ) ) {
            return null; 
        }

        $started  = $this->now();
        
        
        
        
        
        
        
        $response = $this->server->call_tool_internal( $id, $tool_name, $this->with_budget( $arguments, $contract ), $token_id, $allowed_tools, true, $arguments, is_array( $params ) ? $params : array() );
        $approval = $this->server->get_last_approval();
        if ( in_array( $approval['kind'], array( 'paused', 'refused', 'replay' ), true ) ) {
            return $response;
        }
        $outcome = self::read_response( $response );
        if ( $outcome['failed'] || ! is_array( $outcome['data'] ) ) {
            return $response;
        }

        $identity = $this->server->get_request_identity();
        $row      = array(
            'auth_source'     => 'oauth' === $identity['auth_source'] ? 'oauth' : 'legacy',
            'token_id'        => (int) $token_id,
            'oauth_client_id' => $identity['client_id'],
            'wp_user_id'      => (int) $identity['wp_user_id'],
            'tool_name'       => $tool_name,
            'arguments'       => \wp_json_encode( $arguments ),
            'mode'            => $contract['mode'],
            'approval_id'     => $approval['approval_id'],
        );

        
        
        
        if ( $this->store->count_working( $row['auth_source'], $row['wp_user_id'], $row['token_id'], $row['oauth_client_id'] ) >= $this->max_per_credential() ) {
            return $response;
        }

        if ( Task_Contract::MODE_CURSOR === $contract['mode'] ) {
            $chunk = Task_Contract::read_chunk( $outcome['data'] );
            if ( null === $chunk ) {
                return $response;
            }
            if ( ! $chunk['done'] ) {
                return $this->create( $id, $row + array(
                    'phase'            => 'cursor',
                    'task_cursor'      => $chunk['cursor'],
                    'result'           => $this->encode_result( $outcome['data'] ),
                    'progress_current' => $chunk['progress']['current'],
                    'progress_total'   => $chunk['progress']['total'],
                    'status_message'   => $chunk['progress']['message'],
                ), $started );
            }
            if ( null === $contract['status_ability'] || '' === $chunk['job_id'] ) {
                return $response; 
            }
            $job_id = $chunk['job_id'];
        } else {
            $job_id = Task_Contract::read_job_id( $outcome['data'] );
            if ( '' === $job_id ) {
                return $response;
            }
        }

        
        
        
        $status = $this->check_status( $contract, $job_id, $token_id, $allowed_tools );
        if ( null === $status || 'working' !== $status['state'] ) {
            return $response;
        }
        return $this->create( $id, $row + array(
            'phase'            => 'poll',
            'job_id'           => $job_id,
            'result'           => $this->encode_result( $outcome['data'] ),
            'progress_current' => $status['progress']['current'],
            'progress_total'   => $status['progress']['total'],
            'status_message'   => $status['progress']['message'],
        ), $started );
    }

    private function create( $id, array $row, $started ) {
        $now = $this->now();
        $row['task_id']    = self::generate_task_id();
        $row['status']     = 'working';
        $row['ticks']      = 1;
        $row['created_at'] = self::datetime( $started );
        $row['updated_at'] = self::datetime( $now );
        $row['expires_at'] = self::datetime( $started + self::WORKING_TTL_SECONDS );
        $row['signature']  = self::sign( $row );
        if ( ! $this->store->create( $row ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INTERNAL_ERROR, 'Could not create the task' );
        }
        $this->server->audit_task_event( (int) $row['token_id'], '_task_created', array( 'task_id' => $row['task_id'], 'tool' => $row['tool_name'], 'mode' => $row['mode'] ) );
        $this->schedule( $row['task_id'], 'poll' === $row['phase'] ? 60 : 0 );
        $result               = $this->present( $row );
        $result['resultType'] = 'task';
        return JSON_RPC::success_response( $id, $result );
    }

    
    
    

    public function get( $id, $params, $token_id, $allowed_tools ) {
        $row = $this->owned_task( $params, $token_id );
        if ( null === $row ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Failed to retrieve task: Task not found' );
        }
        if ( 'working' === $row['status'] && $this->is_due( $row ) ) {
            $this->tick( $row['task_id'], $token_id, $allowed_tools, true );
            $fresh = $this->store->find( $row['task_id'] );
            $row   = is_array( $fresh ) ? $fresh : $row;
        }
        return JSON_RPC::success_response( $id, $this->present( $row ) );
    }

    
    public function update( $id, $params, $token_id ) {
        $row = $this->owned_task( $params, $token_id );
        if ( null === $row ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Failed to update task: Task not found' );
        }
        if ( ! isset( $params['inputResponses'] ) || ! is_array( $params['inputResponses'] ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'inputResponses must be a JSON object' );
        }
        return JSON_RPC::success_response( $id, array() );
    }

    public function cancel( $id, $params, $token_id, $allowed_tools ) {
        $row = $this->owned_task( $params, $token_id );
        if ( null === $row ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Failed to cancel task: Task not found' );
        }
        if ( 'working' === $row['status'] ) {
            $contract = $this->contract_for( $row['tool_name'] );
            if ( 'poll' === $row['phase'] && is_array( $contract ) && null !== $contract['cancel_ability'] && '' !== (string) $row['job_id'] ) {
                
                
                $cancel_tool = Dynamic_Tool_Registrar::build_tool_name( $contract['cancel_ability'] );
                if ( $this->registry->get_tool( $cancel_tool ) ) {
                    $this->server->call_tool_internal( 0, $cancel_tool, array( 'job_id' => (string) $row['job_id'] ), $token_id, $allowed_tools, false, 'skip' );
                }
            }
            $this->finish( $row, 'cancelled', 'Cancelled by the client.', null, null );
        }
        return JSON_RPC::success_response( $id, array() );
    }

    



    private function owned_task( $params, $token_id ) {
        $task_id = is_array( $params ) && isset( $params['taskId'] ) && is_string( $params['taskId'] ) ? $params['taskId'] : '';
        if ( ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $task_id ) ) {
            return null;
        }
        $row = $this->store->find( $task_id );
        if ( ! is_array( $row ) ) {
            return null;
        }
        if ( strtotime( $row['expires_at'] . ' UTC' ) < $this->now() && 'working' !== $row['status'] ) {
            return null; 
        }
        $identity = $this->server->get_request_identity();
        $source   = 'oauth' === $identity['auth_source'] ? 'oauth' : 'legacy';
        if ( $source !== $row['auth_source'] || (int) $identity['wp_user_id'] !== (int) $row['wp_user_id'] || (int) $row['wp_user_id'] <= 0 ) {
            return null;
        }
        if ( 'oauth' === $source ) {
            
            if ( (string) $identity['client_id'] === '' || (string) $identity['client_id'] !== (string) $row['oauth_client_id'] ) {
                return null;
            }
        } elseif ( (int) $token_id !== (int) $row['token_id'] ) {
            return null;
        }
        return $row;
    }

    
    
    

    private function is_due( array $row ) {
        $idle = $this->now() - strtotime( $row['updated_at'] . ' UTC' );
        return 'poll' === $row['phase'] ? $idle >= self::STATUS_MIN_INTERVAL : $idle >= self::STALL_SECONDS;
    }

    





    public function tick( $task_id, $token_id, $allowed_tools, $single = false ) {
        $now    = $this->now();
        $budget = $this->tick_budget();
        $lock   = bin2hex( random_bytes( 8 ) );
        
        
        
        if ( $this->store->count_running( self::datetime( $now ) ) >= $this->max_concurrent_ticks() ) {
            return;
        }
        
        
        
        
        
        
        if ( Server::is_paused() ) {
            return;
        }
        if ( ! $this->store->claim( $task_id, $lock, self::datetime( $now ), self::datetime( $now + $budget + self::LOCK_MARGIN_SECONDS ) ) ) {
            return;
        }
        try {
            $deadline = $now + $budget;
            do {
                $row = $this->store->find( $task_id );
                if ( ! is_array( $row ) || 'working' !== $row['status'] || $row['lock_token'] !== $lock ) {
                    return; 
                }
                
                
                
                if ( ! self::signature_valid( $row ) ) {
                    $this->finish( $row, 'failed', 'The task failed its integrity check.', null, Error_Codes::INTERNAL_ERROR, array(), $lock );
                    return;
                }
                $contract = $this->contract_for( $row['tool_name'] );
                if ( null === $contract ) {
                    $this->finish( $row, 'failed', 'The tool is no longer available as a task.', null, Error_Codes::INTERNAL_ERROR, array(), $lock );
                    return;
                }
                $continue = 'poll' === $row['phase']
                    ? $this->tick_poll( $row, $contract, $token_id, $allowed_tools, $lock )
                    : $this->tick_cursor( $row, $contract, $token_id, $allowed_tools, max( 1, $deadline - $this->now() ), $lock );
            } while ( $continue && ! $single && $this->now() < $deadline - 1 );
        } finally {
            $this->store->release( $task_id, $lock );
        }
    }

    
    private function tick_cursor( array $row, array $contract, $token_id, $allowed_tools, $remaining, $lock ) {
        $arguments = json_decode( (string) $row['arguments'], true );
        if ( ! is_array( $arguments ) ) {
            $this->finish( $row, 'failed', 'The task lost its arguments.', null, Error_Codes::INTERNAL_ERROR, array(), $lock );
            return false;
        }
        $arguments['cursor'] = (string) $row['task_cursor'];
        
        $response = $this->server->call_tool_internal( 0, $row['tool_name'], $this->with_budget( $arguments, $contract, $remaining ), $token_id, $allowed_tools, false, 'skip' );
        $outcome  = self::read_response( $response );
        if ( $outcome['failed'] ) {
            $this->finish( $row, 'failed', $outcome['message'], null, $outcome['code'], array(), $lock );
            return false;
        }
        $chunk = Task_Contract::read_chunk( $outcome['data'] );
        if ( null === $chunk ) {
            $this->finish( $row, 'failed', 'The tool returned a result that does not follow the task contract.', null, Error_Codes::INTERNAL_ERROR, array(), $lock );
            return false;
        }
        $merged = Task_Contract::merge_chunk( json_decode( (string) $row['result'], true ), $outcome['data'], $contract['results_key'] );
        if ( ! $chunk['done'] && $chunk['cursor'] === (string) $row['task_cursor'] ) {
            
            $this->finish( $row, 'failed', 'The tool made no progress.', $merged, Error_Codes::INTERNAL_ERROR, array(), $lock );
            return false;
        }
        $fields = array(
            'result'           => $this->encode_result( $merged ),
            'progress_current' => $chunk['progress']['current'],
            'progress_total'   => $chunk['progress']['total'],
            'status_message'   => $chunk['progress']['message'],
            'ticks'            => (int) $row['ticks'] + 1,
            'updated_at'       => self::datetime( $this->now() ),
        );
        if ( ! $chunk['done'] ) {
            
            
            return $this->store->update_working( $row['task_id'], $fields + array( 'task_cursor' => $chunk['cursor'] ), $lock );
        }
        if ( null !== $contract['status_ability'] && '' !== $chunk['job_id'] ) {
            $this->store->update_working( $row['task_id'], $fields + array( 'phase' => 'poll', 'job_id' => $chunk['job_id'], 'task_cursor' => null ), $lock );
            return false;
        }
        $this->finish( $row, 'completed', $chunk['progress']['message'], $merged, null, $fields, $lock );
        return false;
    }

    
    private function tick_poll( array $row, array $contract, $token_id, $allowed_tools, $lock ) {
        $status = $this->check_status( $contract, (string) $row['job_id'], $token_id, $allowed_tools, $detail );
        if ( null === $status ) {
            
            
            $this->store->update_working( $row['task_id'], array( 'updated_at' => self::datetime( $this->now() ), 'ticks' => (int) $row['ticks'] + 1 ), $lock );
            return false;
        }
        $fields = array(
            'progress_current' => $status['progress']['current'],
            'progress_total'   => $status['progress']['total'],
            'status_message'   => $status['progress']['message'],
            'ticks'            => (int) $row['ticks'] + 1,
            'updated_at'       => self::datetime( $this->now() ),
        );
        if ( 'working' === $status['state'] ) {
            $this->store->update_working( $row['task_id'], $fields, $lock );
            return false;
        }
        $result = array( 'job_id' => (string) $row['job_id'], 'start' => json_decode( (string) $row['result'], true ), 'status' => $detail );
        $this->finish( $row, $status['state'], $status['progress']['message'], $result, 'failed' === $status['state'] ? Error_Codes::INTERNAL_ERROR : null, $fields, $lock );
        return false;
    }

    private function check_status( array $contract, $job_id, $token_id, $allowed_tools, &$detail = null ) {
        $detail      = null;
        $status_tool = Dynamic_Tool_Registrar::build_tool_name( $contract['status_ability'] );
        if ( ! $this->registry->get_tool( $status_tool ) ) {
            return null;
        }
        $outcome = self::read_response( $this->server->call_tool_internal( 0, $status_tool, array( 'job_id' => $job_id ), $token_id, $allowed_tools, false, 'skip' ) );
        if ( $outcome['failed'] ) {
            return null;
        }
        $detail = $outcome['data'];
        return Task_Contract::read_status( $outcome['data'] );
    }

    





    private function finish( array $row, $status, $message, $result, $error_code, array $fields = array(), $lock = null ) {
        $now    = $this->now();
        $fields = array_merge( $fields, array(
            'status'         => $status,
            'status_message' => substr( (string) $message, 0, 500 ),
            'arguments'      => null, 
            'task_cursor'    => null,
            'updated_at'     => self::datetime( $now ),
            'completed_at'   => self::datetime( $now ),
            'expires_at'     => self::datetime( $now + $this->retention_seconds() ),
        ) );
        if ( null !== $result ) {
            $fields['result'] = $this->encode_result( $result );
        }
        if ( 'failed' === $status ) {
            $fields['error'] = \wp_json_encode( array(
                'code'    => null === $error_code ? Error_Codes::INTERNAL_ERROR : (int) $error_code,
                'message' => '' !== (string) $message ? substr( (string) $message, 0, 500 ) : 'Task failed',
            ) );
        }
        if ( ! $this->store->update_working( $row['task_id'], $fields, $lock ) ) {
            return;
        }
        Task_Scheduler::clear( $row['task_id'] );
        $this->server->audit_task_event( (int) $row['token_id'], '_task_' . $status, array( 'task_id' => $row['task_id'], 'tool' => $row['tool_name'] ) );
    }

    
    
    

    private function schedule( $task_id, $delay ) {
        Task_Scheduler::schedule( $task_id, $this->now() + (int) $delay, 0 === (int) $delay );
    }

    




    public function run_background_tick( $task_id ) {
        $row = is_string( $task_id ) ? $this->store->find( $task_id ) : null;
        if ( ! is_array( $row ) || 'working' !== $row['status'] ) {
            return;
        }
        if ( strtotime( $row['created_at'] . ' UTC' ) + self::WORKING_TTL_SECONDS < $this->now() ) {
            return; 
        }
        $grant = $this->background_grant( $row );
        if ( null === $grant ) {
            return;
        }
        $previous_user = \get_current_user_id();
        \wp_set_current_user( (int) $row['wp_user_id'] );
        $this->server->set_request_identity( $row['auth_source'], (int) $row['wp_user_id'], $row['oauth_client_id'] );
        try {
            $this->tick( $row['task_id'], (int) $row['token_id'], $grant['allowed_tools'] );
        } finally {
            $this->server->clear_request_identity();
            \wp_set_current_user( $previous_user );
        }
        $after = $this->store->find( $row['task_id'] );
        if ( is_array( $after ) && 'working' === $after['status'] ) {
            $age = $this->now() - strtotime( $after['created_at'] . ' UTC' );
            
            
            $progressed = (int) $after['ticks'] > (int) $row['ticks'];
            
            
            
            $this->schedule( $after['task_id'], 'poll' === $after['phase'] ? ( $age < 600 ? 60 : 300 ) : ( $progressed ? 0 : 15 ) );
        }
    }

    
    private function background_grant( array $row ) {
        if ( (int) $row['wp_user_id'] <= 0 || ! \get_userdata( (int) $row['wp_user_id'] ) ) {
            return null;
        }
        if ( 'oauth' === $row['auth_source'] ) {
            $token = $this->oauth_token_row( (int) $row['token_id'] );
            if ( ! is_array( $token ) || ! (int) $token['is_active'] || strtotime( $token['expires_at'] . ' UTC' ) < $this->now()
                || (int) $token['wp_user_id'] !== (int) $row['wp_user_id'] || (string) $token['client_id'] !== (string) $row['oauth_client_id']
                || ! class_exists( '\\Easy_MCP_AI\\OAuth\\Scope_Map' ) ) {
                return null;
            }
            
            
            
            
            
            
            if ( ! $this->oauth_audience_matches( isset( $token['resource'] ) ? (string) $token['resource'] : '' ) ) {
                return null;
            }
            return array( 'allowed_tools' => \Easy_MCP_AI\OAuth\Scope_Map::resolve_allowed_tools( (string) $token['scope'] ) );
        }
        $token = $this->server->get_token_manager()->get_token_by_id( (int) $row['token_id'] );
        if ( ! is_array( $token ) || empty( $token['is_active'] ) || (int) $token['wp_user_id'] !== (int) $row['wp_user_id'] ) {
            return null;
        }
        if ( ! empty( $token['expires_at'] ) && strtotime( $token['expires_at'] . ' UTC' ) < $this->now() ) {
            return null;
        }
        if ( \Easy_MCP_AI\Auth\Token_Manager::is_bound_elsewhere( $token ) ) {
            return null;
        }
        return array( 'allowed_tools' => null ); 
    }

    protected function oauth_audience_matches( $resource ) {
        if ( ! class_exists( '\\Easy_MCP_AI\\OAuth\\Token_Endpoint' ) ) {
            $file = dirname( __DIR__ ) . '/oauth/class-token-endpoint.php';
            if ( ! is_readable( $file ) ) {
                return false;
            }
            require_once $file;
        }
        $canonical = \rest_url( 'easy-mcp-ai/v1/mcp' );
        return \Easy_MCP_AI\OAuth\Token_Endpoint::resource_matches( '' !== $resource ? $resource : $canonical, $canonical );
    }

    protected function oauth_token_row( $token_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_access_tokens';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table; a grant check must be fresh.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT id, wp_user_id, client_id, scope, resource, is_active, expires_at FROM {$table} WHERE id = %d LIMIT 1", (int) $token_id ), ARRAY_A );
        return is_array( $row ) ? $row : null;
    }

    
    
    

    
    public function cleanup() {
        $now = $this->now();
        $this->store->fail_stale(
            self::datetime( $now - self::WORKING_TTL_SECONDS ), self::datetime( $now ),
            self::datetime( $now + $this->retention_seconds() ), 'The task did not finish within 24 hours.'
        );
        return $this->store->delete_expired( self::datetime( $now ) );
    }

    private function retention_seconds() {
        $days = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_tasks_retention_days', 7 );
        return 86400 * ( $days >= 1 && $days <= 365 ? $days : 7 );
    }

    
    public function tick_budget() {
        $configured = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_tasks_tick_budget_seconds', 0 );
        if ( $configured >= 1 && $configured <= 300 ) {
            return $configured;
        }
        $limit = (int) ini_get( 'max_execution_time' );
        if ( $limit <= 0 ) {
            return self::DEFAULT_BUDGET;
        }
        $started = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : (float) $this->now(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- A float cast of a server-set timestamp.
        $left    = (int) floor( $limit * 0.8 - max( 0, microtime( true ) - $started ) );
        return max( 1, min( 60, $left ) );
    }

    private function with_budget( array $arguments, array $contract, $remaining = null ) {
        if ( Task_Contract::MODE_CURSOR !== $contract['mode'] || ! $contract['accepts_time_budget'] || isset( $arguments['time_budget'] ) ) {
            return $arguments;
        }
        $budget = null === $remaining ? min( self::DEFAULT_BUDGET, $this->tick_budget() ) : (int) $remaining;
        $arguments['time_budget'] = max( 1, min( (int) $contract['max_time_budget'], $budget ) );
        return $arguments;
    }

    
    
    

    
    public function present( array $row ) {
        $created = strtotime( $row['created_at'] . ' UTC' );
        $task    = array(
            'taskId'         => $row['task_id'],
            'status'         => $row['status'],
            'createdAt'      => gmdate( 'Y-m-d\TH:i:s\Z', $created ),
            'lastUpdatedAt'  => gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $row['updated_at'] . ' UTC' ) ),
            'ttlMs'          => max( 0, strtotime( $row['expires_at'] . ' UTC' ) - $created ) * 1000,
            'pollIntervalMs' => self::POLL_INTERVAL_MS,
        );
        $message = isset( $row['status_message'] ) ? (string) $row['status_message'] : '';
        $total   = isset( $row['progress_total'] ) ? (int) $row['progress_total'] : 0;
        $current = isset( $row['progress_current'] ) ? (int) $row['progress_current'] : 0;
        if ( 'working' === $row['status'] && $total > 0 ) {
            $message = trim( sprintf( '%d / %d. %s', $current, $total, $message ) );
        }
        if ( '' !== $message ) {
            $task['statusMessage'] = $message;
        }
        if ( 'completed' === $row['status'] || 'cancelled' === $row['status'] ) {
            $data = isset( $row['result'] ) ? json_decode( (string) $row['result'], true ) : null;
            if ( 'completed' === $row['status'] || null !== $data ) {
                $task['result'] = $this->tool_result( $row, $data );
            }
        }
        if ( 'failed' === $row['status'] ) {
            $error         = isset( $row['error'] ) ? json_decode( (string) $row['error'], true ) : null;
            $task['error'] = is_array( $error ) && isset( $error['code'], $error['message'] )
                ? array( 'code' => (int) $error['code'], 'message' => (string) $error['message'] )
                : array( 'code' => Error_Codes::INTERNAL_ERROR, 'message' => 'Task failed' );
        }
        return $task;
    }

    
    private function tool_result( array $row, $data ) {
        $value  = is_array( $data ) && empty( $data ) ? new \stdClass() : $data;
        $result = array(
            'content' => array( array( 'type' => 'text', 'text' => \wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ) ),
            'isError' => false,
        );
        
        $tool = $this->registry->get_tool( $row['tool_name'] );
        if ( 'cursor' === $row['mode'] && 'cursor' === $row['phase'] && is_array( $data ) && $tool && method_exists( $tool, 'get_output_schema' ) && null !== $tool->get_output_schema() ) {
            $result['structuredContent'] = $value;
        }
        return $result;
    }

    
    public static function read_response( $response ) {
        if ( ! is_array( $response ) || isset( $response['error'] ) || ! isset( $response['result'] ) ) {
            $error = is_array( $response ) && isset( $response['error'] ) && is_array( $response['error'] ) ? $response['error'] : array();
            return array(
                'failed'  => true,
                'code'    => isset( $error['code'] ) ? (int) $error['code'] : Error_Codes::INTERNAL_ERROR,
                'message' => isset( $error['message'] ) ? (string) $error['message'] : 'Tool call failed',
                'data'    => null,
            );
        }
        $result = $response['result'];
        $text   = isset( $result['content'][0]['text'] ) && is_string( $result['content'][0]['text'] ) ? $result['content'][0]['text'] : '';
        if ( ! empty( $result['isError'] ) ) {
            return array( 'failed' => true, 'code' => Error_Codes::INTERNAL_ERROR, 'message' => '' !== $text ? $text : 'Tool call failed', 'data' => null );
        }
        if ( isset( $result['structuredContent'] ) ) {
            $data = $result['structuredContent'];
            $data = is_object( $data ) ? json_decode( \wp_json_encode( $data ), true ) : $data;
        } else {
            $data = json_decode( $text, true );
        }
        return array( 'failed' => false, 'code' => 0, 'message' => '', 'data' => is_array( $data ) ? $data : null );
    }

    private function encode_result( $data ) {
        $json = \wp_json_encode( $data );
        if ( is_string( $json ) && strlen( $json ) <= self::MAX_RESULT_BYTES ) {
            return $json;
        }
        
        $summary = array();
        foreach ( is_array( $data ) ? $data : array() as $key => $value ) {
            if ( ! is_array( $value ) ) {
                $summary[ $key ] = $value;
            }
        }
        $summary['results_truncated'] = true;
        return \wp_json_encode( $summary );
    }

    









    public static function sign( array $row ) {
        $fields = array();
        foreach ( array( 'task_id', 'auth_source', 'token_id', 'oauth_client_id', 'wp_user_id', 'tool_name', 'mode', 'created_at' ) as $key ) {
            $fields[] = isset( $row[ $key ] ) ? (string) $row[ $key ] : '';
        }
        $fields[] = hash( 'sha256', isset( $row['arguments'] ) ? (string) $row['arguments'] : '' );
        if ( ! class_exists( '\\Easy_MCP_AI\\Auth\\Token_Keys' ) ) {
            require_once dirname( __DIR__ ) . '/auth/class-token-keys.php';
        }
        return hash_hmac( 'sha256', 'easy-mcp-ai-task|' . implode( '|', $fields ), (string) \Easy_MCP_AI\Auth\Token_Keys::signing_key() );
    }

    private static function signature_valid( array $row ) {
        return isset( $row['signature'] ) && is_string( $row['signature'] ) && '' !== $row['signature']
            && hash_equals( self::sign( $row ), $row['signature'] );
    }

    private function max_per_credential() {
        $max = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_tasks_max_per_credential', 5 );
        return $max >= 1 && $max <= 100 ? $max : 5;
    }

    private function max_concurrent_ticks() {
        $max = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_tasks_max_concurrent_ticks', 3 );
        return $max >= 1 && $max <= 50 ? $max : 3;
    }

    
    public static function generate_task_id() {
        $bytes    = random_bytes( 16 );
        $bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
        $bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
    }

    private function now() {
        return (int) call_user_func( $this->clock );
    }

    private static function datetime( $timestamp ) {
        return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
    }
}
