<?php
namespace Easy_MCP_AI\Admin;

use Easy_MCP_AI\Auth\Connect_Policy;
use Easy_MCP_AI\Auth\Token_Manager;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\Tools\Tool_Registry;

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once dirname( __DIR__ ) . '/auth/class-connect-policy.php';


class Self_Service_Keys {
    private $manager;
    private $registry;
    private $forms = array();

    public function __construct( Token_Manager $manager, Tool_Registry $registry ) {
        $this->manager = $manager;
        $this->registry = $registry;
        \add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        \add_action( 'show_user_profile', array( $this, 'render' ) );
        \add_action( 'admin_footer', array( $this, 'render_forms' ) );
        \add_action( 'admin_post_easy_mcp_ai_self_create_key', array( $this, 'handle_create' ) );
        \add_action( 'admin_post_easy_mcp_ai_self_revoke_key', array( $this, 'handle_revoke' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( 'profile.php' !== $hook || ! self::enabled() ) { return; }
        $base = 'assets/';
        \wp_enqueue_style( 'easy-mcp-ai-profile-keys', EASY_MCP_AI_PLUGIN_URL . $base . 'css/self-service-keys.css', array(), filemtime( EASY_MCP_AI_PLUGIN_DIR . $base . 'css/self-service-keys.css' ) );
        \wp_enqueue_script( 'easy-mcp-ai-profile-keys', EASY_MCP_AI_PLUGIN_URL . $base . 'js/self-service-keys.js', array(), filemtime( EASY_MCP_AI_PLUGIN_DIR . $base . 'js/self-service-keys.js' ), true );
    }

    
    public static function enabled() {
        return Config::get( 'self_service_keys' ) && \get_current_user_id() && Connect_Policy::current_user_can_connect( Connect_Policy::CHANNEL_SELF_KEY );
    }

    public static function limit( $key ) {
        return Config::get( $key );
    }

    public static function expiry_presets() {
        return array_values( Admin_Page::token_expiry_presets() );
    }

    
    public function create( array $data ) {
        if ( ! self::enabled() ) { return new \WP_Error( 'forbidden', __( 'Self-service keys are not available for your account.', 'easy-mcp-ai' ) ); }
        $name = isset( $data['key_name'] ) && is_string( $data['key_name'] ) ? trim( \sanitize_text_field( $data['key_name'] ) ) : '';
        if ( '' === $name || strlen( $name ) > 255 ) { return new \WP_Error( 'name', __( 'Enter a short, non-empty key name.', 'easy-mcp-ai' ) ); }
        $days = $data['expiry_days'] ?? null;
        if ( ! is_string( $days ) || ! in_array( $days, array_merge( array_map( 'strval', self::expiry_presets() ), array( 'custom', 'never' ) ), true ) ) {
            return new \WP_Error( 'expiry', __( 'Choose one of the available expiry periods.', 'easy-mcp-ai' ) );
        }
        $expires_at = Admin_Page::resolve_submitted_expiry( $days, $data['expiry_custom'] ?? '' );
        if ( \is_wp_error( $expires_at ) ) {
            return new \WP_Error( 'expiry', $expires_at->get_error_message() );
        }
        $selected = $data['key_tools'] ?? array();
        $available = array_column( $this->offered_tools(), 'name' );
        if ( ! is_array( $selected ) || ! $selected ) { return new \WP_Error( 'tools', __( 'Select at least one available tool.', 'easy-mcp-ai' ) ); }
        foreach ( $selected as $tool ) {
            if ( ! is_string( $tool ) || ! in_array( $tool, $available, true ) ) {
                return new \WP_Error( 'tools', __( 'The tool selection is no longer available. Reload your profile and try again.', 'easy-mcp-ai' ) );
            }
        }
        
        return $this->manager->create_self_service_token( $name, \get_current_user_id(), array_values( array_unique( $selected ) ), $expires_at, self::limit( 'self_service_max_keys' ) );
    }

    







    const NEVER_OFFERED = array( 'wp_cli_run' );

    









    private function offered_tools(): array {
        require_once dirname( __DIR__ ) . '/auth/class-permission-guard.php';
        return array_values( array_filter( Server::available_tools( $this->registry, true ), static function ( array $definition ): bool {
            return ! isset( $definition['_meta'][ \Easy_MCP_AI\Tools\Privileged_Tool::META_KEY ] )
                && ! in_array( $definition['name'], self::NEVER_OFFERED, true )
                && ! in_array( $definition['name'], \Easy_MCP_AI\Auth\Permission_Guard::EVERY_CREDENTIAL, true );
        } ) );
    }

    public function revoke( $id ) {
        if ( ! self::enabled() ) { return new \WP_Error( 'forbidden', __( 'Self-service keys are not available for your account.', 'easy-mcp-ai' ) ); }
        $token = $this->manager->get_token_by_id( $id );
        if ( ! $token || (int) $token['wp_user_id'] !== \get_current_user_id() ) {
            return new \WP_Error( 'forbidden', __( 'You can only revoke your own keys.', 'easy-mcp-ai' ) );
        }
        if ( false === $this->manager->revoke_token( $id, \get_current_user_id() ) ) {
            return new \WP_Error( 'revoke', __( 'The key could not be revoked. Please try again.', 'easy-mcp-ai' ) );
        }
        return true;
    }

    private function guard_request( $nonce ) {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! self::enabled() ) {
            \wp_die( \esc_html__( 'Self-service keys are not available for this request.', 'easy-mcp-ai' ), '', array( 'response' => 403 ) );
        }
        
        
        if ( false === \check_admin_referer( $nonce ) ) {
            \wp_die( \esc_html__( 'The security token for this request is invalid or has expired.', 'easy-mcp-ai' ), '', array( 'response' => 403 ) );
        }
    }

    public function handle_create() {
        $this->guard_request( 'easy_mcp_ai_self_create_key' );
        $result = $this->create( \wp_unslash( $_POST ) );
        if ( \is_wp_error( $result ) ) { \wp_die( \esc_html( $result->get_error_message() ), '', array( 'response' => 400, 'back_link' => true ) ); }
        Token_Manager::stash_new_token( $result );
        \wp_safe_redirect( \admin_url( 'profile.php?easy_mcp_key=' . $result['id'] . '#easy-mcp-keys' ) );
        exit;
    }

    public function handle_revoke() {
        $raw = $_POST['key_id'] ?? '';
        $id = is_string( $raw ) && ctype_digit( $raw ) ? (int) $raw : 0;
        $this->guard_request( 'easy_mcp_ai_self_revoke_key_' . $id );
        $result = $this->revoke( $id );
        if ( \is_wp_error( $result ) ) { \wp_die( \esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) ); }
        \wp_safe_redirect( \admin_url( 'profile.php#easy-mcp-keys' ) );
        exit;
    }

    public function render( $user ) {
        if ( ! self::enabled() || (int) $user->ID !== \get_current_user_id() ) { return; }
        $page = isset( $_GET['easy_mcp_keys_page'] ) && is_scalar( $_GET['easy_mcp_keys_page'] ) ? max( 1, min( 100000, (int) $_GET['easy_mcp_keys_page'] ) ) : 1;
        $tokens = $this->manager->get_user_tokens( $user->ID, $page );
        $more = count( $tokens ) > 20;
        $tokens = array_slice( $tokens, 0, 20 );
        $tools = $this->offered_tools();
        $id = isset( $_GET['easy_mcp_key'] ) && is_scalar( $_GET['easy_mcp_key'] ) ? \absint( $_GET['easy_mcp_key'] ) : 0;
        $raw_token = $id ? Token_Manager::take_new_token( $id ) : false;
        $this->forms = array( 'easy-mcp-self-create' => array( 'action' => 'easy_mcp_ai_self_create_key', 'nonce' => 'easy_mcp_ai_self_create_key' ) );
        require __DIR__ . '/views/self-service-keys.php';
    }

    
    public function render_forms() {
        foreach ( $this->forms as $id => $form ) {
            echo '<form id="' . \esc_attr( $id ) . '" method="post" action="' . \esc_url( \admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="' . \esc_attr( $form['action'] ) . '">';
            if ( isset( $form['key_id'] ) ) { echo '<input type="hidden" name="key_id" value="' . \esc_attr( $form['key_id'] ) . '">'; }
            \wp_nonce_field( $form['nonce'] );
            echo '</form>';
        }
    }
}
