<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Slimseo_Get_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_slimseo_get_post_seo';
	}

	public function get_description() {
		return 'Gets the Slim SEO metadata for a post or page via the WordPress core REST API. Returns { post_id, slim_seo } where `slim_seo` is Slim SEO\'s stored meta verbatim. Slim SEO stores SPARSELY — only keys that have been set are present, so a post with no SEO data returns an empty object rather than a fixed shape. Possible keys: title, description, facebook_image, twitter_image, canonical, noindex.';
	}

	public function get_category() {
		return 'slim-seo';
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
		if ( ! class_exists( 'SlimSEO\\Container' ) ) {
			throw new \RuntimeException( 'Slim SEO is not active on this site. Please install and activate Slim SEO to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
		$base    = $this->resolve_post_rest_base( $post_id );

		$data = $this->rest_request( 'GET', '/wp/v2/' . $base . '/' . $post_id, array( '_fields' => 'meta.slim_seo' ) );

		return array(
			'post_id'  => $post_id,
			'slim_seo' => $data['meta']['slim_seo'] ?? array(),
		);
	}
}
