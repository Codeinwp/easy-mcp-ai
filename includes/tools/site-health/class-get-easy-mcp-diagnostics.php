<?php
namespace Easy_MCP_AI\Tools\Site_Health;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Text_Redactor;
use Easy_MCP_AI\Diagnostics\Diagnostics;
use Easy_MCP_AI\Diagnostics\Diagnostic_Result;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


















class Get_Easy_Mcp_Diagnostics extends Base_Tool {

    public function get_name() {
        return 'wp_get_easy_mcp_diagnostics';
    }

    public function get_description() {
        return 'Get the Easy MCP AI self-diagnostics report (the Diagnostics card on the plugin dashboard): the plugin\'s own checks of authentication headers, sessions, database tables, tool visibility, plugin conflicts, configuration and environment. By default returns the cached report; `rerun` (default false) runs the full suite first, including the slower checks, exactly like the card\'s Re-run button, and stores the new report. Returns { generated_at (ISO 8601 UTC or null), stale, summary: { total, pass, warn, fail, unknown, deferred }, results: [{ id, status (PASS | WARN | FAIL | UNKNOWN), tier (blocker | warning | info), label, detail, fix, deferred }] }. A blocker-tier fail is what raises the plugin\'s admin banner. Requires manage_options.';
    }

    public function get_category() {
        return 'site_health';
    }

    public function get_required_capability() {
        return 'manage_options';
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

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'rerun' => array(
                    'type'        => 'boolean',
                    'default'     => false,
                    'description' => 'Run the full diagnostics suite now (slower; makes loopback requests to this site) instead of returning the cached report.',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $rerun = Site_Health_Guard::flag( $arguments, 'rerun', false );

        $this->load_diagnostics();

        if ( $rerun ) {
            
            
            
            if ( ! \current_user_can( 'manage_options' ) ) {
                throw new \RuntimeException( 'Re-running diagnostics requires the manage_options capability.' );
            }
            if ( ! Diagnostics::has_registered_checks() ) {
                Diagnostics::register_core_checks( $this->live_tool_registry() );
            }
            $results = Diagnostics::run( true );
        } else {
            $results = Diagnostics::cached();
        }

        $at   = Diagnostics::last_run_at();
        $rows = array();
        foreach ( $results as $r ) {
            if ( ! $r instanceof Diagnostic_Result ) {
                continue;
            }
            $evidence = $r->evidence();
            $rows[]   = array(
                'id'       => $r->id(),
                'status'   => $r->status(),
                'tier'     => $r->tier(),
                'label'    => $r->label(),
                'detail'   => Text_Redactor::redact( (string) $r->detail() ),
                'fix'      => Text_Redactor::redact( (string) $r->fix() ),
                'deferred' => is_array( $evidence ) && ! empty( $evidence['deferred'] ),
            );
        }

        $out = array(
            'generated_at' => ( null === $at || 0 === $at ) ? null : Site_Health_Guard::iso_utc( $at ),
            'stale'        => Diagnostics::is_stale(),
            'summary'      => Diagnostics::summary(),
            'results'      => $rows,
        );
        if ( empty( $rows ) ) {
            $out['note'] = 'No diagnostics report has been generated yet. Call again with rerun=true, or open the Easy MCP AI dashboard in wp-admin.';
        }
        return $out;
    }

    




    protected function load_diagnostics() {
        $dir = EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/';
        require_once $dir . 'class-diagnostic-result.php';
        require_once $dir . 'class-diagnostics.php';
        
        require_once $dir . 'class-check-notices.php';
        
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
    }

    






    protected function live_tool_registry() {
        return class_exists( '\\Easy_MCP_AI\\Plugin' ) ? \Easy_MCP_AI\Plugin::instance()->get_tool_registry() : null;
    }
}
