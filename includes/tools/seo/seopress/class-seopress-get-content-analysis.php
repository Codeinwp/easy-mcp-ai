<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Get_Content_Analysis extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_get_content_analysis';
	}

	public function get_description() {
		return 'Gets the SEOPress content analysis for a post or page via the seopress/v1 REST API (SEO score, internal/outbound links, heading hierarchy, content structure and keyword analysis). WARNING: this triggers a server-side loop-back HTTP fetch and render of the post\'s own front-end URL to analyze the live HTML — it can be slow and may be blocked by a CDN or WAF, in which case SEOPress returns a fallback object describing the failure reason. The analysis (or fallback) is returned as-is.';
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

		$body = $this->rest_request( 'GET', '/seopress/v1/posts/' . $post_id . '/content-analysis' );

		return array(
			'post_id'  => $post_id,
			'analysis' => $body,
		);
	}
}
