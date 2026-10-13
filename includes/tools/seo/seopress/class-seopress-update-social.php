<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Update_Social extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_update_post_social';
	}

	public function get_description() {
		return 'Updates the SEOPress social (Open Graph / Twitter) settings for a post or page via the seopress/v1 REST API. Only the fields you provide are changed.';
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
				'post_id'             => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'og_title'            => array(
					'type'        => 'string',
					'description' => 'The Open Graph (Facebook) title.',
				),
				'og_description'      => array(
					'type'        => 'string',
					'description' => 'The Open Graph (Facebook) description.',
				),
				'og_image'            => array(
					'type'        => 'string',
					'description' => 'The Open Graph (Facebook) image URL.',
				),
				'twitter_title'       => array(
					'type'        => 'string',
					'description' => 'The Twitter card title.',
				),
				'twitter_description' => array(
					'type'        => 'string',
					'description' => 'The Twitter card description.',
				),
				'twitter_image'       => array(
					'type'        => 'string',
					'description' => 'The Twitter card image URL.',
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

		$text_map = array(
			'og_title'            => '_seopress_social_fb_title',
			'og_description'      => '_seopress_social_fb_desc',
			'twitter_title'       => '_seopress_social_twitter_title',
			'twitter_description' => '_seopress_social_twitter_desc',
		);
		foreach ( $text_map as $arg => $key ) {
			if ( isset( $arguments[ $arg ] ) ) {
				$body[ $key ] = sanitize_text_field( (string) $arguments[ $arg ] );
			}
		}

		$url_map = array(
			'og_image'      => '_seopress_social_fb_img',
			'twitter_image' => '_seopress_social_twitter_img',
		);
		foreach ( $url_map as $arg => $key ) {
			if ( isset( $arguments[ $arg ] ) ) {
				$body[ $key ] = esc_url_raw( (string) $arguments[ $arg ] );
			}
		}

		$this->rest_request( 'PUT', '/seopress/v1/posts/' . $post_id . '/social-settings', $body );

		return array(
			'post_id' => $post_id,
			'updated' => true,
		);
	}
}
