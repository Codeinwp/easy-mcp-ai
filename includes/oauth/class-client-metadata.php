<?php
namespace Easy_MCP_AI\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Client_Metadata {

    const MAX_URL_LENGTH = 1024;

    const MAX_BYTES = 65536;

    const TIMEOUT = 5;

    const MIN_TTL     = 300;
    const MAX_TTL     = 86400;
    const DEFAULT_TTL = 3600;

    const STALE_GRACE = 86400;

    const MAX_REDIRECT_URIS = 20;

    const GRANT_TYPES = array( 'authorization_code', 'refresh_token' );

    const REASON_INVALID_URL      = 'cimd_invalid_url';
    const REASON_FETCH_FAILED     = 'cimd_fetch_failed';
    const REASON_INVALID          = 'cimd_invalid';
    const REASON_UNSUPPORTED_AUTH = 'cimd_unsupported_auth';
    const REASON_CLIENT_CAP       = 'cimd_client_cap';
    const REASON_STORE_FAILED     = 'cimd_store_failed';

    const SPECIAL_USE = array(
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'ff00::/8',
    );

    private static $resolver = null;

    private static $ipv6_lookup_off = false;

    public static function is_url( string $client_id ): bool {
        return 0 === strpos( $client_id, 'https://' );
    }

    public static function storage_id( string $client_id ): string {
        if ( self::is_url( $client_id ) && Client_Metadata_Support::resolves() ) {
            return hash( 'sha256', $client_id );
        }
        return $client_id;
    }

    public static function valid_url( string $url ): bool {
        if ( strlen( $url ) > self::MAX_URL_LENGTH ) {
            return false;
        }
        $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
        $host = '((?:' . $label . '\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?)';
        if ( ! preg_match( '#^https://' . $host . '(?::443)?(/[A-Za-z0-9\-._~!$&\'()*+,;=:@/]*)$#D', $url, $m ) ) {
            return false;
        }
        if ( strlen( $m[1] ) > 253 || '/' === $m[2] ) {
            return false;
        }
        foreach ( explode( '/', $m[2] ) as $segment ) {
            if ( '.' === $segment || '..' === $segment ) {
                return false;
            }
        }
        return true;
    }

    public static function host( string $url ): string {
        return (string) wp_parse_url( $url, PHP_URL_HOST );
    }

    public static function is_local_redirect( string $redirect_uri ): bool {
        return 'https' !== strtolower( (string) wp_parse_url( $redirect_uri, PHP_URL_SCHEME ) );
    }

    public static function for_authorization( string $url, string $redirect_uri, bool $accept_new = true ) {
        if ( ! self::valid_url( $url ) ) {
            return self::refusal( self::REASON_INVALID_URL, '', $url );
        }
        $id     = hash( 'sha256', $url );
        $row    = self::stored_row( $id );
        $usable = null !== $row && self::is_usable( $row, $url );
        if ( ! $usable && ! $accept_new ) {
            return null;
        }
        $now = time();
        if ( $usable && self::is_fresh( $row, $now ) && self::lists( $row, $redirect_uri ) ) {
            return $row;
        }

        $fetched = self::fetch( $url );
        if ( ! $fetched['ok'] ) {
            if ( $fetched['transport'] ) {
                Client_Metadata_Support::record_fetch( true );
            }
            if ( $fetched['retryable'] && $usable ) {
                $current = self::stored_row( $id );
                if ( null !== $current && self::is_usable( $current, $url ) && self::within_grace( $current, time() ) ) {
                    return $current;
                }
            }
            if ( ! $fetched['retryable'] ) {
                self::forget( $row );
            }
            return self::refusal( $fetched['reason'], $fetched['detail'], $url );
        }
        Client_Metadata_Support::record_fetch( false );

        $checked = self::validate_document( $url, $fetched['body'] );
        if ( ! $checked['ok'] ) {
            self::forget( $row );
            return self::refusal( $checked['reason'], $checked['detail'], $url );
        }
        return self::store( $url, $id, $checked['fields'], $fetched['ttl'], $row );
    }

    public static function is_stored( string $url ): bool {
        $row = self::stored_row( hash( 'sha256', $url ) );
        return null !== $row && self::is_usable( $row, $url );
    }

    public static function probe( string $url ): string {
        if ( ! self::valid_url( $url ) ) {
            return self::REASON_INVALID_URL . ':';
        }
        $fetched = self::fetch( $url );
        if ( ! $fetched['ok'] ) {
            return $fetched['reason'] . ':' . $fetched['detail'];
        }
        $checked = self::validate_document( $url, $fetched['body'] );
        return $checked['ok'] ? '' : $checked['reason'] . ':' . $checked['detail'];
    }

    public static function validate_document( string $url, string $body ): array {
        $doc = json_decode( $body, true );
        if ( ! is_array( $doc ) || ( array() !== $doc && array_keys( $doc ) === range( 0, count( $doc ) - 1 ) ) ) {
            return self::invalid( 'not_a_json_object' );
        }
        if ( ! isset( $doc['client_id'] ) || ! is_string( $doc['client_id'] ) || $doc['client_id'] !== $url ) {
            return self::invalid( 'client_id_mismatch' );
        }
        if ( array_key_exists( 'client_secret', $doc ) || array_key_exists( 'client_secret_expires_at', $doc ) ) {
            return self::invalid( 'client_secret_published' );
        }

        $name = isset( $doc['client_name'] ) && is_string( $doc['client_name'] )
            ? mb_substr( sanitize_text_field( str_replace( array( "\r", "\n" ), '', $doc['client_name'] ) ), 0, Client_Registry::MAX_CLIENT_NAME_LENGTH )
            : '';
        if ( '' === $name ) {
            return self::invalid( 'client_name_missing' );
        }

        if ( ! isset( $doc['redirect_uris'] ) || ! is_array( $doc['redirect_uris'] ) || array() === $doc['redirect_uris'] ) {
            return self::invalid( 'redirect_uris_missing' );
        }
        if ( count( $doc['redirect_uris'] ) > self::MAX_REDIRECT_URIS ) {
            return self::invalid( 'too_many_redirect_uris' );
        }
        $redirect_uris = array();
        foreach ( $doc['redirect_uris'] as $uri ) {
            if ( is_string( $uri ) && Client_Registry::redirect_uri_acceptable( $uri ) ) {
                $redirect_uris[] = sanitize_url( $uri, Client_Registry::redirect_uri_allowed_protocols() );
            }
        }
        $redirect_uris = array_values( array_unique( array_filter( $redirect_uris ) ) );
        if ( array() === $redirect_uris ) {
            return self::invalid( 'redirect_uris_unusable' );
        }

        $method = $doc['token_endpoint_auth_method'] ?? Client_Registry::AUTH_METHOD_NONE;
        if ( Client_Registry::AUTH_METHOD_NONE !== $method ) {
            $offered = $doc['token_endpoint_auth_methods_supported'] ?? null;
            if ( ! is_array( $offered ) || ! in_array( Client_Registry::AUTH_METHOD_NONE, $offered, true ) ) {
                return array(
                    'ok'     => false,
                    'reason' => self::REASON_UNSUPPORTED_AUTH,
                    'detail' => is_string( $method ) ? sanitize_key( $method ) : 'not_a_string',
                );
            }
        }

        $grant_types = array( 'authorization_code' );
        if ( isset( $doc['grant_types'] ) && is_array( $doc['grant_types'] ) ) {
            $declared    = array_values( array_intersect( self::GRANT_TYPES, $doc['grant_types'] ) );
            $grant_types = array() === $declared ? $grant_types : $declared;
        }

        return array(
            'ok'     => true,
            'fields' => array(
                'client_name'   => $name,
                'redirect_uris' => $redirect_uris,
                'grant_types'   => $grant_types,
            ),
        );
    }

    public static function can_read_ipv6(): bool {
        return ! self::$ipv6_lookup_off && function_exists( 'dns_get_record' ) && defined( 'DNS_AAAA' );
    }

    public static function host_is_public( string $host ): bool {
        $addresses = self::resolve( $host );
        return null !== $addresses && self::all_public( $addresses );
    }

    public static function is_public_ip( string $ip ): bool {
        $packed = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A malformed value warns; false is the answer.
        if ( false === $packed ) {
            return false;
        }
        if ( 16 === strlen( $packed ) && 0 === strncmp( $packed, str_repeat( "\0", 10 ) . "\xff\xff", 12 ) ) {
            $packed = substr( $packed, 12 );
        }
        foreach ( self::SPECIAL_USE as $block ) {
            list( $network, $bits ) = explode( '/', $block );
            $base = inet_pton( $network );
            if ( strlen( $base ) === strlen( $packed ) && self::same_prefix( $packed, $base, (int) $bits ) ) {
                return false;
            }
        }
        return true;
    }

    private static function fetch( string $url ): array {
        $addresses = self::resolve( self::host( $url ) );
        if ( null === $addresses ) {
            return self::fetch_failure( 'ipv6_unchecked', true, true );
        }
        if ( ! self::all_public( $addresses ) ) {
            return self::fetch_failure( 'host_not_public', false, false );
        }
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => self::TIMEOUT,
                'redirection'         => 0,
                'limit_response_size' => self::MAX_BYTES + 1,
                'user-agent'          => 'EasyMCPAI-OAuth/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ),
                'headers'             => array( 'Accept' => 'application/json' ),
            )
        );
        if ( is_wp_error( $response ) ) {
            return self::fetch_failure( 'no_response', true, true );
        }
        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return self::fetch_failure( 'http_' . $code, false, $code >= 500 || 429 === $code );
        }
        $body = (string) wp_remote_retrieve_body( $response );
        if ( strlen( $body ) > self::MAX_BYTES ) {
            return self::fetch_failure( 'too_large', false, false, self::REASON_INVALID );
        }
        return array(
            'ok'   => true,
            'body' => $body,
            'ttl'  => self::ttl( wp_remote_retrieve_header( $response, 'cache-control' ) ),
        );
    }

    private static function fetch_failure( string $detail, bool $transport, bool $retryable, string $reason = self::REASON_FETCH_FAILED ): array {
        return array(
            'ok'        => false,
            'reason'    => $reason,
            'detail'    => $detail,
            'transport' => $transport,
            'retryable' => $retryable,
        );
    }

    private static function ttl( $header ): int {
        $value = strtolower( is_array( $header ) ? implode( ',', $header ) : (string) $header );
        if ( preg_match( '/(?:^|,)\s*(?:no-store|no-cache)\b/', $value ) ) {
            return self::MIN_TTL;
        }
        if ( preg_match( '/(?:^|,)\s*max-age\s*=\s*"?(\d+)/', $value, $m ) ) {
            return max( self::MIN_TTL, min( self::MAX_TTL, (int) $m[1] ) );
        }
        return self::DEFAULT_TTL;
    }

    private static function all_public( array $addresses ): bool {
        if ( array() === $addresses ) {
            return false;
        }
        foreach ( $addresses as $address ) {
            if ( ! self::is_public_ip( $address ) ) {
                return false;
            }
        }
        return true;
    }

    private static function resolve( string $host ): ?array {
        if ( null !== self::$resolver ) {
            $addresses = call_user_func( self::$resolver, $host );
            return null === $addresses ? null : array_values( array_filter( (array) $addresses, 'is_string' ) );
        }
        $aaaa = null;
        if ( self::can_read_ipv6() ) {
            $aaaa = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A failed lookup warns; false is the answer.
        }
        return self::addresses( gethostbynamel( $host ), $aaaa );
    }

    private static function addresses( $ipv4, $aaaa ): ?array {
        $addresses = is_array( $ipv4 ) ? array_values( array_filter( $ipv4, 'is_string' ) ) : array();
        if ( ! is_array( $aaaa ) ) {
            return array() !== $addresses && ! self::all_public( $addresses ) ? $addresses : null;
        }
        foreach ( $aaaa as $record ) {
            if ( is_array( $record ) && isset( $record['ipv6'] ) && is_string( $record['ipv6'] ) ) {
                $addresses[] = $record['ipv6'];
            }
        }
        return $addresses;
    }

    private static function same_prefix( string $a, string $b, int $bits ): bool {
        $bytes = intdiv( $bits, 8 );
        if ( 0 !== strncmp( $a, $b, $bytes ) ) {
            return false;
        }
        $rest = $bits % 8;
        if ( 0 === $rest ) {
            return true;
        }
        $mask = ( 0xff << ( 8 - $rest ) ) & 0xff;
        return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
    }

    private static function refusal( string $reason, string $detail, string $url ): \WP_Error {
        $host = self::host( $url );
        switch ( $reason ) {
            case self::REASON_INVALID_URL:
                $code    = 'invalid_client';
                $message = __( 'This application identifies itself with a client ID this site cannot use. A client ID that is a URL must use HTTPS and a host name, have a path, and carry no query string.', 'easy-mcp-ai' );
                break;
            case self::REASON_FETCH_FAILED:
                $code    = 'invalid_client';
                /* translators: 1: host name, 2: what failed, such as http_404 */
                $message = sprintf( __( 'This site could not read the application\'s registration from %1$s (%2$s). Try again in a moment.', 'easy-mcp-ai' ), $host, $detail );
                break;
            case self::REASON_UNSUPPORTED_AUTH:
                $code    = 'invalid_client';
                $message = __( 'The application\'s registration asks to authenticate in a way this site does not support. Applications that sign in with a client ID URL must be public clients.', 'easy-mcp-ai' );
                break;
            case self::REASON_CLIENT_CAP:
                $code    = 'server_error';
                $message = __( 'Maximum number of registered clients has been reached.', 'easy-mcp-ai' );
                break;
            case self::REASON_STORE_FAILED:
                $code    = 'server_error';
                $message = __( 'This site could not save the application\'s registration. Please try again.', 'easy-mcp-ai' );
                break;
            default:
                $code    = 'invalid_client';
                /* translators: 1: host name, 2: what is wrong, such as client_id_mismatch */
                $message = sprintf( __( 'The application\'s registration at %1$s is not valid (%2$s).', 'easy-mcp-ai' ), $host, $detail );
        }
        return new \WP_Error(
            $code,
            $message,
            array(
                'status'      => 400,
                'no_redirect' => true,
                'log_reason'  => $reason,
                'log_detail'  => $detail,
            )
        );
    }

    private static function invalid( string $detail ): array {
        return array(
            'ok'     => false,
            'reason' => self::REASON_INVALID,
            'detail' => $detail,
        );
    }

    private static function stored_row( string $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; a sign-in must read the current row.
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE client_id = %s LIMIT 1", $id ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return is_object( $row ) ? $row : null;
    }

    private static function is_usable( $row, string $url ): bool {
        return 1 === (int) ( $row->is_active ?? 0 ) && isset( $row->metadata_url ) && $url === (string) $row->metadata_url;
    }

    private static function is_fresh( $row, int $now ): bool {
        $expires = isset( $row->metadata_expires_at ) ? strtotime( (string) $row->metadata_expires_at . ' UTC' ) : false;
        return false !== $expires && $expires > $now;
    }

    private static function within_grace( $row, int $now ): bool {
        $fetched = isset( $row->metadata_fetched_at ) ? strtotime( (string) $row->metadata_fetched_at . ' UTC' ) : false;
        return false !== $fetched && $now - $fetched <= self::STALE_GRACE;
    }

    private static function forget( $row ): void {
        if ( null === $row || ! isset( $row->client_id ) ) {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
        $wpdb->update(
            $table,
            array(
                'metadata_fetched_at' => null,
                'metadata_expires_at' => null,
            ),
            array( 'client_id' => (string) $row->client_id ),
            array( '%s', '%s' ),
            array( '%s' )
        );
    }

    private static function lists( $row, string $redirect_uri ): bool {
        $registered = json_decode( (string) ( $row->redirect_uris ?? '' ), true );
        return is_array( $registered ) && Client_Registry::redirect_uri_is_registered( $redirect_uri, $registered );
    }

    private static function store( string $url, string $id, array $fields, int $ttl, $row ) {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $now   = current_time( 'mysql', true );
        $data  = array(
            'client_name'         => $fields['client_name'],
            'redirect_uris'       => wp_json_encode( $fields['redirect_uris'] ),
            'grant_types'         => wp_json_encode( $fields['grant_types'] ),
            'response_types'      => wp_json_encode( array( 'code' ) ),
            'metadata_url'        => $url,
            'metadata_fetched_at' => $now,
            'metadata_expires_at' => gmdate( 'Y-m-d H:i:s', time() + $ttl ),
            'is_active'           => 1,
        );
        $formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

        if ( null === $row && self::at_client_cap() ) {
            $row = self::stored_row( $id );
            if ( null === $row ) {
                return self::refusal( self::REASON_CLIENT_CAP, '', $url );
            }
        }
        if ( null === $row ) {
            $client_ip = class_exists( '\Easy_MCP_AI\Client_IP' ) ? (string) \Easy_MCP_AI\Client_IP::get() : '';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Plugin-owned table write.
            $inserted = $wpdb->insert(
                $table,
                array(
                    'client_id'     => $id,
                    'scope'         => '',
                    'created_at'    => $now,
                    'created_by_ip' => $client_ip,
                ) + $data,
                array_merge( array( '%s', '%s', '%s', '%s' ), $formats )
            );
            if ( false !== $inserted ) {
                $data = array();
            }
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin-owned table write.
        if ( array() !== $data && false === $wpdb->update( $table, $data, array( 'client_id' => $id ), $formats, array( '%s' ) ) ) {
            self::forget( $row );
            return self::refusal( self::REASON_STORE_FAILED, '', $url );
        }

        $stored = self::stored_row( $id );
        return null !== $stored && self::is_usable( $stored, $url ) ? $stored : self::refusal( self::REASON_STORE_FAILED, '', $url );
    }

    private static function at_client_cap(): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'easy_mcp_ai_oauth_clients';
        $max   = (int) \Easy_MCP_AI\Config::get( 'easy_mcp_ai_oauth_max_clients', Client_Registry::DEFAULT_MAX_CLIENTS );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table prefixed by $wpdb->prefix; the cap needs a live count.
        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE is_active = %d", 1 ) );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $count >= $max;
    }
}
