<?php




namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Tsf_Update_Post_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_tsf_update_post_seo';
	}

	public function get_description() {
		return 'Updates The SEO Framework SEO metadata for a post or page (SEO title, description, canonical URL, noindex/nofollow/noarchive, Open Graph and Twitter title/description, social image) via the WordPress post meta API. The noindex, nofollow and noarchive fields are tri-state strings: "-1" (force off), "0" (default), "1" (force on) — booleans are rejected. Only the fields you provide are changed.';
	}

	public function get_category() {
		return 'the-seo-framework';
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
				'title'           => array(
					'type'        => 'string',
					'description' => 'The SEO title (_genesis_title).',
				),
				'description'     => array(
					'type'        => 'string',
					'description' => 'The meta description (_genesis_description).',
				),
				'canonical'       => array(
					'type'        => 'string',
					'description' => 'The canonical URL (_genesis_canonical_uri).',
				),
				'noindex'         => array(
					'type'        => 'string',
					'enum'        => array( '-1', '0', '1' ),
					'description' => 'Tri-state robots noindex: "-1" force off, "0" default, "1" force on.',
				),
				'nofollow'        => array(
					'type'        => 'string',
					'enum'        => array( '-1', '0', '1' ),
					'description' => 'Tri-state robots nofollow: "-1" force off, "0" default, "1" force on.',
				),
				'noarchive'       => array(
					'type'        => 'string',
					'enum'        => array( '-1', '0', '1' ),
					'description' => 'Tri-state robots noarchive: "-1" force off, "0" default, "1" force on.',
				),
				'og_title'        => array(
					'type'        => 'string',
					'description' => 'The Open Graph title (_open_graph_title).',
				),
				'og_description'  => array(
					'type'        => 'string',
					'description' => 'The Open Graph description (_open_graph_description).',
				),
				'twitter_title'   => array(
					'type'        => 'string',
					'description' => 'The Twitter card title (_twitter_title).',
				),
				'twitter_description' => array(
					'type'        => 'string',
					'description' => 'The Twitter card description (_twitter_description).',
				),
				'social_image_url' => array(
					'type'        => 'string',
					'description' => 'The social image URL (_social_image_url).',
				),
				'social_image_id'  => array(
					'type'        => 'integer',
					'description' => 'The social image attachment ID (_social_image_id).',
				),
			),
			'required'   => array( 'post_id' ),
		);
	}

	public function execute( array $arguments ) {
		if ( ! function_exists( 'tsf' ) ) {
			throw new \RuntimeException( 'The SEO Framework is not active on this site. Please install and activate The SEO Framework to use this tool.' );
		}

		$post_id = $this->parse_required_id( $arguments['post_id'] ?? null, 'post_id' );

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			throw new \RuntimeException( 'You do not have permission to edit this post.' );
		}

		$updated = array();

		$text_map = array(
			'title'               => '_genesis_title',
			'description'         => '_genesis_description',
			'og_title'            => '_open_graph_title',
			'og_description'      => '_open_graph_description',
			'twitter_title'       => '_twitter_title',
			'twitter_description' => '_twitter_description',
		);
		foreach ( $text_map as $arg => $key ) {
			if ( isset( $arguments[ $arg ] ) ) {
				update_post_meta( $post_id, $key, sanitize_text_field( (string) $arguments[ $arg ] ) );
				$updated[] = $key;
			}
		}

		$url_map = array(
			'canonical'        => '_genesis_canonical_uri',
			'social_image_url' => '_social_image_url',
		);
		foreach ( $url_map as $arg => $key ) {
			if ( isset( $arguments[ $arg ] ) ) {
				update_post_meta( $post_id, $key, esc_url_raw( (string) $arguments[ $arg ] ) );
				$updated[] = $key;
			}
		}

		$qubit_map = array(
			'noindex'   => '_genesis_noindex',
			'nofollow'  => '_genesis_nofollow',
			'noarchive' => '_genesis_noarchive',
		);
		foreach ( $qubit_map as $arg => $key ) {
			if ( ! isset( $arguments[ $arg ] ) ) {
				continue;
			}
			$value = $arguments[ $arg ];
			if ( is_bool( $value ) ) {
				throw new \InvalidArgumentException( sprintf( 'Field "%s" must be a tri-state value ("-1", "0", or "1"), not a boolean.', $arg ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			
			
			
			if ( is_numeric( $value ) ) {
				$value = (int) $value;
			}
			if ( ! is_int( $value ) || ! in_array( $value, array( -1, 0, 1 ), true ) ) {
				throw new \InvalidArgumentException( sprintf( 'Field "%s" must be one of -1, 0, or 1.', $arg ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			update_post_meta( $post_id, $key, $value );
			$updated[] = $key;
		}

		if ( isset( $arguments['social_image_id'] ) ) {
			update_post_meta( $post_id, '_social_image_id', (int) $arguments['social_image_id'] );
			$updated[] = '_social_image_id';
		}

		return array(
			'post_id'        => $post_id,
			'updated_fields' => $updated,
		);
	}
}
