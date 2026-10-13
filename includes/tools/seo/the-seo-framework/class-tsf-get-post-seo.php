<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Tsf_Get_Post_Seo extends Base_Tool {

	




	const META_KEYS = array(
		'_genesis_title',
		'_genesis_description',
		'_genesis_canonical_uri',
		'_genesis_noindex',
		'_genesis_nofollow',
		'_genesis_noarchive',
		'_open_graph_title',
		'_open_graph_description',
		'_twitter_title',
		'_twitter_description',
		'_social_image_url',
		'_social_image_id',
	);

	public function get_name() {
		return 'wp_tsf_get_post_seo';
	}

	public function get_description() {
		return 'Gets The SEO Framework SEO metadata for a post or page (SEO title, description, canonical URL, noindex/nofollow/noarchive, Open Graph and Twitter title/description, social image) via the WordPress post meta API.';
	}

	public function get_category() {
		return 'the-seo-framework';
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
		if ( ! function_exists( 'tsf' ) ) {
			throw new \RuntimeException( 'The SEO Framework is not active on this site. Please install and activate The SEO Framework to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		if ( ! get_post( $post_id ) ) {
			throw new \RuntimeException( 'Invalid post ID.' );
		}

		$seo = array();
		foreach ( self::META_KEYS as $key ) {
			$seo[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return array(
			'post_id' => $post_id,
			'seo'     => $seo,
		);
	}
}
