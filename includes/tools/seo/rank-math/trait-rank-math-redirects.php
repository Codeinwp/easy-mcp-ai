<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
















trait Rank_Math_Redirects {

	




	protected static $rm_redirect_cap = 'rank_math_redirections';

	




	protected static $rm_comparisons = array( 'exact', 'contains', 'start', 'end', 'regex' );

	




	protected static $rm_redirect_codes = array( 301, 302, 307 );

	




	protected static $rm_error_codes = array( 410, 451 );

	public function get_category() {
		return 'rank-math';
	}

	




	public function get_required_capability() {
		return function_exists( 'rank_math' ) ? self::$rm_redirect_cap : 'manage_options';
	}

	





	protected function require_rank_math_redirects(): void {
		if ( ! function_exists( 'rank_math' ) || ! class_exists( '\\RankMath\\Helper' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active on this site. Install and activate Rank Math SEO to manage its redirects.' );
		}
		
		
		if ( is_callable( array( '\\RankMath\\Helper', 'is_invalid_registration' ) ) && \RankMath\Helper::is_invalid_registration() ) {
			throw new \RuntimeException( 'Rank Math is installed but its setup is not finished, so it loads none of its modules. Connect or skip the account step of its setup wizard under Rank Math in wp-admin.' );
		}
		if ( ! \RankMath\Helper::is_module_active( 'redirections' ) || ! class_exists( '\\RankMath\\Redirections\\Redirection' ) || ! class_exists( '\\RankMath\\Redirections\\DB' ) ) {
			throw new \RuntimeException( 'Rank Math\'s Redirections module is switched off. Switch it on under Rank Math › Dashboard › Modules in wp-admin.' );
		}
		if ( ! current_user_can( self::$rm_redirect_cap ) ) {
			throw new \RuntimeException( 'This account cannot manage Rank Math redirects. Rank Math gives its Redirections permission to administrators by default; other roles get it under Rank Math › Role Manager.' );
		}
	}

	





	protected function find_redirect( int $id ): ?array {
		$row = \RankMath\Redirections\DB::get_redirection_by_id( $id );
		return is_array( $row ) && isset( $row['id'] ) ? $row : null;
	}

	




	protected function require_redirect( int $id ): array {
		$row = $this->find_redirect( $id );
		if ( null === $row ) {
			throw new \InvalidArgumentException( sprintf( 'No Rank Math redirect with id %d. List redirects with wp_rm_list_redirects.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $row;
	}

	










	protected function object_links( int $id ): array {
		$rows  = \RankMath\Admin\Database\Database::table( 'rank_math_redirections_cache' )
			->where( 'redirection_id', $id )
			->where( 'object_id', '>', 0 )
			->get( ARRAY_A );
		$links = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$links[] = array(
				'from_url'    => (string) $row['from_url'],
				'object_id'   => (int) $row['object_id'],
				'object_type' => (string) $row['object_type'],
			);
		}
		return $links;
	}

	







	protected function restore_object_links( int $id, array $links ): void {
		$exact = array();
		foreach ( $this->raw_sources( $id ) ?? array() as $source ) {
			if ( is_array( $source ) && 'exact' === ( $source['comparison'] ?? '' ) ) {
				$exact[] = (string) $source['pattern'];
			}
		}
		foreach ( $links as $link ) {
			if ( in_array( $link['from_url'], $exact, true ) ) {
				\RankMath\Redirections\Cache::add( $link + array( 'redirection_id' => $id ) );
			}
		}
	}

	






	protected function raw_sources( int $id ): ?array {
		$row = \RankMath\Admin\Database\Database::table( 'rank_math_redirections' )->where( 'id', $id )->one( ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}
		$sources = maybe_unserialize( $row['sources'] ?? '' );
		return is_array( $sources ) ? array_values( $sources ) : array();
	}

	






	protected function parse_sources( $value ): array {
		if ( ! is_array( $value ) || array() === $value ) {
			throw new \InvalidArgumentException( 'sources must be a non-empty array of { pattern, comparison, ignore_case } objects.' );
		}
		$sources = array();
		foreach ( array_values( $value ) as $i => $source ) {
			if ( ! is_array( $source ) ) {
				throw new \InvalidArgumentException( sprintf( 'sources[%d] must be an object with a pattern.', $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$pattern = $source['pattern'] ?? null;
			if ( ! is_string( $pattern ) || '' === trim( $pattern ) ) {
				throw new \InvalidArgumentException( sprintf( 'sources[%d].pattern is required: a path such as /old-page, or a regular expression with comparison "regex".', $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$comparison = $source['comparison'] ?? 'exact';
			if ( ! is_string( $comparison ) || ! in_array( $comparison, self::$rm_comparisons, true ) ) {
				throw new \InvalidArgumentException( sprintf( 'sources[%d].comparison must be one of: %s.', $i, implode( ', ', self::$rm_comparisons ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$ignore_case = ! empty( $source['ignore_case'] ) && rest_sanitize_boolean( $source['ignore_case'] );
			
			
			if ( $ignore_case && 'exact' !== $comparison ) {
				throw new \InvalidArgumentException( sprintf( 'sources[%d].ignore_case works only with comparison "exact": Rank Math ignores it for the others. For a regex, start the pattern with (?i).', $i ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$sources[] = array(
				'pattern'    => $pattern,
				'comparison' => $comparison,
				'ignore'     => $ignore_case ? 'case' : '',
			);
		}
		return $sources;
	}

	








	protected function build_model( array $data ): \RankMath\Redirections\Redirection {
		$dropped = array();
		foreach ( $data['sources'] as $source ) {
			if ( ! \RankMath\Redirections\Redirection::from( array( 'sources' => array( $source ) ) )->has_sources() ) {
				$dropped[] = $source['pattern'];
			}
		}
		if ( array() !== $dropped ) {
			throw new \InvalidArgumentException( 'Rank Math does not accept these sources, so they would not be saved: ' . implode( ', ', $dropped ) . '. A source is a path on this site (not "/" and not another domain), or a valid regular expression with comparison "regex".' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return \RankMath\Redirections\Redirection::from( $data );
	}

	






	protected function refuse_loop( \RankMath\Redirections\Redirection $model ): void {
		if ( $model->is_infinite_loop() ) {
			throw new \InvalidArgumentException( 'The target is the same address as a source, so the redirect would loop. Rank Math refuses it too.' );
		}
	}

	





	protected function parse_code( $value ): int {
		$code = is_numeric( $value ) ? (int) $value : 0;
		if ( (string) $code !== trim( (string) $value ) || ! in_array( $code, array_merge( self::$rm_redirect_codes, self::$rm_error_codes ), true ) ) {
			throw new \InvalidArgumentException( 'code must be one of 301, 302 or 307 (redirect), or 410 or 451 (answer without redirecting).' );
		}
		return $code;
	}

	







	protected function check_target( int $code, string $target ): void {
		if ( in_array( $code, self::$rm_error_codes, true ) ) {
			if ( '' !== $target ) {
				throw new \InvalidArgumentException( sprintf( 'A %d answers without redirecting, so it takes no target.', $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			return;
		}
		if ( '' === trim( wp_strip_all_tags( $target, true ) ) ) {
			throw new \InvalidArgumentException( sprintf( 'A %d redirect needs a target: the URL or path to send visitors to.', $code ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	









	protected static function normalise( array $row ): array {
		$sources = $row['sources'] ?? array();
		if ( is_string( $sources ) ) {
			$sources = self::decoded_sources( maybe_unserialize( $sources ) );
		}
		$out = array();
		foreach ( is_array( $sources ) ? $sources : array() as $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$out[] = array(
				'pattern'     => (string) ( $source['pattern'] ?? '' ),
				'comparison'  => (string) ( $source['comparison'] ?? 'exact' ),
				'ignore_case' => ! empty( $source['ignore'] ),
			);
		}
		$target        = (string) ( $row['url_to'] ?? '' );
		$last_accessed = (string) ( $row['last_accessed'] ?? '' );
		return array(
			'id'            => (int) ( $row['id'] ?? 0 ),
			'sources'       => $out,
			'target'        => '' === $target ? null : $target,
			'code'          => (int) ( $row['header_code'] ?? 0 ),
			'status'        => (string) ( $row['status'] ?? '' ),
			'hits'          => (int) ( $row['hits'] ?? 0 ),
			'created'       => (string) ( $row['created'] ?? '' ),
			'updated'       => (string) ( $row['updated'] ?? '' ),
			'last_accessed' => '' === $last_accessed || '0000-00-00 00:00:00' === $last_accessed ? null : $last_accessed,
		);
	}

	





	protected static function decoded_sources( $sources ): array {
		$out = array();
		foreach ( is_array( $sources ) ? $sources : array() as $source ) {
			if ( is_array( $source ) ) {
				$source['pattern'] = wp_specialchars_decode( (string) ( $source['pattern'] ?? '' ) );
				$out[]             = $source;
			}
		}
		return $out;
	}
}
