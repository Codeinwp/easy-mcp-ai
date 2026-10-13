<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Yoast_Get_Post_Seo extends Base_Tool {

	




	const META_KEYS = array(
		'seo_title'           => '_yoast_wpseo_title',
		'meta_description'    => '_yoast_wpseo_metadesc',
		'focus_keyword'       => '_yoast_wpseo_focuskw',
		'og_title'            => '_yoast_wpseo_opengraph-title',
		'og_description'      => '_yoast_wpseo_opengraph-description',
		'og_image'            => '_yoast_wpseo_opengraph-image',
		'twitter_title'       => '_yoast_wpseo_twitter-title',
		'twitter_description' => '_yoast_wpseo_twitter-description',
		'twitter_image'       => '_yoast_wpseo_twitter-image',
		'is_cornerstone'      => '_yoast_wpseo_is_cornerstone',
	);

	




	const STORED_FIELDS = array( 'seo_title', 'meta_description', 'focus_keyword', 'is_cornerstone' );

	public function get_name() {
		return 'wp_yoast_get_post_seo';
	}

	public function get_description() {
		return 'Gets Yoast SEO data for one post or page by ID; works on every site, including local and staging. `yoast_head_json` is what search engines get: it shows Yoast\'s generated title when no SEO title is set, and has no focus keyphrase or cornerstone flag. For what is actually saved, read `stored_meta`: { seo_title, meta_description, focus_keyword, is_cornerstone }, where `""` means not set and is_cornerstone `"1"` means cornerstone; it is present only if you can edit the post. `post_type` is the REST base (`pages` for a page); omit it to use the post\'s own type. Post types outside the REST API (e.g. tribe_events) return `yoast_meta_fallback` instead: the saved values, unset ones omitted.';
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
				'post_id'   => array(
					'type'        => 'integer',
					'description' => 'The ID of the post or page.',
				),
				'post_type' => array(
					'type'        => 'string',
					'description' => 'The REST API base for the post type (e.g. posts, pages). Omit it to use the post\'s own type.',
					'default'     => 'posts',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! class_exists( 'WPSEO_Options' ) ) {
			throw new \RuntimeException( 'Yoast SEO is not active on this site. Please install and activate Yoast SEO to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			throw new \RuntimeException( 'Invalid post ID.' );
		}

		if ( ! empty( $arguments['post_type'] ) ) {
			$rest_base = $this->validate_rest_route_segment( $arguments['post_type'], 'post_type' );
		} else {
			$pt_obj    = get_post_type_object( $post->post_type );
			$rest_base = ( $pt_obj && ! empty( $pt_obj->rest_base ) ) ? $pt_obj->rest_base : null;
		}

		
		$rest_error = null;
		if ( $rest_base ) {
			try {
				$data = $this->rest_request( 'GET', '/wp/v2/' . $rest_base . '/' . $post_id );
				if ( ! empty( $data['yoast_head_json'] ) ) {
					return $this->head_result( $post_id, $data['yoast_head_json'] );
				}
			} catch ( \Exception $e ) {
				
				$rest_error = $e;
			}
		}

		$this->guard_fallback( $post_id, $rest_error );

		
		
		
		
		return array(
			'post_id'             => $post_id,
			'yoast_meta_fallback' => $this->fallback_meta( $post_id ),
		);
	}

	







	private function head_result( $post_id, $yoast_head_json ) {
		$result = array(
			'post_id'         => $post_id,
			'yoast_head_json' => $yoast_head_json,
		);
		$stored = $this->stored_meta( $post_id );
		if ( null !== $stored ) {
			$result['stored_meta'] = $stored;
		}
		return $result;
	}

	








	private function guard_fallback( $post_id, $rest_error ) {
		if ( current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( null !== $rest_error ) {
			throw $rest_error;
		}
		throw new \RuntimeException( sprintf( 'Sorry, you are not allowed to edit post %d, so its stored Yoast SEO values cannot be read.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	





	private function fallback_meta( $post_id ) {
		$yoast_data = array();
		foreach ( self::META_KEYS as $field => $meta_key ) {
			$value = get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $value ) {
				$yoast_data[ $field ] = $value;
			}
		}
		return $yoast_data;
	}

	














	private function stored_meta( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return null;
		}
		$stored = array();
		foreach ( self::STORED_FIELDS as $field ) {
			$value            = get_post_meta( $post_id, self::META_KEYS[ $field ], true );
			$stored[ $field ] = is_scalar( $value ) ? (string) $value : '';
		}
		return $stored;
	}
}
