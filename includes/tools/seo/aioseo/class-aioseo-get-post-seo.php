<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Aioseo_Get_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_aioseo_get_post_seo';
	}

	public function get_description() {
		return 'Gets AIOSEO SEO metadata for a post or page. Returns two fields: aioseo_head_json (available on free plan, read-only rendered JSON of SEO head tags) and aioseo_meta_data (writable SEO fields including title, description, og_title, og_description, twitter_title, twitter_description, no_index, canonical_url). The AIOSEO REST API is included free in core since AIOSEO 4.9.8+ for all plans; on AIOSEO < 4.9.8 free it required the legacy REST API addon.';
	}

	public function get_category() {
		return 'aioseo';
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
				'post_id'   => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'The REST API base for the post type (e.g. posts, pages).',
					'default'     => 'posts',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'aioseo' ) ) {
			throw new \RuntimeException( 'All in One SEO (AIOSEO) is not active on this site. Please install and activate AIOSEO to use this tool.' );
		}

		$post_id   = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
		$post_type = ! empty( $arguments['post_type'] ) ? $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' ) : 'posts';

		$data = $this->rest_request( 'GET', '/wp/v2/' . $post_type . '/' . $post_id );

		return array(
			'post_id'          => $post_id,
			'aioseo_meta_data' => $data['aioseo_meta_data'] ?? array(),
			'aioseo_head_json' => $data['aioseo_head_json'] ?? array(),
		);
	}
}
