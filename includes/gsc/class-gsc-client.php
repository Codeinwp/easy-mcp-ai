<?php
namespace Easy_MCP_AI\GSC;

use Easy_MCP_AI\Abstract_Google_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GSC_Client extends Abstract_Google_Client {

    const OPTION_JSON     = 'easy_mcp_ai_gsc_service_account_json';
    const OPTION_SITE_URL = 'easy_mcp_ai_gsc_default_site_url';
    const TRANSIENT_TOKEN = 'easy_mcp_ai_gsc_token';
    const SCOPE           = 'https://www.googleapis.com/auth/webmasters';
    const HKDF_INFO       = 'easy_mcp_ai_gsc_creds_v1';

    protected static function product_name(): string {
        return 'Google Search Console';
    }

    









    public static function validate_site_url( string $value ): string {
        $trimmed = trim( $value );
        if ( '' === $trimmed ) {
            throw new \InvalidArgumentException( 'site_url is required.' );
        }
        if ( ! preg_match( '#^(https?://\S+|sc-domain:[A-Za-z0-9.\-]+)$#i', $trimmed ) ) {
            throw new \InvalidArgumentException( 'site_url must be a full http(s) URL or "sc-domain:example.com".' );
        }
        return $trimmed;
    }

    







    public static function request( string $method, string $url, ?array $body = null, bool $admin_context = false ): array {
        $scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
        $host   = \wp_parse_url( $url, PHP_URL_HOST );
        $allowed_hosts = array(
            'www.googleapis.com',
            'oauth2.googleapis.com',
            'searchconsole.googleapis.com',
        );
        if ( 'https' !== $scheme || ! in_array( $host, $allowed_hosts, true ) ) {
            throw new \RuntimeException( 'GSC client: refusing to attach bearer token to non-Google host.' );
        }

        $token = self::get_access_token();

        $args = array(
            'method'              => strtoupper( $method ),
            'headers'             => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'timeout'             => 20,
            
            
            'limit_response_size' => 8388608,
        );
        if ( null !== $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = \wp_remote_request( $url, $args );

        if ( \is_wp_error( $response ) ) {
            throw new \RuntimeException(
                'GSC API request failed (transport error): ' . $response->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $code    = (int) \wp_remote_retrieve_response_code( $response );
        $raw     = \wp_remote_retrieve_body( $response );
        $decoded = json_decode( $raw, true );

        if ( 403 === $code ) {
            if ( $admin_context ) {
                $creds = self::get_credentials();
                throw new \RuntimeException(
                    "Access denied. Add the service account ({$creds['client_email']}) as a user in Search Console → Settings → Users & permissions." // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException(
                'Access denied by Google Search Console. The configured service account does not have access to this property. A site administrator can grant access in Easy MCP AI → Tools → External data → Test connection.'
            );
        }
        if ( 404 === $code ) {
            throw new \RuntimeException(
                'Site not found in Search Console. Verify the site_url matches a verified property in your Google Search Console account, and that the service account has been granted access.'
            );
        }
        if ( 429 === $code ) {
            throw new \RuntimeException(
                'Google API quota exceeded. searchAnalytics: 1,200 req/min. URL Inspection: 2,000 req/day, 600 req/min.'
            );
        }
        if ( $code < 200 || $code >= 300 ) {
            $err = $decoded['error']['message'] ?? "HTTP {$code}";
            throw new \RuntimeException( "GSC API error: {$err}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return is_array( $decoded ) ? $decoded : array();
    }

    public static function get( string $url, bool $admin_context = false ): array {
        return self::request( 'GET', $url, null, $admin_context );
    }

    public static function post( string $url, array $body, bool $admin_context = false ): array {
        return self::request( 'POST', $url, $body, $admin_context );
    }

    public static function default_site_url(): string {
        $url = \get_option( self::OPTION_SITE_URL, '' );
        if ( empty( $url ) ) {
            throw new \RuntimeException(
                'No site_url provided and no default property configured. Set one in Easy MCP AI → Tools → External data.'
            );
        }
        return self::validate_site_url( $url );
    }
}
