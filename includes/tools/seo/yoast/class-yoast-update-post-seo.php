<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Yoast_Update_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_yoast_update_post_seo';
	}

	public function get_description() {
		return 'Updates Yoast SEO metadata on a post or page by writing to the Yoast postmeta keys directly. Fields: seo_title, meta_description, focus_keyword, is_cornerstone, og_title, og_description, og_image, twitter_title, twitter_description, twitter_image, noindex ("0"=default/"1"=noindex/"2"=index), nofollow ("0"/"1"), advanced_robots (csv of noimageindex,noarchive,nosnippet), canonical (URL), primary_term + taxonomy, breadcrumb_title, schema_page_type, schema_article_type.';
	}

	public function get_category() {
		return 'yoast-seo';
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
				'post_id'             => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'seo_title'           => array(
					'type'        => 'string',
					'description' => 'The SEO title (_yoast_wpseo_title).',
				),
				'meta_description'    => array(
					'type'        => 'string',
					'description' => 'The meta description (_yoast_wpseo_metadesc).',
				),
				'focus_keyword'       => array(
					'type'        => 'string',
					'description' => 'The focus keyword (_yoast_wpseo_focuskw).',
				),
				'is_cornerstone'      => array(
					'type'        => 'boolean',
					'description' => 'Whether this is cornerstone content (_yoast_wpseo_is_cornerstone).',
				),
				'og_title'            => array(
					'type'        => 'string',
					'description' => 'The Open Graph title (_yoast_wpseo_opengraph-title).',
				),
				'og_description'      => array(
					'type'        => 'string',
					'description' => 'The Open Graph description (_yoast_wpseo_opengraph-description).',
				),
				'twitter_title'       => array(
					'type'        => 'string',
					'description' => 'The Twitter card title (_yoast_wpseo_twitter-title).',
				),
				'twitter_description' => array(
					'type'        => 'string',
					'description' => 'The Twitter card description (_yoast_wpseo_twitter-description).',
				),
				'og_image'            => array(
					'type'        => 'string',
					'description' => 'The Open Graph image URL (_yoast_wpseo_opengraph-image).',
				),
				'twitter_image'       => array(
					'type'        => 'string',
					'description' => 'The Twitter card image URL (_yoast_wpseo_twitter-image).',
				),
				'noindex'             => array(
					'type'        => 'string',
					'enum'        => array( '0', '1', '2' ),
					'description' => 'Robots noindex (_yoast_wpseo_meta-robots-noindex). Tri-state: "0"=default, "1"=noindex, "2"=index. NOT a boolean.',
				),
				'nofollow'            => array(
					'type'        => 'string',
					'enum'        => array( '0', '1' ),
					'description' => 'Robots nofollow (_yoast_wpseo_meta-robots-nofollow). "0"=default, "1"=nofollow.',
				),
				'advanced_robots'     => array(
					'type'        => 'string',
					'description' => 'Advanced robots directives (_yoast_wpseo_meta-robots-adv), comma-separated. Allowed tokens: noimageindex, noarchive, nosnippet.',
				),
				'canonical'           => array(
					'type'        => 'string',
					'description' => 'The canonical URL (_yoast_wpseo_canonical).',
				),
				'primary_term'        => array(
					'type'        => 'integer',
					'description' => 'The primary term ID (_yoast_wpseo_primary_{taxonomy}). Requires taxonomy.',
				),
				'taxonomy'            => array(
					'type'        => 'string',
					'description' => 'The taxonomy slug for primary_term (e.g. category). Required when primary_term is set.',
				),
				'breadcrumb_title'    => array(
					'type'        => 'string',
					'description' => 'The breadcrumb title (_yoast_wpseo_bctitle).',
				),
				'schema_page_type'    => array(
					'type'        => 'string',
					'description' => 'The schema page type (_yoast_wpseo_schema_page_type), e.g. WebPage, AboutPage.',
				),
				'schema_article_type' => array(
					'type'        => 'string',
					'description' => 'The schema article type (_yoast_wpseo_schema_article_type), e.g. Article, NewsArticle.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			throw new \RuntimeException( 'Yoast SEO is not active on this site. Please install and activate Yoast SEO to use this tool.' );
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
			'seo_title'           => '_yoast_wpseo_title',
			'meta_description'    => '_yoast_wpseo_metadesc',
			'focus_keyword'       => '_yoast_wpseo_focuskw',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
		);

		$url_meta_map = array(
			'og_image'      => '_yoast_wpseo_opengraph-image',
			'twitter_image' => '_yoast_wpseo_twitter-image',
		);

		$updated = array();

		foreach ( $meta_map as $arg_key => $meta_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				if ( false !== update_post_meta( $post_id, $meta_key, sanitize_text_field( $arguments[ $arg_key ] ) ) ) {
					$updated[] = $arg_key;
				}
			}
		}

		foreach ( $url_meta_map as $arg_key => $meta_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				if ( false !== update_post_meta( $post_id, $meta_key, esc_url_raw( $arguments[ $arg_key ] ) ) ) {
					$updated[] = $arg_key;
				}
			}
		}

		if ( isset( $arguments['is_cornerstone'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_is_cornerstone', (bool) $arguments['is_cornerstone'] ? '1' : '' );
			$updated[] = 'is_cornerstone';
		}

		if ( isset( $arguments['noindex'] ) ) {
			$noindex = (int) $arguments['noindex'];
			if ( ! in_array( $noindex, array( 0, 1, 2 ), true ) ) {
				throw new \InvalidArgumentException( 'noindex must be one of 0 (default), 1 (noindex), or 2 (index).' );
			}
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', (string) $noindex );
			$updated[] = 'noindex';
		}

		if ( isset( $arguments['nofollow'] ) ) {
			$nofollow = (int) $arguments['nofollow'];
			if ( ! in_array( $nofollow, array( 0, 1 ), true ) ) {
				throw new \InvalidArgumentException( 'nofollow must be one of 0 (default) or 1 (nofollow).' );
			}
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', (string) $nofollow );
			$updated[] = 'nofollow';
		}

		if ( isset( $arguments['advanced_robots'] ) ) {
			$allowed_adv = array( 'noimageindex', 'noarchive', 'nosnippet' );
			$tokens      = array_filter( array_map( 'trim', explode( ',', (string) $arguments['advanced_robots'] ) ) );
			$clean       = array();
			foreach ( $tokens as $token ) {
				if ( ! in_array( $token, $allowed_adv, true ) ) {
					throw new \InvalidArgumentException( 'advanced_robots tokens must be among: ' . implode( ', ', $allowed_adv ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				}
				if ( ! in_array( $token, $clean, true ) ) {
					$clean[] = $token;
				}
			}
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-adv', implode( ',', $clean ) );
			$updated[] = 'advanced_robots';
		}

		if ( isset( $arguments['canonical'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_canonical', esc_url_raw( $arguments['canonical'] ) );
			$updated[] = 'canonical';
		}

		if ( isset( $arguments['breadcrumb_title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_bctitle', sanitize_text_field( $arguments['breadcrumb_title'] ) );
			$updated[] = 'breadcrumb_title';
		}

		if ( isset( $arguments['schema_page_type'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_schema_page_type', sanitize_text_field( $arguments['schema_page_type'] ) );
			$updated[] = 'schema_page_type';
		}

		if ( isset( $arguments['schema_article_type'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_schema_article_type', sanitize_text_field( $arguments['schema_article_type'] ) );
			$updated[] = 'schema_article_type';
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
			update_post_meta( $post_id, '_yoast_wpseo_primary_' . $taxonomy, (string) $primary_term );
			$updated[] = 'primary_term';
		}

		return array(
			'post_id'        => $post_id,
			'updated_fields' => $updated,
		);
	}
}
