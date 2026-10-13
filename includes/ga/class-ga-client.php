<?php
namespace Easy_MCP_AI\GA;

use Easy_MCP_AI\Abstract_Google_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GA_Client extends Abstract_Google_Client {

    const OPTION_JSON        = 'easy_mcp_ai_ga_service_account_json';
    const OPTION_PROPERTY_ID = 'easy_mcp_ai_ga_default_property_id';
    const TRANSIENT_TOKEN    = 'easy_mcp_ai_ga_token';
    const SCOPE              = 'https://www.googleapis.com/auth/analytics.readonly';
    const HKDF_INFO          = 'easy_mcp_ai_ga_creds_v1';

    protected static function product_name(): string {
        return 'Google Analytics';
    }

    







    public static function request( string $method, string $url, ?array $body = null, bool $admin_context = false ): array {
        $scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
        $host   = \wp_parse_url( $url, PHP_URL_HOST );
        $allowed_hosts = array(
            'oauth2.googleapis.com',
            'analyticsdata.googleapis.com',
            'analyticsadmin.googleapis.com',
        );
        if ( 'https' !== $scheme || ! in_array( $host, $allowed_hosts, true ) ) {
            throw new \RuntimeException( 'GA client: refusing to attach bearer token to non-Google host.' );
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
                'GA API request failed (transport error): ' . $response->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $code    = (int) \wp_remote_retrieve_response_code( $response );
        $raw     = \wp_remote_retrieve_body( $response );
        $decoded = json_decode( $raw, true );

        if ( 403 === $code ) {
            if ( $admin_context ) {
                $creds = self::get_credentials();
                throw new \RuntimeException(
                    "Access denied. Add the service account ({$creds['client_email']}) as a Viewer in GA4 → Admin → Property access management, and enable both the Analytics Data API and Analytics Admin API in your Google Cloud project." // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
            throw new \RuntimeException(
                'Access denied by Google Analytics. The configured service account does not have access to this property. A site administrator can grant access in Easy MCP AI → Tools → External data → Test connection.'
            );
        }
        if ( 404 === $code ) {
            throw new \RuntimeException(
                'Property not found. Check the numeric property ID (found in GA4 Admin → Property details).'
            );
        }
        if ( 429 === $code ) {
            throw new \RuntimeException(
                'Google Analytics Data API quota exceeded. Core reporting: 1,250 tokens/hour per property. Realtime: 10,000 requests/day per property. Retry after quota reset.'
            );
        }
        if ( $code < 200 || $code >= 300 ) {
            $err = $decoded['error']['message'] ?? "HTTP {$code}";
            throw new \RuntimeException( "GA API error: {$err}" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        return is_array( $decoded ) ? $decoded : array();
    }

    public static function get( string $url, bool $admin_context = false ): array {
        return self::request( 'GET', $url, null, $admin_context );
    }

    public static function post( string $url, array $body, bool $admin_context = false ): array {
        return self::request( 'POST', $url, $body, $admin_context );
    }

    




    public static function default_property_id(): string {
        $id = \get_option( self::OPTION_PROPERTY_ID, '' );
        if ( empty( $id ) ) {
            throw new \RuntimeException(
                'No property_id provided and no default property configured. Set one in Easy MCP AI → Tools → External data.'
            );
        }
        return self::normalize_property( (string) $id );
    }

    









    public static function normalize_property( string $input ): string {
        $trimmed = trim( $input );
        if ( '' === $trimmed ) {
            throw new \RuntimeException( 'Invalid property_id; expected numeric GA4 property ID.' );
        }
        if ( 'universal' === $trimmed ) {
            return 'properties/0';
        }
        if ( 0 === strpos( $trimmed, 'properties/' ) ) {
            $trimmed = substr( $trimmed, strlen( 'properties/' ) );
        }
        if ( ! preg_match( '/^\d+$/', $trimmed ) ) {
            throw new \RuntimeException( 'Invalid property_id; expected numeric GA4 property ID.' );
        }
        return 'properties/' . $trimmed;
    }

    









    public static function build_date_ranges( array $input ): array {
        if ( empty( $input ) ) {
            throw new \RuntimeException( 'date_ranges is required and must be a non-empty array.' );
        }
        $out = array();
        foreach ( $input as $i => $range ) {
            if ( ! is_array( $range ) ) {
                throw new \RuntimeException( "date_ranges[{$i}] must be an object with start_date and end_date." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            foreach ( array( 'start_date', 'end_date' ) as $field ) {
                $value = $range[ $field ] ?? '';
                if ( ! is_string( $value ) || '' === $value ) {
                    throw new \RuntimeException( "date_ranges[{$i}].{$field} is required." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $is_iso   = (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
                $is_magic = in_array( $value, array( 'today', 'yesterday' ), true )
                    || (bool) preg_match( '/^\d+daysAgo$/', $value );
                if ( ! $is_iso && ! $is_magic ) {
                    throw new \RuntimeException( "date_ranges[{$i}].{$field} must be YYYY-MM-DD, NdaysAgo, yesterday, or today." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
            }
            $entry = array(
                'startDate' => $range['start_date'],
                'endDate'   => $range['end_date'],
            );
            if ( ! empty( $range['name'] ) && is_string( $range['name'] ) ) {
                $entry['name'] = $range['name'];
            }
            $out[] = $entry;
        }
        return $out;
    }
}
