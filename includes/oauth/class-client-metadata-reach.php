<?php
namespace Easy_MCP_AI\OAuth;

use Easy_MCP_AI\Admin\Site_Kind;
use Easy_MCP_AI\Config;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Client_Metadata_Reach {

    const OUTCOMES = array( 'reached', 'refused', 'unreachable' );

    const REDIRECT_URI = 'https://claude.ai/api/mcp/auth_callback';

    const DCR_SHAPED_ID = '00000000000000000000000000000000';

    const CODE_CHALLENGE = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';

    const EXCERPT_LENGTH = 200;

    const REFUSER_TEXT = array(
        'Imunify360'  => array( 'imunify360' ),
        'ModSecurity' => array( 'mod_security', 'modsecurity' ),
        'Wordfence'   => array( 'wordfence' ),
        'Sucuri'      => array( 'sucuri' ),
        'Cloudflare'  => array( 'cloudflare' ),
    );

    const REFUSER_HEADERS = array(
        'Sucuri'     => 'x-sucuri-id',
        'Cloudflare' => 'cf-ray',
    );

    public static function check( string $client_id ): array {
        $variant = Discovery::ISSUER_SITE;
        $result  = self::send( $client_id, $variant );
        if ( 'reached' === $result['outcome'] && null !== Discovery::issuer( Discovery::ISSUER_RESOURCE ) ) {
            $variant = Discovery::ISSUER_RESOURCE;
            $result  = self::send( $client_id, $variant );
        }
        $result['control'] = 'reached' === $result['outcome'] ? '' : self::send( self::DCR_SHAPED_ID, $variant )['outcome'];
        return $result;
    }

    public static function url( string $client_id, string $state, string $variant = Discovery::ISSUER_SITE, bool $as_stored = false ): string {
        $params = array(
            'response_type'         => 'code',
            'client_id'             => $client_id,
            'redirect_uri'          => self::REDIRECT_URI,
            'code_challenge'        => self::CODE_CHALLENGE,
            'code_challenge_method' => 'S256',
            'scope'                 => 'mcp',
            'state'                 => $state,
            'resource'              => self::as_clients_see( rest_url( Discovery::NAMESPACE_V1 . '/mcp' ), $as_stored ),
        );
        return self::as_clients_see( Discovery::authorization_endpoint( $variant ), $as_stored ) . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
    }

    private static function as_clients_see( string $url, bool $as_stored ): string {
        return $as_stored || Config::get( 'oauth_allow_http' ) ? $url : (string) preg_replace( '#^http://#i', 'https://', $url );
    }

    public static function classify( $response, string $state ): array {
        if ( is_wp_error( $response ) ) {
            return array(
                'outcome' => 'unreachable',
                'status'  => 0,
                'refuser' => '',
                'excerpt' => self::excerpt( $response->get_error_message() ),
            );
        }
        $status   = (int) wp_remote_retrieve_response_code( $response );
        $location = self::header( $response, 'location' );
        $login    = self::host_and_path( strtok( wp_login_url(), '?' ) );
        if ( $status >= 300 && $status < 400 && '' !== $state && null !== $login && self::host_and_path( strtok( $location, '?' ) ) === $login && false !== strpos( $location, $state ) ) {
            return array(
                'outcome' => 'reached',
                'status'  => $status,
                'refuser' => '',
                'excerpt' => '',
            );
        }
        $body = (string) wp_remote_retrieve_body( $response );
        return array(
            'outcome' => 'refused',
            'status'  => $status,
            'refuser' => self::refuser( $body, self::header( $response, 'server' ), $response ),
            'excerpt' => self::excerpt( $body ),
        );
    }

    private static function send( string $client_id, string $variant ): array {
        $state  = wp_generate_password( 32, false );
        $secure = self::url( $client_id, $state, $variant );
        $result = self::send_to( $secure, $state );
        $plain  = self::url( $client_id, $state, $variant, true );
        if ( 'unreachable' !== $result['outcome'] || strtok( $secure, '?' ) === strtok( $plain, '?' ) || ! self::is_local_host( $plain ) ) {
            return $result;
        }
        $retry = self::send_to( $plain, $state );
        return 'reached' === $retry['outcome'] ? $retry : $result;
    }

    private static function is_local_host( string $url ): bool {
        $host = trim( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), '[]' );
        return 'localhost' === $host || '::1' === $host || 1 === preg_match( '/^127(\.\d{1,3}){3}$/', $host ) || Site_Kind::has_local_suffix( $host );
    }

    private static function send_to( string $url, string $state ): array {
        $response = wp_remote_get(
            $url,
            array(
                'timeout'     => 10,
                'redirection' => 0,
                'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ),
                'user-agent'  => 'EasyMCPAI-OAuth/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ),
            )
        );
        return self::classify( $response, $state );
    }

    private static function host_and_path( $url ): ?string {
        return is_string( $url ) && preg_match( '#^https?://(.+)$#i', $url, $match ) ? $match[1] : null;
    }

    private static function refuser( string $body, string $server, array $response ): string {
        $text = strtolower( $body . ' ' . $server );
        foreach ( self::REFUSER_TEXT as $name => $needles ) {
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $text, $needle ) ) {
                    return $name;
                }
            }
        }
        foreach ( self::REFUSER_HEADERS as $name => $header ) {
            if ( '' !== self::header( $response, $header ) ) {
                return $name;
            }
        }
        return '';
    }

    private static function header( array $response, string $name ): string {
        $value = wp_remote_retrieve_header( $response, $name );
        if ( is_array( $value ) ) {
            $value = reset( $value );
        }
        return is_string( $value ) ? $value : '';
    }

    private static function excerpt( string $text ): string {
        $plain = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );
        return function_exists( 'mb_substr' ) ? mb_substr( $plain, 0, self::EXCERPT_LENGTH, 'UTF-8' ) : substr( $plain, 0, self::EXCERPT_LENGTH );
    }
}
