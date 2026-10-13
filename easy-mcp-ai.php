<?php
/**
 * Plugin Name: Easy MCP AI - Connector for Claude, ChatGPT & SEO Data
 * Plugin URI:  https://easymcpai.com
 * Description: Connect Claude, ChatGPT & any AI to WordPress. Manage your entire site by chat — content, media, GA4, Search Console, SEO & more. 243 tools. Free.
 * Version:     2.1.1
 * Author:      EasyMCPAI
 * Author URI:  https://easymcpai.com/
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-mcp-ai
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WordPress Available:  yes
 * Requires License:    no
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}























$GLOBALS['easy_mcp_ai_ini_memory_limit'] = ini_get( 'memory_limit' );












if ( isset( $_SERVER['REQUEST_URI'] ) ) {
    $easy_mcp_ai_req = (string) $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only path match, not stored/output.
    $easy_mcp_ai_own = (
        false !== strpos( $easy_mcp_ai_req, '/.well-known/oauth-' )
        || false !== strpos( $easy_mcp_ai_req, '/.well-known/openid-configuration' )
        
        
        
        
        
        || false !== strpos( $easy_mcp_ai_req, '/easy-mcp-ai/v1/' )
        || false !== strpos( $easy_mcp_ai_req, 'easy_mcp_ai_oauth=' )
        
        
        || false !== strpos( $easy_mcp_ai_req, '/easy-mcp-ai/authorize' )
    );

    







    if ( ! $easy_mcp_ai_own ) {
        $easy_mcp_ai_path   = rtrim( (string) wp_parse_url( $easy_mcp_ai_req, PHP_URL_PATH ), '/' );
        $easy_mcp_ai_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only comparison.
        $easy_mcp_ai_ctype  = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( (string) $_SERVER['CONTENT_TYPE'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only comparison.
        $easy_mcp_ai_own    = (
            ( '/authorize' === $easy_mcp_ai_path && 'GET' === $easy_mcp_ai_method && false !== strpos( $easy_mcp_ai_req, 'response_type=code' ) )
            || ( '/register' === $easy_mcp_ai_path && 'POST' === $easy_mcp_ai_method && 0 === strpos( $easy_mcp_ai_ctype, 'application/json' ) )
            || ( '/token' === $easy_mcp_ai_path && 'POST' === $easy_mcp_ai_method )
        );
        unset( $easy_mcp_ai_path, $easy_mcp_ai_method, $easy_mcp_ai_ctype );
    }

    








































    if ( ! $easy_mcp_ai_own
        && (
            false !== strpos( $easy_mcp_ai_req, 'wp-login.php' )
            || false !== strpos( $easy_mcp_ai_req, 'rest_route=' )
        )
    ) {
        $easy_mcp_ai_decoded = rawurldecode( $easy_mcp_ai_req );
        $easy_mcp_ai_own     = (
            false !== strpos( $easy_mcp_ai_decoded, '/easy-mcp-ai/v1/' )
            || false !== strpos( $easy_mcp_ai_decoded, 'easy_mcp_ai_oauth=' )
        );
        unset( $easy_mcp_ai_decoded );
    }

    



























    if ( ! $easy_mcp_ai_own
        && isset( $_SERVER['REQUEST_METHOD'] )
        && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read-only comparison.
        && false !== strpos( $easy_mcp_ai_req, 'wp-login.php' )
        && ! empty( $_POST['redirect_to'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only substring match; nothing is acted on or stored.
        && is_string( $_POST['redirect_to'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- see above.
    ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- read-only substring match; nothing is acted on, stored or output.
        $easy_mcp_ai_rt = (string) $_POST['redirect_to'];

        
        
        
        
        foreach ( array( $easy_mcp_ai_rt, rawurldecode( $easy_mcp_ai_rt ) ) as $easy_mcp_ai_candidate ) {
            if ( false !== strpos( $easy_mcp_ai_candidate, '/easy-mcp-ai/v1/' )
                || false !== strpos( $easy_mcp_ai_candidate, 'easy_mcp_ai_oauth=' ) ) {
                $easy_mcp_ai_own = true;
                break;
            }
        }
        unset( $easy_mcp_ai_rt, $easy_mcp_ai_candidate );
    }

    if ( $easy_mcp_ai_own ) {
        @ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed, PluginCheck.CodeAnalysis.PHPErrorReporting.IniDirectiveDisplay_errors, WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- Intentional & request-scoped: stop other plugins' PHP notices from corrupting our OAuth/MCP JSON responses. Suppresses display only (logging unaffected).
        @ini_set( 'display_startup_errors', '0' ); // phpcs:ignore WordPress.PHP.IniSet.Risky, WordPress.PHP.NoSilencedErrors.Discouraged, Squiz.PHP.DiscouragedFunctions.Discouraged -- See above; display suppression only, scoped to our own JSON endpoints.

        











        








        $GLOBALS['easy_mcp_ai_server_had_auth_header'] = ! empty( $_SERVER['HTTP_AUTHORIZATION'] );

        if ( empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            require_once __DIR__ . '/includes/class-auth-header.php';
            $easy_mcp_ai_hdrs = Easy_MCP_AI\Auth_Header::request_headers();
            if ( null !== $easy_mcp_ai_hdrs ) {
                $easy_mcp_ai_bearer = Easy_MCP_AI\Auth_Header::recover_bearer( $easy_mcp_ai_hdrs );
                if ( null !== $easy_mcp_ai_bearer ) {
                    $_SERVER['HTTP_AUTHORIZATION'] = $easy_mcp_ai_bearer;
                } elseif ( ! isset( $_SERVER['PHP_AUTH_USER'] ) && ! isset( $_SERVER['PHP_AUTH_PW'] ) ) {
                    











                    $easy_mcp_ai_basic = Easy_MCP_AI\Auth_Header::recover_basic_api_key( $easy_mcp_ai_hdrs );
                    if ( null !== $easy_mcp_ai_basic ) {
                        $_SERVER['HTTP_AUTHORIZATION'] = $easy_mcp_ai_basic;
                    }
                    unset( $easy_mcp_ai_basic );
                }
                unset( $easy_mcp_ai_bearer );
            }
            unset( $easy_mcp_ai_hdrs );
        }
    }
    unset( $easy_mcp_ai_req, $easy_mcp_ai_own );
}

define( 'EASY_MCP_AI_VERSION', '2.1.1' );
define( 'EASY_MCP_AI_PLUGIN_FILE', __FILE__ );
define( 'EASY_MCP_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EASY_MCP_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EASY_MCP_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-plugin.php';
require_once EASY_MCP_AI_PLUGIN_DIR . 'includes/class-themeisle-sdk.php';

















$easy_mcp_ai_autoload = EASY_MCP_AI_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $easy_mcp_ai_autoload ) ) {
    require_once $easy_mcp_ai_autoload;
}
unset( $easy_mcp_ai_autoload );
Easy_MCP_AI\Themeisle_SDK::register( __FILE__ );

register_activation_hook( __FILE__, array( 'Easy_MCP_AI\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Easy_MCP_AI\\Deactivator', 'deactivate' ) );

Easy_MCP_AI\Plugin::instance();

add_filter(
    'plugin_action_links_' . EASY_MCP_AI_PLUGIN_BASENAME,
    function ( $links ) {
        if ( \Easy_MCP_AI\Config::get( 'hide_admin' ) ) {
            return $links;
        }
        $prepend = array(
            'dashboard' => '<a href="' . esc_url( admin_url( 'admin.php?page=easy-mcp-ai' ) ) . '">' . esc_html__( 'Getting Started', 'easy-mcp-ai' ) . '</a>',
            'abilities'      => '<a href="' . esc_url( admin_url( 'admin.php?page=easy-mcp-ai-tools&section=abilities' ) ) . '">' . esc_html__( 'Abilities', 'easy-mcp-ai' ) . '</a>',
            'external_data'  => '<a href="' . esc_url( admin_url( 'admin.php?page=easy-mcp-ai-tools&section=external' ) ) . '">' . esc_html__( 'External Data', 'easy-mcp-ai' ) . '</a>',
        );
        $append = array(
            'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=easy-mcp-ai-settings' ) ) . '">' . esc_html__( 'Settings', 'easy-mcp-ai' ) . '</a>',
        );
        return array_merge( $prepend, $links, $append );
    }
);

add_filter(
    'plugin_row_meta',
    function ( $links, $file ) {
        if ( EASY_MCP_AI_PLUGIN_BASENAME !== $file || \Easy_MCP_AI\Config::get( 'hide_admin' ) ) {
            return $links;
        }
        $links[] = '<a href="https://wordpress.org/support/plugin/easy-mcp-ai/reviews/" target="_blank" rel="noopener">' . esc_html__( 'Rate Plugin', 'easy-mcp-ai' ) . '</a>';
        return $links;
    },
    10,
    2
);
