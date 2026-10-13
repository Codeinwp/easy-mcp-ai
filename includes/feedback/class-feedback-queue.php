<?php
namespace Easy_MCP_AI\Feedback;

use Easy_MCP_AI\Approvals\Approval_Gate;
use Easy_MCP_AI\Approvals\Approval_Store;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















class Feedback_Queue {

    
    const ROW_TOOL = '_feedback_report';
    
    const TTL_SECONDS = 1209600; 
    



    const MAX_PENDING = 10;
    
    const REPLACED = 'replaced';
    const SUMMARY_MIN = 10;
    const SUMMARY_MAX = 200;
    const DETAILS_MAX = 1500;

    
    const ENDPOINT = 'https://api.themeisle.com/tracking/feedback';
    const SLUG     = 'easy-mcp-ai';
    
    const AREA     = 'agent';

    
    const KINDS = array(
        'missing_feature' => 'Missing feature',
        'bug'             => 'Bug',
    );

    
    private $store;
    
    private $now;

    



    public function __construct( Approval_Store $store, ?callable $now = null ) {
        $this->store = $store;
        $this->now   = is_callable( $now ) ? $now : 'time';
    }

    
    public static function load(): void {
        
        
        
        $root = dirname( __DIR__ ) . '/';
        require_once $root . 'auth/class-token-keys.php';
        require_once $root . 'approvals/interface-approval-store.php';
        require_once $root . 'approvals/class-approval-schema.php';
        require_once $root . 'approvals/class-wpdb-approval-store.php';
        require_once $root . 'approvals/class-approval-gate.php';
        if ( ! class_exists( '\\Easy_MCP_AI\\Text_Redactor' ) ) {
            require_once $root . 'class-text-redactor.php';
        }
        if ( ! class_exists( '\\Easy_MCP_AI\\Site_Host' ) ) {
            require_once $root . 'class-site-host.php';
        }
        require_once __DIR__ . '/interface-feedback-report-store.php';
        require_once __DIR__ . '/class-wpdb-feedback-store.php';
    }

    
    public static function create(): self {
        self::load();
        return new self( new Wpdb_Feedback_Store() );
    }

    
    
    

    









    public static function compose( $kind, $summary, $details = '' ): array {
        $kind = is_string( $kind ) ? $kind : '';
        if ( ! isset( self::KINDS[ $kind ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'kind must be one of: %s.', implode( ', ', array_keys( self::KINDS ) ) ) );
        }
        $summary = self::clean( is_string( $summary ) ? $summary : '', true );
        $details = self::clean( is_string( $details ) ? $details : '', false );
        $length  = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
        if ( $length( $summary ) < self::SUMMARY_MIN ) {
            throw new \InvalidArgumentException( sprintf( 'summary must be at least %d characters: say what the user wanted to do.', self::SUMMARY_MIN ) );
        }
        if ( $length( $summary ) > self::SUMMARY_MAX ) {
            throw new \InvalidArgumentException( sprintf( 'summary must be at most %d characters; put the rest in details.', self::SUMMARY_MAX ) );
        }
        if ( $length( $details ) > self::DETAILS_MAX ) {
            throw new \InvalidArgumentException( sprintf( 'details must be at most %d characters.', self::DETAILS_MAX ) );
        }
        $text = '[' . self::KINDS[ $kind ] . '] ' . $summary . ( '' !== $details ? "\n\n" . $details : '' );
        return array(
            'text'      => $text,
            'arguments' => array( 'kind' => $kind, 'summary' => $summary, 'details' => $details ),
        );
    }

    




    public static function clean( string $text, bool $single_line ): string {
        $text = \wp_strip_all_tags( (string) $text );
        $text = str_replace( array( "\r\n", "\r" ), "\n", $text );
        
        $text = preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]/u', ' ', $text );
        $text = \Easy_MCP_AI\Text_Redactor::redact( (string) $text );
        $text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', (string) $text );
        $host = class_exists( '\\Easy_MCP_AI\\Site_Host' ) ? \Easy_MCP_AI\Site_Host::current() : '';
        if ( '' !== $host ) {
            $text = preg_replace( '/(?<![A-Za-z0-9.\-])(?:www\.)*' . preg_quote( $host, '/' ) . '(?![A-Za-z0-9\-])/i', '[site]', (string) $text );
        }
        if ( $single_line ) {
            $text = preg_replace( '/\s+/u', ' ', (string) $text );
        } else {
            $text = preg_replace( "/[ \t]+/", ' ', (string) $text );
            $text = preg_replace( "/\n{3,}/", "\n\n", (string) $text );
        }
        return trim( (string) $text );
    }

    
    public static function text_hash( string $text ): string {
        $normal = strtolower( preg_replace( '/\s+/u', ' ', trim( (string) $text ) ) );
        return hash( 'sha256', self::ROW_TOOL . "\n" . $normal );
    }

    








    public function queue( array $identity, $kind, $summary, $details = '' ): array {
        $composed = self::compose( $kind, $summary, $details );
        if ( ! $this->store instanceof Feedback_Report_Store ) {
            throw new \RuntimeException( 'The report queue is not available on this site, so nothing was stored or sent.' );
        }
        $now  = $this->time();
        $hash = self::text_hash( $composed['text'] );

        $existing = $this->store->find_pending_by_hash( $identity, $hash, $this->mysql( $now ) );
        if ( is_array( $existing ) && Approval_Gate::row_signature_valid( $existing ) ) {
            return $this->reply( $existing, false, $composed['arguments']['kind'] );
        }

        $row = array(
            'approval_id'     => Approval_Gate::uuid4(),
            'auth_source'     => isset( $identity['auth_source'] ) && 'oauth' === $identity['auth_source'] ? 'oauth' : 'legacy',
            'token_id'        => isset( $identity['token_id'] ) ? (int) $identity['token_id'] : 0,
            'oauth_client_id' => isset( $identity['oauth_client_id'] ) && is_string( $identity['oauth_client_id'] ) && '' !== $identity['oauth_client_id'] ? $identity['oauth_client_id'] : null,
            'wp_user_id'      => isset( $identity['wp_user_id'] ) ? (int) $identity['wp_user_id'] : 0,
            'tool_name'       => self::ROW_TOOL,
            'arguments'       => \wp_json_encode( $composed['arguments'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
            'args_hash'       => $hash,
            'preview'         => $composed['text'],
            'preview_hash'    => hash( 'sha256', $composed['text'] ),
            'status'          => Approval_Gate::STATUS_PENDING,
            'created_at'      => $this->mysql( $now ),
            'expires_at'      => $this->mysql( $now + self::TTL_SECONDS ),
        );
        $row['signature'] = Approval_Gate::sign_row( $row );
        if ( ! $this->store->create( $row ) ) {
            throw new \RuntimeException( 'The report could not be stored, so nothing was queued or sent. Try again shortly.' );
        }
        
        
        $this->make_room( $now );
        return $this->reply( $row, true, $composed['arguments']['kind'] );
    }

    













    private function make_room( int $now ): void {
        $waiting = $this->store->list_pending( $this->mysql( $now ), 100 );
        foreach ( array_slice( (array) $waiting, self::MAX_PENDING ) as $old ) {
            if ( is_array( $old ) && isset( $old['approval_id'] )
                && $this->store->decide( $old['approval_id'], Approval_Gate::STATUS_EXPIRED, 0, self::REPLACED, $this->mysql( $now ) ) ) {
                $this->store->delete_report( $old['approval_id'] );
            }
        }
    }

    





    private function reply( array $row, bool $queued, string $kind ): array {
        $url     = Approval_Gate::approve_url( $row['approval_id'] );
        $opening = $queued
            ? 'Queued for approval; nothing has been sent yet.'
            : 'This report is already waiting for approval; nothing new was queued and nothing has been sent.';
        $policy  = 'missing_feature' === $kind
            ? ' Per the authors\' policy, a requested feature is added within 1-2 weeks once its report is approved.'
            : ' The authors review every approved report.';
        return array(
            'queued'         => (bool) $queued,
            'already_queued' => ! $queued,
            'sent'           => false,
            'status'         => 'awaiting_approval',
            'report_id'      => (string) $row['approval_id'],
            'report'         => (string) $row['preview'],
            'approve_url'    => $url,
            'expires_at'     => (string) $row['expires_at'],
            'message'        => $opening
                . ' A person must approve this exact text before it goes to the plugin\'s authors.'
                . ' If the user is present, give them this link to review it: ' . $url . '.'
                . ' Otherwise carry on with your task; the report waits up to 14 days and is never sent without approval.'
                . $policy,
        );
    }

    
    
    

    




    public function find_report( string $approval_id ): ?array {
        if ( ! preg_match( '/^[0-9a-f-]{36}$/', (string) $approval_id ) ) {
            return null;
        }
        $row = $this->store->find( (string) $approval_id );
        if ( ! is_array( $row ) || self::ROW_TOOL !== $row['tool_name'] || ! Approval_Gate::row_signature_valid( $row ) ) {
            return null;
        }
        return $row;
    }

    




    public static function may_decide( array $row ): bool {
        $user = (int) \get_current_user_id();
        return ( $user > 0 && $user === (int) $row['wp_user_id'] ) || \current_user_can( 'manage_options' );
    }

    
    public function is_expired( array $row ): bool {
        return (int) strtotime( (string) $row['expires_at'] . ' UTC' ) <= $this->time();
    }

    








    public function mark_sent( string $approval_id, int $decided_by, string $via ): array {
        $row = $this->find_report( $approval_id );
        $out = $this->undecidable( $row );
        if ( null !== $out ) {
            return $out;
        }
        $now = $this->mysql( $this->time() );
        if ( ! $this->store->decide( $row['approval_id'], Approval_Gate::STATUS_APPROVED, (int) $decided_by, (string) $via, $now ) ) {
            return array( 'ok' => false, 'status' => 'decided', 'message' => \__( 'This report was already decided elsewhere.', 'easy-mcp-ai' ) );
        }
        $this->store->update( $row['approval_id'], array(
            'status'      => Approval_Gate::STATUS_CONSUMED,
            'consumed_at' => $now,
            'arguments'   => null,
            'result'      => \wp_json_encode( array( 'sent' => true, 'from' => 'browser' ) ),
        ) );
        return array( 'ok' => true, 'status' => 'sent', 'message' => \__( 'Sent. Thank you, we will take it from here.', 'easy-mcp-ai' ) );
    }

    





    public function deny( string $approval_id, int $decided_by, string $via ): array {
        $row = $this->find_report( $approval_id );
        $out = $this->undecidable( $row );
        if ( null !== $out ) {
            return $out;
        }
        if ( ! $this->store->decide( $row['approval_id'], Approval_Gate::STATUS_DENIED, (int) $decided_by, (string) $via, $this->mysql( $this->time() ) ) ) {
            return array( 'ok' => false, 'status' => 'decided', 'message' => \__( 'This report was already decided elsewhere.', 'easy-mcp-ai' ) );
        }
        $this->store->update( $row['approval_id'], array( 'arguments' => null ) );
        return array( 'ok' => true, 'status' => 'denied', 'message' => \__( 'Discarded. Nothing was sent.', 'easy-mcp-ai' ) );
    }

    





    private function undecidable( ?array $row ): ?array {
        if ( ! is_array( $row ) ) {
            return array( 'ok' => false, 'status' => 'not_found', 'message' => \__( 'This report does not exist. It may have been cleaned up.', 'easy-mcp-ai' ) );
        }
        if ( Approval_Gate::STATUS_PENDING !== $row['status'] ) {
            return array( 'ok' => false, 'status' => 'decided', 'message' => self::status_sentence( $row ) );
        }
        if ( $this->is_expired( $row ) ) {
            return array( 'ok' => false, 'status' => 'expired', 'message' => \__( 'This report expired without being sent.', 'easy-mcp-ai' ) );
        }
        return null;
    }

    
    public static function status_sentence( array $row ): string {
        switch ( $row['status'] ) {
            case Approval_Gate::STATUS_CONSUMED:
                return \__( 'This report was approved and sent.', 'easy-mcp-ai' );
            case Approval_Gate::STATUS_DENIED:
                return \__( 'This report was discarded. Nothing was sent.', 'easy-mcp-ai' );
            case Approval_Gate::STATUS_APPROVED:
                return \__( 'This report is being sent.', 'easy-mcp-ai' );
            default:
                return \__( 'This report expired without being sent.', 'easy-mcp-ai' );
        }
    }

    
    
    

    





    public static function payload( array $row ): array {
        return array(
            'slug'     => self::SLUG,
            'version'  => defined( 'EASY_MCP_AI_VERSION' ) ? (string) EASY_MCP_AI_VERSION : '',
            'feedback' => (string) $row['preview'],
            'data'     => array( 'feedback-area' => self::AREA ),
        );
    }

    
    public static function privacy_sentence(): string {
        return \__( 'Only this text and the plugin version reach us. Your browser sends it, the same way as the Share feedback form; no e-mail or user details are included.', 'easy-mcp-ai' );
    }

    




    public static function kind_of( array $row ): string {
        $arguments = isset( $row['arguments'] ) && is_string( $row['arguments'] ) ? json_decode( $row['arguments'], true ) : null;
        if ( is_array( $arguments ) && isset( $arguments['kind'], self::KINDS[ $arguments['kind'] ] ) ) {
            return $arguments['kind'];
        }
        return 0 === strpos( (string) ( $row['preview'] ?? '' ), '[' . self::KINDS['bug'] . ']' ) ? 'bug' : 'missing_feature';
    }

    
    public static function send_label( string $kind ): string {
        return 'bug' === $kind ? \__( 'Send for a fix', 'easy-mcp-ai' ) : \__( 'Send for development', 'easy-mcp-ai' );
    }

    
    
    

    




    public function pending( int $limit = 50 ): array {
        if ( ! $this->store instanceof Feedback_Report_Store ) {
            return array();
        }
        $rows = $this->store->list_pending( $this->mysql( $this->time() ), (int) $limit );
        return array_values( array_filter( (array) $rows, static function ( $row ) {
            return is_array( $row ) && Approval_Gate::row_signature_valid( $row );
        } ) );
    }

    






    public function instructions_line( array $identity ): string {
        if ( ! $this->store instanceof Feedback_Report_Store ) {
            return '';
        }
        $rows = $this->store->list_pending_for( $identity, $this->mysql( $this->time() ), 20 );
        $rows = array_values( array_filter( (array) $rows, static function ( $row ) {
            return is_array( $row ) && Approval_Gate::row_signature_valid( $row );
        } ) );
        if ( empty( $rows ) ) {
            return '';
        }
        $links = array();
        foreach ( array_slice( $rows, 0, 3 ) as $row ) {
            $links[] = Approval_Gate::approve_url( $row['approval_id'] );
        }
        return sprintf(
            '%d feedback report(s) you queued earlier are waiting for a person to approve them before they are sent to the plugin\'s authors: %s. If the user is present, mention them; nothing is sent without approval.',
            count( $rows ),
            implode( ', ', $links )
        );
    }

    private function time(): int {
        return (int) call_user_func( $this->now );
    }

    private function mysql( int $timestamp ): string {
        return gmdate( 'Y-m-d H:i:s', (int) $timestamp );
    }
}
