<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Get_Term_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_get_term_seo';
	}

	public function get_description() {
		return 'Gets SEOPress SEO metadata for a taxonomy term, read directly from term meta (fast, version-independent). Returns title, description, canonical, robots (noindex/nofollow/noimageindex/nosnippet), Open Graph, Twitter and redirect settings. Returns raw stored values (not the templated/rendered output).';
	}

	public function get_category() {
		return 'seopress';
	}

	public function get_required_capability() {
		return 'edit_posts';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'term_id'  => array(
					'type'        => 'integer',
					'description' => 'The ID of the taxonomy term.',
				),
				'taxonomy' => array(
					'type'        => 'string',
					'description' => 'The taxonomy the term belongs to (e.g. category, post_tag).',
				),
			),
			'required'   => array( 'term_id', 'taxonomy' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'seopress_get_service' ) ) {
			throw new \RuntimeException( 'SEOPress is not active on this site. Please install and activate SEOPress to use this tool.' );
		}

		$term_id  = $this->parse_required_id( $arguments['term_id'] ?? null, 'term_id' );
		$taxonomy = $this->validate_rest_route_segment( $arguments['taxonomy'] ?? '', 'taxonomy' );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			throw new \InvalidArgumentException( sprintf( 'Taxonomy "%s" does not exist.', $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! term_exists( $term_id, $taxonomy ) ) {
			throw new \InvalidArgumentException( sprintf( 'Term %d does not exist in taxonomy "%s".', $term_id, $taxonomy ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		
		
		
		$get = static function ( $key ) use ( $term_id ) {
			$v = get_term_meta( $term_id, $key, true );
			return ( '' === $v || false === $v ) ? null : $v;
		};

		$seo = array(
			'title'             => $get( '_seopress_titles_title' ),
			'description'       => $get( '_seopress_titles_desc' ),
			'canonical'         => $get( '_seopress_robots_canonical' ),
			'robots'            => array(
				'noindex'      => 'yes' === get_term_meta( $term_id, '_seopress_robots_index', true ),
				'nofollow'     => 'yes' === get_term_meta( $term_id, '_seopress_robots_follow', true ),
				'noimageindex' => 'yes' === get_term_meta( $term_id, '_seopress_robots_imageindex', true ),
				'nosnippet'    => 'yes' === get_term_meta( $term_id, '_seopress_robots_snippet', true ),
			),
			'og'                => array(
				'title'       => $get( '_seopress_social_fb_title' ),
				'description' => $get( '_seopress_social_fb_desc' ),
				'image'       => $get( '_seopress_social_fb_img' ),
			),
			'twitter'           => array(
				'title'       => $get( '_seopress_social_twitter_title' ),
				'description' => $get( '_seopress_social_twitter_desc' ),
				'image'       => $get( '_seopress_social_twitter_img' ),
			),
			'redirect'          => array(
				'enabled' => 'yes' === get_term_meta( $term_id, '_seopress_redirections_enabled', true ),
				'type'    => $get( '_seopress_redirections_type' ),
				'url'     => $get( '_seopress_redirections_value' ),
			),
		);

		return array(
			'term_id'  => $term_id,
			'taxonomy' => $taxonomy,
			'seo'      => $seo,
		);
	}
}
