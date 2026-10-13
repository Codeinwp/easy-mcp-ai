<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Update_Title_Desc extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_update_post_title_desc';
	}

	public function get_description() {
		return 'Updates the SEOPress SEO title and/or meta description for a post or page via the seopress/v1 REST API. Only the fields you provide are changed.';
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
				'post_id'     => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'title'       => array(
					'type'        => 'string',
					'description' => 'The SEOPress SEO title.',
				),
				'description' => array(
					'type'        => 'string',
					'description' => 'The SEOPress meta description.',
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}

		$body = array();
		if ( isset( $arguments['title'] ) ) {
			$body['title'] = sanitize_text_field( (string) $arguments['title'] );
		}
		if ( isset( $arguments['description'] ) ) {
			$body['description'] = sanitize_text_field( (string) $arguments['description'] );
		}

		$this->rest_request( 'PUT', '/seopress/v1/posts/' . $post_id . '/title-description-metas', $body );

		return array(
			'post_id' => $post_id,
			'updated' => true,
		);
	}
}
