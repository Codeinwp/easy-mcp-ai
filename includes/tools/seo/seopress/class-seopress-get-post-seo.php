<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Get_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_get_post_seo';
	}

	public function get_description() {
		return 'Gets SEOPress SEO metadata for a post or page, read directly from post meta (fast, works on any post status including drafts). Returns title, description, canonical, robots (noindex/nofollow/noimageindex/nosnippet), Open Graph, Twitter, breadcrumbs title, primary category, target keywords and redirect settings. Returns raw stored values (not the templated/rendered output).';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'seopress_get_service' ) ) {
			throw new \RuntimeException( 'SEOPress is not active on this site. Please install and activate SEOPress to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		if ( ! get_post( $post_id ) ) {
			throw new \RuntimeException( 'Post not found.' );
		}

		
		
		
		$get = static function ( $key ) use ( $post_id ) {
			$v = get_post_meta( $post_id, $key, true );
			return ( '' === $v || false === $v ) ? null : $v;
		};

		$target_kw = $get( '_seopress_analysis_target_kw' );

		$seo = array(
			'title'             => $get( '_seopress_titles_title' ),
			'description'       => $get( '_seopress_titles_desc' ),
			'canonical'         => $get( '_seopress_robots_canonical' ),
			'robots'            => array(
				'noindex'      => 'yes' === get_post_meta( $post_id, '_seopress_robots_index', true ),
				'nofollow'     => 'yes' === get_post_meta( $post_id, '_seopress_robots_follow', true ),
				'noimageindex' => 'yes' === get_post_meta( $post_id, '_seopress_robots_imageindex', true ),
				'nosnippet'    => 'yes' === get_post_meta( $post_id, '_seopress_robots_snippet', true ),
			),
			'breadcrumbs_title' => $get( '_seopress_robots_breadcrumbs' ),
			'primary_category'  => $get( '_seopress_robots_primary_cat' ),
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
			'target_keywords'   => null === $target_kw
				? array()
				: array_values( array_filter( array_map( 'trim', explode( ',', (string) $target_kw ) ), static function ( $k ) {
					return '' !== $k;
				} ) ),
			'redirect'          => array(
				'enabled' => 'yes' === get_post_meta( $post_id, '_seopress_redirections_enabled', true ),
				'type'    => $get( '_seopress_redirections_type' ),
				'url'     => $get( '_seopress_redirections_value' ),
			),
		);

		return array(
			'post_id' => $post_id,
			'seo'     => $seo,
		);
	}
}
