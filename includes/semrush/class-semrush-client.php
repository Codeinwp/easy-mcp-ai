<?php
namespace Easy_MCP_AI\Semrush;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}









class Semrush_Client {

	const OPTION_API_KEY = 'easy_mcp_ai_semrush_api_key';

	const BASE_URL_SEO       = 'https://api.semrush.com/';
	const BASE_URL_BACKLINKS = 'https://api.semrush.com/analytics/v1/';
	const BASE_URL_BALANCE   = 'https://www.semrush.com/users/countapiunits.html';

	const ALLOWED_HOSTS = array( 'api.semrush.com', 'www.semrush.com' );

	
	const ERR_LIMIT_EXCEEDED       = 30;
	const ERR_PARAM_ACTION         = 40;
	const ERR_PARAM_TYPE           = 41;
	const ERR_PARAM_DOMAIN         = 42;
	const ERR_PARAM_PHRASE         = 43;
	const ERR_PARAM_URL            = 44;
	const ERR_PARAM_VS_DOMAIN      = 45;
	const ERR_NOTHING_FOUND        = 50;  
	const ERR_KEY_HASH_FAILURE     = 70;
	const ERR_INVALID_IMPORT_KEY   = 110;
	const ERR_WRONG_KEY_ID_PAIR    = 120;
	const ERR_HASH_FORMAT          = 121;
	const ERR_KEY_FORMAT           = 122;
	const ERR_API_DISABLED         = 130;
	const ERR_LIMIT_EXCEEDED_2     = 131;
	const ERR_BALANCE_ZERO         = 132;
	const ERR_DB_ACCESS_DENIED     = 133;
	const ERR_TOTAL_LIMIT_EXCEEDED = 134;

	const BACKLINKS_TYPES = array(
		'backlinks_overview',
		'backlinks',
		'backlinks_refdomains',
		'backlinks_anchors',
	);

	const COST_TABLE = array(
		'domain_rank'            => array( 'base' => 10, 'shape' => 'flat' ),
		'domain_organic'         => array( 'base' => 10, 'shape' => 'per_row' ),
		'domain_organic_organic' => array( 'base' => 40, 'shape' => 'per_row' ),
		'phrase_this'            => array( 'base' => 10, 'shape' => 'flat' ),
		'phrase_related'         => array( 'base' => 40, 'shape' => 'per_row' ),
		'phrase_kdi'             => array( 'base' => 50, 'shape' => 'per_row' ),
		'phrase_questions'       => array( 'base' => 40, 'shape' => 'per_row' ),
		'backlinks_overview'     => array( 'base' => 40, 'shape' => 'flat' ),
		'backlinks'              => array( 'base' => 40, 'shape' => 'per_row' ),
		'backlinks_refdomains'   => array( 'base' => 40, 'shape' => 'per_row' ),
		'backlinks_anchors'      => array( 'base' => 40, 'shape' => 'per_row' ),
		'url_organic'            => array( 'base' => 10, 'shape' => 'per_row' ),
	);

	
	const CIPHER_VERSION    = "v2\x00";
	const CIPHER_PREFIX_LEN = 3;
	const HKDF_INFO         = 'easy_mcp_ai_semrush_v2';

	private static function derive_key(): string {
		if ( ! defined( 'SECURE_AUTH_KEY' ) || ! defined( 'SECURE_AUTH_SALT' ) ) {
			throw new \RuntimeException(
				'Semrush credentials are unavailable: SECURE_AUTH_KEY and SECURE_AUTH_SALT must be defined in wp-config.php.'
			);
		}
		$material = SECURE_AUTH_KEY . SECURE_AUTH_SALT;
		if ( strlen( $material ) < 64 || false !== strpos( $material, 'put your unique phrase here' ) ) {
			throw new \RuntimeException(
				'Semrush credentials are unavailable: WordPress security salts are still placeholder values. Generate fresh salts and re-save credentials.'
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
			throw new \RuntimeException( 'Semrush API key not configured. Go to Easy MCP AI → Tools → External data.' );
		}
		$plain = self::decrypt( $enc );
		if ( false === $plain || '' === $plain ) {
			throw new \RuntimeException( 'Failed to decrypt Semrush API key. Re-save credentials in Easy MCP AI → Tools → External data.' );
		}
		return $plain;
	}

	public function base_url_for_type( string $type ): string {
		return in_array( $type, self::BACKLINKS_TYPES, true ) ? self::BASE_URL_BACKLINKS : self::BASE_URL_SEO;
	}

	





	public function request( string $type, array $params ): array {
		$url    = $this->base_url_for_type( $type );
		$scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = \wp_parse_url( $url, PHP_URL_HOST );
		if ( 'https' !== $scheme || ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			throw new \RuntimeException( 'Semrush client: refusing to attach credentials to non-Semrush host.' );
		}

		$api_key   = $this->get_api_key();
		$query     = array_merge( array( 'type' => $type, 'key' => $api_key ), $params );
		$full_url  = \add_query_arg( $query, $url );

		$response = \wp_remote_get( $full_url, array(
			'timeout'             => 30,
			'headers'             => array( 'Accept' => 'text/csv' ),
			
			'limit_response_size' => 33554432,
		) );

		if ( \is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Semrush request failed (transport error): ' . $response->get_error_message() ); // phpcs:ignore
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$body = (string) \wp_remote_retrieve_body( $response );

		if ( 429 === $code ) {
			throw new \RuntimeException( 'Semrush request limit exceeded — back off and retry.' );
		}
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( "Semrush HTTP error: status {$code}." ); // phpcs:ignore
		}

		$body = trim( $body );
		if ( '' === $body ) {
			throw new \RuntimeException( 'Semrush returned an empty response — the database or parameters may be invalid.' );
		}

		if ( preg_match( '/^ERROR\s+(\d+)\s*::\s*(.+)$/is', $body, $m ) ) {
			$err_code = (int) $m[1];
			$err_msg  = trim( $m[2] );
			if ( 50 === $err_code ) {
				return array();
			}
			$this->parse_error( $err_code, $err_msg, $api_key );
		}

		return $this->parse_csv( $body );
	}

	




	public function parse_error( int $code, string $msg, string $api_key = '' ): void {
		if ( '' === $api_key ) {
			try {
				$api_key = $this->get_api_key();
			} catch ( \RuntimeException $e ) {
				
			}
		}
		if ( '' !== $api_key ) {
			$msg = str_replace( $api_key, '[REDACTED]', $msg );
		}

		switch ( $code ) {
			case self::ERR_LIMIT_EXCEEDED:
			case self::ERR_LIMIT_EXCEEDED_2:
				throw new \RuntimeException( 'Semrush request limit exceeded — back off and retry.' );
			case self::ERR_PARAM_ACTION:
			case self::ERR_PARAM_TYPE:
				throw new \RuntimeException( 'Internal plugin error: missing required parameter (Semrush ' . (int) $code . ').' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- $code is an int from a regex match, cast above.
			case self::ERR_PARAM_DOMAIN:
				throw new \RuntimeException( 'Missing required parameter: domain.' );
			case self::ERR_PARAM_PHRASE:
				throw new \RuntimeException( 'Missing required parameter: phrase.' );
			case self::ERR_PARAM_URL:
				throw new \RuntimeException( 'Missing required parameter: url.' );
			case self::ERR_PARAM_VS_DOMAIN:
				throw new \RuntimeException( 'Missing required parameter: vs_domain.' );
			case self::ERR_KEY_HASH_FAILURE:
				throw new \RuntimeException( 'Semrush API key invalid (hash failure). Use the Version 3 key from semrush.com → My Profile → API Keys.' );
			case self::ERR_INVALID_IMPORT_KEY:
				throw new \RuntimeException( 'Semrush API key format is invalid.' );
			case self::ERR_WRONG_KEY_ID_PAIR:
				throw new \RuntimeException( 'Semrush API key/ID pair is wrong.' );
			case self::ERR_HASH_FORMAT:
				throw new \RuntimeException( 'Semrush API key hash malformed.' );
			case self::ERR_KEY_FORMAT:
				throw new \RuntimeException( 'Semrush API key malformed or empty.' );
			case self::ERR_API_DISABLED:
				throw new \RuntimeException( 'Semrush API access is disabled — check your subscription at semrush.com.' );
			case self::ERR_BALANCE_ZERO:
				throw new \RuntimeException( 'Semrush API units balance exhausted. Top up at semrush.com to continue.' );
			case self::ERR_DB_ACCESS_DENIED:
				throw new \RuntimeException( 'Database access denied — your Semrush subscription does not include this database or report family.' );
			case self::ERR_TOTAL_LIMIT_EXCEEDED:
				throw new \RuntimeException( 'Semrush total request limit exceeded.' );
			default:
				throw new \RuntimeException( 'Semrush error ' . $code . ': ' . $msg ); // phpcs:ignore
		}
	}

	




	public function parse_csv( string $body ): array {
		$lines = preg_split( "/\r\n|\r|\n/", $body );
		if ( empty( $lines ) ) {
			return array();
		}
		$lines = array_values( array_filter( $lines, static function ( $l ) { return '' !== trim( $l ); } ) );
		if ( count( $lines ) < 2 ) {
			return array();
		}
		$header = str_getcsv( array_shift( $lines ), ';', '"', '\\' );
		$rows   = array();
		foreach ( $lines as $line ) {
			$cells = str_getcsv( $line, ';', '"', '\\' );
			$row   = array();
			foreach ( $header as $i => $code ) {
				$raw = $cells[ $i ] ?? '';
				$row[ $code ] = self::coerce_value( $code, $raw );
			}
			$rows[] = $row;
		}
		return $rows;
	}

	




	public static function coerce_value( string $code, string $raw ) {
		if ( '' === $raw ) {
			return null;
		}
		$ints   = array( 'Po', 'Pp', 'Pn', 'Pd', 'Nq', 'Nr', 'Or', 'Ad', 'Ot', 'At', 'Oc', 'Ac', 'Kd', 'Rk' );
		$floats = array( 'Cp', 'Co', 'Tr', 'Tc' );
		if ( in_array( $code, $ints, true ) ) {
			return (int) $raw;
		}
		if ( in_array( $code, $floats, true ) ) {
			return (float) $raw;
		}
		return $raw;
	}

	





	public function request_balance(): int {
		$url    = self::BASE_URL_BALANCE;
		$scheme = \wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = \wp_parse_url( $url, PHP_URL_HOST );
		if ( 'https' !== $scheme || ! in_array( $host, self::ALLOWED_HOSTS, true ) ) {
			throw new \RuntimeException( 'Semrush client: refusing to attach credentials to non-Semrush host.' );
		}

		$api_key = $this->get_api_key();
		$full    = \add_query_arg( array( 'key' => $api_key ), $url );

		$response = \wp_remote_get( $full, array(
			'timeout'             => 15,
			
			'limit_response_size' => 1048576,
		) );
		if ( \is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Semrush balance request failed (transport): ' . $response->get_error_message() ); // phpcs:ignore
		}
		$code = (int) \wp_remote_retrieve_response_code( $response );
		$body = trim( (string) \wp_remote_retrieve_body( $response ) );
		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( "Semrush balance HTTP error: status {$code}." ); // phpcs:ignore
		}
		
		if ( preg_match( '/^ERROR\s+(\d+)\s*::\s*(.+)$/is', $body, $m ) ) {
			$this->parse_error( (int) $m[1], trim( $m[2] ) );
		}
		$normalized = str_replace( ',', '', $body );
		if ( ! preg_match( '/^\d+$/', $normalized ) ) {
			throw new \RuntimeException( 'Unexpected balance response from Semrush.' );
		}
		return (int) $normalized;
	}

	




	public function report( string $type, array $params ): array {
		$rows  = $this->request( $type, $params );
		$entry = self::COST_TABLE[ $type ] ?? null;
		if ( empty( $rows ) && null !== $entry && 'flat' === $entry['shape'] ) {
			throw new \RuntimeException( 'Semrush returned no data — the database or parameters may be invalid.' );
		}
		return array(
			'items'           => $rows,
			'_units_cost'     => self::estimate_cost( $type, $rows ),
			'_cost_estimated' => true,
		);
	}

	public static function estimate_cost( string $type, array $rows ): int {
		if ( ! isset( self::COST_TABLE[ $type ] ) ) {
			return 0;
		}
		$entry = self::COST_TABLE[ $type ];
		if ( 'flat' === $entry['shape'] ) {
			return (int) $entry['base'];
		}
		return (int) $entry['base'] * count( $rows );
	}

	


	public function get_balance(): array {
		$balance = $this->request_balance();
		return array(
			'balance'         => $balance,
			'fetched_at'      => gmdate( 'c' ),
			'_units_cost'     => 0,
			'_cost_estimated' => false,
		);
	}

	


	public function test_connection(): array {
		$balance = $this->request_balance();
		return array( 'ok' => true, 'balance' => $balance );
	}
}
