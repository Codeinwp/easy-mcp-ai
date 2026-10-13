<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Rankmath_Update_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_rm_update_post_seo';
	}

	public function get_description() {
		return 'Updates Rank Math SEO fields on a post by writing to the Rank Math postmeta keys. Fields: title (rank_math_title), description (rank_math_description), focus_keyword (rank_math_focus_keyword), canonical_url (rank_math_canonical_url), facebook_title, facebook_description, facebook_image, twitter_title, twitter_description, twitter_image, robots (array of index/noindex/nofollow/noarchive/noimageindex/nosnippet), advanced_robots (array), breadcrumb_title, primary_term + taxonomy, pillar_content (boolean).';
	}

	public function get_category() {
		return 'rank-math';
	}

	public function get_required_capability() {
		return 'edit_posts';
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
				'post_id'              => array(
					'type'        => 'integer',
					'description' => 'The ID of the post.',
				),
				'title'                => array(
					'type'        => 'string',
					'description' => 'The SEO title (rank_math_title).',
				),
				'description'          => array(
					'type'        => 'string',
					'description' => 'The meta description (rank_math_description).',
				),
				'focus_keyword'        => array(
					'type'        => 'string',
					'description' => 'The focus keyword (rank_math_focus_keyword).',
				),
				'canonical_url'        => array(
					'type'        => 'string',
					'description' => 'The canonical URL (rank_math_canonical_url).',
				),
				'facebook_title'       => array(
					'type'        => 'string',
					'description' => 'The Facebook/Open Graph title (rank_math_facebook_title).',
				),
				'facebook_description' => array(
					'type'        => 'string',
					'description' => 'The Facebook/Open Graph description (rank_math_facebook_description).',
				),
				'twitter_title'        => array(
					'type'        => 'string',
					'description' => 'The Twitter card title (rank_math_twitter_title).',
				),
				'twitter_description'  => array(
					'type'        => 'string',
					'description' => 'The Twitter card description (rank_math_twitter_description).',
				),
				'facebook_image'       => array(
					'type'        => 'string',
					'description' => 'The Facebook/Open Graph image URL (rank_math_facebook_image).',
				),
				'twitter_image'        => array(
					'type'        => 'string',
					'description' => 'The Twitter card image URL (rank_math_twitter_image).',
				),
				'robots'               => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Robots meta (rank_math_robots) as an array of tokens. Allowed: index, noindex, nofollow, noarchive, noimageindex, nosnippet. A comma-separated string is also accepted and split on ",".',
				),
				'advanced_robots'      => array(
					'type'        => 'object',
					'description' => 'Advanced robots (rank_math_advanced_robots), e.g. keys max-snippet, max-video-preview, max-image-preview.',
				),
				'breadcrumb_title'     => array(
					'type'        => 'string',
					'description' => 'The breadcrumb title (rank_math_breadcrumb_title).',
				),
				'primary_term'         => array(
					'type'        => 'integer',
					'description' => 'The primary term ID (rank_math_primary_{taxonomy}). Requires taxonomy.',
				),
				'taxonomy'             => array(
					'type'        => 'string',
					'description' => 'The taxonomy slug for primary_term (e.g. category). Required when primary_term is set.',
				),
				'pillar_content'       => array(
					'type'        => 'boolean',
					'description' => 'Whether this is pillar content (rank_math_pillar_content).',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'rank_math' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active on this site. Please install and activate Rank Math SEO to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \InvalidArgumentException( 'Post not found.' );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}

		$meta_map = array(
			'title'                => 'rank_math_title',
			'description'          => 'rank_math_description',
			'focus_keyword'        => 'rank_math_focus_keyword',
			'canonical_url'        => 'rank_math_canonical_url',
			'facebook_title'       => 'rank_math_facebook_title',
			'facebook_description' => 'rank_math_facebook_description',
			'twitter_title'        => 'rank_math_twitter_title',
			'twitter_description'  => 'rank_math_twitter_description',
			'facebook_image'       => 'rank_math_facebook_image',
			'twitter_image'        => 'rank_math_twitter_image',
		);

		$url_fields = array( 'canonical_url', 'facebook_image', 'twitter_image' );

		$updated = array();

		foreach ( $meta_map as $arg_key => $meta_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				$value = in_array( $arg_key, $url_fields, true )
					? esc_url_raw( $arguments[ $arg_key ] )
					: sanitize_text_field( $arguments[ $arg_key ] );
				if ( false !== update_post_meta( $post_id, $meta_key, $value ) ) {
					$updated[] = $arg_key;
				}
			}
		}

		if ( isset( $arguments['robots'] ) ) {
			$allowed_robots = array( 'index', 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' );
			$robots_in      = $arguments['robots'];
			if ( is_string( $robots_in ) ) {
				$robots_in = explode( ',', $robots_in );
			}
			if ( ! is_array( $robots_in ) ) {
				throw new \InvalidArgumentException( 'robots must be an array of tokens or a comma-separated string.' );
			}
			$robots = array();
			foreach ( $robots_in as $token ) {
				$token = sanitize_text_field( trim( (string) $token ) );
				if ( '' === $token ) {
					continue;
				}
				if ( ! in_array( $token, $allowed_robots, true ) ) {
					throw new \InvalidArgumentException( 'robots tokens must be among: ' . implode( ', ', $allowed_robots ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( ! in_array( $token, $robots, true ) ) {
					$robots[] = $token;
				}
			}
			update_post_meta( $post_id, 'rank_math_robots', $robots );
			$updated[] = 'robots';
		}

		if ( isset( $arguments['advanced_robots'] ) ) {
			$advanced = $arguments['advanced_robots'];
			if ( ! is_array( $advanced ) ) {
				throw new \InvalidArgumentException( 'advanced_robots must be an object/array.' );
			}
			update_post_meta( $post_id, 'rank_math_advanced_robots', $advanced );
			$updated[] = 'advanced_robots';
		}

		if ( isset( $arguments['breadcrumb_title'] ) ) {
			update_post_meta( $post_id, 'rank_math_breadcrumb_title', sanitize_text_field( $arguments['breadcrumb_title'] ) );
			$updated[] = 'breadcrumb_title';
		}

		if ( isset( $arguments['pillar_content'] ) ) {
			update_post_meta( $post_id, 'rank_math_pillar_content', (bool) $arguments['pillar_content'] ? 'on' : '' );
			$updated[] = 'pillar_content';
		}

		if ( isset( $arguments['primary_term'] ) ) {
			if ( empty( $arguments['taxonomy'] ) ) {
				throw new \InvalidArgumentException( 'taxonomy is required when primary_term is provided.' );
			}
			$taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'], 'taxonomy' );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				throw new \InvalidArgumentException( 'Unknown taxonomy: ' . $taxonomy ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$primary_term = (int) $arguments['primary_term'];
			if ( ! term_exists( $primary_term, $taxonomy ) ) {
				throw new \InvalidArgumentException( 'primary_term does not exist in taxonomy ' . $taxonomy . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			update_post_meta( $post_id, 'rank_math_primary_' . $taxonomy, (string) $primary_term );
			$updated[] = 'primary_term';
		}

		return array(
			'post_id'        => $post_id,
			'updated_fields' => $updated,
		);
	}
}
