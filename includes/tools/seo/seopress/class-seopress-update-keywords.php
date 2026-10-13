<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Update_Keywords extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_update_post_keywords';
	}

	public function get_description() {
		return 'Updates the SEOPress target keywords used for content analysis on a post or page via the seopress/v1 REST API. Pass a comma-separated string (e.g. "wordpress mcp, ai assistant, seo tools").';
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
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'         => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'target_keywords' => array(
					'type'        => 'string',
					'description' => 'The target keywords as a comma-separated string, e.g. "wordpress mcp, ai assistant, seo tools".',
				),
			),
			'required'   => array( 'post_id', 'target_keywords' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'seopress_get_service' ) ) {
			throw new \RuntimeException( 'SEOPress is not active on this site. Please install and activate SEOPress to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}

		$this->validate_required( $arguments, array( 'target_keywords' ) );

		$keywords = $arguments['target_keywords'];
		if ( is_array( $keywords ) ) {
			$keywords = array_map( 'sanitize_text_field', $keywords );
			$keywords = implode( ',', $keywords );
		} else {
			$keywords = sanitize_text_field( (string) $keywords );
		}

		$body = array( '_seopress_analysis_target_kw' => $keywords );

		$this->rest_request( 'PUT', '/seopress/v1/posts/' . $post_id . '/target-keywords', $body );

		return array(
			'post_id' => $post_id,
			'updated' => true,
		);
	}
}
