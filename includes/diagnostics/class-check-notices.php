<?php























namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Notices {

    


    public static function run() {
        if ( ! class_exists( '\Easy_MCP_AI\Admin\Admin_Page' ) ) {
            $reason = __( 'Could not read the site configuration on this request.', 'easy-mcp-ai' );
            return array(
                Diagnostic_Result::unknown( 'a7', Diagnostic_Result::TIER_BLOCKER, self::permalink_label(), $reason ),
                Diagnostic_Result::unknown( 'a8', Diagnostic_Result::TIER_BLOCKER, self::transport_label(), $reason ),
            );
        }

        $plain = \Easy_MCP_AI\Admin\Admin_Page::permalinks_are_plain();
        return array(
            self::evaluate_permalinks( $plain, $plain && self::plain_routes_in_place() ),
            self::evaluate_oauth_transport( \Easy_MCP_AI\Admin\Admin_Page::oauth_transport_problem() ),
        );
    }

    



















    public static function evaluate_permalinks( $are_plain, $routed = false ) {
        $label = self::permalink_label();

        if ( ! $are_plain ) {
            return Diagnostic_Result::pass( 'a7', Diagnostic_Result::TIER_BLOCKER, $label, '', array( 'permalinks_plain' => false ) );
        }

        if ( $routed ) {
            return Diagnostic_Result::warn(
                'a7',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'Permalinks are set to "Plain". Rules in .htaccess send OAuth discovery and sign-in requests to WordPress anyway; the connection checks measure whether they arrive.', 'easy-mcp-ai' ),
                __( 'Nothing is required. Choosing any structure other than Plain in Settings → Permalinks is still the simplest setup, and removes these rules.', 'easy-mcp-ai' ),
                array( 'permalinks_plain' => true, 'plain_routes' => true )
            );
        }

        return Diagnostic_Result::fail(
            'a7',
            Diagnostic_Result::TIER_BLOCKER,
            $label,
            __( 'Permalinks are set to "Plain", so WordPress does not route /wp-json/ or /.well-known/ requests. An AI client cannot discover this site\'s OAuth endpoints and "Connect" will fail. On Apache this can also strip the Bearer token before WordPress ever sees it.', 'easy-mcp-ai' ),
            __( 'Open Settings → Permalinks and choose any structure other than Plain — "Post name" is the usual choice — then save. No other change is needed.', 'easy-mcp-ai' ),
            array( 'permalinks_plain' => true )
        );
    }

    





    private static function plain_routes_in_place() {
        return Htaccess_Plain_Routes::is_in_place();
    }

    













    public static function evaluate_oauth_transport( $problem ) {
        $label = self::transport_label();

        if ( '' === (string) $problem ) {
            return Diagnostic_Result::pass( 'a8', Diagnostic_Result::TIER_BLOCKER, $label, '', array( 'transport_problem' => '' ) );
        }

        if ( 'http' === $problem ) {
            return Diagnostic_Result::fail(
                'a8',
                Diagnostic_Result::TIER_BLOCKER,
                $label,
                __( 'This site is not using HTTPS. The OAuth 2.1 specification requires it, so authorization, token and discovery requests are refused and "Connect" fails in Claude and other OAuth clients. Existing API tokens are unaffected and keep working.', 'easy-mcp-ai' ),
                __( 'Serve the site over HTTPS and update the WordPress Address and Site Address settings to match.', 'easy-mcp-ai' ),
                array( 'transport_problem' => 'http' )
            );
        }

        return Diagnostic_Result::fail(
            'a8',
            Diagnostic_Result::TIER_BLOCKER,
            $label,
            __( 'Your site is served over HTTPS, but PHP still sees the request as insecure. This happens when a CDN, load balancer or reverse proxy handles HTTPS and passes the request on unencrypted — Cloudflare\'s Flexible SSL mode is the most common cause. OAuth requests are refused as a result, so "Connect" fails even though your site is secure for visitors. Existing API tokens are unaffected and keep working.', 'easy-mcp-ai' ),
            __( 'If you use Cloudflare, switch SSL/TLS mode to Full or Full (Strict) — that is the better fix, because it encrypts the connection to your server too. Otherwise add this to wp-config.php above the "stop editing" line: if ( isset( $_SERVER[\'HTTP_X_FORWARDED_PROTO\'] ) && \'https\' === $_SERVER[\'HTTP_X_FORWARDED_PROTO\'] ) { $_SERVER[\'HTTPS\'] = \'on\'; }', 'easy-mcp-ai' ),
            array( 'transport_problem' => 'proxy' )
        );
    }

    private static function permalink_label() {
        return __( 'Permalinks allow API discovery', 'easy-mcp-ai' );
    }

    private static function transport_label() {
        return __( 'OAuth clients can reach this site securely', 'easy-mcp-ai' );
    }
}
