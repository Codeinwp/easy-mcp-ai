<?php
namespace Easy_MCP_AI\History;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















class Change_External_Intent {

    
    const WRITE_VERBS = 'create|update|delete|set|add|remove|toggle|send|apply|import|save|change|refund|cancel|merge|assign|reorder|duplicate|publish|restore|enable|disable|manage|upsert|book|register';

    





    const SUPPRESSED_PATTERNS = 'generation|summarization|editorial_updates|content_classification|comment_analysis';

    
    private $repo;

    
    private $emitted = false;

    public function __construct( Change_Log_Repository $repo ) {
        $this->repo = $repo;
    }

    public function register() {
        \add_action( 'http_api_debug', array( $this, 'on_http_api_debug' ), 10, 5 );
        
        
        
        \add_action( 'easy_mcp_ai_change_context_disarming', array( $this, 'maybe_record' ), 30 );
        \add_action( 'easy_mcp_ai_change_context_armed', array( $this, 'on_armed' ) );
    }

    public function on_armed() {
        $this->emitted = false;
    }

    




    public function on_http_api_debug( $response, $context, $class, $parsed_args, $url ) {
        if ( ! Change_Context::is_active() ) {
            return;
        }
        $method = strtoupper( (string) ( is_array( $parsed_args ) ? ( $parsed_args['method'] ?? 'GET' ) : 'GET' ) );
        if ( 'GET' === $method || 'HEAD' === $method ) {
            return;
        }
        $host = (string) \wp_parse_url( (string) $url, PHP_URL_HOST );
        if ( '' === $host ) {
            return;
        }
        $hosts = (array) Change_Context::get( '_outbound_hosts', array() );
        if ( ! in_array( $host, $hosts, true ) ) {
            $hosts[] = $host;
            Change_Context::set( array( '_outbound_hosts' => $hosts ) );
        }
    }

    
    public function outbound_hosts() {
        return Change_Context::is_active() ? (array) Change_Context::get( '_outbound_hosts', array() ) : array();
    }

    




    public static function is_write_shaped( $tool_name ) {
        $tool_name = (string) $tool_name;
        $is_write  = false;
        if ( 0 === strpos( $tool_name, 'wp_ability_' )
            && ! preg_match( '/(?:^|_)(?:' . self::SUPPRESSED_PATTERNS . ')(?:_|$)/i', $tool_name )
            && preg_match( '/(?:^|_)(?:' . self::WRITE_VERBS . ')(?:_|$)/i', $tool_name ) ) {
            $is_write = true;
        }
        return (bool) \apply_filters( 'easy_mcp_ai_change_log_is_write_ability', $is_write, $tool_name );
    }

    


    public function maybe_record() {
        if ( $this->emitted || ! Change_Context::is_active() ) {
            return;
        }
        if ( Change_Context::rows_written() > 0 ) {
            return; 
        }
        $tool = (string) Change_Context::get( 'tool_name', '' );
        if ( ! self::is_write_shaped( $tool ) ) {
            return;
        }
        $hosts = $this->outbound_hosts();
        if ( ! $hosts ) {
            return; 
        }
        $this->emitted = true;

        $ctx  = Change_Context::all();
        $args = $this->redact_args( (array) Change_Context::get( 'tool_args', array() ) );
        $this->repo->insert( array(
            'audit_id'        => $ctx['audit_id']        ?? null,
            'auth_source'     => $ctx['auth_source']     ?? 'legacy',
            'token_id'        => $ctx['token_id']        ?? 0,
            'oauth_client_id' => $ctx['oauth_client_id'] ?? null,
            'wp_user_id'      => $ctx['wp_user_id']      ?? 0,
            'tool_name'       => $tool,
            'ip_address'      => $ctx['ip_address']      ?? null,
            'action'          => 'update',
            'object_type'     => 'external',
            'object_id'       => $tool,
            'object_subtype'  => self::ability_prefix( $tool ),
            'after_value'     => array(
                'args'      => $args,
                'endpoints' => $hosts,
                'note'      => 'Remote change — local before/after not available.',
            ),
            'capture_mode'    => 'external',
        ) );
    }

    
    private static function ability_prefix( $tool ) {
        if ( preg_match( '/^wp_ability_([a-z0-9]+)_/', (string) $tool, $m ) ) {
            return $m[1];
        }
        return null;
    }

    







    private function redact_args( array $args ) {
        $out = array();
        foreach ( $args as $k => $v ) {
            if ( Change_DB_Interceptor::column_is_sensitive( (string) $k ) ) {
                $out[ $k ] = '[REDACTED]';
            } elseif ( is_array( $v ) ) {
                $out[ $k ] = $this->redact_args( $v );
            } else {
                $out[ $k ] = $v;
            }
        }
        return $out;
    }
}
