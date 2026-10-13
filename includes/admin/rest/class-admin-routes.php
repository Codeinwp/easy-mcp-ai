<?php








namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Support_Text;
use Easy_MCP_AI\Diagnostics\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Admin_Routes {

    
    private $tool_registry;

    
    private static $checks_registered = false;

    



    public function __construct( $tool_registry = null ) {
        $this->tool_registry = $tool_registry;
    }

    public function register() {
        require_once __DIR__ . '/class-admin-rest-controller.php';
        require_once __DIR__ . '/class-status-controller.php';
        require_once __DIR__ . '/class-dashboard-controller.php';
        require_once __DIR__ . '/class-tools-controller.php';
        require_once __DIR__ . '/class-settings-controller.php';
        require_once __DIR__ . '/class-tokens-controller.php';
        require_once dirname( __DIR__ ) . '/class-access-presets.php';
        require_once dirname( __DIR__ ) . '/class-site-kind.php';
        require_once dirname( __DIR__ ) . '/class-setup-state.php';
        require_once dirname( __DIR__ ) . '/class-support-text.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/auth/class-token-manager.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/history/class-change-log-repository.php';

        $status = new Status_Controller(
            array( $this, 'diagnostics_results' ),
            array(
                'last_run'    => array( $this, 'diagnostics_last_run' ),
                'deep_run'    => array( $this, 'diagnostics_run_deep' ),
                'connection_due' => array( $this, 'diagnostics_connection_due' ),
                'system_info' => array( $this, 'system_info_text' ),
                'ai_prompt'   => array( $this, 'ai_prompt_text' ),
                'check_action'    => array( $this, 'diagnostics_check_action' ),
                'write_auth_rule' => array( $this, 'write_auth_rule' ),
                'write_plain_routes' => array( $this, 'write_plain_routes' ),
            )
        );
        $status->register_routes();

        global $wpdb;
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-audit-log-repository.php';
        $dashboard = new Dashboard_Controller(
            $wpdb,
            new \Easy_MCP_AI\History\Change_Log_Repository(),
            new \Easy_MCP_AI\Auth\Token_Manager(),
            $this->tool_registry
        );
        $dashboard->register_routes();

        $catalogue = null;
        if ( $this->tool_registry ) {
            require_once dirname( __DIR__ ) . '/class-tool-catalogue.php';
            $catalogue = new \Easy_MCP_AI\Admin\Tool_Catalogue( $this->tool_registry );
        }
        $tools = new Tools_Controller( array( $this, 'tool_groups' ), $catalogue );
        $tools->register_routes();

        ( new Settings_Controller( $this->tool_registry ) )->register_routes();
        $tokens = new Tokens_Controller(
            new \Easy_MCP_AI\Auth\Token_Manager(),
            new \Easy_MCP_AI\Admin\Access_Presets( $this->tool_registry ),
            $this->tool_registry
        );
        $tokens->register_routes();
        require_once __DIR__ . '/class-activity-controller.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-audit-log-repository.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/history/class-change-redactor.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
        $activity = new Activity_Controller(
            $wpdb,
            new \Easy_MCP_AI\History\Change_Log_Repository(),
            new \Easy_MCP_AI\Audit_Log_Repository(),
            array( \Easy_MCP_AI\Admin\Admin_Page::class, 'batched_cleanup' )
        );
        $activity->register_routes();
        require_once __DIR__ . '/class-oauth-controller.php';
        $oauth = new OAuth_Controller( $wpdb, new \Easy_MCP_AI\Admin\Access_Presets( $this->tool_registry ) );
        $oauth->register_routes();

        require_once __DIR__ . '/class-setup-controller.php';
        ( new Setup_Controller() )->register_routes();

        require_once __DIR__ . '/class-external-data-controller.php';
        require_once dirname( __DIR__ ) . '/class-external-data-service.php';
        ( new External_Data_Controller( new \Easy_MCP_AI\Admin\External_Data_Service() ) )->register_routes();

        
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/feedback/class-feedback-queue.php';
        require_once __DIR__ . '/class-feedback-controller.php';
        ( new Feedback_Controller() )->register_routes();
        
        require_once __DIR__ . '/class-rest-namespaces-controller.php';
        ( new Rest_Namespaces_Controller() )->register_routes();
    }

    





    public function tool_groups() {
        if ( ! $this->tool_registry ) {
            return array();
        }
        require_once dirname( __DIR__ ) . '/class-tool-groups.php';
        return ( new \Easy_MCP_AI\Admin\Tool_Groups( $this->tool_registry ) )->build();
    }

    







    public function diagnostics_results() {
        $this->load_diagnostics();
        $this->load_live_checks();
        if ( ! Diagnostics::is_stale() ) {
            return \Easy_MCP_AI\Diagnostics\Diagnostics_Notices::with_live( Diagnostics::cached() );
        }
        $this->register_checks();
        return \Easy_MCP_AI\Diagnostics\Diagnostics_Notices::with_live( Diagnostics::run() );
    }

    









    public function diagnostics_run_deep( $scope = 'all' ) {
        $this->load_diagnostics();
        $this->register_checks();
        return Diagnostics::run( true, 'connection' === $scope ? Diagnostics::CONNECTION_GROUPS : null );
    }

    
    public function diagnostics_connection_due() {
        $this->load_diagnostics();
        return Diagnostics::connection_checks_due();
    }

    
    public function diagnostics_last_run() {
        $this->load_diagnostics();
        return Diagnostics::last_run_at();
    }

    








    public function diagnostics_check_action( $result ) {
        $dir = EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/';
        if ( 'a1' === $result->id() ) {
            
            require_once $dir . 'class-htaccess-auth-rule.php';
            $offer = \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::offer_now( $result );
            if ( \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::OFFER_NONE === $offer['offer'] ) {
                return null;
            }
            return array(
                'kind'  => 'auth_rule',
                'offer' => $offer['offer'],
                'path'  => (string) $offer['path'],
                'block' => \Easy_MCP_AI\Diagnostics\Htaccess_Auth_Rule::block_text(),
            );
        }
        if ( 'a7' === $result->id() ) {
            require_once $dir . 'class-htaccess-plain-routes.php';
            $offer = \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::offer_now();
            if ( \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::OFFER_NONE === $offer['offer'] ) {
                return null;
            }
            return array(
                'kind'  => 'plain_routes',
                'offer' => $offer['offer'],
                'path'  => (string) $offer['path'],
                'block' => \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::block_text( \Easy_MCP_AI\Diagnostics\Htaccess_Plain_Routes::home_root() ),
            );
        }
        if ( 'a10' === $result->id() ) {
            require_once $dir . 'class-check-discovery.php';
            $documents = array();
            foreach ( \Easy_MCP_AI\Diagnostics\Check_Discovery::mirror_urls() as $name => $url ) {
                $documents[] = array( 'name' => (string) $name, 'url' => (string) $url );
            }
            return array( 'kind' => 'well_known', 'documents' => $documents );
        }
        return null;
    }

    
    public function write_auth_rule() {
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
        $this->load_diagnostics();
        $this->register_checks();
        return \Easy_MCP_AI\Admin\Admin_Page::write_auth_rule();
    }

    
    public function write_plain_routes(): string {
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
        $this->load_diagnostics();
        $this->register_checks();
        return \Easy_MCP_AI\Admin\Admin_Page::write_plain_routes();
    }

    private function load_diagnostics() {
        $dir = EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/';
        require_once $dir . 'class-diagnostic-result.php';
        require_once $dir . 'class-diagnostics.php';
    }

    private function load_live_checks() {
        
        
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-check-notices.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-diagnostics-notices.php';
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/admin/class-admin-page.php';
    }

    private function register_checks() {
        $this->load_live_checks();
        if ( ! self::$checks_registered ) {
            self::$checks_registered = true;
            Diagnostics::register_core_checks( $this->tool_registry );
        }
    }

    





    public function system_info_text() {
        global $wpdb;
        $this->load_diagnostics();
        require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/diagnostics/class-diagnostics-site-health.php';

        $tools = 0;
        if ( $this->tool_registry && method_exists( $this->tool_registry, 'get_tools_by_category' ) ) {
            if ( ! class_exists( '\\Easy_MCP_AI\\Tools\\Tool_State' ) ) {
                require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/tools/class-tool-state.php';
            }
            $tools = Dashboard_Controller::count_active_tools(
                (array) $this->tool_registry->get_tools_by_category(),
                \Easy_MCP_AI\Tools\Tool_State::off_names( $this->tool_registry ),
                (array) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_allowed_tool_patterns', array() )
            );
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name, no user input
        $oauth_clients = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}easy_mcp_ai_oauth_clients WHERE is_active = 1" );

        return Support_Text::system_info(
            array(
                'token_count'        => ( new \Easy_MCP_AI\Auth\Token_Manager() )->count_tokens(),
                'oauth_client_count' => $oauth_clients,
                'tool_count'         => $tools,
                'external'           => array(
                    'Google Search Console' => '' !== (string) \get_option( 'easy_mcp_ai_gsc_service_account_json', '' ),
                    'Google Analytics'      => '' !== (string) \get_option( 'easy_mcp_ai_ga_service_account_json', '' ),
                    'DataForSEO'            => '' !== (string) \get_option( 'easy_mcp_ai_dfs_login', '' ),
                    'SEMrush'               => '' !== (string) \get_option( 'easy_mcp_ai_semrush_api_key', '' ),
                    
                    'Ahrefs (DR)'           => '' !== (string) \get_option( 'easy_mcp_ai_ahrefs_api_key', '' )
                        && (bool) \get_option( 'easy_mcp_ai_ahrefs_enabled', false ),
                ),
            ),
            array(
                'results'   => Diagnostics::cached(),
                'summary'   => Diagnostics::summary(),
                'last_run'  => Diagnostics::last_run_at(),
                'copy_safe' => array( '\Easy_MCP_AI\Diagnostics\Diagnostics_Site_Health', 'is_copy_safe' ),
            )
        );
    }

    
    public function ai_prompt_text() {
        return Support_Text::ai_prompt(
            \home_url(),
            \rest_url( 'easy-mcp-ai/v1/mcp' ),
            defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : ''
        );
    }
}
