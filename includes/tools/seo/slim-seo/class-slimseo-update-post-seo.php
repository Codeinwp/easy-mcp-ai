<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Slimseo_Update_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_slimseo_update_post_seo';
	}

	public function get_description() {
		return 'Updates the Slim SEO metadata for a post or page (title, description, facebook_image, twitter_image, canonical, noindex) via the WordPress core REST API. Slim SEO stores all fields in a single object, so this tool reads the current values and merges your changes over them — fields you omit are preserved.';
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
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'        => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'title'          => array(
					'type'        => 'string',
					'description' => 'The Slim SEO title.',
				),
				'description'    => array(
					'type'        => 'string',
					'description' => 'The Slim SEO meta description.',
				),
				'facebook_image' => array(
					'type'        => 'string',
					'description' => 'The Open Graph (Facebook) image URL.',
				),
				'twitter_image'  => array(
					'type'        => 'string',
					'description' => 'The Twitter card image URL.',
				),
				'canonical'      => array(
					'type'        => 'string',
					'description' => 'The canonical URL.',
				),
				'noindex'        => array(
					'type'        => 'boolean',
					'description' => 'Set true to mark this post as noindex.',
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

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}

		$base = $this->resolve_post_rest_base( $post_id );

		
		
		$current = $this->rest_request( 'GET', '/wp/v2/' . $base . '/' . $post_id, array( '_fields' => 'meta.slim_seo' ) );
		$merged  = $current['meta']['slim_seo'] ?? array();
		if ( ! is_array( $merged ) ) {
			$merged = array();
		}

		$text_fields = array( 'title', 'description' );
		foreach ( $text_fields as $field ) {
			if ( isset( $arguments[ $field ] ) ) {
				$merged[ $field ] = sanitize_text_field( (string) $arguments[ $field ] );
			}
		}

		$url_fields = array( 'facebook_image', 'twitter_image', 'canonical' );
		foreach ( $url_fields as $field ) {
			if ( isset( $arguments[ $field ] ) ) {
				$merged[ $field ] = esc_url_raw( (string) $arguments[ $field ] );
			}
		}

		if ( isset( $arguments['noindex'] ) ) {
			$merged['noindex'] = (bool) $arguments['noindex'];
		}

		$this->rest_request( 'POST', '/wp/v2/' . $base . '/' . $post_id, array( 'meta' => array( 'slim_seo' => $merged ) ) );

		return array(
			'post_id'  => $post_id,
			'slim_seo' => $merged,
		);
	}
}
