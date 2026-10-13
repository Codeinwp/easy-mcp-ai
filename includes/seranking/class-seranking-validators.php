<?php
namespace Easy_MCP_AI\SeRanking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}




class SeRanking_Validators {

	public static function validate_bare_domain( string $d ): void {
		if ( '' === $d ) {
			throw new \InvalidArgumentException( 'domain is required.' );
		}
		if ( strlen( $d ) > 253 ) {
			throw new \InvalidArgumentException( 'domain exceeds maximum length of 253 characters (RFC 1035).' );
		}
		if ( 0 === stripos( $d, 'http://' ) || 0 === stripos( $d, 'https://' ) ) {
			throw new \InvalidArgumentException( 'domain must be a bare domain (no http:// or https:// prefix).' );
		}
		if ( 0 === stripos( $d, 'www.' ) ) {
			throw new \InvalidArgumentException( 'domain must be a bare domain (no www. prefix).' );
		}
		if ( false !== strpos( $d, '/' ) || preg_match( '/\s/', $d ) ) {
			throw new \InvalidArgumentException( 'domain must not contain a path or whitespace.' );
		}
		if ( ! preg_match( '/^(?!-)[a-z0-9-]+(\.[a-z0-9-]+)*\.[a-z]{2,}$/i', $d ) ) {
			throw new \InvalidArgumentException( 'domain has invalid format (expected a bare domain like example.com).' );
		}
	}

	




	public static function validate_source( string $s ): void {
		if ( ! preg_match( '/^[a-z]{2}$/', $s ) ) {
			throw new \InvalidArgumentException( 'source must be a 2-letter lowercase region code (e.g. us, uk, de).' );
		}
	}

	public static function validate_keyword( string $k ): void {
		if ( '' === trim( $k ) ) {
			throw new \InvalidArgumentException( 'keyword is required and must not be empty.' );
		}
	}

	


	public static function validate_keywords( array $k ): void {
		$count = count( $k );
		if ( $count < 1 ) {
			throw new \InvalidArgumentException( 'keywords must contain at least 1 item.' );
		}
		if ( $count > 5000 ) {
			throw new \InvalidArgumentException( 'keywords must not exceed 5000 items.' );
		}
		foreach ( $k as $kw ) {
			if ( ! is_string( $kw ) || '' === trim( $kw ) ) {
				throw new \InvalidArgumentException( 'each keyword must be a non-empty string.' );
			}
		}
	}

	


	public static function validate_target( string $t ): void {
		if ( '' === trim( $t ) ) {
			throw new \InvalidArgumentException( 'target is required.' );
		}
		if ( strlen( $t ) > 2048 ) {
			throw new \InvalidArgumentException( 'target exceeds maximum length of 2048 characters.' );
		}
		if ( preg_match( '/\s/', $t ) ) {
			throw new \InvalidArgumentException( 'target must not contain whitespace.' );
		}
	}

	


	public static function validate_mode( string $m ): void {
		$allowed = array( 'domain', 'host', 'url' );
		if ( ! in_array( $m, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'mode must be one of: domain, host, url.' );
		}
	}

	


	public static function validate_scope( string $s ): void {
		$allowed = array( 'base_domain', 'domain', 'url' );
		if ( ! in_array( $s, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'scope must be one of: base_domain, domain, url.' );
		}
	}

	




	public static function validate_enum( string $v, array $allowed ): void {
		if ( ! in_array( $v, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'value must be one of: ' . implode( ', ', $allowed ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	


	public static function validate_limit( int $n, int $max ): void {
		if ( $n < 1 || $n > $max ) {
			throw new \InvalidArgumentException( 'limit must be between 1 and ' . $max . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}
}
