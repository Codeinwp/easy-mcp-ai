<?php
namespace Easy_MCP_AI\SeRanking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}










class SeRanking_Client {

	const OPTION_API_KEY = 'easy_mcp_ai_seranking_api_key';

	const BASE_URL = 'https://api.seranking.com';

	const ALLOWED_HOSTS = array( 'api.seranking.com' );

	
	const CIPHER_VERSION    = "v2\x00";
	const CIPHER_PREFIX_LEN = 3;
	const HKDF_INFO         = 'easy_mcp_ai_seranking_v2';

	private static function derive_key(): string {
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			throw new \RuntimeException(
				'SE Ranking credentials are unavailable: SECURE_AUTH_KEY and SECURE_AUTH_SALT must be defined in wp-config.php.'
			);
		}
		$material = SECURE_AUTH_KEY . SECURE_AUTH_SALT;
		if ( strlen( $material ) < 64 || false !== strpos( $material, 'put your unique phrase here' ) ) {
			throw new \RuntimeException(
				'SE Ranking credentials are unavailable: WordPress security salts are still placeholder values. Generate fresh salts and re-save credentials.'
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

	




	public function get_api_key(): string {
		$enc = \get_option( self::OPTION_API_KEY, '' );
		if ( empty( $enc ) ) {
			throw new \RuntimeException( 'SE Ranking API key not configured. Go to Easy MCP AI → Tools → External data.' );
		}
		$plain = self::decrypt( $enc );
		if ( false === $plain || '' === $plain ) {
			throw new \RuntimeException( 'Failed to decrypt SE Ranking API key. Re-save credentials in Easy MCP AI → Tools → External data.' );
		}
		return $plain;
	}

	













	public function request( string $method, string $path, array $query = array(), array $body = array() ): array {
		$url = self::BASE_URL . $path;

		
		$scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = \wp_parse_url( $url, PHP_URL_HOST );
		if ( 'https' !== $scheme || ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			throw new \RuntimeException( 'SE Ranking client: refusing to attach credentials to non-SE-Ranking host.' );
		}

		if ( ! empty( $query ) ) {
			$url = \add_query_arg( array_map( 'rawurlencode', $query ), $url ); 
		}

		$api_key = $this->get_api_key();

		$args = array(
			'method'      => $method,
			'timeout'     => 30,
			'redirection' => 0, 
			'headers'     => array(
				'Authorization' => 'Token ' . $api_key,
				'Accept'        => 'application/json',
				'User-Agent'    => 'EasyMCPAI/' . EASY_MCP_AI_VERSION,
			),
		);

		if ( ! empty( $body ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = \wp_json_encode( $body );
		}

		
		
		
		
		
		$max_attempts   = 3;
		$retry_statuses = array( 500, 502, 503, 504 );
		$response       = null;
		$code           = 0;

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$response = \wp_remote_request( $url, $args );

			if ( \is_wp_error( $response ) ) {
				if ( $attempt < $max_attempts ) {
					usleep( 250000 * $attempt ); 
					continue;
				}
				$msg = str_replace( $api_key, '[REDACTED]', $response->get_error_message() );
				throw new \RuntimeException( 'SE Ranking request failed (transport error): ' . $msg ); // phpcs:ignore
			}

			$code = (int) \wp_remote_retrieve_response_code( $response );
			if ( in_array( $code, $retry_statuses, true ) && $attempt < $max_attempts ) {
				usleep( 250000 * $attempt );
				continue;
			}
			break;
		}

		$raw  = (string) \wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$this->throw_api_error( $code, is_array( $json ) ? $json : null, $api_key );
		}

		return is_array( $json ) ? $json : array();
	}

	






	public function throw_api_error( int $http, ?array $json, string $key = '' ): void {
		$err  = ( is_array( $json ) && isset( $json['error'] ) && is_array( $json['error'] ) ) ? $json['error'] : array();
		$msg  = isset( $err['message'] ) ? (string) $err['message'] : '';
		$desc = isset( $err['description'] ) ? (string) $err['description'] : '';

		if ( '' === $key ) {
			try {
				$key = $this->get_api_key();
			} catch ( \RuntimeException $e ) {
				
			}
		}
		$redact = static function ( string $s ) use ( $key ): string {
			return ( '' !== $key ) ? str_replace( $key, '[REDACTED]', $s ) : $s;
		};
		$msg  = $redact( $msg );
		$desc = $redact( $desc );

		if ( 403 === $http || false !== stripos( $msg, 'insufficient' ) || false !== stripos( $msg, 'funds' ) ) {
			throw new \RuntimeException( 'SE Ranking credits exhausted (HTTP 403 Insufficient funds). Check balance with wp_seranking_account_balance.' );
		}
		if ( 429 === $http ) {
			throw new \RuntimeException( 'SE Ranking rate limit reached (10 req/s). Retry with backoff.' );
		}
		if ( 400 === $http && false !== stripos( $msg, 'invalid source' ) ) {
			throw new \RuntimeException( 'SE Ranking API error 400: ' . $msg . ( '' !== $desc ? ' — ' . $desc : '' ) ); // phpcs:ignore
		}
		if ( 406 === $http ) {
			throw new \RuntimeException( 'SE Ranking: unsupported output format.' );
		}
		if ( 405 === $http ) {
			throw new \RuntimeException( 'SE Ranking: wrong HTTP method.' );
		}

		throw new \RuntimeException( 'SE Ranking API error ' . $http . ': ' . $msg . ( '' !== $desc ? ' — ' . $desc : '' ) ); // phpcs:ignore
	}

	





	public function get_balance(): array {
		$json = $this->request( 'GET', '/v1/account/subscription' );
		$info = isset( $json['subscription_info'] ) && is_array( $json['subscription_info'] ) ? $json['subscription_info'] : array();
		return array(
			'units_left'  => (int) ( $info['units_left'] ?? 0 ),
			'units_limit' => (int) ( $info['units_limit'] ?? 0 ),
			'status'      => (string) ( $info['status'] ?? '' ),
			
			
			
			'expiration_date' => (string) ( $info['expiration_date'] ?? ( $info['expiraton_date'] ?? '' ) ),
			'fetched_at'      => gmdate( 'c' ),
		);
	}

	


	public function test_connection(): array {
		$balance = $this->get_balance();
		return array( 'ok' => true, 'units_left' => $balance['units_left'] );
	}
}
