<?php
namespace Easy_MCP_AI\Cli_Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}















































final class Context {

    
    const SELF_CALL = 'wp_cli_run does not call itself here: only a command its route splits into parts runs wp_cli_run, for its own part, and a part cannot split again. Run the commands one at a time.';

    
    const UNPLANNED = 'wp_cli_run stopped before calling %1$s: the preview a person approved listed %2$s, and an approval covers only the calls its preview showed. What this command reads changed after the preview was made (an earlier call of the same command can do that). Nothing after this point ran; run the command again to see and approve what is left.';

    
    public $out;

    
    private $dispatcher;

    
    private $may_use;

    
    private $tool_status;

    
    private $skip_inner_approval;

    
    private $approved_calls = null;

    
    private $planned_calls = array();

    
    private $changed = false;

    
    private $calls = array();

    
    private $pending_approval = null;

    
    private $not_run = array();

    
    private $notes = array();

    
    private $not_captured = array();

    
    private $redacted = array();

    
    private $missing_target = null;

    




    public function __construct( Output $out, ?callable $dispatcher = null, ?callable $may_use = null, bool $skip_inner_approval = false, ?callable $tool_status = null ) {
        $this->out                 = $out;
        $this->dispatcher          = $dispatcher ? $dispatcher : array( self::class, 'call_uncounted' );
        $this->may_use             = $may_use ? $may_use : array( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_may_use' );
        $this->skip_inner_approval = $skip_inner_approval;
        $this->tool_status         = $tool_status ? $tool_status : array( '\\Easy_MCP_AI\\MCP\\Server', 'current_call_tool_status' );
    }

    






    public static function call_uncounted( string $tool, array $arguments, ?string $approval = null ): ?array {
        return \Easy_MCP_AI\MCP\Server::call_as_current_caller( $tool, $arguments, $approval, false );
    }

    














    public function delegate( string $tool, array $arguments ): array {
        if ( 'wp_cli_run' === $tool && ! Router::in_split_frame() ) {
            $this->out->error( self::SELF_CALL );
        }
        $dispatched = call_user_func( $this->dispatcher, $tool, $arguments, $this->approval_mode( $tool ) );
        if ( ! is_array( $dispatched ) ) {
            $this->out->error( 'wp_cli_run can run ' . $tool . ' only inside an MCP tools/call.' );
        }
        $kind          = (string) ( $dispatched['approval']['kind'] ?? 'run' );
        $audit_id      = isset( $dispatched['audit_id'] ) ? (int) $dispatched['audit_id'] : null;
        $this->calls[] = array(
            'tool'     => $tool,
            'audit_id' => $audit_id,
            'approval' => $kind,
        );
        if ( 'paused' === $kind ) {
            $this->pause( $tool, $dispatched );
        }
        return self::delegated( is_array( $dispatched['response'] ?? null ) ? $dispatched['response'] : array(), $audit_id, $kind );
    }

    






    private function pause( string $tool, array $dispatched ): void {
        $approval_id            = isset( $dispatched['approval']['approval_id'] ) ? (string) $dispatched['approval']['approval_id'] : null;
        $this->pending_approval = array(
            'tool'        => $tool,
            'approval_id' => $approval_id,
            'approve_url' => ( null !== $approval_id && class_exists( '\\Easy_MCP_AI\\Approvals\\Approval_Gate' ) ) ? \Easy_MCP_AI\Approvals\Approval_Gate::approve_url( $approval_id ) : null,
        );
        $this->out->error( $tool . " needs a person's approval on this site. Approve it, then run the same command again." );
    }

    






    private static function delegated( array $response, ?int $audit_id, string $kind ): array {
        $error = self::error_of( $response );
        return array(
            'ok'       => null === $error,
            'payload'  => null === $error ? self::payload_of( $response ) : array(),
            'error'    => $error,
            'details'  => null === $error ? null : self::details_of( $response ),
            'audit_id' => $audit_id,
            'approval' => $kind,
        );
    }

    





    public function approved_plan( array $plan ): void {
        $counts = array();
        foreach ( $plan as $call ) {
            $tool            = is_array( $call ) && isset( $call['tool'] ) ? (string) $call['tool'] : '';
            $counts[ $tool ] = ( isset( $counts[ $tool ] ) ? $counts[ $tool ] : 0 ) + 1;
        }
        $this->approved_calls = $counts;
        $this->planned_calls  = $counts;
    }

    







    private function approval_mode( string $tool ): ?string {
        if ( ! $this->skip_inner_approval || ! $this->approved_calls ) {
            return null;
        }
        if ( ! empty( $this->approved_calls[ $tool ] ) ) {
            --$this->approved_calls[ $tool ];
            return 'skip';
        }
        $planned = isset( $this->planned_calls[ $tool ] ) ? $this->planned_calls[ $tool ] : 0;
        $listed  = 0 === $planned
            ? 'no call to ' . $tool
            : sprintf( '%d %s to %s, all made', $planned, 1 === $planned ? 'call' : 'calls', $tool );
        $this->out->error( sprintf( self::UNPLANNED, $tool, $listed ) );
        return null; 
    }

    






    public function merge_part( Context $part ): void {
        foreach ( $part->notes as $note ) {
            $this->note( $note );
        }
        foreach ( $part->not_captured as $phrase ) {
            $this->not_captured( $phrase );
        }
        foreach ( $part->redacted as $field ) {
            $this->redacted( $field );
        }
        foreach ( $part->calls as $call ) {
            $this->calls[] = $call;
        }
        foreach ( $part->not_run as $item ) {
            $this->not_run[] = $item;
        }
        $this->merge_outcome( $part );
    }

    




    private function merge_outcome( Context $part ): void {
        if ( $part->changed ) {
            $this->changed = true;
        }
        if ( null === $this->pending_approval && null !== $part->pending_approval ) {
            $this->pending_approval = $part->pending_approval;
        }
        if ( null === $this->missing_target && null !== $part->missing_target ) {
            $this->missing_target = $part->missing_target;
        }
    }

    




    public function may_use( string $tool ): bool {
        return true === call_user_func( $this->may_use, $tool );
    }

    






    public function tool_status( string $tool ): ?array {
        if ( ! is_callable( $this->tool_status ) ) {
            return null;
        }
        $status = call_user_func( $this->tool_status, $tool );
        return is_array( $status ) && isset( $status['status'] ) ? $status : null;
    }

    
    public function mark_changed(): void {
        $this->changed = true;
    }

    
    public function changed(): bool {
        return $this->changed;
    }

    
    public function not_run( string $item ): void {
        $this->not_run[] = $item;
    }

    
    public function note( string $text ): void {
        $this->notes[] = $text;
    }

    
    public function not_captured( string $phrase ): void {
        if ( ! in_array( $phrase, $this->not_captured, true ) ) {
            $this->not_captured[] = $phrase;
        }
    }

    
    public function redacted( string $field ): void {
        if ( ! in_array( $field, $this->redacted, true ) ) {
            $this->redacted[] = $field;
        }
    }

    






    public function fail_missing_target( string $message ): void {
        $this->missing_target = $message;
    }

    






    public function require_cap( string $cap, int ...$args ): void {
        if ( ! \current_user_can( $cap, ...$args ) ) {
            $this->out->error( sprintf( 'Sorry, you are not allowed to do this: wp_cli_run needs the %s capability for this command.', $cap ) );
        }
    }

    






    public function require_network( string $network_cap ): void {
        if ( ! \is_multisite() ) {
            return;
        }
        if ( ! \is_super_admin() || ! \current_user_can( $network_cap ) ) {
            $this->out->error( sprintf( 'Sorry, you are not allowed to do this: on a network this command needs a super admin with the %s capability.', $network_cap ) );
        }
    }

    
    public function user_id(): int {
        return (int) \get_current_user_id();
    }

    
    public function calls(): array {
        return $this->calls;
    }

    
    public function pending_approval(): ?array {
        return $this->pending_approval;
    }

    
    public function not_run_items(): array {
        return $this->not_run;
    }

    
    public function notes(): array {
        return $this->notes;
    }

    
    public function not_captured_phrases(): array {
        return $this->not_captured;
    }

    
    public function redacted_fields(): array {
        return $this->redacted;
    }

    
    public function missing_target(): ?string {
        return $this->missing_target;
    }

    





    public static function error_of( array $response ): ?string {
        if ( isset( $response['error'] ) ) {
            return (string) ( $response['error']['message'] ?? 'the call was refused' );
        }
        $result = $response['result'] ?? array();
        if ( ! empty( $result['isError'] ) ) {
            $text = (string) ( $result['content'][0]['text'] ?? 'the tool reported an error' );
            return 0 === strpos( $text, 'Error: ' ) ? substr( $text, 7 ) : $text;
        }
        return null;
    }

    





    public static function details_of( array $response ): ?string {
        $text = $response['result']['content'][1]['text'] ?? null;
        return is_string( $text ) ? $text : null;
    }

    





    public static function payload_of( array $response ): array {
        $result = $response['result'] ?? array();
        if ( isset( $result['structuredContent'] ) && is_array( $result['structuredContent'] ) ) {
            return $result['structuredContent'];
        }
        $decoded = json_decode( (string) ( $result['content'][0]['text'] ?? '' ), true );
        return is_array( $decoded ) ? $decoded : array();
    }
}
