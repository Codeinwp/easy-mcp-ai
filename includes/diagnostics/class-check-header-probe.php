<?php

namespace Easy_MCP_AI\Diagnostics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Check_Header_Probe {

    const SECRET_OPTION = 'easy_mcp_ai_header_probe_secret';

    const ROUTE = '/header-probe';

    public static function mcp_probe_headers( $secret ) {
        return array(
            'Mcp-Session-Id'       => 'emai-probe-' . substr( (string) $secret, 0, 12 ),
            'Mcp-Protocol-Version' => '2026-07-28',
            'Mcp-Method'           => 'tools/call',
            'Mcp-Name'             => 'emai_probe_' . substr( (string) $secret, 0, 12 ),
        );
    }

    const TIMEOUT = 5;

    public static function run() {
        $secret = '';

        try {
            $secret = self::arm();

            if ( '' === $secret ) {
                return array(
                    self::unknown( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
                    self::unknown_mcp_headers( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
                    self::unknown_basic( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
                );
            }

            try {
                $body = self::send_probe( $secret );
            } catch ( \Throwable $e ) {
                $body = null;
            }
            try {
                $basic_body = self::send_probe( $secret, 'basic' );
            } catch ( \Throwable $e ) {
                $basic_body = null;
            }
        } catch ( \Throwable $e ) {
            return array(
                self::unknown( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
                self::unknown_mcp_headers( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
                self::unknown_basic( __( 'Could not prepare the connection test on this site.', 'easy-mcp-ai' ) ),
            );
        } finally {
            self::disarm();
        }

        if ( class_exists( '\Easy_MCP_AI\Diagnostics\Check_Conflicts' ) ) {
            try {
                Check_Conflicts::record_rest_auth_observation( $body, $secret );
            } catch ( \Throwable $e ) {
                unset( $e );
            }
        }

        return array(
            self::evaluate( $body, $secret ),
            self::evaluate_mcp_headers( $body, $secret ),
            self::evaluate_basic( $basic_body, $secret ),
        );
    }

    public static function evaluate_basic( $body, $secret ) {
        if ( ! is_array( $body ) || empty( $body['proof'] )
            || ! hash_equals( self::expected_proof( $secret ), (string) $body['proof'] ) ) {
            return self::unknown_basic(
                __( 'The test request did not reach this site\'s own code, so the result would not mean anything. Some hosts block a site from calling itself; that on its own is not a fault.', 'easy-mcp-ai' )
            );
        }

        if ( ! array_key_exists( 'php_auth', $body ) ) {
            return self::unknown_basic( __( 'This site answered without the username-and-password result, so it was not measured.', 'easy-mcp-ai' ) );
        }

        $label      = self::basic_label();
        $in_server  = ! empty( $body['server_var'] );
        $in_php     = ! empty( $body['php_auth'] );
        $in_headers = ! empty( $body['getallheaders'] );
        $evidence   = array( 'server_var' => $in_server, 'php_auth' => $in_php, 'getallheaders' => $in_headers );

        if ( $in_server || $in_php ) {
            return Diagnostic_Result::pass( 'a13', Diagnostic_Result::TIER_WARNING, $label, __( 'Confirmed: an API key sent as a username and password reaches WordPress.', 'easy-mcp-ai' ), $evidence );
        }

        if ( $in_headers ) {
            return Diagnostic_Result::warn(
                'a13',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'The web server is not passing a username-and-password sign-in to PHP in the normal way. This plugin recovers it from a fallback source, so bridges that sign in this way still work today — but a site relying on that fallback breaks as soon as anything about the server changes.', 'easy-mcp-ai' ),
                __( 'Open Settings → Permalinks and press Save Changes so WordPress writes the rewrite that passes the header to PHP properly.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::warn(
            'a13',
            Diagnostic_Result::TIER_WARNING,
            $label,
            __( 'A username-and-password sign-in sent to this site never reaches WordPress. Bridges and connectors that only offer username and password fields cannot sign in with an API key here; clients that send a Bearer token are unaffected.', 'easy-mcp-ai' ),
            __( 'On Apache, with mod_php or FastCGI, re-save Settings → Permalinks so WordPress writes the rewrite that passes the header to PHP, or add the rule to .htaccess by hand where WordPress cannot write it. If the header is still lost with the rule in place, the web server needs CGIPassAuth On in its configuration, or on nginx the equivalent fastcgi_param, which usually only the host can set — quote this check when asking them.', 'easy-mcp-ai' ),
            $evidence
        );
    }

    private static function basic_label() {
        return __( 'API keys sent as a username and password reach WordPress', 'easy-mcp-ai' );
    }

    private static function unknown_basic( $detail ) {
        return Diagnostic_Result::unknown( 'a13', Diagnostic_Result::TIER_WARNING, self::basic_label(), $detail );
    }

    public static function evaluate_mcp_headers( $body, $secret ) {
        $label = __( 'AI session headers reach this site', 'easy-mcp-ai' );

        if ( ! is_array( $body ) || ! isset( $body['proof'] )
            || ! hash_equals( self::expected_proof( $secret ), (string) $body['proof'] ) ) {
            return Diagnostic_Result::unknown(
                'a11',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'The test request did not reach this site\'s own code, so there is nothing to report. Some hosts stop a site from calling its own address; on its own that is not a fault.', 'easy-mcp-ai' )
            );
        }

        if ( isset( $body['headers_collected'] ) && ! $body['headers_collected'] ) {
            return Diagnostic_Result::unknown(
                'a11',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'This site\'s server does not hand PHP a list of the request headers, so whether the session headers arrived could not be measured. That is a property of the server software, not a fault, and sign-in itself is checked separately.', 'easy-mcp-ai' )
            );
        }

        if ( ! isset( $body['mcp_headers'] ) || ! is_array( $body['mcp_headers'] ) ) {
            return Diagnostic_Result::unknown(
                'a11',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'This site answered without the session-header result, so it was not measured.', 'easy-mcp-ai' )
            );
        }

        $lost = array_keys( array_filter( $body['mcp_headers'], static function ( $ok ) {
            return ! $ok;
        } ) );

        if ( empty( $lost ) ) {
            return Diagnostic_Result::pass(
                'a11',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'The headers an AI client uses to keep its session reached this site unchanged.', 'easy-mcp-ai' ),
                $body['mcp_headers']
            );
        }

        return Diagnostic_Result::warn(
            'a11',
            Diagnostic_Result::TIER_WARNING,
            $label,
            sprintf(
                /* translators: %s: comma-separated HTTP header names. */
                __( 'A test request carrying the headers an AI client uses to keep its session arrived without them, or with them altered: %s. Something between the internet and PHP is removing or rewriting them. Signing in can still succeed, and then every call after it behaves as though it were the first — which reads as an assistant that connects and then forgets.', 'easy-mcp-ai' ),
                implode( ', ', $lost )
            ),
            __( 'Ask your host or CDN to pass these headers through unchanged. They are ordinary request headers; a proxy, a security rule or an aggressive header allow-list is the usual cause.', 'easy-mcp-ai' ),
            $body['mcp_headers']
        );
    }

    public static function expected_proof( $secret ) {
        return \wp_hash( 'easy_mcp_ai_header_probe|' . (string) $secret );
    }

    public static function authorize_probe( $supplied ) {
        $expected = (string) \get_option( self::SECRET_OPTION, '' );

        if ( '' === $expected || '' === (string) $supplied ) {
            return false;
        }

        return hash_equals( $expected, (string) $supplied );
    }

    public static function probe_response( array $server, $headers, $secret, $rest_auth = null ) {
        $collected = is_array( $headers );
        $headers   = $collected ? $headers : array();

        $found_in_headers = false;
        foreach ( array_keys( $headers ) as $name ) {
            if ( 0 === strcasecmp( (string) $name, 'Authorization' ) ) {
                $found_in_headers = true;
                break;
            }
        }

        $server_delivered = isset( $GLOBALS['easy_mcp_ai_server_had_auth_header'] )
            ? (bool) $GLOBALS['easy_mcp_ai_server_had_auth_header']
            : ! empty( $server['HTTP_AUTHORIZATION'] );

        $php_auth = ! empty( $server['PHP_AUTH_USER'] ) || ! empty( $server['PHP_AUTH_PW'] );

        $mcp = array();
        foreach ( self::mcp_probe_headers( $secret ) as $name => $expected ) {
            $mcp[ $name ] = false;
            foreach ( $headers as $got_name => $got_value ) {
                if ( 0 === strcasecmp( (string) $got_name, $name ) ) {
                    $mcp[ $name ] = ( (string) $got_value === $expected );
                    break;
                }
            }
        }

        return array(
            'proof'             => self::expected_proof( $secret ),
            'server_var'        => $server_delivered,
            'php_auth'          => $php_auth,
            'getallheaders'     => $found_in_headers,
            'headers_collected' => $collected,
            'mcp_headers'       => $collected ? $mcp : null,
            'rest_auth'         => is_array( $rest_auth ) ? $rest_auth : null,
        );
    }

    public static function evaluate( $body, $secret ) {
        if ( ! is_array( $body ) || empty( $body['proof'] )
            || ! hash_equals( self::expected_proof( $secret ), (string) $body['proof'] ) ) {
            return self::unknown(
                __( 'The test request did not reach this site\'s own code, so the result would not mean anything. Some hosts block a site from calling itself; that on its own is not a fault.', 'easy-mcp-ai' )
            );
        }

        $label      = self::label();
        $in_server  = ! empty( $body['server_var'] );
        $in_headers = ! empty( $body['getallheaders'] );
        $evidence   = array( 'server_var' => $in_server, 'getallheaders' => $in_headers );

        if ( $in_server ) {
            return Diagnostic_Result::pass( 'a1', Diagnostic_Result::TIER_WARNING, $label, __( 'Confirmed: an Authorization header sent to this site reaches WordPress.', 'easy-mcp-ai' ), $evidence );
        }

        if ( $in_headers ) {
            return Diagnostic_Result::warn(
                'a1',
                Diagnostic_Result::TIER_WARNING,
                $label,
                __( 'The web server is not passing the Authorization header to PHP in the normal way. This plugin recovers it from a fallback source, so AI clients still work today — but a site relying on that fallback breaks as soon as anything about the server changes.', 'easy-mcp-ai' ),
                __( 'Open Settings → Permalinks and press Save Changes so WordPress writes the rewrite that passes the header to PHP properly.', 'easy-mcp-ai' ),
                $evidence
            );
        }

        return Diagnostic_Result::warn(
            'a1',
            Diagnostic_Result::TIER_WARNING,
            $label,
            __( 'An Authorization header sent to this site never reaches WordPress. This is the usual reason an AI client that worked yesterday suddenly reports an invalid token: the credential is fine, the web server is removing it before WordPress sees it.', 'easy-mcp-ai' ),
            __( 'On Apache, with mod_php or FastCGI, re-save Settings → Permalinks so WordPress writes the rewrite that passes the header to PHP, or add the rule to .htaccess by hand where WordPress cannot write it. If the header is still lost with the rule in place, the web server needs CGIPassAuth On in its configuration, or on nginx the equivalent fastcgi_param, which usually only the host can set — quote this check when asking them.', 'easy-mcp-ai' ),
            $evidence
        );
    }

    private static function arm() {
        $secret = self::generate_secret();

        \update_option( self::SECRET_OPTION, $secret, false );

        return hash_equals( (string) \get_option( self::SECRET_OPTION, '' ), $secret ) ? $secret : '';
    }

    private static function disarm() {
        \delete_option( self::SECRET_OPTION );
    }

    public static function generate_secret() {
        if ( function_exists( 'random_bytes' ) ) {
            try {
                return bin2hex( random_bytes( 32 ) );
            } catch ( \Throwable $e ) {
                unset( $e );
            }
        }

        return (string) \wp_generate_password( 64, false, false );
    }

    private static function send_probe( $secret, $scheme = 'bearer' ) {
        if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'rest_url' ) ) {
            return null;
        }

        $url = \add_query_arg(
            'probe',
            rawurlencode( $secret ),
            \rest_url( 'easy-mcp-ai/v1' . self::ROUTE )
        );

        $response = \wp_remote_get(
            $url,
            array(
                'timeout'     => self::TIMEOUT,
                'redirection' => 0,
                'headers'     => array_merge(
                    array( 'Authorization' => self::probe_authorization( $secret, $scheme ) ),
                    self::mcp_probe_headers( $secret )
                ),
                'sslverify'   => false,
            )
        );

        if ( \is_wp_error( $response ) ) {
            return null;
        }

        $decoded = json_decode( (string) \wp_remote_retrieve_body( $response ), true );

        return is_array( $decoded ) ? $decoded : null;
    }

    public static function probe_authorization( $secret, $scheme = 'bearer' ) {
        if ( 'basic' === $scheme ) {
            return 'Basic ' . base64_encode( 'easy-mcp:wpmcp_' . (string) $secret );
        }
        return 'Bearer ' . (string) $secret;
    }

    private static function label() {
        return __( 'Login credentials reach WordPress', 'easy-mcp-ai' );
    }

    private static function unknown( $reason ) {
        return Diagnostic_Result::unknown( 'a1', Diagnostic_Result::TIER_WARNING, self::label(), $reason );
    }

    private static function unknown_mcp_headers( $reason ) {
        return Diagnostic_Result::unknown(
            'a11',
            Diagnostic_Result::TIER_WARNING,
            __( 'AI session headers reach this site', 'easy-mcp-ai' ),
            $reason
        );
    }
}
