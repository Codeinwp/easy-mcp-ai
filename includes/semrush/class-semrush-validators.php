<?php
namespace Easy_MCP_AI\Semrush;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}




class Semrush_Validators {

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

	public static function validate_target_type( string $t ): void {
		$allowed = array( 'root_domain', 'domain', 'url' );
		if ( ! in_array( $t, $allowed, true ) ) {
			throw new \InvalidArgumentException( 'target_type must be one of: root_domain, domain, url.' );
		}
	}

	public static function validate_database( string $d ): void {
		if ( '' === $d || strlen( $d ) > 8 || ! preg_match( '/^[a-z]{2,8}$/', $d ) ) {
			throw new \InvalidArgumentException( 'database must be a 2–8 char lowercase code (e.g. us, uk, de).' );
		}
	}

	public static function validate_phrase( string $p ): void {
		$len = strlen( $p );
		if ( $len < 1 || $len > 80 ) {
			throw new \InvalidArgumentException( 'phrase must be 1–80 characters.' );
		}
		$tokens = preg_split( '/\s+/', trim( $p ) );
		if ( is_array( $tokens ) && count( $tokens ) > 10 ) {
			throw new \InvalidArgumentException( 'phrase must not exceed 10 whitespace-separated tokens.' );
		}
	}

	public static function validate_display_filter( string $f ): void {
		if ( ! preg_match( '/^[+-]\|[A-Za-z]{1,4}\|(?:Eq|Lt|Gt|Co|Bw|Ew|Nc|Nb|Ne)\|[^|]+(\|.+)?$/', $f ) ) {
			throw new \InvalidArgumentException( 'display_filter has invalid shape. Expected e.g. +|Nq|Gt|100' );
		}
	}
}
