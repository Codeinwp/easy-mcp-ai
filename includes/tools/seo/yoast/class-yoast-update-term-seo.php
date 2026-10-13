<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

























class Yoast_Update_Term_Seo extends Base_Tool {

	


	const FIELDS = array(
		'seo_title'           => 'wpseo_title',
		'meta_description'    => 'wpseo_desc',
		'focus_keyword'       => 'wpseo_focuskw',
		'is_cornerstone'      => 'wpseo_is_cornerstone',
		'noindex'             => 'wpseo_noindex',
		'canonical'           => 'wpseo_canonical',
		'breadcrumb_title'    => 'wpseo_bctitle',
		'og_title'            => 'wpseo_opengraph-title',
		'og_description'      => 'wpseo_opengraph-description',
		'og_image'            => 'wpseo_opengraph-image',
		'twitter_title'       => 'wpseo_twitter-title',
		'twitter_description' => 'wpseo_twitter-description',
		'twitter_image'       => 'wpseo_twitter-image',
	);

	



	const ADVANCED = array( 'noindex', 'canonical', 'breadcrumb_title' );

	const URL_FIELDS = array( 'canonical', 'og_image', 'twitter_image' );

	



	const IMAGE_IDS = array(
		'og_image'      => 'wpseo_opengraph-image-id',
		'twitter_image' => 'wpseo_twitter-image-id',
	);

	public function get_name() {
		return 'wp_yoast_update_term_seo';
	}

	public function get_description() {
		return 'Updates the Yoast SEO values of a category, tag or other term — the fields of Yoast\'s SEO box on the term edit screen. Pass term_id and only the fields to change; the others keep their value, "" clears a text field. Use this, not wp_update_term_meta: Yoast keeps term SEO in its own settings and never reads _yoast_wpseo_* term meta. Requires permission to edit the term; noindex, canonical and breadcrumb_title also need Yoast\'s advanced-settings permission (Editors and Administrators by default). A refused field changes nothing. Returns { updated_fields, stored_differently, fields } — stored_differently lists fields Yoast\'s sanitiser changed or dropped.';
	}

	public function get_category() {
		return 'yoast-seo';
	}

	public function get_required_capability() {
		
		
		
		return 'read';
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
		$text = array( 'type' => 'string' );
		return array(
			'type'       => 'object',
			'properties' => array(
				'term_id'             => array( 'type' => 'integer', 'description' => 'The ID of the category, tag or other term.' ),
				'seo_title'           => $text + array( 'description' => 'SEO title. Yoast variables such as %%term_title%% and %%sep%% are kept.' ),
				'meta_description'    => $text + array( 'description' => 'Meta description.' ),
				'focus_keyword'       => $text + array( 'description' => 'Focus keyphrase.' ),
				'is_cornerstone'      => array( 'type' => 'boolean', 'description' => 'Cornerstone content.' ),
				'noindex'             => $text + array( 'enum' => array( 'default', 'index', 'noindex' ), 'description' => 'Search engine visibility: "default" follows the taxonomy setting, "index" or "noindex" overrides it. Advanced field.' ),
				'canonical'           => $text + array( 'description' => 'Canonical URL, "" to clear. Advanced field.' ),
				'breadcrumb_title'    => $text + array( 'description' => 'Breadcrumb title. Advanced field.' ),
				'og_title'            => $text + array( 'description' => 'Open Graph (Facebook) title.' ),
				'og_description'      => $text + array( 'description' => 'Open Graph (Facebook) description.' ),
				'og_image'            => $text + array( 'description' => 'Open Graph image URL, "" to clear.' ),
				'twitter_title'       => $text + array( 'description' => 'X (Twitter) card title.' ),
				'twitter_description' => $text + array( 'description' => 'X (Twitter) card description.' ),
				'twitter_image'       => $text + array( 'description' => 'X (Twitter) card image URL, "" to clear.' ),
			),
			'required'   => array( 'term_id' ),
		);
	}

	public function execute( array $arguments ) {
		$term    = self::resolve_term( $this->parse_required_id( $arguments['term_id'] ?? null, 'term_id' ) );
		$changes = $this->validated_changes( $arguments );

		$this->write_and_refresh( $term, $this->merged_values( $term, $changes ) );

		$after = self::read( $term );
		return array(
			'term_id'            => (int) $term->term_id,
			'taxonomy'           => $term->taxonomy,
			'updated_fields'     => array_keys( $changes ),
			'stored_differently' => $this->altered_fields( $changes, $after ),
			'fields'             => $after,
		);
	}

	








	private function validated_changes( array $arguments ) {
		$unknown = array_diff( array_keys( $arguments ), array_merge( array( 'term_id' ), array_keys( self::FIELDS ) ) );
		if ( $unknown ) {
			throw new \InvalidArgumentException( sprintf( 'Unknown field(s): %s. Accepted: %s.', implode( ', ', array_map( 'sanitize_key', $unknown ) ), implode( ', ', array_keys( self::FIELDS ) ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$changes = array();
		foreach ( array_keys( self::FIELDS ) as $field ) {
			if ( array_key_exists( $field, $arguments ) ) {
				$changes[ $field ] = $this->validate_field( $field, $arguments[ $field ] );
			}
		}
		if ( ! $changes ) {
			throw new \InvalidArgumentException( 'Nothing to update: pass at least one field. Accepted: ' . implode( ', ', array_keys( self::FIELDS ) ) . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		$advanced = array_values( array_intersect( array_keys( $changes ), self::ADVANCED ) );
		if ( $advanced && ! self::can_edit_advanced( get_current_user_id() ) ) {
			throw new \RuntimeException( sprintf( 'Yoast SEO does not let the current user edit %s: those fields need the wpseo_edit_advanced_metadata capability unless "Restrict advanced settings for authors" is off in Yoast. Nothing was changed.', implode( ', ', $advanced ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $changes;
	}

	






	private function merged_values( $term, array $changes ) {
		$values = self::stored( $term );
		foreach ( $changes as $field => $value ) {
			$values[ self::FIELDS[ $field ] ] = 'is_cornerstone' === $field ? ( $value ? '1' : '0' ) : $value;
			if ( array_key_exists( $field, self::IMAGE_IDS ) ) {
				$values[ self::IMAGE_IDS[ $field ] ] = $this->attachment_id_for( $value );
			}
		}
		return $values;
	}

	






	private function attachment_id_for( $url ) {
		$id = ( '' !== $url && function_exists( 'attachment_url_to_postid' ) ) ? (int) attachment_url_to_postid( $url ) : 0;
		return $id > 0 ? (string) $id : '';
	}

	







	private function write_and_refresh( $term, array $values ) {
		$history = class_exists( '\\Easy_MCP_AI\\History\\Change_Context' );

		
		
		if ( $history ) {
			\Easy_MCP_AI\History\Change_Context::expect_scoped_option( 'wpseo_taxonomy_meta', array( $term->taxonomy, (string) $term->term_id ) );
		}
		\WPSEO_Taxonomy_Meta::set_values( (int) $term->term_id, $term->taxonomy, $values );
		if ( $history ) {
			
			
			\Easy_MCP_AI\History\Change_Context::consume_scoped_option( 'wpseo_taxonomy_meta' );
			
			\Easy_MCP_AI\History\Change_Context::expect_refresh_only( 'term', (int) $term->term_id );
		}
		do_action( 'edited_term', (int) $term->term_id, (int) $term->term_taxonomy_id, $term->taxonomy, array() );
	}

	






	private function altered_fields( array $changes, array $after ) {
		$altered = array();
		foreach ( $changes as $field => $value ) {
			if ( $after[ $field ] !== $value ) {
				$altered[] = $field;
			}
		}
		return $altered;
	}

	







	public static function resolve_term( $term_id ) {
		if ( ! class_exists( 'WPSEO_Taxonomy_Meta' ) || ! class_exists( 'WPSEO_Options' ) ) {
			throw new \RuntimeException( 'Yoast SEO is not active on this site. Please install and activate Yoast SEO to use this tool.' );
		}
		$term = get_term( $term_id );
		if ( ! $term || is_wp_error( $term ) ) {
			throw new \InvalidArgumentException( 'Term not found.' );
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			throw new \RuntimeException( sprintf( 'Insufficient capability for taxonomy %s: WordPress does not grant edit_term for term %d to the current user.', $term->taxonomy, $term_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $term;
	}

	







	public static function can_edit_advanced( $user_id ) {
		if ( user_can( (int) $user_id, 'wpseo_manage_options' ) || user_can( (int) $user_id, 'wpseo_edit_advanced_metadata' ) ) {
			return true;
		}
		return false === \WPSEO_Options::get( 'disableadvanced_meta' );
	}

	





	public static function read( $term ) {
		$stored = self::stored( $term );
		$out    = array();
		foreach ( self::FIELDS as $field => $key ) {
			$value         = isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
			$out[ $field ] = 'is_cornerstone' === $field ? '1' === $value : $value;
		}
		return $out;
	}

	





	private static function stored( $term ) {
		$stored = \WPSEO_Taxonomy_Meta::get_term_meta( $term, $term->taxonomy );
		if ( ! is_array( $stored ) ) {
			throw new \RuntimeException( 'Yoast SEO could not read the stored SEO values for this term.' );
		}
		return $stored;
	}

	






	private function validate_field( $field, $value ) {
		if ( 'is_cornerstone' === $field ) {
			if ( ! is_bool( $value ) ) {
				throw new \InvalidArgumentException( 'is_cornerstone must be a boolean.' );
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be a string ("" clears it).', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		if ( 'noindex' === $field && ! in_array( $value, array( 'default', 'index', 'noindex' ), true ) ) {
			throw new \InvalidArgumentException( 'noindex must be one of: default, index, noindex.' );
		}
		if ( in_array( $field, self::URL_FIELDS, true ) && '' !== $value ) {
			$this->assert_http_url( $field, $value );
		}
		return $value;
	}

	








	private function assert_http_url( $field, $value ) {
		$parts  = wp_parse_url( $value );
		$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
		if ( empty( $parts['host'] ) || ! in_array( $scheme, array( 'http', 'https' ), true ) || preg_match( '/\s/', $value ) ) {
			throw new \InvalidArgumentException( sprintf( '%s must be an http(s) URL, or "" to clear it.', $field ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}
}
