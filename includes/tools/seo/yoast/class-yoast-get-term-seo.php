<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;





class Yoast_Get_Term_Seo extends Base_Tool {

	public function get_name() {
		return 'wp_yoast_get_term_seo';
	}

	public function get_description() {
		return 'Gets the Yoast SEO values of a category, tag or other term: SEO title, meta description, focus keyphrase, cornerstone, noindex, canonical, breadcrumb title and the Open Graph / X card fields. Yoast stores term SEO in its own settings, not in term meta, so wp_get_term_meta does not show these. Returns { term_id, taxonomy, fields, can_edit_advanced } — can_edit_advanced says whether the current user may change noindex, canonical and breadcrumb_title. Requires permission to edit the term.';
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
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'term_id' => array( 'type' => 'integer', 'description' => 'The ID of the category, tag or other term.' ),
			),
			'required'   => array( 'term_id' ),
		);
	}

	public function execute( array $arguments ) {
		$term = Yoast_Update_Term_Seo::resolve_term( $this->parse_required_id( $arguments['term_id'] ?? null, 'term_id' ) );
		return array(
			'term_id'           => (int) $term->term_id,
			'taxonomy'          => $term->taxonomy,
			'fields'            => Yoast_Update_Term_Seo::read( $term ),
			'can_edit_advanced' => Yoast_Update_Term_Seo::can_edit_advanced( get_current_user_id() ),
		);
	}
}
