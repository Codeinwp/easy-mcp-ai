<?php
namespace Easy_MCP_AI\DFS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}







class DataforSEO_Client {

	

	const OPTION_LOGIN        = 'easy_mcp_ai_dfs_login';
	const OPTION_API_PASSWORD = 'easy_mcp_ai_dfs_api_password';

	

	





	const TRANSIENT_BALANCE_PREFIX = 'easy_mcp_ai_dfs_balance_';

	





	public static function balance_transient_key( string $login ): string {
		return self::TRANSIENT_BALANCE_PREFIX . md5( $login );
	}

	

	const BASE_URL = 'https://api.dataforseo.com';

	

	const STATUS_OK                  = 20000;
	const STATUS_AUTH_FAILED         = 40100;
	const STATUS_PAYMENT_REQUIRED    = 40200;
	const STATUS_RATE_LIMIT          = 40202;
	const STATUS_COST_LIMIT          = 40203;
	const STATUS_NOT_SUBSCRIBED      = 40204;
	const STATUS_IP_NOT_WHITELISTED  = 40207;
	const STATUS_TOO_MANY_CONCURRENT = 40209;
	const STATUS_INSUFFICIENT_FUNDS  = 40210;
	const STATUS_NOT_FOUND           = 40400;
	const STATUS_INVALID_FIELD       = 40501;
	const STATUS_INTERNAL_ERROR      = 50000;

	

	const CIPHER_VERSION    = "v2\x00";
	const CIPHER_PREFIX_LEN = 3;
	const HKDF_INFO         = 'easy_mcp_ai_dfs_v2';

	






	private static function derive_key(): string {
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			throw new \RuntimeException(
				'DataforSEO credentials are unavailable: SECURE_AUTH_KEY and SECURE_AUTH_SALT must be defined in wp-config.php. Generate fresh values at https://api.wordpress.org/secret-key/1.1/salt/, update wp-config.php, then re-save your DataforSEO credentials in Easy MCP AI → Tools → External data.'
			);
		}
		$material = SECURE_AUTH_KEY . SECURE_AUTH_SALT;
		if ( strlen( $material ) < 64 || false !== strpos( $material, 'put your unique phrase here' ) ) {
			throw new \RuntimeException(
				'DataforSEO credentials are unavailable: WordPress security salts are still set to placeholder values. Generate fresh values at https://api.wordpress.org/secret-key/1.1/salt/ (or run: wp config shuffle-salts), update wp-config.php, then re-save your DataforSEO credentials in Easy MCP AI → Tools → External data.'
			);
		}
		return hash_hkdf( 'sha256', $material, 32, self::HKDF_INFO );
	}

	





	public static function encrypt( string $plaintext ): string {
		$key = self::derive_key();
		$iv  = random_bytes( 12 );
		$tag = '';
		$ct  = openssl_encrypt( $plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16 );
		return base64_encode( self::CIPHER_VERSION . $iv . $tag . $ct ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- AES-256-GCM ciphertext encoding for safe storage in wp_options.
	}

	





	public static function decrypt( string $encrypted ) {
		$raw = base64_decode( $encrypted, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decodes AES-256-GCM ciphertext stored in wp_options.
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

	





	public static function get_credentials(): array {
		$login = \get_option( self::OPTION_LOGIN, '' );
		$password = \get_option( self::OPTION_API_PASSWORD, '' );

		if ( empty( $login ) || empty( $password ) ) {
			throw new \RuntimeException(
				'DataforSEO credentials not configured. Go to Easy MCP AI → Tools → External data.'
			);
		}

		$decrypted_login = self::decrypt( $login );
		if ( false === $decrypted_login ) {
			throw new \RuntimeException(
				'Failed to decrypt DataforSEO login. The stored value could not be decrypted with the current WordPress security salts (SECURE_AUTH_KEY/SECURE_AUTH_SALT). This usually means the salts changed after the credentials were saved. Re-save your credentials in Easy MCP AI → Tools → External data.'
			);
		}

		$decrypted_password = self::decrypt( $password );
		if ( false === $decrypted_password ) {
			throw new \RuntimeException(
				'Failed to decrypt DataforSEO API password. The stored value could not be decrypted with the current WordPress security salts (SECURE_AUTH_KEY/SECURE_AUTH_SALT). This usually means the salts changed after the credentials were saved. Re-save your credentials in Easy MCP AI → Tools → External data.'
			);
		}

		return array(
			'login'       => $decrypted_login,
			'api_password' => $decrypted_password,
		);
	}

	








	public static function auth_header( ?array $creds = null ): string {
		if ( null === $creds ) {
			$creds = self::get_credentials();
		}
		$credentials = $creds['login'] . ':' . $creds['api_password'];
		return 'Basic ' . base64_encode( $credentials ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic Auth encoding (RFC 7617).
	}

	











	public function request( string $method, string $url, ?array $body = null, bool $admin_context = false ): array {
		
		$scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = \wp_parse_url( $url, PHP_URL_HOST );

		if ( 'https' !== $scheme || 'api.dataforseo.com' !== $host ) {
			throw new \RuntimeException(
				'DataforSEO client: refusing to attach credentials to non-DataforSEO host.'
			);
		}

		
		
		
		
		

		
		$creds = self::get_credentials();

		$args = array(
			'method'              => strtoupper( $method ),
			'headers'             => array(
				'Authorization' => self::auth_header( $creds ),
			),
			'timeout'             => 30,
			
			
			'limit_response_size' => 33554432,
		);

		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = \wp_json_encode( $body );
		}

		$response = \wp_remote_request( $url, $args );

		if ( \is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'DataforSEO API request failed (transport error): ' . $response->get_error_message() // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$raw  = \wp_remote_retrieve_body( $response );

		
		if ( ( 401 === $code || 403 === $code ) && $admin_context ) {
			throw new \RuntimeException(
				'DataforSEO authentication failed for the configured credentials. Verify the API password (different from account password) at app.dataforseo.com/api-access.'
			);
		}

		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		
		if ( $code < 200 || $code >= 300 ) {
			$excerpt = \mb_substr( $raw, 0, 200 );
			throw new \RuntimeException(
				"DataforSEO HTTP error {$code}: {$excerpt}" // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			throw new \RuntimeException(
				'DataforSEO returned non-JSON response.'
			);
		}

		return $this->parse_envelope( $decoded );
	}

	











	public function post( string $url, array $body, bool $admin_context = false ): array {
		
		
		$is_list = ( count( $body ) === 0 ) || ( array_keys( $body ) === range( 0, count( $body ) - 1 ) );

		if ( ! $is_list ) {
			$body = array( $body );
		}

		return $this->request( 'POST', $url, $body, $admin_context );
	}

	







	public function get( string $url, bool $admin_context = false ): array {
		return $this->request( 'GET', $url, null, $admin_context );
	}

	






	public static function map_status_code( int $code, string $message ): string {
		switch ( $code ) {
			case 20000:
				return '';
			case 40100:
				return 'DataforSEO authentication failed. Verify your login and API password at Easy MCP AI → Tools → External data. The API password is generated at app.dataforseo.com/api-access and is different from your account password.';
			case 40200:
				return 'DataforSEO requires payment to access this endpoint. Activate or upgrade your plan at app.dataforseo.com.';
			case 40202:
				return 'DataforSEO rate limit exceeded (2000 calls/minute global; Google Ads Live endpoints capped at 12 calls/minute). Retry after a minute.';
			case 40203:
				return 'DataforSEO cost limit exceeded for this account. Raise the cost limit in app.dataforseo.com or wait for the next reset.';
			case 40204:
				return 'DataforSEO refused this request: your account is not subscribed to the API family this tool uses (SERP / Keywords / Labs / Backlinks / OnPage). Activate the plan at app.dataforseo.com.';
			case 40207:
				return 'DataforSEO refused the request because this server\'s IP is not on your account\'s whitelist. Whitelist the IP at app.dataforseo.com or remove the whitelist requirement.';
			case 40209:
				return 'DataforSEO refused the request: too many simultaneous queries (OnPage caps at 30 concurrent). Reduce concurrency and retry.';
			case 40210:
				return 'DataforSEO account balance is too low to complete this request. Top up at app.dataforseo.com/billing and retry.';
			case 40400:
			case 40401:
			case 40402:
				return 'DataforSEO endpoint or resource not found.';
			case 40501:
			case 40502:
			case 40503:
			case 40506:
				return sprintf(
					'DataforSEO rejected the request body: %s. Likely a malformed parameter — check the tool\'s input schema.',
					$message
				);
			default:
				if ( $code >= 50000 && $code < 60000 ) {
					return sprintf(
						'DataforSEO server error: %s. Retry in a few minutes.',
						$message
					);
				}
				return sprintf(
					'DataforSEO error %d: %s.',
					$code,
					$message
				);
		}
	}

	












	public function parse_envelope( array $response ): array {
		$outer_code    = (int) ( $response['status_code'] ?? 0 );
		$outer_message = (string) ( $response['status_message'] ?? '' );

		
		if ( 20000 !== $outer_code ) {
			throw new \RuntimeException(
				self::map_status_code( $outer_code, $outer_message ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$tasks = (array) ( $response['tasks'] ?? array() );
		if ( empty( $tasks ) ) {
			return array(
				'tasks' => array(),
				'cost'  => (float) ( $response['cost'] ?? 0 ),
			);
		}

		$successes = array();
		$errors    = array();

		foreach ( $tasks as $i => $task ) {
			$task_code    = (int) ( $task['status_code'] ?? 0 );
			$task_message = (string) ( $task['status_message'] ?? '' );

			if ( 20000 !== $task_code ) {
				$errors[] = array(
					'index'   => $i,
					'code'    => $task_code,
					'message' => $task_message,
				);
			} else {
				$successes[] = $task;
			}
		}

		
		if ( empty( $successes ) && ! empty( $errors ) ) {
			$first_error = $errors[0];
			throw new \RuntimeException(
				self::map_status_code( $first_error['code'], $first_error['message'] ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			);
		}

		$result = array(
			'tasks' => $successes,
			'cost'  => (float) ( $response['cost'] ?? 0 ),
		);

		if ( ! empty( $errors ) ) {
			$result['_errors'] = $errors;
		}

		return $result;
	}

	












	public function get_balance( bool $admin_context = false ): array {
		$envelope = $this->get( self::BASE_URL . '/v3/appendix/user_data', $admin_context );
		$tasks    = (array) ( $envelope['tasks'] ?? array() );

		if ( empty( $tasks ) ) {
			throw new \RuntimeException(
				'DataforSEO returned unexpected balance payload.'
			);
		}

		$task   = $tasks[0];
		$result = (array) ( $task['result'][0] ?? null );

		if ( empty( $result ) ) {
			throw new \RuntimeException(
				'DataforSEO returned unexpected balance payload.'
			);
		}

		return array(
			'login'    => (string) ( $result['login'] ?? '' ),
			'balance'  => (float) ( $result['money']['balance'] ?? 0 ),
			'total'    => (float) ( $result['money']['total'] ?? 0 ),
			'currency' => 'USD',
			'timezone' => (string) ( $result['timezone'] ?? '' ),
		);
	}
}
