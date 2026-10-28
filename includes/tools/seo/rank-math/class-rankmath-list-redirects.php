<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Rankmath_List_Redirects extends Base_Tool {

	use Rank_Math_Redirects;

	const STATUSES = array( 'any', 'active', 'inactive', 'trashed' );

	const MAX_PER_PAGE = 200;

	public function get_name() {
		return 'wp_rm_list_redirects';
	}

	public function get_description() {
		return 'Lists the redirects of Rank Math SEO\'s Redirections module, newest first. Optional: `search` (part of a source or target), `status` ("active", "inactive", "trashed", or "any" = everything outside the trash, the default), `per_page` (1-200, default 20), `page` (1-based). Returns { redirects: [{ id, sources: [{ pattern, comparison, ignore_case }], target, code, status, hits, created, updated, last_accessed }], total, page, per_page, total_pages }. `target` is null for a 410 or 451. Requires Rank Math with its Redirections module on.';
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
				'search'   => array(
					'type'        => 'string',
					'description' => 'Part of a source pattern or of the target (substring).',
				),
				'status'   => array(
					'type'        => 'string',
					'enum'        => self::STATUSES,
					'description' => 'Only redirects with this status. Default: any (everything outside the trash).',
				),
				'per_page' => array(
					'type'        => 'integer',
					'description' => 'Redirects per page, 1-200.',
					'default'     => 20,
					'minimum'     => 1,
					'maximum'     => self::MAX_PER_PAGE,
				),
				'page'     => array(
					'type'        => 'integer',
					'description' => 'Page number, starting at 1.',
					'default'     => 1,
					'minimum'     => 1,
				),
			),
			'required'   => array(),
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_redirects();

		$status = isset( $arguments['status'] ) && '' !== $arguments['status'] ? $arguments['status'] : 'any';
		$this->refuse_unless_one_of( 'status', $status, self::STATUSES, $status, implode( ', ', self::STATUSES ) );
		$per_page = isset( $arguments['per_page'] ) ? min( self::MAX_PER_PAGE, max( 1, (int) $arguments['per_page'] ) ) : 20;
		$page     = isset( $arguments['page'] ) ? max( 1, (int) $arguments['page'] ) : 1;

		$result = \RankMath\Redirections\DB::get_redirections(
			array(
				'limit'  => $per_page,
				'paged'  => $page,
				'search' => isset( $arguments['search'] ) ? sanitize_text_field( (string) $arguments['search'] ) : '',
				'status' => $status,
			)
		);
		$rows  = is_array( $result['redirections'] ?? null ) ? $result['redirections'] : array();
		$total = (int) ( $result['count'] ?? 0 );

		return array(
			'redirects'   => array_values( array_map( array( __CLASS__, 'normalise' ), $rows ) ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total / $per_page ),
		);
	}
}
