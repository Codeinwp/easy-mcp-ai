<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Yoast_Get_Head extends Base_Tool {

	public function get_name() {
		return 'wp_yoast_get_head';
	}

	public function get_description() {
		return 'Gets the rendered Yoast SEO head HTML and JSON-LD for a URL on this site. Pass the exact permalink: the home page needs its trailing slash, and the query string is ignored (`?p=123` returns the home page). On a local or staging site use wp_yoast_get_post_seo with the post ID instead: Yoast builds no URL index there, so this tool usually fails. It also fails for other hosts and for pages Yoast has not indexed; the error says which. Returns { html, json, status }.';
	}

	public function get_category() {
		return 'yoast-seo';
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
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			throw new \RuntimeException( 'Yoast SEO is not active on this site. Please install and activate Yoast SEO to use this tool.' );
		}

		$this->validate_required( $arguments, array( 'url' ) );

		$params = array( 'url' => esc_url_raw( $arguments['url'] ) );

		try {
			return $this->rest_request( 'GET', '/yoast/v1/get_head', $params );
		} catch ( \RuntimeException $e ) {
			
			
			if ( self::REST_ERROR_WITHOUT_MESSAGE !== $e->getCode() ) {
				throw $e;
			}
			throw new \RuntimeException( $this->explain_not_found( $params['url'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	











	private function explain_not_found( $url ) {
		
		
		
		
		$url_parts = wp_parse_url( $url );
		$site_host = (string) wp_parse_url( site_url(), PHP_URL_HOST );

		if ( ! is_array( $url_parts ) || empty( $url_parts['host'] ) || $url_parts['host'] !== $site_host ) {
			return sprintf( 'No Yoast SEO data: Yoast resolves only URLs on this site (host %s). For one post or page, use wp_yoast_get_post_seo with its ID.', $site_host );
		}
		if ( empty( $url_parts['path'] ) ) {
			return sprintf( 'No Yoast SEO data: the URL has no path. For the home page pass %s (with the trailing slash).', home_url( '/' ) );
		}
		if ( ! $this->yoast_builds_index() ) {
			$environment = wp_get_environment_type();
			if ( 'production' === $environment ) {
				return 'No Yoast SEO data: Yoast indexing is turned off on this site (Yoast\\WP\\SEO\\should_index_indexables filter). For one post or page, use wp_yoast_get_post_seo with its ID.';
			}
			return sprintf( 'No Yoast SEO data: this site\'s environment type is "%s" and Yoast indexes only production sites. For one post or page, use wp_yoast_get_post_seo with its ID.', $environment );
		}
		return 'No Yoast SEO data for this permalink: not published, a different permalink, or not indexed yet. For one post or page, use wp_yoast_get_post_seo with its ID.';
	}

	






	private function yoast_builds_index() {
		if ( function_exists( 'YoastSEO' ) ) {
			try {
				return (bool) YoastSEO()->helpers->indexable->should_index_indexables();
			} catch ( \Throwable $e ) {
				
			}
		}
		return 'production' === wp_get_environment_type();
	}
}
