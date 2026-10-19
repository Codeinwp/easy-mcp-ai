<?php






namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Setup_State;
use Easy_MCP_AI\Admin\Site_Kind;
use Easy_MCP_AI\Admin\Support_Text;
use Easy_MCP_AI\Diagnostics\Blocker_Copy;
use Easy_MCP_AI\Diagnostics\Diagnostic_Result;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}





class Status_Controller extends Admin_Rest_Controller {

    



    const REACHABILITY_ID_PREFIX = 'a';

    
    private $results_provider;

    
    private $extras;

    






    public function __construct( callable $results_provider, array $extras = array() ) {
        $this->results_provider = $results_provider;
        $this->extras           = $extras;
    }

    public function register_routes() {
        $this->register_route( '/status', \WP_REST_Server::READABLE, array( $this, 'get_status' ) );
        if ( $this->has( 'deep_run' ) ) {
            $this->register_route( '/diagnostics/run', \WP_REST_Server::CREATABLE, array( $this, 'run_diagnostics' ) );
        }
        if ( $this->has( 'system_info' ) ) {
            $this->register_route( '/diagnostics/system-info', \WP_REST_Server::READABLE, array( $this, 'get_system_info' ) );
        }
        if ( $this->has( 'ai_prompt' ) ) {
            $this->register_route( '/diagnostics/ai-prompt', \WP_REST_Server::READABLE, array( $this, 'get_ai_prompt' ) );
        }
        if ( $this->has( 'write_auth_rule' ) ) {
            $this->register_route( '/diagnostics/auth-rule', \WP_REST_Server::CREATABLE, array( $this, 'write_auth_rule' ) );
        }
        if ( $this->has( 'write_plain_routes' ) ) {
            $this->register_route( '/diagnostics/plain-routes', \WP_REST_Server::CREATABLE, array( $this, 'write_plain_routes' ) );
        }
    }

    private function has( $name ) {
        return isset( $this->extras[ $name ] ) && is_callable( $this->extras[ $name ] );
    }

    private function call( $name, ...$args ) {
        return $this->has( $name ) ? call_user_func_array( $this->extras[ $name ], $args ) : null;
    }

    



    public function get_status( $request ) {
        return $this->ok( $this->payload( call_user_func( $this->results_provider ) ) );
    }

    










    public function run_diagnostics( $request ) {
        $scope = 'connection' === $request->get_param( 'scope' ) ? 'connection' : 'all';
        return $this->ok( $this->payload( $this->call( 'deep_run', $scope ) ) );
    }

    



    public function get_system_info( $request ) {
        return $this->ok( array( 'text' => (string) $this->call( 'system_info' ) ) );
    }

    



    public function get_ai_prompt( $request ) {
        return $this->ok( array( 'text' => (string) $this->call( 'ai_prompt' ) ) );
    }

    







    public function write_auth_rule( $request ) {
        return $this->htaccess_write(
            'write_auth_rule',
            'auth_rule',
            array( 'auth_rule_refused' => array( 'easy_mcp_ai_auth_rule_refused', self::network_refusal(), 403 ) )
        );
    }

    







    public function write_plain_routes( $request ) {
        return $this->htaccess_write(
            'write_plain_routes',
            'plain_routes',
            array(
                'plain_routes_refused'        => array( 'easy_mcp_ai_plain_routes_refused', self::network_refusal(), 403 ),
                'plain_routes_not_applicable' => array(
                    'easy_mcp_ai_plain_routes_not_applicable',
                    \__( 'Permalinks are no longer Plain on this site, so there are no rules to add. Reload the page.', 'easy-mcp-ai' ),
                    409,
                ),
            )
        );
    }

    










    private function htaccess_write( string $extra, string $prefix, array $refusals ) {
        $outcome = (string) $this->call( $extra );
        if ( isset( $refusals[ $outcome ] ) ) {
            list( $code, $message, $status ) = $refusals[ $outcome ];
            return $this->fail( $code, $message, $status );
        }
        $outcomes = array(
            $prefix . '_written'  => 'written',
            $prefix . '_reverted' => 'reverted',
        );
        return $this->ok( array(
            'outcome' => isset( $outcomes[ $outcome ] ) ? $outcomes[ $outcome ] : 'failed',
            'status'  => $this->payload( call_user_func( $this->results_provider ) ),
        ) );
    }

    
    private static function network_refusal(): string {
        return \__( 'Not permitted: on a network only a super administrator can change .htaccess.', 'easy-mcp-ai' );
    }

    private function payload( $results ) {
        if ( ! is_array( $results ) ) {
            $results = array();
        }
        $actions = array();
        if ( $this->has( 'check_action' ) ) {
            foreach ( $results as $r ) {
                if ( $r instanceof Diagnostic_Result && $r->is_problem() ) {
                    $action = call_user_func( $this->extras['check_action'], $r );
                    if ( is_array( $action ) ) {
                        $actions[ $r->id() ] = $action;
                    }
                }
            }
        }
        $last_run = $this->call( 'last_run' );
        $endpoint = \rest_url( 'easy-mcp-ai/v1/mcp' );
        $version  = defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '';
        return self::build_payload(
            $results,
            Site_Kind::detect( \home_url() ),
            $version,
            is_numeric( $last_run ) ? (int) $last_run : null,
            array(
                'externalTestUrl' => Support_Text::external_test_url( $endpoint, \home_url() ),
                'mailto'          => Support_Text::mailto( \home_url(), $version ),
            ),
            
            true === \Easy_MCP_AI\Config::get( 'paused' ),
            Setup_State::is_complete(),
            \Easy_MCP_AI\Config::is_locked( 'paused' ) ? \Easy_MCP_AI\Config::constant_name( 'paused' ) : null,
            $actions,
            \Easy_MCP_AI\Config_Admin::invalid_settings(),
            \Easy_MCP_AI\Config::source( 'paused' ),
            true === $this->call( 'connection_due' )
        );
    }

    

















    public static function build_payload( array $results, $site_kind, $version, $last_run_at = null, array $support = array(), $paused = false, $setup_complete = false, $paused_locked = null, array $actions = array(), array $invalid_settings = array(), $paused_locked_source = '', $connection_checks_due = false ) {
        $checks   = array();
        $problems = array();
        $blockers = array();
        $live     = true;
        $counts   = array( 'total' => 0, 'passed' => 0, 'warned' => 0, 'failed' => 0, 'skipped' => 0 );

        foreach ( $results as $index => $r ) {
            if ( ! $r instanceof Diagnostic_Result ) {
                continue;
            }
            $checks[] = array(
                'id'     => $r->id(),
                'label'  => $r->label(),
                'status' => $r->status(),
                'tier'   => $r->tier(),
                'fix'    => $r->fix(),
                'note'   => (string) $r->detail(),
                'action' => isset( $actions[ $r->id() ] ) ? $actions[ $r->id() ] : null,
                
                
                'measuredAt' => null === $r->measured_at() ? null : gmdate( 'Y-m-d\TH:i:s\Z', (int) $r->measured_at() ),
            );
            $counts['total']++;
            switch ( $r->status() ) {
                case Diagnostic_Result::STATUS_PASS:
                    $counts['passed']++;
                    break;
                case Diagnostic_Result::STATUS_WARN:
                    $counts['warned']++;
                    break;
                case Diagnostic_Result::STATUS_FAIL:
                    $counts['failed']++;
                    break;
                default:
                    
                    $counts['skipped']++;
            }
            if ( ! $r->is_problem() ) {
                continue;
            }
            $problems[] = array( $index, $r );
            if ( $r->renders_in_notice() ) {
                $blockers[] = $r;
            }
            if ( Diagnostic_Result::TIER_BLOCKER === $r->tier()
                && 0 === strpos( $r->id(), self::REACHABILITY_ID_PREFIX ) ) {
                $live = false;
            }
        }

        $top_fix = null;
        $top     = self::most_severe( $problems );
        if ( null !== $top ) {
            $top_fix = array(
                'id'    => $top->id(),
                'label' => $top->label(),
                'fix'   => $top->fix(),
            );
        }

        return array(
            'live'            => $live,
            'topFix'          => $top_fix,
            'checks'          => $checks,
            'siteKind'        => $site_kind,
            'paused'          => (bool) $paused,
            'pausedLocked'    => is_string( $paused_locked ) && '' !== $paused_locked ? $paused_locked : null,
            'pausedLockedSource' => is_string( $paused_locked ) && '' !== $paused_locked && in_array( $paused_locked_source, array( 'constant', 'environment' ), true ) ? $paused_locked_source : null,
            'setupComplete'   => (bool) $setup_complete,
            'version'         => (string) $version,
            'lastRun'         => null === $last_run_at || (int) $last_run_at <= 0 ? null : gmdate( 'Y-m-d\TH:i:s\Z', (int) $last_run_at ),
            'counts'          => $counts,
            'connectionChecksDue' => (bool) $connection_checks_due,
            'blockers'        => array_map( array( Blocker_Copy::class, 'for_result' ), Blocker_Copy::sort( $blockers ) ),
            'invalidSettings' => array_values( array_map( 'strval', $invalid_settings ) ),
            'support'         => array(
                'externalTestUrl' => isset( $support['externalTestUrl'] ) ? (string) $support['externalTestUrl'] : '',
                'mailto'          => isset( $support['mailto'] ) ? (string) $support['mailto'] : '',
            ),
        );
    }

    






    private static function most_severe( array $problems ) {
        if ( empty( $problems ) ) {
            return null;
        }
        usort( $problems, function ( $a, $b ) {
            $rank = self::severity_rank( $a[1] ) - self::severity_rank( $b[1] );
            return 0 !== $rank ? $rank : $a[0] - $b[0];
        } );
        return $problems[0][1];
    }

    private static function severity_rank( Diagnostic_Result $r ) {
        $tiers = array(
            Diagnostic_Result::TIER_BLOCKER => 0,
            Diagnostic_Result::TIER_WARNING => 1,
            Diagnostic_Result::TIER_INFO    => 2,
        );
        $tier = isset( $tiers[ $r->tier() ] ) ? $tiers[ $r->tier() ] : 3;
        return $tier * 2 + ( Diagnostic_Result::STATUS_FAIL === $r->status() ? 0 : 1 );
    }
}
