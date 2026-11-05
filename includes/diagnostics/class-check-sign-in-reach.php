<?php

namespace Easy_MCP_AI\Diagnostics;

use Easy_MCP_AI\OAuth\Client_Metadata_Support;
use Easy_MCP_AI\OAuth\Discovery;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Sign_In_Reach {

    const NOT_OFFERED = array( 'switched_off', 'schema_pending', 'registration_off', 'ipv6_unreadable' );

    public static function run(): array {
        $path = wp_parse_url( Discovery::authorization_endpoint(), PHP_URL_PATH );
        return array( self::evaluate( Client_Metadata_Support::status(), is_string( $path ) ? $path : '/' ) );
    }

    public static function evaluate( array $status, string $path ): Diagnostic_Result {
        $label    = __( 'Sign-in with an AI client\'s web address', 'easy-mcp-ai' );
        $sign_in  = $status['signIn'];
        $evidence = array(
            'reason'   => $status['reason'],
            'testedAt' => $status['testedAt'],
            'signIn'   => $sign_in,
        );

        if ( in_array( $status['reason'], self::NOT_OFFERED, true ) ) {
            return Diagnostic_Result::pass( 'a14', Diagnostic_Result::TIER_INFO, $label, self::not_offered_detail( $status['reason'] ), $evidence );
        }

        $tested = $status['testedAt'] > 0 ? gmdate( 'Y-m-d H:i', $status['testedAt'] ) . ' UTC' : '';
        switch ( $sign_in['outcome'] ) {
            case 'reached':
                return Diagnostic_Result::pass(
                    'a14',
                    Diagnostic_Result::TIER_WARNING,
                    $label,
                    /* translators: %s: date and time of the test, in UTC. */
                    sprintf( __( 'Confirmed on %s: a sign-in request carrying an AI client\'s web address reaches WordPress.', 'easy-mcp-ai' ), $tested ),
                    $evidence
                );
            case 'refused':
                return Diagnostic_Result::warn(
                    'a14',
                    Diagnostic_Result::TIER_WARNING,
                    $label,
                    self::refused_detail( $sign_in, $tested ),
                    self::refused_fix( $sign_in, $path ),
                    $evidence
                );
            case 'unreachable':
                return Diagnostic_Result::unknown(
                    'a14',
                    Diagnostic_Result::TIER_WARNING,
                    $label,
                    sprintf(
                        /* translators: 1: date and time of the test, in UTC; 2: the error. */
                        __( 'On %1$s this site could not send itself a sign-in request (%2$s), so it does not offer the newer sign-in. AI clients use the older one until a later test gets through.', 'easy-mcp-ai' ),
                        $tested,
                        $sign_in['excerpt']
                    ),
                    $evidence
                );
        }

        $reason = 'test_failed' === $status['reason']
            ? __( 'Not tested: the AI clients\' sign-in documents could not be fetched, so the newer sign-in is not offered and nothing was sent.', 'easy-mcp-ai' )
            : __( 'Not tested yet. The test runs in the background after an AI client next reads this site\'s sign-in details.', 'easy-mcp-ai' );
        return Diagnostic_Result::unknown( 'a14', Diagnostic_Result::TIER_INFO, $label, $reason, $evidence );
    }

    private static function not_offered_detail( string $reason ): string {
        switch ( $reason ) {
            case 'switched_off':
                return __( 'Switched off (EASY_MCP_AI_OAUTH_CIMD or the easy_mcp_ai_oauth_cimd filter). AI clients use the older sign-in.', 'easy-mcp-ai' );
            case 'registration_off':
                return __( 'Not offered while Dynamic Client Registration is off.', 'easy-mcp-ai' );
            case 'ipv6_unreadable':
                return __( 'Not offered: this site\'s PHP cannot look up IPv6 addresses, which checking an AI client\'s web address needs. AI clients use the older sign-in.', 'easy-mcp-ai' );
        }
        return __( 'Not offered until the database update that stores these AI clients has run.', 'easy-mcp-ai' );
    }

    private static function refused_detail( array $sign_in, string $tested ): string {
        $page = '' !== $sign_in['excerpt'] ? ': "' . $sign_in['excerpt'] . '"' : '';
        if ( '' === $sign_in['refuser'] ) {
            /* translators: 1: HTTP status code; 2: date and time of the test, in UTC; 3: the start of the answer, quoted, or nothing. */
            $text = __( 'A sign-in request carrying a web address got HTTP %1$d instead of the sign-in page (tested %2$s%3$s), so the newer sign-in is off and AI clients use the older one.', 'easy-mcp-ai' );
            return sprintf( $text, $sign_in['status'], $tested, $page ) . ( 'reached' === $sign_in['control'] ? ' ' . __( 'The same request with an older-style client ID got through.', 'easy-mcp-ai' ) : '' );
        }
        /* translators: %s: name of a firewall, e.g. ModSecurity or Cloudflare. */
        $who = sprintf( __( 'Your server\'s %s firewall', 'easy-mcp-ai' ), $sign_in['refuser'] );
        if ( 'reached' === $sign_in['control'] ) {
            /* translators: 1: who refused; 2: HTTP status code; 3: date and time of the test, in UTC; 4: the start of the refusal page, quoted, or nothing. */
            $text = __( '%1$s refuses a web address in a sign-in request (HTTP %2$d, tested %3$s%4$s), and lets the same request through with an older-style client ID. This site has switched to the older sign-in, which it allows, so AI clients still connect.', 'easy-mcp-ai' );
        } else {
            /* translators: 1: who refused; 2: HTTP status code; 3: date and time of the test, in UTC; 4: the start of the refusal page, quoted, or nothing. */
            $text = __( '%1$s refused every sign-in request this site sent itself (HTTP %2$d, tested %3$s%4$s), including one with an older-style client ID. The newer sign-in is off. If AI clients cannot sign in either, the same rule is stopping them.', 'easy-mcp-ai' );
        }
        return sprintf( $text, $who, $sign_in['status'], $tested, $page );
    }

    private static function refused_fix( array $sign_in, string $path ): string {
        if ( in_array( $sign_in['refuser'], array( 'ModSecurity', 'Imunify360' ), true ) ) {
            /* translators: %s: URL path of the sign-in page. */
            return sprintf( __( 'To use the newer sign-in, ask your host to exclude the rule for every path that starts with %s; the rule ID is in the ModSecurity or Imunify360 hits log. The site offers it again after its next test.', 'easy-mcp-ai' ), $path );
        }
        if ( '' === $sign_in['refuser'] ) {
            /* translators: %s: URL path of the sign-in page. */
            return sprintf( __( 'Find what gave that answer. If it is a firewall or security plugin, allow web addresses in the client_id parameter of every path that starts with %s there. The site tries again at its next test.', 'easy-mcp-ai' ), $path );
        }
        /* translators: %s: URL path of the sign-in page. */
        return sprintf( __( 'To use the newer sign-in, allow web addresses in the client_id parameter of every path that starts with %s in your firewall or security plugin. The site offers it again after its next test.', 'easy-mcp-ai' ), $path );
    }
}
