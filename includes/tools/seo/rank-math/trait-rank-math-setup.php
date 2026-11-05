<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Rank_Math_Setup {

	protected function require_rank_math_ready( string $purpose ): void {
		if ( ! function_exists( 'rank_math' ) || ! class_exists( '\\RankMath\\Helper' ) ) {
			throw new \RuntimeException( sprintf( 'Rank Math SEO is not active on this site. Install and activate Rank Math SEO to %s.', $purpose ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( is_callable( array( '\\RankMath\\Helper', 'is_invalid_registration' ) ) && \RankMath\Helper::is_invalid_registration() ) {
			throw new \RuntimeException( 'Rank Math is installed but its setup is not finished, so it loads none of its modules. Connect or skip the account step of its setup wizard under Rank Math in wp-admin.' );
		}
	}
}
