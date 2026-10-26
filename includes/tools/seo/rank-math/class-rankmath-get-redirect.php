<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;




class Rankmath_Get_Redirect extends Base_Tool {

	use Rank_Math_Redirects;

	public function get_name() {
		return 'wp_rm_get_redirect';
	}

	public function get_description() {
		return 'Gets one redirect of Rank Math SEO\'s Redirections module by id, including one in the trash. Required: `id` (from wp_rm_list_redirects). Returns { redirect: { id, sources: [{ pattern, comparison, ignore_case }], target, code, status, hits, created, updated, last_accessed } }; `target` is null for a 410 or 451. An id that names no redirect is refused.';
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
				'id' => array(
					'type'        => 'integer',
					'description' => 'Redirect id.',
				),
			),
			'required'   => array( 'id' ),
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_redirects();
		$id = $this->parse_required_id( $arguments['id'] ?? null, 'id' );
		return array( 'redirect' => self::normalise( $this->require_redirect( $id ) ) );
	}
}
