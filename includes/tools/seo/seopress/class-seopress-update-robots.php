<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Seopress_Update_Robots extends Base_Tool {

	public function get_name() {
		return 'wp_seopress_update_post_robots';
	}

	public function get_description() {
		return 'Updates the SEOPress robots / indexing settings for a post or page via the seopress/v1 REST API (noindex, nofollow, no image index, no snippet, canonical URL, primary category and breadcrumbs). Only the fields you provide are changed.';
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
				'post_id'        => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'noindex'        => array(
					'type'        => 'boolean',
					'description' => 'Set true to add this post to the noindex robots directive.',
				),
				'nofollow'       => array(
					'type'        => 'boolean',
					'description' => 'Set true to add this post to the nofollow robots directive.',
				),
				'no_image_index' => array(
					'type'        => 'boolean',
					'description' => 'Set true to add the noimageindex robots directive.',
				),
				'no_snippet'     => array(
					'type'        => 'boolean',
					'description' => 'Set true to add the nosnippet robots directive.',
				),
				'canonical'      => array(
					'type'        => 'string',
					'description' => 'The canonical URL for this post.',
				),
				'primary_cat'    => array(
					'type'        => 'string',
					'description' => 'The primary category for this post.',
				),
				'breadcrumbs'    => array(
					'type'        => 'string',
					'description' => 'The custom breadcrumbs label for this post.',
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

		$bool_map = array(
			'noindex'        => '_seopress_robots_index',
			'nofollow'       => '_seopress_robots_follow',
			'no_image_index' => '_seopress_robots_imageindex',
			'no_snippet'     => '_seopress_robots_snippet',
		);
		foreach ( $bool_map as $arg => $key ) {
			
			
			
			
			if ( isset( $arguments[ $arg ] ) ) {
				$body[ $key ] = rest_sanitize_boolean( $arguments[ $arg ] ) ? 'yes' : '';
			}
		}

		if ( isset( $arguments['canonical'] ) ) {
			$body['_seopress_robots_canonical'] = esc_url_raw( (string) $arguments['canonical'] );
		}
		if ( isset( $arguments['primary_cat'] ) ) {
			$body['_seopress_robots_primary_cat'] = sanitize_text_field( (string) $arguments['primary_cat'] );
		}
		if ( isset( $arguments['breadcrumbs'] ) ) {
			$body['_seopress_robots_breadcrumbs'] = sanitize_text_field( (string) $arguments['breadcrumbs'] );
		}

		$this->rest_request( 'PUT', '/seopress/v1/posts/' . $post_id . '/meta-robot-settings', $body );

		return array(
			'post_id' => $post_id,
			'updated' => true,
		);
	}
}
