<?php
namespace Easy_MCP_AI\MCP;

use Easy_MCP_AI\Auth\Token_Manager;
use Easy_MCP_AI\Auth\Permission_Guard;
use Easy_MCP_AI\Tools\Tool_Registry;
use Easy_MCP_AI\Tools\Tool_State;
use Easy_MCP_AI\Resources\Resource_Registry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}







class Server {
    const PROTOCOL_VERSION = '2026-07-28';
    
    const LEGACY_PROTOCOL_VERSION = '2025-11-25';
    const SERVER_NAME      = 'easy-mcp-ai';

    













    public static function effective_required_capability( $category, $tool_default_cap ) {
        if ( in_array( $category, \Easy_MCP_AI\Tools\Base_Tool::EXTERNAL_DATA_CATEGORIES, true ) ) {
            $cap = \Easy_MCP_AI\Config::get( 'easy_mcp_ai_external_data_min_capability', 'manage_options' );
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            if ( ! is_string( $cap ) || ! in_array( $cap, array( 'manage_options', 'edit_others_posts', 'publish_posts' ), true ) ) {
                $cap = 'manage_options';
            }
            return $cap;
        }
        return $tool_default_cap;
    }

    private $tool_registry;
    private $resource_registry;
    private $token_manager;
    private $session_manager;
    private $permission_guard;
    private $audit_log_enabled;
    private $allowed_tool_patterns;
    private $last_negotiated_version;

    
    
    
    private $request_auth_source = null;
    private $request_wp_user_id  = 0;
    private $request_client_id   = null;
    
    private $request_scope       = null;
    
    
    private $scope_challenge     = null;

    
    
    
    private $task_manager        = null;
    private $skip_rate_limit     = false;
    
    
    
    
    private $approval_mode       = null;
    
    private $last_approval       = array( 'kind' => 'run', 'approval_id' => null );
    
    
    private $last_audit_id       = null;

    
    
    
    private $approval_gate       = null;
    private $request_client_caps = null;
    private $request_client_protocol = null;

    
    private $feedback_queue      = null;

    








    private static $active_call = null;

    public function __construct( Tool_Registry $tool_registry, Resource_Registry $resource_registry, Token_Manager $token_manager ) {
        $this->tool_registry     = $tool_registry;
        $this->resource_registry = $resource_registry;
        $this->token_manager     = $token_manager;
        $this->session_manager   = new Session();
        $this->permission_guard  = new Permission_Guard( $token_manager );
        $this->audit_log_enabled    = (bool)  \Easy_MCP_AI\Config::get( 'easy_mcp_ai_audit_log_enabled', true );
        $this->allowed_tool_patterns = (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() );

        register_shutdown_function( array( $this, 'finalise_interrupted_calls' ) );
    }

    







    private $in_flight = array();

    
















    public function finalise_interrupted_calls() {
        
        
        
        
        if ( class_exists( '\\Easy_MCP_AI\\Compat\\Compat_Registry' ) ) {
            \Easy_MCP_AI\Compat\Compat_Registry::release( 0 );
        }
        if ( empty( $this->in_flight ) ) {
            return;
        }
        $unfinished      = $this->in_flight;
        $this->in_flight = array();
        foreach ( $unfinished as $audit_id ) {
            $this->update_audit_status( (int) $audit_id, 'error' );
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) || ! \Easy_MCP_AI\History\Change_Context::is_active() ) {
            return;
        }
        try {
            \do_action( 'easy_mcp_ai_change_context_disarming' );
        } catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            
        } finally {
            \Easy_MCP_AI\History\Change_Context::clear();
        }
    }

    





    public function set_request_identity( $auth_source, $wp_user_id, $client_id = null, $scope = null ) {
        $this->request_auth_source = $auth_source;
        $this->request_wp_user_id  = (int) $wp_user_id;
        $this->request_client_id   = $client_id;
        $this->request_scope       = is_string( $scope ) ? $scope : null;
    }

    public function clear_request_identity() {
        $this->request_auth_source = null;
        $this->request_wp_user_id  = 0;
        $this->request_client_id   = null;
        $this->request_scope       = null;
    }

    






    public function take_scope_challenge() {
        $challenge             = $this->scope_challenge;
        $this->scope_challenge = null;
        return $challenge;
    }

    












    private function privileged_scope_challenge( $tool_name ) {
        if ( 'oauth' !== $this->request_auth_source || ! class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' ) ) {
            return null;
        }
        $group = \Easy_MCP_AI\Privileged\Privileged_Groups::group_of_tool( $tool_name );
        $tool  = null === $group ? null : $this->tool_registry->get_tool( $tool_name );
        if ( ! $tool || ! Tool_State::is_enabled( $tool ) || ! \current_user_can( $tool->get_required_capability() ) ) {
            return null;
        }
        $scopes   = preg_split( '/\s+/', trim( (string) $this->request_scope ), -1, PREG_SPLIT_NO_EMPTY );
        $scopes[] = \Easy_MCP_AI\Privileged\Privileged_Groups::scope_for_group( $group );
        return implode( ' ', array_values( array_unique( $scopes ) ) );
    }

    
    public function get_request_identity() {
        return array(
            'auth_source' => $this->request_auth_source,
            'wp_user_id'  => (int) $this->request_wp_user_id,
            'client_id'   => $this->request_client_id,
        );
    }

    public function get_token_manager() {
        return $this->token_manager;
    }

    public function get_task_manager() {
        if ( null === $this->task_manager ) {
            $this->task_manager = new \Easy_MCP_AI\Tasks\Task_Manager( $this, $this->tool_registry, new \Easy_MCP_AI\Tasks\Wpdb_Task_Store() );
        }
        return $this->task_manager;
    }

    public function set_task_manager( $task_manager ) {
        $this->task_manager = $task_manager;
    }

    





    
    public function get_approval_gate() {
        if ( null === $this->approval_gate ) {
            $manager = $this->token_manager;
            $this->approval_gate = new \Easy_MCP_AI\Approvals\Approval_Gate(
                new \Easy_MCP_AI\Approvals\Wpdb_Approval_Store(),
                static function ( $token_id ) use ( $manager ) {
                    $row = $manager->get_token_by_id( (int) $token_id );
                    return is_array( $row ) && ! empty( $row['unattended'] );
                }
            );
        }
        return $this->approval_gate;
    }

    public function set_approval_gate( $gate ) {
        $this->approval_gate = $gate;
    }

    
    public function set_feedback_queue( ?\Easy_MCP_AI\Feedback\Feedback_Queue $queue ): void {
        $this->feedback_queue = $queue;
    }

    



    public function set_request_client_caps( $caps, $protocol = null ) {
        $this->request_client_caps     = is_array( $caps ) ? $caps : ( is_object( $caps ) ? (array) $caps : null );
        $this->request_client_protocol = is_string( $protocol ) && '' !== $protocol ? $protocol : null;
    }

    
    private function client_declared_apps() {
        $caps = $this->request_client_caps;
        if ( ! is_array( $caps ) || ! isset( $caps['extensions'] ) ) {
            return false;
        }
        $extensions = is_object( $caps['extensions'] ) ? (array) $caps['extensions'] : $caps['extensions'];
        return is_array( $extensions ) && array_key_exists( 'io.modelcontextprotocol/ui', $extensions );
    }

    
    private static function is_app_only_definition( array $definition ) {
        if ( ! isset( $definition['_meta']['ui']['visibility'] ) || ! is_array( $definition['_meta']['ui']['visibility'] ) ) {
            return false;
        }
        return ! in_array( 'model', $definition['_meta']['ui']['visibility'], true );
    }

    






    public static function is_app_only_tool( object $tool ): bool {
        if ( ! method_exists( $tool, 'get_ui_meta' ) ) {
            return false;
        }
        try {
            $ui = $tool->get_ui_meta();
        } catch ( \Throwable $e ) {
            return false;
        }
        return is_array( $ui ) && self::is_app_only_definition( array( '_meta' => array( 'ui' => $ui ) ) );
    }

    
    private function request_identity( $token_id ) {
        return array(
            'auth_source'     => 'oauth' === $this->request_auth_source ? 'oauth' : 'legacy',
            'token_id'        => (int) $token_id,
            'oauth_client_id' => is_string( $this->request_client_id ) ? $this->request_client_id : null,
            'wp_user_id'      => (int) $this->request_wp_user_id,
        );
    }

    











    public function call_tool_internal( $id, $tool_name, array $arguments, $token_id, $allowed_tools, $count_rate_limit, $approval = null, array $answer = array() ) {
        $previous              = $this->skip_rate_limit;
        $previous_mode         = $this->approval_mode;
        $this->skip_rate_limit = ! $count_rate_limit;
        $this->approval_mode   = $approval;
        $params                = array( 'name' => $tool_name, 'arguments' => $arguments );
        foreach ( array( 'requestState', 'inputResponses' ) as $key ) {
            if ( array_key_exists( $key, $answer ) ) {
                $params[ $key ] = $answer[ $key ];
            }
        }
        try {
            return $this->handle_tools_call( $id, $params, $token_id, $allowed_tools );
        } finally {
            $this->skip_rate_limit = $previous;
            $this->approval_mode   = $previous_mode;
        }
    }

    
    public function get_last_approval() {
        return $this->last_approval;
    }

    
























    public static function call_as_current_caller( string $tool_name, array $arguments, $approval = null, bool $count_rate_limit = true ): ?array {
        if ( null === self::$active_call ) {
            return null;
        }
        $server                = self::$active_call['server'];
        $server->last_audit_id = null;
        $response              = $server->call_tool_internal(
            'composite-' . $tool_name,
            $tool_name,
            $arguments,
            self::$active_call['token_id'],
            self::$active_call['allowed_tools'],
            $count_rate_limit,
            'skip' === $approval ? 'skip' : null
        );
        return array(
            'response' => $response,
            'approval' => $server->get_last_approval(),
            'audit_id' => $server->last_audit_id,
        );
    }

    



    public static function current_call_approval_kind(): ?string {
        if ( null === self::$active_call ) {
            return null;
        }
        return isset( self::$active_call['approval_kind'] ) ? (string) self::$active_call['approval_kind'] : null;
    }

    
    public function audit_task_event( $token_id, $event, array $details ) {
        $audit_id = $this->log_tool_call( $token_id, $event, $details, 'success' );
        return (int) $audit_id;
    }

    public function handle_message( $message, $token_id = null, $allowed_tools = null ) {
        $method = isset( $message['method'] ) ? $message['method'] : '';
        $params = isset( $message['params'] ) ? $message['params'] : array();
        $id     = isset( $message['id'] ) ? $message['id'] : null;

        if ( JSON_RPC::is_notification( $message ) ) {
            return null;
        }

        switch ( $method ) {
            case 'initialize':
                return $this->handle_initialize( $id, $params, $token_id, $allowed_tools );
            case 'ping':
                return JSON_RPC::success_response( $id, new \stdClass() );
            case 'tools/list':
                return $this->handle_tools_list( $id, $params, $token_id, $allowed_tools );
            case 'tools/call':
                return $this->handle_tools_call( $id, $params, $token_id, $allowed_tools );
            case 'resources/list':
                return $this->handle_resources_list( $id, $params, $token_id, $allowed_tools );
            case 'resources/read':
                return $this->handle_resources_read( $id, $params, $token_id, $allowed_tools );
            
            
            
            case 'prompts/list':
                return $this->handle_prompts_list( $id, $token_id );
            case 'prompts/get':
                return $this->handle_prompts_get( $id, $params, $token_id );
            default:
                return JSON_RPC::error_response( $id, Error_Codes::METHOD_NOT_FOUND, 'Method not found' );
        }
    }

    
    const LEGACY_PROTOCOL_VERSIONS = array( '2025-11-25', '2025-06-18', '2025-03-26' );
    const SUPPORTED_PROTOCOL_VERSIONS = array( '2026-07-28', '2025-11-25', '2025-06-18', '2025-03-26' );

    




    public function handle_modern_message( $message, $token_id = null, $allowed_tools = null ) {
        $id = isset( $message['id'] ) ? $message['id'] : null;
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        
        
        
        $caps = isset( $message['params']['_meta']['io.modelcontextprotocol/clientCapabilities'] ) ? $message['params']['_meta']['io.modelcontextprotocol/clientCapabilities'] : null;
        $this->set_request_client_caps( is_object( $caps ) ? (array) $caps : ( is_array( $caps ) ? $caps : array() ), self::PROTOCOL_VERSION );
        try {
            return $this->dispatch_modern_message( $message, $id, $token_id, $allowed_tools );
        } finally {
            $this->request_client_caps     = null;
            $this->request_client_protocol = null;
        }
    }

    private function dispatch_modern_message( $message, $id, $token_id, $allowed_tools ) {
        $method = $message['method'];
        if ( 'server/discover' === $method ) {
            if ( ! $this->check_rate_limit( $token_id ) ) {
                return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
            }
            self::arm_skill_sync();
            $response = JSON_RPC::success_response( $id, array(
                'supportedVersions' => self::SUPPORTED_PROTOCOL_VERSIONS,
                'capabilities' => array(
                    'tools'      => new \stdClass(),
                    'resources'  => new \stdClass(),
                    'prompts'    => new \stdClass(),
                    'extensions' => array(
                        'io.modelcontextprotocol/tasks'  => new \stdClass(),
                        
                        
                        'io.modelcontextprotocol/skills' => new \stdClass(),
                    ),
                ),
                'instructions' => $this->instructions( $token_id, $allowed_tools ),
            ) );
        } elseif ( in_array( $method, array( 'tasks/get', 'tasks/update', 'tasks/cancel' ), true ) ) {
            $response = $this->handle_task_method( $id, $method, isset( $message['params'] ) ? $message['params'] : array(), $token_id, $allowed_tools );
        } elseif ( 'tools/call' === $method ) {
            $response = $this->handle_modern_tools_call( $message, $token_id, $allowed_tools );
        } elseif ( in_array( $method, array( 'tools/list', 'resources/list', 'resources/read', 'prompts/list', 'prompts/get' ), true ) ) {
            $response = $this->handle_message( $message, $token_id, $allowed_tools );
        } elseif ( 'skills/list' === $method ) {
            $response = $this->handle_skills_list( $id, $token_id );
        } elseif ( 'skills/get' === $method ) {
            $response = $this->handle_skills_get( $id, isset( $message['params'] ) ? $message['params'] : array(), $token_id );
        } elseif ( 'subscriptions/listen' === $method && self::listens_for_tasks( $message ) && ! self::client_declared_tasks( $message['params'] ) ) {
            
            
            
            
            
            return JSON_RPC::error_response( $id, Error_Codes::MISSING_CLIENT_CAPABILITY, 'Missing required client capability', array(
                'requiredCapabilities' => array( 'extensions' => array( 'io.modelcontextprotocol/tasks' => new \stdClass() ) ),
            ) );
        } elseif ( 'subscriptions/listen' === $method ) {
            $response = $this->handle_subscriptions_listen( $id, isset( $message['params'] ) ? $message['params'] : array(), $token_id );
        } else {
            return JSON_RPC::error_response( $id, Error_Codes::METHOD_NOT_FOUND, 'Method not found' );
        }
        if ( isset( $response['error'] ) ) {
            if ( 'resources/read' === $method && Error_Codes::RESOURCE_NOT_FOUND === $response['error']['code'] ) {
                $response['error']['code'] = Error_Codes::INVALID_PARAMS;
            }
            return $response;
        }
        if ( ! isset( $response['result']['resultType'] ) ) {
            $response['result']['resultType'] = 'complete';
        }
        $response['result']['_meta']['io.modelcontextprotocol/serverInfo'] = array(
            'name' => self::SERVER_NAME, 'version' => EASY_MCP_AI_VERSION,
        );
        if ( in_array( $method, array( 'server/discover', 'tools/list', 'resources/list', 'resources/read', 'prompts/list', 'prompts/get', 'skills/list', 'skills/get' ), true ) ) {
            
            $response['result']['ttlMs'] = 0;
            $response['result']['cacheScope'] = 'private';
        }
        if ( 'tools/list' === $method ) {
            usort( $response['result']['tools'], function ( $a, $b ) {
                return strcmp( $a['name'], $b['name'] );
            } );
        }
        return $response;
    }

    




    private function handle_modern_tools_call( $message, $token_id, $allowed_tools ) {
        $params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : array();
        if ( self::client_declared_tasks( $params ) ) {
            $response = $this->get_task_manager()->call( isset( $message['id'] ) ? $message['id'] : null, $params, $token_id, $allowed_tools );
            if ( null !== $response ) {
                return $response;
            }
        }
        return $this->handle_message( $message, $token_id, $allowed_tools );
    }

    




    public static function client_declared_tasks( $params ) {
        $key = 'io.modelcontextprotocol/clientCapabilities';
        if ( ! is_array( $params ) || ! isset( $params['_meta'][ $key ]['extensions'] ) || ! is_array( $params['_meta'][ $key ]['extensions'] ) ) {
            return false;
        }
        $extensions = $params['_meta'][ $key ]['extensions'];
        return array_key_exists( 'io.modelcontextprotocol/tasks', $extensions ) && is_array( $extensions['io.modelcontextprotocol/tasks'] );
    }

    






















    private function handle_subscriptions_listen( $id, $params, $token_id ) {
        $params = is_array( $params ) ? $params : array();
        if ( array_key_exists( 'notifications', $params ) && ! is_array( $params['notifications'] ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'notifications must be a JSON object' );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }
        return JSON_RPC::success_response( $id, array(
            '_meta' => array( self::SUBSCRIPTION_ID_META => $id ),
        ) );
    }

    
    const SUBSCRIPTION_ID_META = 'io.modelcontextprotocol/subscriptionId';

    




    public static function subscriptions_acknowledged( $id ) {
        return array(
            'jsonrpc' => '2.0',
            'method'  => 'notifications/subscriptions/acknowledged',
            'params'  => array(
                '_meta'         => array( self::SUBSCRIPTION_ID_META => $id ),
                'notifications' => new \stdClass(),
            ),
        );
    }

    
    private static function listens_for_tasks( $message ) {
        return isset( $message['params']['notifications'] ) && is_array( $message['params']['notifications'] )
            && array_key_exists( 'taskIds', $message['params']['notifications'] );
    }

    private function handle_task_method( $id, $method, $params, $token_id, $allowed_tools ) {
        $params = is_array( $params ) ? $params : array();
        if ( ! self::client_declared_tasks( $params ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::MISSING_CLIENT_CAPABILITY, 'Missing required client capability', array(
                'requiredCapabilities' => array( 'extensions' => array( 'io.modelcontextprotocol/tasks' => new \stdClass() ) ),
            ) );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }
        $manager = $this->get_task_manager();
        if ( 'tasks/get' === $method ) {
            return $manager->get( $id, $params, $token_id, $allowed_tools );
        }
        if ( 'tasks/cancel' === $method ) {
            return $manager->cancel( $id, $params, $token_id, $allowed_tools );
        }
        return $manager->update( $id, $params, $token_id );
    }

    






    private function handle_initialize( $id, $params, $token_id, ?array $allowed_tools = null ) {
        
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }

        
        
        
        
        $client_version = isset( $params['protocolVersion'] ) ? $params['protocolVersion'] : null;
        if ( $client_version && in_array( $client_version, self::LEGACY_PROTOCOL_VERSIONS, true ) ) {
            $negotiated_version = $client_version;
        } else {
            
            $negotiated_version = self::LEGACY_PROTOCOL_VERSION;
        }

        
        $this->last_negotiated_version = $negotiated_version;

        self::arm_skill_sync();
        return JSON_RPC::success_response( $id, array(
            'protocolVersion' => $negotiated_version,
            'capabilities'    => array(
                'tools'     => new \stdClass(),
                'resources' => new \stdClass(),
                'prompts'   => new \stdClass(),
            ),
            'serverInfo'      => array( 'name' => self::SERVER_NAME, 'version' => EASY_MCP_AI_VERSION ),
            'instructions'    => $this->instructions( $token_id, $allowed_tools ),
        ) );
    }

    
    const INSTRUCTIONS = 'WordPress MCP Server. Use tools to manage posts, pages, media, comments, users, and site settings. Use resources to read site information.';

    




    const FEEDBACK_INSTRUCTIONS = 'When a tool fails or does not do what it says on a valid request (not a wrong ID, a missing item or a permission refusal), or the user needs something no tool here does, call wp_send_feedback (kind "bug" or "missing_feature", a summary under 200 characters) before you answer: it only queues a report for a person to approve, so do not ask the user first.';

    













    const DISABLED_TOOLS_INSTRUCTIONS = 'Switched-off tools, plugin integrations and abilities are not in your tool list: when no tool you have does a task, call wp_list_disabled_tools with a few words of it before saying it cannot be done.';

    
















    private function instructions( $token_id, ?array $allowed_tools = null ): string {
        $disabled = $this->sentence_while_available( $token_id, $allowed_tools, 'wp_list_disabled_tools', self::DISABLED_TOOLS_INSTRUCTIONS );
        $cli      = $this->cli_instructions( $token_id, $allowed_tools );
        $parts    = array(
            'disabled' => $disabled,
            'cli'      => '' === $disabled ? $cli : '',
            'feedback' => $this->sentence_while_available( $token_id, $allowed_tools, 'wp_send_feedback', self::FEEDBACK_INSTRUCTIONS ),
            'waiting'  => $this->feedback_line( $token_id ),
        );
        $text  = $this->instructions_with( $token_id, $allowed_tools, $parts );
        
        
        
        
        
        
        if ( '' !== $parts['disabled'] && '' === $cli && strlen( $text ) > self::instructions_limit() ) {
            $parts['disabled'] = '';
            $text              = $this->instructions_with( $token_id, $allowed_tools, $parts );
        }
        return $text;
    }

    







    private function instructions_with( $token_id, ?array $allowed_tools, array $parts ): string {
        $tail = implode( ' ', array_filter( $parts, 'strlen' ) );
        $base = $this->skill_instructions( $token_id, $allowed_tools, '' === $tail ? 0 : strlen( $tail ) + 1 );
        return '' === $tail ? $base : $base . ' ' . $tail;
    }

    
    private static function instructions_limit(): int {
        return \Easy_MCP_AI\Skills\Skill_Instructions::MAX_LENGTH;
    }

    









    private function sentence_while_available( $token_id, ?array $allowed_tools, string $tool_name, string $sentence ): string {
        if ( null === $token_id ) {
            return '';
        }
        try {
            return 'available' === $this->tool_status_for( $token_id, $allowed_tools, $tool_name )['status'] ? $sentence : '';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    




    private function feedback_line( $token_id ): string {
        if ( null === $token_id || \Easy_MCP_AI\Config::is_white_labelled() || ! Tool_State::resolve( 'wp_send_feedback', false )[0] ) {
            return '';
        }
        try {
            if ( null === $this->feedback_queue ) {
                $this->feedback_queue = \Easy_MCP_AI\Feedback\Feedback_Queue::create();
            }
            return (string) $this->feedback_queue->instructions_line( $this->request_identity( $token_id ) );
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    









    private function skill_instructions( $token_id, ?array $allowed_tools, int $reserve ): string {
        try {
            $served = \Easy_MCP_AI\Skills\Skills::served();
            if ( $served->is_empty() ) {
                return self::INSTRUCTIONS;
            }
            $can_use = null !== $token_id && $this->token_may_use( $token_id, is_array( $allowed_tools ) ? $allowed_tools : null, 'wp_get_skill' );
            return \Easy_MCP_AI\Skills\Skill_Instructions::build( $served, $can_use, self::INSTRUCTIONS, $reserve );
        } catch ( \Throwable $e ) {
            return self::INSTRUCTIONS;
        }
    }

    




    private static function arm_skill_sync(): void {
        try {
            \Easy_MCP_AI\Skills\Skill_Sync::arm();
        } catch ( \Throwable $e ) {
            return;
        }
    }

    









    private function skills_gate( $id, $token_id, bool $is_list ): ?array {
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        if ( self::is_paused() && ! $is_list ) {
            return JSON_RPC::error_response( $id, Error_Codes::PAUSED, Error_Codes::PAUSED_MESSAGE );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }
        if ( ! \current_user_can( 'read' ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Insufficient permissions to read skills' );
        }
        return null;
    }

    
    private static function served_skills(): ?\Easy_MCP_AI\Skills\Skill_Set {
        if ( self::is_paused() ) {
            return null;
        }
        return \Easy_MCP_AI\Skills\Skills::served();
    }

    
    private function handle_skills_list( $id, $token_id ): array {
        $refusal = $this->skills_gate( $id, $token_id, true );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $entries = array();
        $served  = self::served_skills();
        foreach ( null === $served ? array() : $served->skills() as $skill ) {
            $entries[] = \Easy_MCP_AI\Skills\Skill_Set::entry( $skill );
        }
        return JSON_RPC::success_response( $id, array( 'skills' => $entries ) );
    }

    
    private function handle_skills_get( $id, $params, $token_id ): array {
        $refusal = $this->skills_gate( $id, $token_id, false );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $uri = is_array( $params ) && isset( $params['uri'] ) && is_string( $params['uri'] ) ? $params['uri'] : '';
        foreach ( self::served_skills()->skills() as $skill ) {
            if ( \Easy_MCP_AI\Skills\Skill_Set::uri( $skill ) === $uri ) {
                return JSON_RPC::success_response( $id, array( 'skill' => \Easy_MCP_AI\Skills\Skill_Set::entry( $skill ) ) );
            }
        }
        return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, '' === $uri ? 'Missing skill URI' : 'No skill is served at ' . $uri );
    }

    




    private function handle_prompts_list( $id, $token_id ): array {
        $refusal = $this->skills_gate( $id, $token_id, true );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $prompts = array();
        $served  = self::served_skills();
        foreach ( null === $served ? array() : $served->skills() as $skill ) {
            $prompts[] = array(
                'name'        => $skill['name'],
                'title'       => ucfirst( str_replace( '-', ' ', $skill['name'] ) ),
                'description' => \Easy_MCP_AI\Skills\Skill_Set::description( $skill ),
                'arguments'   => array(),
            );
        }
        return JSON_RPC::success_response( $id, array( 'prompts' => $prompts ) );
    }

    
    private function handle_prompts_get( $id, $params, $token_id ): array {
        $refusal = $this->skills_gate( $id, $token_id, false );
        if ( null !== $refusal ) {
            return $refusal;
        }
        $name  = is_array( $params ) && isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : '';
        $skill = self::served_skills()->find( $name );
        if ( null === $skill ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Unknown prompt' );
        }
        return JSON_RPC::success_response( $id, array(
            'description' => \Easy_MCP_AI\Skills\Skill_Set::description( $skill ),
            'messages'    => array(
                array(
                    'role'    => 'user',
                    'content' => array(
                        'type' => 'text',
                        'text' => 'Use the "' . $skill['name'] . '" skill below on this site. Follow its steps in order. For a step whose tool is not available to this connection, tell me what would need to change instead of skipping it silently.',
                    ),
                ),
                array(
                    'role'    => 'user',
                    'content' => array(
                        'type'     => 'resource',
                        'resource' => array(
                            'uri'      => \Easy_MCP_AI\Skills\Skill_Set::uri( $skill ),
                            'mimeType' => 'text/markdown',
                            'text'     => $skill['files'][0]['text'],
                        ),
                    ),
                ),
            ),
        ) );
    }

    
    const CLI_LIFECYCLE_TOOLS = array( 'wp_install_plugin', 'wp_delete_plugin', 'wp_uninstall_plugin', 'wp_install_theme', 'wp_delete_theme', 'wp_update_core' );

    



















    private function cli_instructions( $token_id, ?array $allowed_tools ): string {
        if ( null === $token_id ) {
            return '';
        }
        try {
            $cli = $this->tool_status_for( $token_id, $allowed_tools, 'wp_cli_run' );
            if ( ! in_array( $cli['status'], array( 'off', 'off_locked' ), true ) ) {
                return '';
            }
            $off    = 'off' === $cli['status'] ? array( 'wp_cli_run' ) : array();
            $locked = 'off_locked' === $cli['status'] ? array( 'wp_cli_run' ) : array();
            $needed = array();
            foreach ( self::CLI_LIFECYCLE_TOOLS as $name ) {
                $status = $this->tool_status_for( $token_id, $allowed_tools, $name )['status'];
                if ( 'off' === $status ) {
                    $off[] = $name;
                } elseif ( 'off_locked' === $status ) {
                    $locked[] = $name;
                } else {
                    continue;
                }
                $needed[] = $name;
            }
            $cli_tool = $this->tool_registry->get_tool( 'wp_cli_run' );
            $text     = sprintf( 'WP-CLI (wp_cli_run) is switched off; it covers %s.', $cli_tool instanceof \Easy_MCP_AI\Tools\Base_Tool ? $cli_tool->get_covers() : '' );
            $text .= array() === $needed ? '' : sprintf( ' The tools that install or delete plugins and themes and update core are off too (%s).', implode( ', ', $needed ) );
            return $text . ' Before saying a task cannot be done, tell the user which tool to switch on, by its name, and that ' . Tool_Status::who_switches_on( $off, $locked, Tool_Status::switch_path( 'wp_cli_run', true ) ) . '.';
        } catch ( \Throwable $e ) {
            return '';
        }
    }

    




    public function get_last_negotiated_version() {
        return isset( $this->last_negotiated_version ) ? $this->last_negotiated_version : null;
    }

    
    public static function is_paused() {
        return true === \Easy_MCP_AI\Config::get( 'paused' );
    }

    
    public static function available_tools( Tool_Registry $registry, $require_instance = false, $definitions = null ) {
        if ( ! \Easy_MCP_AI\Config::tool_policy_valid() ) {
            return array();
        }
        $patterns = (array) \Easy_MCP_AI\Config::get( 'allowed_tool_patterns' );
        return array_values( array_filter( null === $definitions ? $registry->get_all_definitions() : $definitions, static function ( $definition ) use ( $registry, $patterns, $require_instance ) {
            $name = $definition['name'];
            $tool = $registry->get_tool( $name );
            
            
            if ( ! ( $tool ? Tool_State::is_enabled( $tool ) : Tool_State::resolve( $name, false )[0] ) ) {
                return false;
            }
            if ( ! self::matches_tool_patterns( $name, $patterns ) ) {
                return false;
            }
            if ( ! $tool ) { return ! $require_instance; }
            $cap = self::effective_required_capability( $tool->get_category(), $tool->get_required_capability() );
            return ! $cap || \current_user_can( $cap );
        } ) );
    }

    private function handle_tools_list( $id, $params, $token_id, $allowed_tools = null ) {
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        if ( ! \Easy_MCP_AI\Config::tool_policy_valid() || self::is_paused() ) {
            return JSON_RPC::success_response( $id, array( 'tools' => array() ) );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }

        $all_tools = $this->tool_registry->get_all_definitions();
        
        $allowed = null !== $allowed_tools ? $allowed_tools : $this->permission_guard->get_allowed_tools( $token_id );
        
        
        
        $all_tools = array_values( array_filter( $all_tools, static function ( $tool ) use ( $allowed ) {
            return Permission_Guard::allowlist_permits( (array) $allowed, $tool['name'] );
        } ) );
        $all_tools = self::available_tools( $this->tool_registry, false, $all_tools );
        
        
        if ( ! $this->client_declared_apps() ) {
            $all_tools = array_values( array_filter( $all_tools, static function ( $tool ) {
                return ! self::is_app_only_definition( $tool );
            } ) );
        }
        $all_tools = array_map( function ( $tool ) {
            $tool['inputSchema'] = Gemini_Safe_Schema::sanitize( $tool['inputSchema'] )['schema'];
            if ( isset( $tool['outputSchema'] ) ) {
                $tool['outputSchema'] = Gemini_Safe_Schema::sanitize( $tool['outputSchema'] )['schema'];
            }
            return $tool;
        }, $all_tools );

        return JSON_RPC::success_response( $id, array( 'tools' => $all_tools ) );
    }

    









    private function handle_tools_call( $id, $params, $token_id, ?array $allowed_tools = null ): array {
        $tool_name = isset( $params['name'] ) ? $params['name'] : '';
        $arguments = isset( $params['arguments'] ) ? $params['arguments'] : array();

        if ( empty( $tool_name ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Missing tool name' );
        }

        
        
        
        if ( ! is_array( $arguments ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Tool arguments must be a JSON object' );
        }

        
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        $this->stamp_last_call( $token_id );
        
        
        
        if ( self::is_paused() ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::PAUSED, Error_Codes::PAUSED_MESSAGE );
        }
        if ( ! \Easy_MCP_AI\Config::tool_policy_valid() ) {
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Invalid deployment tool policy' );
        }
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        if ( null !== $allowed_tools ) {
            if ( ! $this->permission_guard->can_use_tool_with_scope( $allowed_tools, $tool_name ) ) {
                $this->log_refusal( $token_id, $tool_name, $arguments );
                $this->scope_challenge = $this->privileged_scope_challenge( $tool_name );
                return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Token does not have permission to use this tool' );
            }
        } elseif ( ! $this->permission_guard->can_use_tool( $token_id, $tool_name ) ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Token does not have permission to use this tool' );
        }

        
        if ( ! $this->skip_rate_limit && ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }

        $tool = $this->tool_registry->get_tool( $tool_name );
        if ( null === $tool ) {
            
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Unknown tool' );
        }

        $required_cap = self::effective_required_capability( $tool->get_category(), $tool->get_required_capability() );
        if ( $required_cap && ! \current_user_can( $required_cap ) ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            $switched_off = self::privileged_switch_off( $tool, $tool_name );
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, null !== $switched_off ? 'This tool ' . Tool_Status::predicate( $switched_off ) : 'Insufficient WordPress permissions for this tool' );
        }

        
        
        
        
        if ( ! Tool_State::is_enabled( $tool ) ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'This tool ' . Tool_Status::predicate( Tool_Status::off( $tool_name, (bool) $tool->is_disabled_by_default() ) ) );
        }

        if ( ! $this->tool_matches_pattern_filter( $tool_name ) ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'This tool ' . Tool_Status::predicate( array( 'status' => 'pattern' ) ) );
        }

        
        
        
        if ( self::is_app_only_tool( $tool ) && ! $this->client_declared_apps() ) {
            $this->log_refusal( $token_id, $tool_name, $arguments );
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'This tool is only callable from an approval card.' );
        }

        
        
        
        
        $privileged_group = self::privileged_group( $tool_name );
        if ( null !== $privileged_group ) {
            $refusal = $this->privileged_refusal( $privileged_group );
            if ( null !== $refusal ) {
                $this->log_refusal( $token_id, $tool_name, $arguments );
                return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, $refusal );
            }
            if ( ! $this->check_privileged_rate_limit( $token_id ) ) {
                return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Privileged tool rate limit exceeded. Please try again in a minute.' );
            }
        }

        
        
        
        
        
        
        if ( 'skip' !== $this->approval_mode && method_exists( $tool, 'precheck' ) ) {
            if ( \Easy_MCP_AI\Approvals\Approval_Gate::requires_approval( $tool, is_array( $this->approval_mode ) ? $this->approval_mode : $arguments ) ) {
                
                
                $caller_before     = self::$active_call;
                self::$active_call = array(
                    'server'        => $this,
                    'token_id'      => $token_id,
                    'allowed_tools' => $allowed_tools,
                    'approval_kind' => null,
                );
                try {
                    $refused = self::precheck_refusal( $tool, $arguments );
                } finally {
                    self::$active_call = $caller_before;
                }
                if ( null !== $refused ) {
                    $this->log_tool_call( $token_id, $tool_name, $arguments, 'error' );
                    return JSON_RPC::success_response( $id, $refused );
                }
            }
        }

        
        
        
        
        
        
        
        $approval_row        = null;
        $approval_outcome    = null;
        $this->last_approval = array( 'kind' => 'run', 'approval_id' => null );
        if ( 'skip' === $this->approval_mode ) {
            
            
            $approval = array( 'kind' => 'run' );
        } else {
            $subject  = is_array( $this->approval_mode ) ? $this->approval_mode : $arguments;
            $approval = $this->evaluate_approval( $tool, $subject, $token_id, $params );
        }
        if ( 'redeem' === $approval['kind'] ) {
            if ( $this->get_approval_gate()->claim( $approval['row'] ) ) {
                $approval_row        = $approval['row'];
                $this->last_approval = array( 'kind' => 'redeem', 'approval_id' => $approval_row['approval_id'] );
            } else {
                $approval = array( 'kind' => 'refused', 'reason' => 'consumed', 'message' => 'this approval was already used or has lapsed; call the tool again to request a fresh one.' );
            }
        }
        if ( 'refused' === $approval['kind'] ) {
            $this->last_approval = array( 'kind' => 'refused', 'approval_id' => null );
            $this->log_tool_call( $token_id, $tool_name, $arguments, 'approval_refused' );
            return JSON_RPC::success_response( $id, \Easy_MCP_AI\Approvals\Approval_Gate::refusal_result( $approval['reason'], $approval['message'] ) );
        }
        if ( 'replay' === $approval['kind'] ) {
            $this->last_approval = array( 'kind' => 'replay', 'approval_id' => $approval['row']['approval_id'] );
            $this->log_tool_call( $token_id, $tool_name, $arguments, 'approval_replayed' );
            return JSON_RPC::success_response( $id, $approval['result'] );
        }
        if ( 'ask_form' === $approval['kind'] || 'ask' === $approval['kind'] ) {
            $this->last_approval = array( 'kind' => 'paused', 'approval_id' => $approval['row']['approval_id'] );
            $paused_audit_id = $this->log_tool_call( $token_id, $tool_name, $arguments, 'approval_required' );
            $this->note_audit_approval( $paused_audit_id, $approval['row'] );
            $this->get_approval_gate()->note_audit_id( $approval['row'], $paused_audit_id );
            if ( 'ask_form' === $approval['kind'] ) {
                return $this->get_approval_gate()->build_input_required( $id, $approval['row'] );
            }
            return JSON_RPC::success_response( $id, $this->get_approval_gate()->build_approval_result( $approval['row'], $this->client_declared_apps() ) );
        }

        
        
        
        
        
        
        $audit_id            = $this->log_tool_call( $token_id, $tool_name, $arguments, 'pending' );
        $this->last_audit_id = $audit_id;
        
        
        
        if ( $approval_row ) {
            $this->note_audit_approval( $audit_id, $approval_row );
        }
        
        
        $this->in_flight[]   = (int) $audit_id;
        $final_status        = null;
        $result              = null;
        
        
        
        
        
        
        
        
        
        $composite = self::call_is_composite( $tool, $arguments );
        
        
        
        
        
        $exec_started = null;
        $outer_call   = self::$active_call;
        
        
        $compat_mark  = class_exists( '\\Easy_MCP_AI\\Compat\\Compat_Registry' ) ? \Easy_MCP_AI\Compat\Compat_Registry::mark() : null;
        $option_guard_armed = false;
        
        
        $stack_entered      = false;
        $hook_arguments     = array();
        $call_context       = array();

        
        
        
        
        
        
        
        
        
        
        
        
        
        try {
        if ( ! $composite && class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) ) {
            \Easy_MCP_AI\History\Change_Context::set( array(
                'audit_id'        => $audit_id,
                'tool_name'       => $tool_name,
                'token_id'        => $token_id ? (int) $token_id : 0,
                'auth_source'     => $this->request_auth_source,
                'oauth_client_id' => $this->request_client_id,
                'wp_user_id'      => $this->request_wp_user_id,
                'ip_address'      => self::get_client_ip(),
                
                
                
                
                
                'tool_args'       => self::record_redaction( $tool, $arguments ),
                
                'approval_id'     => $approval_row ? $approval_row['approval_id'] : null,
                
                
                
                'privileged'      => null === $privileged_group ? '' : $privileged_group,
                
                
                
                'own_history'     => method_exists( $tool, 'records_own_history' ) && $tool->records_own_history(),
            ) );
            
            \do_action( 'easy_mcp_ai_change_context_armed' );
        }

            
            
            
            
            
            $arguments    = Gemini_Safe_Schema::coerce( $arguments, Gemini_Safe_Schema::sanitize( $tool->get_input_schema() )['map'] );
            
            
            if ( method_exists( $tool, 'set_request_identity' ) ) {
                $tool->set_request_identity( $this->request_identity( $token_id ) );
            }
            
            
            
            
            if ( class_exists( '\\Easy_MCP_AI\\Meta\\Meta_Exposure' ) ) {
                \Easy_MCP_AI\Meta\Meta_Exposure::register_for_request();
            }
            
            
            \Easy_MCP_AI\Cache\Page_Cache_Queue::listen();
            
            
            
            
            if ( null !== $compat_mark ) {
                \Easy_MCP_AI\Compat\Compat_Registry::arm_for( $tool, $arguments );
            }
            
            
            Option_Guard::arm();
            $option_guard_armed = true;
            self::$active_call = array(
                'server'        => $this,
                'token_id'      => $token_id,
                'allowed_tools' => $allowed_tools,
                
                
                'approval_kind' => $this->last_approval['kind'],
            );
            
            
            
            
            
            $hook_arguments = (array) $this->redact_for_tool( $tool_name, $arguments );
            $call_context   = array(
                'audit_id'    => $audit_id ? (int) $audit_id : null,
                'parent'      => Tool_Call_Stack::current(),
                'composite'   => $composite,
                'read_only'   => self::call_is_read_only( $tool, $arguments ),
                'user_id'     => (int) \get_current_user_id(),
                'auth_source' => 'oauth' === $this->request_auth_source ? 'oauth' : 'api_key',
                'approval_id' => $approval_row ? $approval_row['approval_id'] : null,
            );
            $call_context['depth'] = Tool_Call_Stack::enter( $tool_name );
            $stack_entered         = true;
            







            \do_action( 'easy_mcp_ai_before_tool_call', $tool_name, $hook_arguments, $call_context );
            $exec_started = microtime( true );
            $result       = $tool->execute( $arguments );
            $final_status = 'success';
            
            
            
            
            
            
            if ( is_array( $result ) && isset( $result['error'] ) && is_string( $result['error'] ) ) {
                $result['error'] = self::sanitize_error_message( $result['error'] );
            }
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            
            $output_schema  = method_exists( $tool, 'get_output_schema' ) ? $tool->get_output_schema() : null;
            $has_structured = null !== $output_schema && is_array( $result );
            if ( $has_structured ) {
                $output_map = Gemini_Safe_Schema::sanitize( $output_schema )['map'];
                if ( ! empty( $output_map ) ) {
                    $result = Gemini_Safe_Schema::conform( $result, $output_map );
                }
            }
            
            
            
            
            
            
            
            
            $structured_value = ( $has_structured && empty( $result ) ) ? new \stdClass() : $result;

            $response = array(
                'content' => array( array(
                    'type' => 'text',
                    'text' => is_string( $result )
                        ? $result
                        : wp_json_encode( $has_structured ? $structured_value : $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
                ) ),
            );
            if ( $has_structured ) {
                $response['structuredContent'] = $structured_value;
            }
            $approval_outcome = $response;
            return JSON_RPC::success_response( $id, $response );
        } catch ( \Exception $e ) {
            
            
            
            $final_status = 'error';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'WP MCP Server tool exception [%s]: %s in %s:%d', $tool_name, $e->getMessage(), $e->getFile(), $e->getLine() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
            $approval_outcome = self::error_result( $e );
            return JSON_RPC::success_response( $id, $approval_outcome );
        } catch ( \Error $e ) {
            
            
            $final_status = 'error';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'WP MCP Server tool error [%s]: %s in %s:%d', $tool_name, $e->getMessage(), $e->getFile(), $e->getLine() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
            $approval_outcome = array(
                'content' => array( array( 'type' => 'text', 'text' => 'Tool execution failed. Check server error logs for details.' ) ),
                'isError' => true,
            );
            return JSON_RPC::success_response( $id, $approval_outcome );
        } finally {
            
            
            if ( $option_guard_armed ) {
                Option_Guard::release();
            }
            
            
            if ( null !== $compat_mark ) {
                \Easy_MCP_AI\Compat\Compat_Registry::release( $compat_mark );
            }
            self::$active_call = $outer_call;
            if ( $stack_entered ) {
                Tool_Call_Stack::leave();
            }
            
            
            
            
            $duration_ms = null === $exec_started ? null : (int) round( ( microtime( true ) - $exec_started ) * 1000 );
            
            
            
            try {
                $this->update_audit_status( $audit_id, null === $final_status ? 'error' : $final_status, $duration_ms );
                if ( 'error' === $final_status ) {
                    $this->note_audit_error( $audit_id, $approval_outcome );
                }
                array_pop( $this->in_flight );
                if ( $approval_row ) {
                    
                    
                    
                    try {
                        $this->get_approval_gate()->finish( $approval_row, $approval_outcome, null === $final_status ? 'error' : $final_status, $audit_id );
                    } catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                        
                    }
                }
                if ( ! $composite && class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) ) {
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    
                    try {
                        \do_action( 'easy_mcp_ai_change_context_disarming' );
                    } finally {
                        \Easy_MCP_AI\History\Change_Context::clear();
                    }
                }
                
                
                
                \Easy_MCP_AI\Cache\Page_Cache_Queue::flush();

                
                
                
                
                
                
                
                
                
                
                if ( 'success' === $final_status && ! $composite ) {
                    if ( ! self::call_is_read_only( $tool, $arguments ) ) {
                        










                        \do_action( 'easy_mcp_ai_tool_mutated', $tool_name, $this->redact_for_tool( $tool_name, $arguments ), $result );
                    }
                }
            } finally {
                
                
                
                
                if ( ! $composite && class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) && \Easy_MCP_AI\History\Change_Context::is_active() && $audit_id === \Easy_MCP_AI\History\Change_Context::get( 'audit_id' ) ) {
                    try {
                        \do_action( 'easy_mcp_ai_change_context_disarming' );
                    } catch ( \Throwable $ignored ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
                        
                    } finally {
                        \Easy_MCP_AI\History\Change_Context::clear();
                    }
                }
                if ( $stack_entered ) {
                    self::fire_after_tool_call( $tool_name, $hook_arguments, $call_context, $final_status, $result, $approval_outcome, $duration_ms );
                }
            }
        }
    }

    















    private static function fire_after_tool_call( string $tool_name, array $arguments, array $context, ?string $status, $result, ?array $outcome, ?int $duration_ms ): void {
        $succeeded              = 'success' === $status;
        $context['status']      = $succeeded ? 'success' : 'error';
        $context['duration_ms'] = $duration_ms;
        $context['result']      = $succeeded ? $result : null;
        $context['error']       = ! $succeeded && isset( $outcome['content'][0]['text'] ) && is_string( $outcome['content'][0]['text'] ) ? $outcome['content'][0]['text'] : null;
        try {
            









            \do_action( 'easy_mcp_ai_after_tool_call', $tool_name, $arguments, $context );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'Easy MCP AI easy_mcp_ai_after_tool_call listener failed [%s]: %s', $tool_name, $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
        }
    }

    












    private static function call_is_composite( \Easy_MCP_AI\Tools\Base_Tool $tool, array $arguments ): bool {
        try {
            return (bool) $tool->is_composite( $arguments );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    








    private static function call_is_read_only( \Easy_MCP_AI\Tools\Base_Tool $tool, array $arguments ): bool {
        try {
            return (bool) $tool->is_read_only_call( $arguments );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    
    private static function privileged_group( $tool_name ) {
        return class_exists( '\\Easy_MCP_AI\\Privileged\\Privileged_Groups' )
            ? \Easy_MCP_AI\Privileged\Privileged_Groups::group_of_tool( $tool_name )
            : null;
    }

    













    private static function privileged_switch_off( \Easy_MCP_AI\Tools\Base_Tool $tool, string $tool_name ): ?array {
        $group = self::privileged_group( $tool_name );
        if ( null === $group || Tool_State::is_enabled( $tool ) || ! \Easy_MCP_AI\Privileged\Privileged_Groups::floor_passes( $group, (int) \get_current_user_id() ) ) {
            return null;
        }
        return Tool_Status::off( $tool_name, (bool) $tool->is_disabled_by_default() );
    }

    










    private function privileged_refusal( $group ) {
        if ( ! \Easy_MCP_AI\Privileged\Privileged_Groups::is_enabled( $group ) ) {
            return 'This privileged tool is not enabled on this site.';
        }
        if ( ! $this->audit_log_enabled ) {
            return 'Privileged tools run only while the audit log is on. Ask the site administrator to turn it on.';
        }
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true ) ) {
            return 'Privileged tools run only while change history is on. Ask the site administrator to turn it on.';
        }
        return null;
    }

    






    private function check_privileged_rate_limit( $token_id ) {
        $limit = \Easy_MCP_AI\Privileged\Privileged_Groups::RATE_LIMIT_PER_MINUTE;
        if ( 'oauth' === $this->request_auth_source ) {
            $principal = 'o' . substr( hash( 'sha256', (string) $this->request_client_id . '|' . (int) $this->request_wp_user_id ), 0, 32 );
        } else {
            $principal = 'k' . (int) $token_id;
        }
        $cache_key = 'easy_mcp_ai_priv_rate_' . $principal;
        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $cache_key, 0, 'easy_mcp_ai', 60 );
            return \wp_cache_incr( $cache_key, 1, 'easy_mcp_ai' ) <= $limit;
        }
        
        
        $current = (int) \get_transient( $cache_key );
        if ( $current >= $limit ) {
            return false;
        }
        \set_transient( $cache_key, $current + 1, 60 );
        return true;
    }

    











    private function note_audit_error( ?int $audit_id, ?array $outcome ): void {
        if ( ! $this->audit_log_enabled || ! $audit_id || null === $outcome || ! isset( $outcome['content'][0]['text'] ) || ! is_string( $outcome['content'][0]['text'] ) ) {
            return;
        }
        $text = $outcome['content'][0]['text'];
        if ( class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            $text = \Easy_MCP_AI\Text_Redactor::redact( $text );
        }
        $text = function_exists( 'mb_strcut' ) ? mb_strcut( $text, 0, 1000, 'UTF-8' ) : substr( $text, 0, 1000 );
        if ( '' === trim( $text ) ) {
            return;
        }
        global $wpdb;
        $quiet = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update of the plugin's own audit row.
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array( 'error_message' => $text ),
            array( 'id' => (int) $audit_id ),
            array( '%s' ),
            array( '%d' )
        );
        if ( null !== $quiet ) {
            $wpdb->suppress_errors( $quiet );
        }
    }

    










    private function note_audit_approval( $audit_id, array $row ) {
        if ( ! $this->audit_log_enabled || ! $audit_id || empty( $row['approval_id'] ) ) {
            return;
        }
        global $wpdb;
        $data   = array( 'approval_id' => (string) $row['approval_id'] );
        $format = array( '%s' );
        if ( ! empty( $row['decided_by'] ) ) {
            $data['approved_by']  = (int) $row['decided_by'];
            $data['approved_via'] = isset( $row['decision_via'] ) ? substr( (string) $row['decision_via'], 0, 16 ) : null;
            $data['approved_at']  = isset( $row['decided_at'] ) ? (string) $row['decided_at'] : null;
            array_push( $format, '%d', '%s', '%s' );
        }
        
        
        $quiet = method_exists( $wpdb, 'suppress_errors' ) ? $wpdb->suppress_errors( true ) : null;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update of the plugin's own audit row.
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            $data,
            array( 'id' => (int) $audit_id ),
            $format,
            array( '%d' )
        );
        if ( null !== $quiet ) {
            $wpdb->suppress_errors( $quiet );
        }
    }

    















    public static function current_call_may_use( string $tool_name ): ?bool {
        if ( null === self::$active_call ) {
            return null;
        }
        return self::$active_call['server']->token_may_use(
            self::$active_call['token_id'],
            self::$active_call['allowed_tools'],
            $tool_name
        );
    }

    














    public static function current_call_tool_status( string $tool_name ): ?array {
        if ( null === self::$active_call ) {
            return null;
        }
        return self::$active_call['server']->tool_status_for(
            self::$active_call['token_id'],
            self::$active_call['allowed_tools'],
            $tool_name
        );
    }

    









    private static function precheck_refusal( \Easy_MCP_AI\Tools\Base_Tool $tool, array $arguments ): ?array {
        try {
            $tool->precheck( Gemini_Safe_Schema::coerce( $arguments, Gemini_Safe_Schema::sanitize( $tool->get_input_schema() )['map'] ) );
        } catch ( \Exception $e ) {
            return self::error_result( $e );
        } catch ( \Error $e ) {
            return null;
        }
        return null;
    }

    






    private static function error_result( \Exception $e ): array {
        $content = array( array( 'type' => 'text', 'text' => 'Error: ' . self::sanitize_error_message( $e->getMessage() ) ) );
        if ( $e instanceof Detailed_Tool_Error && '' !== trim( $e->get_details() ) ) {
            $content[] = array( 'type' => 'text', 'text' => self::sanitize_error_message( $e->get_details(), Detailed_Tool_Error::MAX_DETAILS ) );
        }
        return array(
            'content' => $content,
            'isError' => true,
        );
    }

    








    private function token_may_use( $token_id, ?array $allowed_tools, string $tool_name ): bool {
        
        
        
        if ( self::is_paused() ) {
            return false;
        }
        if ( null !== $allowed_tools ) {
            if ( ! $this->permission_guard->can_use_tool_with_scope( $allowed_tools, $tool_name ) ) {
                return false;
            }
        } elseif ( ! $this->permission_guard->can_use_tool( $token_id, $tool_name ) ) {
            return false;
        }
        $tool = $this->tool_registry->get_tool( $tool_name );
        if ( null === $tool ) {
            return false;
        }
        return array() !== self::available_tools( $this->tool_registry, true, array( $tool->get_definition() ) )
            && null === $this->late_call_refusal( $tool, $tool_name );
    }

    





    private function tool_status_for( $token_id, ?array $allowed_tools, string $tool_name ): array {
        
        
        
        if ( self::is_paused() ) {
            return array( 'status' => 'paused' );
        }
        if ( ! \Easy_MCP_AI\Config::tool_policy_valid() ) {
            return array( 'status' => 'policy' );
        }
        $allowed = null !== $allowed_tools
            ? $this->permission_guard->can_use_tool_with_scope( $allowed_tools, $tool_name )
            : $this->permission_guard->can_use_tool( $token_id, $tool_name );
        if ( ! $allowed ) {
            return array( 'status' => 'not_allowed' );
        }
        $tool = $this->tool_registry->get_tool( $tool_name );
        if ( null === $tool ) {
            return array( 'status' => 'not_registered' );
        }
        $cap = self::effective_required_capability( $tool->get_category(), $tool->get_required_capability() );
        if ( $cap && ! \current_user_can( $cap ) ) {
            $switched_off = self::privileged_switch_off( $tool, $tool_name );
            if ( null !== $switched_off ) {
                return $switched_off;
            }
            if ( null !== self::privileged_group( $tool_name ) ) {
                
                return array(
                    'status'  => 'privileged',
                    'message' => 'Insufficient WordPress permissions for this tool',
                );
            }
            return array(
                'status'     => 'capability',
                'capability' => (string) $cap,
            );
        }
        if ( ! Tool_State::is_enabled( $tool ) ) {
            return Tool_Status::off( $tool_name, (bool) $tool->is_disabled_by_default() );
        }
        if ( ! self::matches_tool_patterns( $tool_name, (array) \Easy_MCP_AI\Config::get( 'allowed_tool_patterns' ) ) ) {
            return array( 'status' => 'pattern' );
        }
        $refusal = $this->late_call_refusal( $tool, $tool_name );
        return null !== $refusal ? $refusal : array( 'status' => 'available' );
    }

    










    private function late_call_refusal( \Easy_MCP_AI\Tools\Base_Tool $tool, string $tool_name ): ?array {
        if ( self::is_app_only_tool( $tool ) && ! $this->client_declared_apps() ) {
            return array( 'status' => 'app_only' );
        }
        $group = self::privileged_group( $tool_name );
        $why   = null !== $group ? $this->privileged_refusal( $group ) : null;
        return null !== $why ? array( 'status' => 'privileged', 'message' => $why ) : null;
    }

    








    





    private function evaluate_approval( $tool, array $arguments, $token_id, array $params ) {
        if ( ! \Easy_MCP_AI\Approvals\Approval_Gate::requires_approval( $tool, $arguments ) ) {
            return array( 'kind' => 'run' );
        }
        try {
            return $this->get_approval_gate()->evaluate( $tool, $arguments, $this->request_identity( $token_id ), $params, $this->request_client_caps, $this->request_client_protocol );
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'Easy MCP AI approval gate failed for %s: %s', $tool->get_name(), $e->getMessage() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- intentional debug logging
            }
            return array( 'kind' => 'refused', 'reason' => 'unavailable', 'message' => 'the approval system could not record this request, so the operation was not run. Try again shortly.' );
        }
    }

    public static function sanitize_error_message( $message, int $max_length = Detailed_Tool_Error::MAX_MESSAGE ) {
        if ( ! is_string( $message ) || '' === $message ) {
            return 'Tool execution failed.';
        }

        $message = \Easy_MCP_AI\Config::brand( $message );

        
        $message = preg_replace( '/\s*Stack trace:.*$/s', '', $message );

        
        $message = preg_replace( '/\s+in\s+\S+\.php(?:\(\d+\)|:\d+| on line \d+)/', '', $message );

        
        
        
        
        
        
        
        
        
        
        
        
        $message = preg_replace( '#(?<![\w.\-/\\\\])(?:\\\\?/|[A-Z]:\\\\)(?:(?:[^\s\'"<>/\\\\]+(?: +[^\s\'"<>/\\\\]+)*)?[/\\\\])*[^\s\'"<>/\\\\]*\.(?:php|inc|tpl|phtml)\b#', '[file]', $message );

        
        $message = preg_replace_callback(
            '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
            function ( $m ) {
                $ip = $m[0];
                $parts = array_map( 'intval', explode( '.', $ip ) );
                if ( 127 === $parts[0] ) { return '[internal]'; }
                if ( 10 === $parts[0] ) { return '[internal]'; }
                if ( 192 === $parts[0] && 168 === $parts[1] ) { return '[internal]'; }
                if ( 172 === $parts[0] && $parts[1] >= 16 && $parts[1] <= 31 ) { return '[internal]'; }
                if ( 169 === $parts[0] && 254 === $parts[1] ) { return '[internal]'; }
                return $ip;
            },
            $message
        );
        $message = preg_replace( '/\[?::1\]?|\blocalhost\b/i', '[internal]', $message );

        
        $message    = trim( $message );
        $max_length = max( 1, $max_length );
        if ( strlen( $message ) > $max_length ) {
            
            
            
            $cut     = function_exists( 'mb_strcut' ) ? mb_strcut( $message, 0, $max_length, 'UTF-8' ) : substr( $message, 0, $max_length );
            $message = $cut . '…[truncated]';
        }

        return '' === $message ? 'Tool execution failed.' : $message;
    }

    private function handle_resources_list( $id, $params, $token_id, ?array $allowed_tools = null ): array {
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        
        
        if ( self::is_paused() ) {
            return JSON_RPC::success_response( $id, array( 'resources' => array() ) );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }
        
        
        if ( ! \current_user_can( 'read' ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Insufficient permissions to list resources' );
        }
        $definitions = $this->resource_registry->get_all_definitions(
            function ( $resource ) use ( $token_id, $allowed_tools ) {
                return $this->resource_tool_permitted( $resource, $token_id, $allowed_tools );
            }
        );
        return JSON_RPC::success_response( $id, array( 'resources' => $definitions ) );
    }

    private function handle_resources_read( $id, $params, $token_id, ?array $allowed_tools = null ): array {
        if ( null === $token_id ) {
            return JSON_RPC::error_response( $id, Error_Codes::UNAUTHORIZED, 'Authentication required' );
        }
        
        if ( self::is_paused() ) {
            return JSON_RPC::error_response( $id, Error_Codes::PAUSED, Error_Codes::PAUSED_MESSAGE );
        }
        if ( ! $this->check_rate_limit( $token_id ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::RATE_LIMITED, 'Rate limit exceeded. Please try again later.' );
        }

        
        if ( ! \current_user_can( 'read' ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::FORBIDDEN, 'Insufficient permissions to read resources' );
        }

        $uri = isset( $params['uri'] ) ? $params['uri'] : '';
        if ( empty( $uri ) ) {
            return JSON_RPC::error_response( $id, Error_Codes::INVALID_PARAMS, 'Missing resource URI' );
        }
        $resource = $this->resource_registry->get_resource( $uri );
        if ( null === $resource ) {
            return JSON_RPC::error_response( $id, Error_Codes::RESOURCE_NOT_FOUND, 'Resource not found' );
        }
        
        if ( ! $this->resource_tool_permitted( $resource, $token_id, $allowed_tools ) ) {
            return JSON_RPC::error_response(
                $id,
                Error_Codes::FORBIDDEN,
                sprintf( 'Reading %1$s needs the %2$s tool, which this connection\'s tool list does not include.', $uri, $resource->get_required_tool() )
            );
        }
        try {
            $content = $resource->read();
            $entry   = array(
                'uri'      => $uri,
                'mimeType' => $resource->get_mime_type(),
                'text'     => is_string( $content ) ? $content : wp_json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
            );
            
            
            if ( method_exists( $resource, 'get_meta' ) ) {
                $meta = $resource->get_meta();
                if ( ! empty( $meta ) ) {
                    $entry['_meta'] = $meta;
                }
            }
            
            
            
            if ( 0 === strpos( (string) $uri, 'skill://' ) ) {
                $this->log_tool_call( $token_id, '_skill_read', array( 'uri' => $uri ), 'success' );
            }
            return JSON_RPC::success_response( $id, array( 'contents' => array( $entry ) ) );
        } catch ( \Throwable $e ) {
            return JSON_RPC::error_response( $id, Error_Codes::INTERNAL_ERROR, 'Failed to read resource' );
        }
    }

    










    private function resource_tool_permitted( \Easy_MCP_AI\Resources\Base_Resource $resource, $token_id, ?array $allowed_tools ): bool {
        $tool = $resource->get_required_tool();
        if ( null === $tool ) {
            return true;
        }
        return null !== $allowed_tools
            ? $this->permission_guard->can_use_tool_with_scope( $allowed_tools, $tool )
            : $this->permission_guard->can_use_tool( $token_id, $tool );
    }

    






















    private function log_refusal( $token_id, $tool_name, $arguments ) {
        if ( null === $token_id ) {
            return;
        }

        $limit = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_rate_limit_per_minute', 60 );
        $key   = 'easy_mcp_ai_reflog_' . (int) $token_id;

        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $key, 0, 'easy_mcp_ai', 60 );
            if ( (int) \wp_cache_incr( $key, 1, 'easy_mcp_ai' ) > $limit ) {
                return;
            }
        } else {
            $written = (int) \get_transient( $key );
            if ( $written >= $limit ) {
                return;
            }
            \set_transient( $key, $written + 1, 60 );
        }

        $this->log_tool_call( $token_id, $tool_name, $arguments, self::STATUS_REFUSED );
    }

    private function check_rate_limit( $token_id ) {
        if ( null === $token_id ) {
            return true; 
        }
        $limit     = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_rate_limit_per_minute', 60 );
        $cache_key = 'easy_mcp_ai_rate_' . (int) $token_id;

        
        
        
        if ( \wp_using_ext_object_cache() ) {
            \wp_cache_add( $cache_key, 0, 'easy_mcp_ai', 60 );
            $new_count = \wp_cache_incr( $cache_key, 1, 'easy_mcp_ai' );
            return $new_count <= $limit;
        }

        
        
        
        
        $current = (int) \get_transient( $cache_key );
        if ( $current >= $limit ) {
            return false;
        }
        \set_transient( $cache_key, $current + 1, 60 );
        return true;
    }

    












    public function log_auth_failure( $ip, $reason, array $identity = array() ) {
        if ( ! $this->audit_log_enabled ) {
            return;
        }
        global $wpdb;
        $source = isset( $identity['auth_source'] ) && in_array( $identity['auth_source'], array( 'legacy', 'oauth' ), true )
            ? $identity['auth_source']
            : null;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for audit logging.
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array(
                'token_id'        => ! empty( $identity['token_id'] ) ? (int) $identity['token_id'] : 0,
                'tool_name'       => '_auth_failure',
                'arguments'       => wp_json_encode( array( 'reason' => $reason ) ),
                'result_status'   => 'auth_failure',
                'ip_address'      => $ip,
                'auth_source'     => $source,
                'wp_user_id'      => ! empty( $identity['wp_user_id'] ) ? (int) $identity['wp_user_id'] : null,
                'oauth_client_id' => ! empty( $identity['oauth_client_id'] ) ? (string) $identity['oauth_client_id'] : null,
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
    }

    

























    const STATUS_REFUSED = 'refused';

    
    const LAST_CALL_OPTION = 'easy_mcp_ai_last_tool_call';

    



    private function stamp_last_call( $token_id ) {
        if ( $this->audit_log_enabled ) {
            return;
        }
        
        $oauth = 'oauth' === $this->request_auth_source;
        \update_option(
            self::LAST_CALL_OPTION,
            array(
                'id'              => bin2hex( random_bytes( 8 ) ),
                'at'              => current_time( 'mysql', true ),
                'auth_source'     => $oauth ? 'oauth' : 'legacy',
                'token_id'        => (int) $token_id,
                'oauth_client_id' => $oauth && is_string( $this->request_client_id ) && '' !== $this->request_client_id ? $this->request_client_id : null,
            ),
            false
        );
    }

    private function log_tool_call( $token_id, $tool_name, $arguments, $status ) {
        if ( ! $this->audit_log_enabled ) {
            return 0;
        }
        global $wpdb;
        $safe_args = $this->redact_for_tool( $tool_name, $arguments );
        
        
        
        
        
        
        
        
        $source = in_array( $this->request_auth_source, array( 'legacy', 'oauth' ), true ) ? $this->request_auth_source : null;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct insert required for audit logging.
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            array(
                'token_id'        => $token_id ? (int) $token_id : 0,
                'tool_name'       => $tool_name,
                'arguments'       => wp_json_encode( $safe_args ),
                'result_status'   => $status,
                'ip_address'      => self::get_client_ip(),
                'auth_source'     => $source,
                'wp_user_id'      => $this->request_wp_user_id > 0 ? (int) $this->request_wp_user_id : null,
                'oauth_client_id' => ( is_string( $this->request_client_id ) && '' !== $this->request_client_id ) ? $this->request_client_id : null,
                'created_at'      => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );
        return (int) $wpdb->insert_id;
    }

    




    private function update_audit_status( $audit_id, $status, $duration_ms = null ) {
        if ( ! $this->audit_log_enabled || ! $audit_id ) {
            return;
        }
        global $wpdb;
        $data   = array( 'result_status' => $status );
        $format = array( '%s' );
        if ( null !== $duration_ms ) {
            $data['duration_ms'] = max( 0, (int) $duration_ms );
            $format[]            = '%d';
        }
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct update required to finalize audit status.
            $wpdb->prefix . 'easy_mcp_ai_audit_log',
            $data,
            array( 'id' => (int) $audit_id ),
            $format,
            array( '%d' )
        );
    }

    











    private function redact_for_tool( $tool_name, $args ) {
        $safe = self::redact_sensitive_args( $args );
        if ( ! is_array( $safe ) || ! is_string( $tool_name ) || '' === $tool_name || ! $this->tool_registry ) {
            return $safe;
        }
        $tool = $this->tool_registry->get_tool( $tool_name );
        if ( ! $tool || ! method_exists( $tool, 'get_redacted_arguments' ) ) {
            return $safe;
        }
        foreach ( (array) $tool->get_redacted_arguments() as $key ) {
            if ( is_string( $key ) && array_key_exists( $key, $safe ) ) {
                $safe[ $key ] = '[REDACTED]';
            }
        }
        return self::record_redaction( $tool, $safe );
    }

    










    private static function record_redaction( \Easy_MCP_AI\Tools\Base_Tool $tool, array $args ): array {
        try {
            $masked = $tool->redact_for_record( $args );
        } catch ( \Throwable $e ) {
            $masked = null;
        }
        if ( is_array( $masked ) ) {
            return $masked;
        }
        $all = array();
        foreach ( array_keys( $args ) as $key ) {
            $all[ $key ] = '[REDACTED]';
        }
        return $all;
    }

    private static function redact_sensitive_args( $args ) {
        if ( ! is_array( $args ) ) {
            return $args;
        }
        
        $sensitive_pattern = '/^(password|pass|secret|token|api[_\-]?key|authorization|content_base64|download_url|file_id|private[_\-]?key|access[_\-]?token|client[_\-]?secret|credential)$/i';
        $result = array();
        foreach ( $args as $key => $value ) {
            if ( preg_match( $sensitive_pattern, $key ) ) {
                $result[ $key ] = '[REDACTED]';
            } elseif ( is_array( $value ) ) {
                $result[ $key ] = self::redact_sensitive_args( $value );
            } else {
                $result[ $key ] = $value;
            }
        }
        return $result;
    }

    private static function get_client_ip() {
        
        
        
        
        
        
        if ( class_exists( '\Easy_MCP_AI\Client_IP' ) ) {
            $ip = (string) \Easy_MCP_AI\Client_IP::get();
            return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        } else {
            $ip = '';
        }
        return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
    }

    









    public static function matches_tool_patterns( $tool_name, array $patterns ) {
        if ( empty( $patterns ) ) {
            return true;
        }
        foreach ( $patterns as $pattern ) {
            $pattern = trim( (string) $pattern );
            if ( '' === $pattern ) {
                continue;
            }
            
            if ( false === strpos( $pattern, '*' ) && false === strpos( $pattern, '?' ) ) {
                $pattern = '*' . $pattern . '*';
            }
            if ( fnmatch( $pattern, $tool_name ) ) {
                return true;
            }
        }
        return false;
    }

    private function tool_matches_pattern_filter( $tool_name ) {
        return self::matches_tool_patterns( $tool_name, (array) $this->allowed_tool_patterns );
    }

    public function get_session_manager() {
        return $this->session_manager;
    }
}
