<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Aioseo_Get_Breadcrumb extends Base_Tool {

	public function get_name() {
		return 'wp_aioseo_get_breadcrumb';
	}

	public function get_description() {
		return 'Gets the AIOSEO breadcrumb JSON for a post or page (the aioseo_breadcrumb_json REST field added in AIOSEO 4.9.8). Returns the breadcrumb trail data used for breadcrumb structured data and display.';
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
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'aioseo' ) ) {
			throw new \RuntimeException( 'All in One SEO (AIOSEO) is not active on this site. Please install and activate AIOSEO to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );
		
		
		$base = $this->resolve_post_rest_base( $post_id );

		$data = $this->rest_request( 'GET', '/wp/v2/' . $base . '/' . $post_id );

		return array(
			'post_id'                 => $post_id,
			'aioseo_breadcrumb_json' => $data['aioseo_breadcrumb_json'] ?? array(),
		);
	}
}
