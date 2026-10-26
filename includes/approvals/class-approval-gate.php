<?php
namespace Easy_MCP_AI\Approvals;

use Easy_MCP_AI\Auth\Token_Keys;
use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Tools\Tool_Registry;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















class Approval_Gate {

    
    const TTL_SECONDS = 900;
    
    const RETENTION_DAYS = 7;
    
    const REPLAY_SECONDS = 300;
    
    const REQUEST_KEY = 'approve';
    






    const FORM_PROTOCOL = '2026-07-28';
    
    const META_KEY = 'easy-mcp-ai/approval';
    




    const PREVIEW_MAX = 2000;

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED   = 'denied';
    const STATUS_CONSUMED = 'consumed';
    const STATUS_EXPIRED  = 'expired';

    
    private $store;
    
    private $token_unattended;
    
    private $now;

    public function __construct( Approval_Store $store, $token_unattended = null, $now = null ) {
        $this->store            = $store;
        $this->token_unattended = is_callable( $token_unattended ) ? $token_unattended : null;
        $this->now              = is_callable( $now ) ? $now : 'time';
    }

    public function get_store() {
        return $this->store;
    }

    
    
    

    
    public static function enabled() {
        return (bool) \Easy_MCP_AI\Config::get( 'approval_required' );
    }

    










    public static function matches_any( $tool_name, array $patterns, ?string $route = null ) {
        foreach ( $patterns as $pattern ) {
            $pattern       = trim( (string) $pattern );
            $route_pattern = null;
            
            
            
            
            if ( 1 === preg_match( '/^(\S+)\s+(\S+)$/', $pattern, $parts ) ) {
                $pattern       = $parts[1];
                $route_pattern = strtolower( $parts[2] );
            }
            if ( '' === $pattern || ! self::name_matches( $pattern, (string) $tool_name ) ) {
                continue;
            }
            if ( null === $route_pattern ) {
                return true;
            }
            if ( is_string( $route ) && self::route_glob_matches( $route_pattern, strtolower( $route ) ) ) {
                return true;
            }
        }
        return false;
    }

    










    private static function route_glob_matches( string $glob, string $route ): bool {
        
        
        $regex = self::route_glob_regex( $glob );
        return null !== $regex && 1 === @preg_match( '#^' . $regex . '$#', $route ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed stored entry matches nothing.
    }

    






    private static function route_glob_regex( string $glob ): ?string {
        $regex  = '';
        $length = strlen( $glob );
        for ( $i = 0; $i < $length; $i++ ) {
            $char  = $glob[ $i ];
            $close = false;
            if ( '{' === $char ) {
                $close = strpos( $glob, '}', $i + 1 );
            } elseif ( '[' === $char ) {
                $close = self::class_end( $glob, $i );
            }
            if ( '*' === $char ) {
                $regex .= '.*';
            } elseif ( '?' === $char ) {
                $regex .= '.';
            } elseif ( '{' === $char && false !== $close ) {
                $regex = self::with_placeholder( $regex, '?' === $glob[ $close - 1 ] );
                $i     = $close;
            } elseif ( '[' === $char && false !== $close ) {
                $members = substr( $glob, $i + 1, $close - $i - 1 );
                
                
                
                if ( 1 === preg_match( '/\[[.=:]/', $members ) ) {
                    return null;
                }
                $regex .= self::class_regex( $members );
                $i      = $close;
            } else {
                $regex .= preg_quote( $char, '#' );
            }
        }
        return $regex;
    }

    







    private static function with_placeholder( string $regex, bool $optional ): string {
        if ( ! $optional ) {
            return $regex . '[^/]+';
        }
        if ( '/' === substr( $regex, -1 ) ) {
            return substr( $regex, 0, -1 ) . '(?:/[^/]*)?';
        }
        return $regex . '[^/]*';
    }

    








    private static function class_end( string $glob, int $start ) {
        $from = $start + 1;
        if ( isset( $glob[ $from ] ) && ( '!' === $glob[ $from ] || '^' === $glob[ $from ] ) ) {
            ++$from;
        }
        if ( isset( $glob[ $from ] ) && ']' === $glob[ $from ] ) {
            ++$from;
        }
        return strpos( $glob, ']', $from );
    }

    






    private static function class_regex( string $members ): string {
        $negate = '' !== $members && ( '!' === $members[0] || '^' === $members[0] );
        if ( $negate ) {
            $members = substr( $members, 1 );
        }
        $out  = '';
        $last = strlen( $members ) - 1;
        for ( $i = 0; $i <= $last; $i++ ) {
            $out .= ( '-' === $members[ $i ] && $i > 0 && $i < $last ) ? '-' : preg_quote( $members[ $i ], '#' );
        }
        return '[' . ( $negate ? '^' : '' ) . $out . ']';
    }

    






    private static function name_matches( string $pattern, string $tool_name ): bool {
        if ( false === strpos( $pattern, '*' ) && false === strpos( $pattern, '?' ) ) {
            return $pattern === $tool_name;
        }
        return fnmatch( $pattern, $tool_name );
    }

    















    public static function is_destructive_by_annotation( $tool ) {
        $annotations = is_object( $tool ) && method_exists( $tool, 'get_annotations' ) ? $tool->get_annotations() : array();
        if ( ! is_array( $annotations ) ) {
            $annotations = array();
        }
        $destructive = isset( $annotations['destructiveHint'] ) ? $annotations['destructiveHint'] : null;
        if ( null !== $destructive ) {
            return false !== $destructive;
        }
        return ! ( isset( $annotations['readOnlyHint'] ) && true === $annotations['readOnlyHint'] );
    }

    











    public static function requires_approval( $tool, array $arguments ) {
        $tool_name = $tool->get_name();
        $reason    = '';
        if ( ! self::enabled() ) {
            return false;
        }
        $route = method_exists( $tool, 'approval_route' ) ? $tool->approval_route( $arguments ) : null;
        if ( self::matches_any( $tool_name, (array) \Easy_MCP_AI\Config::get( 'approval_always' ), $route ) ) {
            $required = true;
            $reason   = 'always';
        } elseif ( self::matches_any( $tool_name, (array) \Easy_MCP_AI\Config::get( 'approval_never' ), $route ) ) {
            $required = false;
            $reason   = 'never';
        } else {
            $policy = method_exists( $tool, 'get_approval_policy' ) ? $tool->get_approval_policy( $arguments ) : 'inherit';
            if ( 'always' === $policy || 'never' === $policy ) {
                $required = ( 'always' === $policy );
                $reason   = 'policy';
            } else {
                $required = self::is_destructive_by_annotation( $tool );
                $reason   = 'annotation';
            }
        }
        








        return (bool) \apply_filters( 'easy_mcp_ai_requires_approval', $required, $tool_name, $arguments, $reason );
    }

    







    public static function default_pause_names( Tool_Registry $registry, $definitions = null ) {
        $names = array();
        $defs  = null === $definitions ? $registry->get_all_definitions() : $definitions;
        foreach ( $defs as $definition ) {
            $tool = $registry->get_tool( $definition['name'] );
            if ( $tool && self::is_destructive_by_annotation( $tool ) ) {
                $names[] = $definition['name'];
            }
        }
        sort( $names );
        return $names;
    }

    
    
    

    











    public function evaluate( $tool, array $arguments, array $identity, array $params, $client_caps = null, $client_protocol = null ) {
        if ( ! self::requires_approval( $tool, $arguments ) ) {
            return array( 'kind' => 'run' );
        }
        if ( $this->is_unattended( $identity ) ) {
            return array( 'kind' => 'run' );
        }
        
        
        
        
        
        
        if ( method_exists( $tool, 'approval_refusal' ) ) {
            $refusal = $tool->approval_refusal( $arguments );
            if ( is_string( $refusal ) && '' !== $refusal ) {
                $replay = $this->consumed_replay( $identity, $tool->get_name(), self::args_hash( $tool->get_name(), $arguments ) );
                return $replay ? $replay : array( 'kind' => 'refused', 'reason' => 'preview', 'message' => $refusal );
            }
        }
        $tool_name = $tool->get_name();
        $hash      = self::args_hash( $tool_name, $arguments );

        
        if ( isset( $params['requestState'] ) || isset( $params['inputResponses'] ) ) {
            return $this->evaluate_retry( $tool, $arguments, $identity, $params, $hash, $client_caps, $client_protocol );
        }

        
        $approved = $this->store->find_latest( $identity, $tool_name, $hash, self::STATUS_APPROVED );
        if ( $approved && $this->row_is_valid( $approved, $identity ) ) {
            return $this->redeem_or_refuse( $tool, $arguments, $approved );
        }
        
        $replay = $this->consumed_replay( $identity, $tool_name, $hash );
        if ( $replay ) {
            return $replay;
        }
        
        $pending = $this->store->find_latest( $identity, $tool_name, $hash, self::STATUS_PENDING );
        if ( ! $pending || ! $this->row_is_valid( $pending, $identity ) ) {
            $pending = $this->create_row( $tool, $arguments, $identity, $hash, $client_caps, $client_protocol );
            if ( null === $pending ) {
                return array( 'kind' => 'refused', 'reason' => 'store_failed', 'message' => 'The approval could not be recorded; the operation was not run.' );
            }
        }
        return array( 'kind' => $this->offers_form( $client_caps, $client_protocol ) ? 'ask_form' : 'ask', 'row' => $pending );
    }

    








    private function consumed_replay( array $identity, string $tool_name, string $hash ): ?array {
        $consumed = $this->store->find_latest( $identity, $tool_name, $hash, self::STATUS_CONSUMED );
        return $consumed && $this->row_is_valid( $consumed, $identity ) ? $this->replay_of( $consumed ) : null;
    }

    







    private function replay_of( array $row ): ?array {
        if ( '' === (string) $row['result'] || $this->timestamp( $row['consumed_at'] ) < $this->time() - self::REPLAY_SECONDS ) {
            return null;
        }
        $result = json_decode( (string) $row['result'], true );
        if ( ! is_array( $result ) || ! empty( $result['isError'] ) ) {
            return null;
        }
        return array( 'kind' => 'replay', 'row' => $row, 'result' => $result );
    }

    private function evaluate_retry( $tool, array $arguments, array $identity, array $params, $hash, $client_caps, $client_protocol = null ) {
        $state = $this->verify_state( isset( $params['requestState'] ) ? $params['requestState'] : '' );
        if ( null === $state ) {
            return array( 'kind' => 'refused', 'reason' => 'bad_state', 'message' => 'The approval state on this retry is missing, expired or not ours; call the tool again without it to start over.' );
        }
        if ( $state['sub'] !== self::principal( $identity ) ) {
            return array( 'kind' => 'refused', 'reason' => 'wrong_principal', 'message' => 'This approval belongs to a different credential.' );
        }
        if ( $state['h'] !== $hash ) {
            return array( 'kind' => 'refused', 'reason' => 'args_changed', 'message' => 'The arguments differ from the ones the user was asked to approve; nothing was run. Call the tool again to request a fresh approval.' );
        }
        $row = $this->store->find( $state['op'] );
        if ( ! $row || ! $this->row_is_valid( $row, $identity ) ) {
            return array( 'kind' => 'refused', 'reason' => 'unknown', 'message' => 'The approval was not found or has expired; call the tool again to request a fresh one.' );
        }
        $responses = isset( $params['inputResponses'] ) && is_array( $params['inputResponses'] ) ? $params['inputResponses'] : array();
        $answer    = isset( $responses[ self::REQUEST_KEY ] ) && is_array( $responses[ self::REQUEST_KEY ] ) ? $responses[ self::REQUEST_KEY ] : null;
        if ( null === $answer ) {
            
            if ( self::STATUS_PENDING === $row['status'] ) {
                return array( 'kind' => $this->offers_form( $client_caps, $client_protocol ) ? 'ask_form' : 'ask', 'row' => $row );
            }
        } else {
            $action   = isset( $answer['action'] ) ? (string) $answer['action'] : '';
            $content  = isset( $answer['content'] ) && is_array( $answer['content'] ) ? $answer['content'] : array();
            $decision = isset( $content['decision'] ) ? (string) $content['decision'] : '';
            if ( self::STATUS_PENDING === $row['status'] ) {
                if ( 'accept' === $action && 'approve' === $decision ) {
                    $this->store->decide( $row['approval_id'], self::STATUS_APPROVED, (int) $identity['wp_user_id'], 'form', $this->now_mysql() );
                } elseif ( 'decline' === $action || ( 'accept' === $action && 'deny' === $decision ) ) {
                    $this->store->decide( $row['approval_id'], self::STATUS_DENIED, (int) $identity['wp_user_id'], 'form', $this->now_mysql() );
                } else {
                    
                    
                    
                    return array( 'kind' => 'ask', 'row' => $row );
                }
                $row = $this->store->find( $row['approval_id'] ) ?: $row;
            }
        }
        if ( self::STATUS_APPROVED === $row['status'] ) {
            return $this->redeem_or_refuse( $tool, $arguments, $row );
        }
        if ( self::STATUS_DENIED === $row['status'] ) {
            return array( 'kind' => 'refused', 'reason' => 'denied', 'message' => 'The user denied this operation; nothing was run.' );
        }
        if ( self::STATUS_CONSUMED === $row['status'] ) {
            
            
            
            $replay = $this->replay_of( $row );
            return $replay ? $replay : array( 'kind' => 'refused', 'reason' => 'consumed', 'message' => 'This approval was already used; call the tool again without the approval state to start over.' );
        }
        if ( self::STATUS_PENDING !== $row['status'] ) {
            return array( 'kind' => 'refused', 'reason' => 'unknown', 'message' => 'The approval has expired; call the tool again to request a fresh one.' );
        }
        return array( 'kind' => 'ask', 'row' => $row );
    }

    
    private function redeem_or_refuse( $tool, array $arguments, array $row ) {
        $preview = $this->preview( $tool, $arguments );
        if ( ! hash_equals( (string) $row['preview_hash'], hash( 'sha256', $preview ) ) ) {
            $this->store->update( $row['approval_id'], array( 'status' => self::STATUS_EXPIRED, 'arguments' => null ) );
            return array( 'kind' => 'refused', 'reason' => 'drift', 'message' => 'The target changed after the user approved it (the approved description no longer matches); nothing was run. Call the tool again to request a fresh approval.' );
        }
        return array( 'kind' => 'redeem', 'row' => $row );
    }

    




    public function claim( array $row ) {
        return $this->store->claim( $row['approval_id'], $this->now_mysql() );
    }

    
    public function finish( array $row, $result, $status, $executed_audit_id = null ) {
        $encoded = \wp_json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        if ( ! is_string( $encoded ) || strlen( $encoded ) > 65535 ) {
            $encoded = \wp_json_encode( array( 'content' => array( array( 'type' => 'text', 'text' => 'Executed; the result was too large to keep.' ) ), 'isError' => 'success' !== $status ) );
        }
        $this->store->update( $row['approval_id'], array(
            'result'            => $encoded,
            'executed_audit_id' => $executed_audit_id ? (int) $executed_audit_id : null,
        ) );
    }

    
    public function note_audit_id( array $row, $audit_id ) {
        if ( $audit_id && empty( $row['audit_id'] ) ) {
            $this->store->update( $row['approval_id'], array( 'audit_id' => (int) $audit_id ) );
        }
    }

    
    
    

    







    public function build_input_required( $id, array $row ) {
        $state = $this->sign_state( array(
            'op'  => $row['approval_id'],
            'sub' => self::principal( $row ),
            'h'   => $row['args_hash'],
            'exp' => $this->timestamp( $row['expires_at'] ),
        ) );
        return \Easy_MCP_AI\MCP\JSON_RPC::success_response( $id, array(
            'resultType'    => 'input_required',
            'inputRequests' => array(
                self::REQUEST_KEY => array(
                    'method' => 'elicitation/create',
                    'params' => array(
                        'mode'            => 'form',
                        'message'         => sprintf( 'Approval required: %s', $row['preview'] ),
                        'requestedSchema' => array(
                            'type'       => 'object',
                            'properties' => array(
                                'decision' => array(
                                    'type'        => 'string',
                                    'title'       => 'Decision',
                                    'description' => 'approve to run this operation, deny to refuse it',
                                    'enum'        => array( 'approve', 'deny' ),
                                ),
                            ),
                            'required'   => array( 'decision' ),
                        ),
                    ),
                ),
            ),
            'requestState'  => $state,
        ) );
    }

    





    public function build_approval_result( array $row, $issue_card_secret = false ) {
        $url  = self::approve_url( $row['approval_id'] );
        
        
        
        
        
        
        $card_secret = null;
        if ( $issue_card_secret && self::STATUS_PENDING === $row['status'] ) {
            $card_secret = bin2hex( random_bytes( 16 ) );
            $this->store->update( $row['approval_id'], array( 'card_secret_hash' => hash( 'sha256', $card_secret ) ) );
        }
        $text = sprintf(
            'APPROVAL REQUIRED (id %s). %s Ask the user to open %s and approve, then call this tool again with exactly the same arguments to run it. Do not report the operation as done. The request expires at %s UTC.',
            $row['approval_id'],
            $row['preview'],
            $url,
            $row['expires_at']
        );
        return array(
            'content' => array( array( 'type' => 'text', 'text' => $text ) ),
            'isError' => false,
            '_meta'   => array(
                self::META_KEY => array(
                    'id'          => $row['approval_id'],
                    'status'      => $row['status'],
                    'tool'        => $row['tool_name'],
                    'preview'     => $row['preview'],
                    'expires_at'  => $row['expires_at'],
                    'approve_url' => $url,
                    'card_secret' => $card_secret,
                ),
            ),
        );
    }

    







    public function decide_from_card( $approval_id, $secret, array $identity, $approve ) {
        $row = preg_match( '/^[0-9a-f-]{36}$/', (string) $approval_id ) ? $this->store->find( (string) $approval_id ) : null;
        if ( ! $row || ! $this->row_is_valid( $row, $identity ) ) {
            return array( 'ok' => false, 'message' => 'Unknown or expired approval, or it belongs to another credential.' );
        }
        if ( self::STATUS_PENDING !== $row['status'] ) {
            return array( 'ok' => false, 'message' => 'This approval was already decided.' );
        }
        if ( '' === (string) $row['card_secret_hash'] || ! hash_equals( (string) $row['card_secret_hash'], hash( 'sha256', (string) $secret ) ) ) {
            return array( 'ok' => false, 'message' => 'The card secret does not match this approval.' );
        }
        if ( ! $this->store->decide( $row['approval_id'], $approve ? self::STATUS_APPROVED : self::STATUS_DENIED, (int) $identity['wp_user_id'], 'card', $this->now_mysql() ) ) {
            return array( 'ok' => false, 'message' => 'This approval was decided elsewhere first.' );
        }
        return array( 'ok' => true, 'message' => $approve ? 'Approved; call the original tool again to run it.' : 'Denied.' );
    }

    public static function refusal_result( $reason, $message ) {
        return array(
            'content' => array( array( 'type' => 'text', 'text' => 'Not run: ' . $message ) ),
            'isError' => true,
            '_meta'   => array( self::META_KEY => array( 'status' => 'refused', 'reason' => $reason ) ),
        );
    }

    






    public static function approve_url( $approval_id ) {
        $base = (string) \home_url( '/' );
        return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'easy_mcp_ai_approve=' . rawurlencode( (string) $approval_id );
    }

    
    
    

    private function create_row( $tool, array $arguments, array $identity, $hash, $client_caps = null, $client_protocol = null ) {
        $preview = $this->preview( $tool, $arguments );
        $now     = $this->time();
        $caps    = is_array( $client_caps ) ? \wp_json_encode( $client_caps, JSON_UNESCAPED_SLASHES ) : null;
        $row     = array(
            'approval_id'     => self::uuid4(),
            'auth_source'     => in_array( $identity['auth_source'], array( 'legacy', 'oauth' ), true ) ? $identity['auth_source'] : 'legacy',
            'token_id'        => (int) $identity['token_id'],
            'oauth_client_id' => isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) && '' !== $identity['oauth_client_id'] ? $identity['oauth_client_id'] : null,
            'wp_user_id'      => (int) $identity['wp_user_id'],
            'tool_name'       => $tool->get_name(),
            
            
            'client_protocol'     => is_string( $client_protocol ) && '' !== $client_protocol ? substr( $client_protocol, 0, 16 ) : null,
            'client_capabilities' => is_string( $caps ) && strlen( $caps ) <= 4000 ? $caps : null,
            'arguments'       => \wp_json_encode( $arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'args_hash'       => $hash,
            'preview'         => $preview,
            'preview_hash'    => hash( 'sha256', $preview ),
            'status'          => self::STATUS_PENDING,
            'created_at'      => gmdate( 'Y-m-d H:i:s', $now ),
            'expires_at'      => gmdate( 'Y-m-d H:i:s', $now + self::TTL_SECONDS ),
        );
        $row['signature'] = self::sign_row( $row );
        return $this->store->create( $row ) ? $row : null;
    }

    
    private function row_is_valid( array $row, array $identity ) {
        if ( ! hash_equals( self::sign_row( $row ), (string) $row['signature'] ) ) {
            return false;
        }
        if ( self::principal( $row ) !== self::principal( $identity ) ) {
            return false;
        }
        if ( in_array( $row['status'], array( self::STATUS_PENDING, self::STATUS_APPROVED ), true ) && $this->timestamp( $row['expires_at'] ) <= $this->time() ) {
            return false;
        }
        return true;
    }

    






    public static function client_summary( array $row ) {
        $caps = isset( $row['client_capabilities'] ) && is_string( $row['client_capabilities'] ) ? json_decode( $row['client_capabilities'], true ) : null;
        if ( ! is_array( $caps ) ) {
            return '';
        }
        $elicit = array_key_exists( 'elicitation', $caps );
        $form   = $elicit && ( empty( $caps['elicitation'] ) || ( is_array( $caps['elicitation'] ) && array_key_exists( 'form', $caps['elicitation'] ) ) );
        if ( $form ) {
            return __( 'Your AI client offered an in-chat prompt for this first; this page is the fallback.', 'easy-mcp-ai' );
        }
        return __( 'Your AI client does not support in-chat approval prompts yet, so this page is used instead.', 'easy-mcp-ai' );
    }

    
    public static function row_signature_valid( array $row ) {
        return hash_equals( self::sign_row( $row ), (string) $row['signature'] );
    }

    public function row_expired( array $row ) {
        return $this->timestamp( $row['expires_at'] ) <= $this->time();
    }

    
    public function now() {
        return $this->time();
    }

    public function decide( array $row, $approve, $decided_by, $via ) {
        return $this->store->decide( $row['approval_id'], $approve ? self::STATUS_APPROVED : self::STATUS_DENIED, (int) $decided_by, $via, $this->now_mysql() );
    }

    




    public static function sign_row( array $row ) {
        $parts = array(
            (string) $row['approval_id'], (string) $row['auth_source'], (string) (int) $row['token_id'],
            isset( $row['oauth_client_id'] ) ? (string) $row['oauth_client_id'] : '', (string) (int) $row['wp_user_id'],
            (string) $row['tool_name'], (string) $row['args_hash'], (string) $row['created_at'],
        );
        return hash_hmac( 'sha256', implode( "\n", $parts ), Token_Keys::signing_key() );
    }

    public static function principal( array $identity ) {
        $client = isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) ? $identity['oauth_client_id'] : '';
        return sprintf( '%s:%d:%s:%d', isset( $identity['auth_source'] ) ? $identity['auth_source'] : 'legacy', (int) $identity['token_id'], $client, (int) $identity['wp_user_id'] );
    }

    
    public static function args_hash( $tool_name, array $arguments ) {
        return hash( 'sha256', (string) $tool_name . "\n" . self::canonical( $arguments ) );
    }

    private static function canonical( $value ) {
        if ( is_array( $value ) ) {
            $is_list = self::is_list( $value );
            if ( ! $is_list ) {
                ksort( $value, SORT_STRING );
            }
            $out = array();
            foreach ( $value as $k => $v ) {
                $out[] = ( $is_list ? '' : json_encode( (string) $k ) . ':' ) . self::canonical( $v );
            }
            return $is_list ? '[' . implode( ',', $out ) . ']' : '{' . implode( ',', $out ) . '}';
        }
        if ( is_object( $value ) ) {
            return self::canonical( (array) $value );
        }
        return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    





    private static function is_list( array $value ): bool {
        return array_keys( $value ) === range( 0, count( $value ) - 1 );
    }

    








    private static function sort_keys( array $value ): array {
        if ( ! self::is_list( $value ) ) {
            ksort( $value, SORT_STRING );
        }
        foreach ( $value as $key => $item ) {
            if ( is_array( $item ) ) {
                $value[ $key ] = self::sort_keys( $item );
            }
        }
        return $value;
    }

    





    public function preview( $tool, array $arguments ) {
        $arguments = self::sort_keys( $arguments );
        $text      = '';
        try {
            if ( method_exists( $tool, 'describe' ) ) {
                $text = (string) $tool->describe( $arguments );
            }
        } catch ( \Throwable $e ) {
            $text = '';
        }
        if ( '' === trim( $text ) ) {
            $text = Base_Tool::generic_description( $tool, $arguments );
        }
        $text = self::flatten( $text );
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr( $text, 0, self::PREVIEW_MAX );
        }
        return substr( $text, 0, self::PREVIEW_MAX );
    }

    
    public static function flatten( $text ) {
        return trim( (string) preg_replace( '/\s+/', ' ', (string) $text ) );
    }

    







    public static function preview_overflow( string $description ): ?int {
        $text   = self::flatten( $description );
        $length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );
        return $length > self::PREVIEW_MAX ? $length : null;
    }

    
    
    

    
    public function sign_state( array $payload ) {
        $body = \wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        return bin2hex( $body ) . '.' . hash_hmac( 'sha256', $body, Token_Keys::signing_key() );
    }

    
    public function verify_state( $state ) {
        if ( ! is_string( $state ) || false === strpos( $state, '.' ) ) {
            return null;
        }
        list( $hex, $mac ) = explode( '.', $state, 2 );
        if ( ! ctype_xdigit( $hex ) || 0 !== strlen( $hex ) % 2 ) {
            return null;
        }
        $body = hex2bin( $hex );
        if ( false === $body || ! hash_equals( hash_hmac( 'sha256', $body, Token_Keys::signing_key() ), (string) $mac ) ) {
            return null;
        }
        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) || empty( $payload['op'] ) || empty( $payload['sub'] ) || empty( $payload['h'] ) || empty( $payload['exp'] ) ) {
            return null;
        }
        if ( (int) $payload['exp'] <= $this->time() ) {
            return null;
        }
        return $payload;
    }

    
    
    

    private function is_unattended( array $identity ) {
        if ( 'legacy' !== $identity['auth_source'] || null === $this->token_unattended ) {
            return false;
        }
        try {
            return (bool) call_user_func( $this->token_unattended, (int) $identity['token_id'] );
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    
    private function offers_form( $client_caps, $client_protocol ) {
        return self::FORM_PROTOCOL === $client_protocol && $this->client_supports_form( $client_caps );
    }

    
    private function client_supports_form( $client_caps ) {
        if ( ! is_array( $client_caps ) || ! array_key_exists( 'elicitation', $client_caps ) ) {
            return false;
        }
        $elicit = $client_caps['elicitation'];
        if ( is_object( $elicit ) ) {
            $elicit = (array) $elicit;
        }
        if ( ! is_array( $elicit ) ) {
            return false;
        }
        return empty( $elicit ) || array_key_exists( 'form', $elicit );
    }

    private function time() {
        return (int) call_user_func( $this->now );
    }

    private function now_mysql() {
        return gmdate( 'Y-m-d H:i:s', $this->time() );
    }

    private function timestamp( $mysql ) {
        return (int) strtotime( (string) $mysql . ' UTC' );
    }

    
    public static function uuid4() {
        $b    = random_bytes( 16 );
        $b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 );
        $b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
    }
}
