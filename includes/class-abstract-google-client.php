<?php
namespace Easy_MCP_AI;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}












abstract class Abstract_Google_Client {

    
    
    
    const CIPHER_VERSION = "v2\x00";
    const CIPHER_PREFIX_LEN = 3;

    

    abstract protected static function product_name(): string;

    

    protected static function derive_key_v2(): string {
        return hash_hkdf( 'sha256', SECURE_AUTH_KEY . SECURE_AUTH_SALT, 32, static::HKDF_INFO );
    }

    public static function encrypt( string $plaintext ): string {
        $key = static::derive_key_v2();
        $iv  = random_bytes( 12 );
        $tag = '';
        $ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
        return base64_encode( static::CIPHER_VERSION . $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- AES-256-GCM ciphertext encoding for safe storage in wp_options.
    }

    


    public static function decrypt( string $encoded ) {
        $raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes AES-256-GCM ciphertext stored in wp_options.
        if ( false === $raw ) {
            return false;
        }
        if ( strlen( $raw ) <= self::CIPHER_PREFIX_LEN + 28 ) {
            return false;
        }
        $prefix = substr( $raw, 0, self::CIPHER_PREFIX_LEN );
        if ( static::CIPHER_VERSION !== $prefix ) {
            return false;
        }
        $key = static::derive_key_v2();
        $raw = substr( $raw, self::CIPHER_PREFIX_LEN );
        $iv  = substr( $raw, 0, 12 );
        $tag = substr( $raw, 12, 16 );
        $ct  = substr( $raw, 28 );
        return openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
    }

    protected static function base64url_encode( string $data ): string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Base64URL encoding required by JWT (RFC 7515) for header/payload/signature segments.
    }

    

    protected static function token_transient_key( string $client_email ): string {
        
        
        return static::TRANSIENT_TOKEN . '_' . md5( $client_email );
    }

    


    public static function get_credentials(): array {
        $stored = \get_option( static::OPTION_JSON, '' );
        if ( empty( $stored ) ) {
            throw new \RuntimeException(
                static::product_name() . ' credentials not configured. Go to Easy MCP AI → Tools → External data.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $json = static::decrypt( $stored );
        if ( false === $json ) {
            throw new \RuntimeException(
                'Failed to decrypt ' . static::product_name() . ' credentials. This usually means your WordPress security salts (SECURE_AUTH_KEY/SECURE_AUTH_SALT) have been rotated since the key was saved. Re-save the service account JSON in Easy MCP AI → Tools → External data.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $creds = json_decode( $json, true );
        if ( ! is_array( $creds ) ) {
            throw new \RuntimeException(
                'Service account JSON is invalid. Re-paste the JSON from Google Cloud Console.'
            );
        }
        foreach ( array( 'private_key', 'client_email', 'token_uri' ) as $field ) {
            if ( empty( $creds[ $field ] ) ) {
                throw new \RuntimeException(
                    "Service account JSON is missing required field: {$field}." // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                );
            }
        }
        if ( 'https://oauth2.googleapis.com/token' !== $creds['token_uri'] ) {
            throw new \RuntimeException(
                'Service account JSON contains an unexpected token_uri. Only https://oauth2.googleapis.com/token is accepted.'
            );
        }
        return $creds;
    }

    


    public static function get_access_token(): string {
        $creds         = static::get_credentials();
        $transient_key = static::token_transient_key( $creds['client_email'] );

        $cached = \get_transient( $transient_key );
        if ( ! empty( $cached ) ) {
            $plain = static::decrypt( $cached );
            if ( false !== $plain && '' !== $plain ) {
                return $plain;
            }
            \delete_transient( $transient_key );
        }

        $now = time();
        $exp = $now + 3600;

        
        
        
        $header  = static::base64url_encode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- See comment above.
        $payload = static::base64url_encode( json_encode( array( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- See comment above.
            'iss'   => $creds['client_email'],
            'scope' => static::SCOPE,
            'aud'   => $creds['token_uri'],
            'exp'   => $exp,
            'iat'   => $now,
        ) ) );
        $signing_input = $header . '.' . $payload;

        $signature = '';
        $ok = openssl_sign( $signing_input, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256 );
        if ( ! $ok ) {
            throw new \RuntimeException(
                'Failed to sign JWT. Check that the private_key in your service account JSON is valid.'
            );
        }
        $jwt = $signing_input . '.' . static::base64url_encode( $signature );

        $response = \wp_remote_post( $creds['token_uri'], array(
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
            'timeout' => 15,
        ) );

        if ( \is_wp_error( $response ) ) {
            throw new \RuntimeException(
                'Google token request failed (transport error): ' . $response->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $body = json_decode( \wp_remote_retrieve_body( $response ), true );
        if ( empty( $body['access_token'] ) ) {
            $err = $body['error_description'] ?? $body['error'] ?? 'unknown error';
            throw new \RuntimeException( "Failed to obtain Google access token: {$err}." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $ttl = max( 60, ( $body['expires_in'] ?? 3600 ) - 60 );
        \set_transient( $transient_key, static::encrypt( $body['access_token'] ), $ttl );

        return $body['access_token'];
    }
}
