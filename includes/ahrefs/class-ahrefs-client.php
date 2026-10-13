<?php
namespace Easy_MCP_AI\Ahrefs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

















class Ahrefs_Client {

	const OPTION_API_KEY = 'easy_mcp_ai_ahrefs_api_key';

	
	const CIPHER_VERSION    = "v2\x00";
	const CIPHER_PREFIX_LEN = 3;
	const HKDF_INFO         = 'easy_mcp_ai_ahrefs_v2';

	private static function derive_key(): string {
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			throw new \RuntimeException(
				'Ahrefs credentials are unavailable: SECURE_AUTH_KEY and SECURE_AUTH_SALT must be defined in wp-config.php.'
			);
		}
		$material = SECURE_AUTH_KEY . SECURE_AUTH_SALT;
		if ( strlen( $material ) < 64 || false !== strpos( $material, 'put your unique phrase here' ) ) {
			throw new \RuntimeException(
				'Ahrefs credentials are unavailable: WordPress security salts are still placeholder values. Generate fresh salts and re-save credentials.'
			);
		}
		return hash_hkdf( 'sha256', $material, 32, self::HKDF_INFO );
	}

	public static function encrypt( string $plaintext ): string {
		$key = self::derive_key();
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		return base64_encode( self::CIPHER_VERSION . $iv . $tag . $ct ); // phpcs:ignore
	}

	public static function decrypt( string $encrypted ) {
		$raw = base64_decode( $encrypted, true ); // phpcs:ignore
		if ( false === $raw ) {
			return false;
		}
		if ( strlen( $raw ) <= self::CIPHER_PREFIX_LEN + 28 ) {
			return false;
		}
		$prefix = substr( $raw, 0, self::CIPHER_PREFIX_LEN );
		if ( self::CIPHER_VERSION !== $prefix ) {
			return false;
		}
		$key = self::derive_key();
		$raw = substr( $raw, self::CIPHER_PREFIX_LEN );
		$iv  = substr( $raw, 0, 12 );
		$tag = substr( $raw, 12, 16 );
		$ct  = substr( $raw, 28 );
		return openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
	}

	




	public static function has_api_key(): bool {
		return '' !== (string) \get_option( self::OPTION_API_KEY, '' );
	}

	const ENDPOINT     = 'https://api.ahrefs.com/v3/public/domain-rating-free';
	const ALLOWED_HOST = 'api.ahrefs.com';

	








	public function verify_key(): void {
		$key = $this->get_api_key();

		
		
		if ( self::ALLOWED_HOST !== \wp_parse_url( self::ENDPOINT, PHP_URL_HOST ) ) {
			throw new \RuntimeException( 'Ahrefs client: refusing to call non-Ahrefs host.' );
		}

		$response = \wp_remote_get(
			\add_query_arg( array( 'target' => 'ahrefs.com', 'output' => 'json' ), self::ENDPOINT ),
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'user-agent'          => 'EasyMCPAI/' . ( defined( 'EASY_MCP_AI_VERSION' ) ? EASY_MCP_AI_VERSION : '0' ),
				'headers'             => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $key,
				),
				'limit_response_size' => 1048576,
			)
		);

		if ( \is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Could not reach Ahrefs: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			throw new \RuntimeException( 'Ahrefs rejected this key. Check that it is an APIv3 key from Account settings → API keys.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( "Ahrefs returned HTTP {$code}." ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$parsed = json_decode( (string) \wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $parsed ) || ! isset( $parsed['domain_rating'] ) ) {
			throw new \RuntimeException( 'Ahrefs returned an unexpected response shape.' );
		}
	}

	




	public function get_api_key(): string {
		$enc = \get_option( self::OPTION_API_KEY, '' );
		if ( empty( $enc ) ) {
			throw new \RuntimeException( 'Ahrefs API key not configured. Add one at Easy MCP AI → Tools → External data → Ahrefs. The key is free — create it in your Ahrefs account under Account settings → API keys.' );
		}
		$plain = self::decrypt( $enc );
		if ( false === $plain || '' === $plain ) {
			throw new \RuntimeException( 'Failed to decrypt Ahrefs API key. Re-save it in Easy MCP AI → Tools → External data.' );
		}
		return $plain;
	}
}
