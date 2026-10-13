<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Rankmath_Get_Head extends Base_Tool {

	public function get_name() {
		return 'wp_rm_get_head';
	}

	public function get_description() {
		return 'Gets the rendered SEO head HTML for a URL on this site via the Rank Math REST endpoint. Only this site\'s URLs are accepted: Rank Math validates the `url` argument and rejects any host other than this site, returning rest_invalid_param (HTTP 400), which this tool surfaces as an error. For same-site URLs, Rank Math strips this site\'s home_url prefix and re-parses the remainder as a path, so a same-site URL that does not resolve to a real page silently returns HTTP 200 with the front page\'s or a 404\'s head data rather than erroring — always pass a URL you know exists. Requires Headless CMS Support to be enabled in Rank Math → General Settings → Others → Headless CMS Support.';
	}

	public function get_category() {
		return 'rank-math';
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
				'url' => array(
					'type'        => 'string',
					'description' => 'The URL to get SEO head data for.',
				),
			),
			'required'   => array( 'url' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'rank_math' ) ) {
			throw new \RuntimeException( 'Rank Math SEO is not active on this site. Please install and activate Rank Math SEO to use this tool.' );
		}

		$this->validate_required( $arguments, array( 'url' ) );

		if ( class_exists( '\RankMath\Helper' ) && method_exists( '\RankMath\Helper', 'get_settings' ) ) {
			$headless = \RankMath\Helper::get_settings( 'general.headless_support' );
			if ( empty( $headless ) ) {
				throw new \RuntimeException( 'Rank Math Headless CMS Support is not enabled. Go to Rank Math → General Settings → Others → Headless CMS Support and enable it.' );
			}
		}

		$params = array( 'url' => esc_url_raw( $arguments['url'] ) );

		return $this->rest_request( 'GET', '/rankmath/v1/getHead', $params );
	}
}
