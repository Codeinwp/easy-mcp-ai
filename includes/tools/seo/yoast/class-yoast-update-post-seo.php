<?php

namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Yoast_Update_Post_Seo extends Base_Tool {

	const IMAGE_URLS = array(
		'og_image'      => '_yoast_wpseo_opengraph-image',
		'twitter_image' => '_yoast_wpseo_twitter-image',
	);

	const IMAGE_IDS = array(
		'og_image'      => '_yoast_wpseo_opengraph-image-id',
		'twitter_image' => '_yoast_wpseo_twitter-image-id',
	);

	const ROBOTS = array(
		'noindex'  => array(
			'key'     => '_yoast_wpseo_meta-robots-noindex',
			'values'  => array( '0', '1', '2' ),
			'refusal' => 'noindex must be one of "0" (default), "1" (noindex) or "2" (index). Nothing was changed.',
		),
		'nofollow' => array(
			'key'     => '_yoast_wpseo_meta-robots-nofollow',
			'values'  => array( '0', '1' ),
			'refusal' => 'nofollow must be one of "0" (default) or "1" (nofollow). Nothing was changed.',
		),
	);

	public function get_name() {
		return 'wp_yoast_update_post_seo';
	}

	public function get_description() {
		return 'Updates Yoast SEO metadata on a post or page by writing to the Yoast postmeta keys directly. Fields: seo_title, meta_description, focus_keyword, is_cornerstone, og_title, og_description, og_image, twitter_title, twitter_description, twitter_image, noindex ("0"=default/"1"=noindex/"2"=index), nofollow ("0"/"1"), advanced_robots (csv of noimageindex,noarchive,nosnippet), canonical (URL), primary_term + taxonomy, breadcrumb_title, schema_page_type, schema_article_type. Returns { post_id, updated_fields, fields }: `fields` is the stored value of every field sent, read back after the write (is_cornerstone as a boolean), so a value re-sent unchanged, which updated_fields leaves out, is confirmed too.';
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
					'description' => 'The Open Graph image URL (_yoast_wpseo_opengraph-image). Also sets Yoast\'s image id to the Media Library image the URL is the file or a resized copy of, or removes it; "" clears the image.',
				),
				'twitter_image'       => array(
					'type'        => 'string',
					'description' => 'The Twitter card image URL (_yoast_wpseo_twitter-image). Also sets Yoast\'s image id to the Media Library image the URL is the file or a resized copy of, or removes it; "" clears the image.',
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

	public function precheck( array $arguments ): void {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			return;
		}
		self::robots_values( $arguments );
		self::advanced_robots( $arguments );
		$this->primary_term( $arguments );
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

		$robots       = self::robots_values( $arguments );
		$advanced     = self::advanced_robots( $arguments );
		$primary_term = $this->primary_term( $arguments );

		$meta_map = array(
			'seo_title'           => '_yoast_wpseo_title',
			'meta_description'    => '_yoast_wpseo_metadesc',
			'focus_keyword'       => '_yoast_wpseo_focuskw',
			'og_title'            => '_yoast_wpseo_opengraph-title',
			'og_description'      => '_yoast_wpseo_opengraph-description',
			'twitter_title'       => '_yoast_wpseo_twitter-title',
			'twitter_description' => '_yoast_wpseo_twitter-description',
		);

		$updated = array();

		foreach ( $meta_map as $arg_key => $meta_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				if ( false !== update_post_meta( $post_id, $meta_key, sanitize_text_field( $arguments[ $arg_key ] ) ) ) {
					$updated[] = $arg_key;
				}
			}
		}

		foreach ( self::IMAGE_URLS as $arg_key => $meta_key ) {
			if ( isset( $arguments[ $arg_key ] ) ) {
				$url         = esc_url_raw( $arguments[ $arg_key ] );
				$url_changed = false !== update_post_meta( $post_id, $meta_key, $url );
				$id_changed  = Yoast_Image_Id::store_for_post( $post_id, self::IMAGE_IDS[ $arg_key ], $url );
				if ( $url_changed || $id_changed ) {
					$updated[] = $arg_key;
				}
			}
		}

		if ( isset( $arguments['is_cornerstone'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_is_cornerstone', (bool) $arguments['is_cornerstone'] ? '1' : '' );
			$updated[] = 'is_cornerstone';
		}

		foreach ( $robots as $field => $value ) {
			update_post_meta( $post_id, self::ROBOTS[ $field ]['key'], $value );
			$updated[] = $field;
		}

		if ( null !== $advanced ) {
			update_post_meta( $post_id, '_yoast_wpseo_meta-robots-adv', $advanced );
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

		if ( null !== $primary_term ) {
			update_post_meta( $post_id, '_yoast_wpseo_primary_' . $primary_term['taxonomy'], (string) $primary_term['term'] );
			$updated[] = 'primary_term';
		}

		if ( array() !== $updated ) {
			$this->clear_page_cache( array( $post_id ) );
		}

		return array(
			'post_id'        => $post_id,
			'updated_fields' => $updated,
			'fields'         => self::stored_fields( $post_id, $arguments, array_merge( $meta_map, self::IMAGE_URLS ) ),
		);
	}

	private static function robots_values( array $arguments ): array {
		$values = array();
		foreach ( self::ROBOTS as $field => $robot ) {
			if ( ! isset( $arguments[ $field ] ) ) {
				continue;
			}
			$value = $arguments[ $field ];
			if ( is_int( $value ) || is_bool( $value ) || ( is_float( $value ) && in_array( $value, array( 0.0, 1.0, 2.0 ), true ) ) ) {
				$value = (string) (int) $value;
			}
			if ( ! is_string( $value ) || ! in_array( $value, $robot['values'], true ) ) {
				throw new \InvalidArgumentException( $robot['refusal'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			$values[ $field ] = $value;
		}
		return $values;
	}

	private static function advanced_robots( array $arguments ): ?string {
		if ( ! isset( $arguments['advanced_robots'] ) ) {
			return null;
		}
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
		return implode( ',', $clean );
	}

	private function primary_term( array $arguments ): ?array {
		if ( ! isset( $arguments['primary_term'] ) ) {
			return null;
		}
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
		return array(
			'taxonomy' => $taxonomy,
			'term'     => $primary_term,
		);
	}

	private static function stored_fields( int $post_id, array $arguments, array $text_keys ): array {
		$keys = array_merge(
			$text_keys,
			array(
				'is_cornerstone'      => '_yoast_wpseo_is_cornerstone',
				'noindex'             => '_yoast_wpseo_meta-robots-noindex',
				'nofollow'            => '_yoast_wpseo_meta-robots-nofollow',
				'advanced_robots'     => '_yoast_wpseo_meta-robots-adv',
				'canonical'           => '_yoast_wpseo_canonical',
				'breadcrumb_title'    => '_yoast_wpseo_bctitle',
				'schema_page_type'    => '_yoast_wpseo_schema_page_type',
				'schema_article_type' => '_yoast_wpseo_schema_article_type',
			)
		);
		if ( isset( $arguments['primary_term'], $arguments['taxonomy'] ) ) {
			$keys['primary_term'] = '_yoast_wpseo_primary_' . trim( (string) $arguments['taxonomy'] );
		}
		$meta_id = (int) ( wp_is_post_revision( $post_id ) ?: $post_id );
		$fields  = array();
		foreach ( $keys as $field => $meta_key ) {
			if ( ! isset( $arguments[ $field ] ) ) {
				continue;
			}
			$value            = (string) get_post_meta( $meta_id, $meta_key, true );
			$fields[ $field ] = 'is_cornerstone' === $field ? '1' === $value : $value;
		}
		return $fields;
	}
}
