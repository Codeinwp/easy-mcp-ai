<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;










class Rankmath_Create_Redirect extends Base_Tool {

	use Rank_Math_Redirects;

	public function get_name() {
		return 'wp_rm_create_redirect';
	}

	public function get_description() {
		return 'Creates a redirect in Rank Math SEO\'s Redirections module and returns it as stored. Give the source one of three ways: `sources` (one or more { pattern, comparison, ignore_case }; pattern is a path such as /old-page), `post_id` (a published post or page: redirect its current URL) or `term_id` (a category, tag or other term: redirect its archive URL). A post or term is also linked to the redirect, so it shows in that item\'s Rank Math Redirect field, and needs permission to edit it. `target` is the URL or path to send visitors to, required for code 301 (default), 302 or 307 and refused for 410 or 451. `status` is "active" (default) or "inactive". Refused: a source Rank Math would drop (another domain, "/", an invalid regex), a target equal to a source, and sources an active redirect already uses (Rank Math would overwrite it; change that one with wp_rm_update_redirect). Returns { created, redirect, linked_object }.';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'sources' => array(
					'type'        => 'array',
					'description' => 'The URLs to redirect. Use this, post_id or term_id.',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'pattern'     => array(
								'type'        => 'string',
								'description' => 'A path on this site such as /old-page (a full URL on this site is reduced to its path), or a regular expression with comparison "regex".',
							),
							'comparison'  => array(
								'type'        => 'string',
								'enum'        => self::$rm_comparisons,
								'description' => 'How the pattern is matched. Default: exact.',
							),
							'ignore_case' => array(
								'type'        => 'boolean',
								'description' => 'Match regardless of letter case. Exact matches only: Rank Math ignores it for the other comparisons. Default: false.',
							),
						),
						'required'   => array( 'pattern' ),
					),
				),
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'A published post or page whose current URL is redirected. Use this, sources or term_id.',
				),
				'term_id' => array(
					'type'        => 'integer',
					'description' => 'A category, tag or other term whose archive URL is redirected. Use this, sources or post_id.',
				),
				'target'  => array(
					'type'        => 'string',
					'description' => 'Where to send visitors: a URL, or a path on this site. Required for 301, 302 and 307; refused for 410 and 451.',
				),
				'code'    => array(
					'type'        => 'integer',
					'description' => '301 (permanent, default), 302 (temporary), 307 (temporary, keeps the method), 410 (gone) or 451 (unavailable for legal reasons).',
					'default'     => 301,
				),
				'status'  => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'description' => 'Default: active.',
				),
			),
			'required'   => array(),
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_redirects();

		$object  = $this->linked_object( $arguments );
		$sources = null === $object ? $this->parse_sources( $arguments['sources'] ?? null ) : array(
			array(
				'pattern'    => $object['url'],
				'comparison' => 'exact',
				'ignore'     => '',
			),
		);
		$code   = $this->parse_code( $arguments['code'] ?? 301 );
		$target = isset( $arguments['target'] ) ? (string) $arguments['target'] : '';
		$this->check_target( $code, $target );
		$status = isset( $arguments['status'] ) && '' !== $arguments['status'] ? $arguments['status'] : 'active';
		$this->refuse_unless_one_of( 'status', $status, array( 'active', 'inactive' ), $status, 'active or inactive' );

		$model = $this->build_model(
			array(
				'sources'     => $sources,
				'url_to'      => $target,
				'header_code' => $code,
				'status'      => $status,
			)
		);
		$this->refuse_loop( $model );
		$this->refuse_taken_sources( $model );
		if ( null !== $object ) {
			$this->refuse_linked_object( $object );
		}

		if ( null !== $object ) {
			
			
			$model->set_nocache( true );
		}
		$id = (int) $model->save();
		if ( $id <= 0 ) {
			throw new \RuntimeException( 'Rank Math did not store the redirect.' );
		}
		if ( null !== $object ) {
			\RankMath\Redirections\Cache::add(
				array(
					'from_url'       => $model->sources[0]['pattern'],
					'redirection_id' => $id,
					'object_id'      => $object['id'],
					'object_type'    => $object['type'],
				)
			);
		}

		return array(
			'created'       => true,
			'redirect'      => self::normalise( $this->require_redirect( $id ) ),
			'linked_object' => null === $object ? null : array(
				'type' => $object['type'],
				'id'   => $object['id'],
			),
		);
	}

	








	private function linked_object( array $arguments ): ?array {
		$given = array_keys(
			array_filter(
				array(
					'sources' => $arguments['sources'] ?? null,
					'post_id' => $arguments['post_id'] ?? null,
					'term_id' => $arguments['term_id'] ?? null,
				),
				static function ( $value ) {
					return null !== $value && '' !== $value && array() !== $value;
				}
			)
		);
		if ( 1 !== count( $given ) ) {
			throw new \InvalidArgumentException( 'Give exactly one of sources, post_id or term_id' . ( array() === $given ? '.' : ' (got ' . implode( ', ', $given ) . ').' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'post_id' === $given[0] ) {
			return $this->post_object( $this->parse_required_id( $arguments['post_id'], 'post_id' ) );
		}
		if ( 'term_id' === $given[0] ) {
			return $this->term_object( $this->parse_required_id( $arguments['term_id'], 'term_id' ) );
		}
		return null;
	}

	



	private function post_object( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \InvalidArgumentException( sprintf( 'No post with id %d.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( sprintf( 'This account cannot edit post %d, so it cannot redirect it.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		
		
		if ( 'publish' !== $post->post_status ) {
			throw new \InvalidArgumentException( sprintf( 'Post %1$d is not published (status "%2$s"), so it has no public URL to redirect. Give its address in sources instead.', $post_id, $post->post_status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$url = get_permalink( $post );
		if ( ! is_string( $url ) || '' === $url ) {
			throw new \InvalidArgumentException( sprintf( 'Post %d has no URL to redirect.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			'type' => 'post',
			'id'   => $post_id,
			'url'  => $url,
		);
	}

	



	private function term_object( int $term_id ): array {
		$term = get_term( $term_id );
		if ( ! $term instanceof \WP_Term ) {
			throw new \InvalidArgumentException( sprintf( 'No term with id %d.', $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			throw new \RuntimeException( sprintf( 'This account cannot edit term %d, so it cannot redirect it.', $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$url = get_term_link( $term );
		if ( ! is_string( $url ) || '' === $url ) {
			throw new \InvalidArgumentException( sprintf( 'Term %d has no archive URL to redirect.', $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return array(
			'type' => 'term',
			'id'   => $term_id,
			'url'  => $url,
		);
	}

	











	private function refuse_linked_object( array $object ): void {
		$link = \RankMath\Redirections\Cache::get_by_object_id( $object['id'], $object['type'] );
		$row  = is_object( $link ) && isset( $link->redirection_id ) ? $this->find_redirect( (int) $link->redirection_id ) : null;
		if ( null !== $row ) {
			throw new \InvalidArgumentException( sprintf( 'This %1$s is already linked to redirect %2$d (status "%3$s"); Rank Math\'s Redirect field reads only that link, so it would never show a new one. Change that one with wp_rm_update_redirect (status "active" restores it from the trash), or delete it with wp_rm_delete_redirects and force: true first.', $object['type'], (int) $row['id'], (string) $row['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	







	private function refuse_taken_sources( \RankMath\Redirections\Redirection $model ): void {
		$match = \RankMath\Redirections\DB::match_redirections_source( maybe_serialize( $model->sources ) );
		if ( is_array( $match ) && isset( $match[0]['id'] ) ) {
			throw new \InvalidArgumentException( sprintf( 'Active redirect %d already uses these sources, and Rank Math would overwrite it. Change it with wp_rm_update_redirect, or give different sources.', (int) $match[0]['id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}
}
