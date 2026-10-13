<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_Referring_Domains_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_backlinks_referring_domains_live';
	}

	public function get_description() {
		return 'Returns a paginated list of individual referring domains pointing at a target domain, subdomain, or page. Each domain entry includes rank, backlink count, TLD distribution, and link types. (meter: varies by plan; Backlinks is the most expensive API family)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Backlinks referring domains list',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target' ),
			'properties' => array(
				'target'                => array(
					'type'        => 'string',
					'description' => 'The target domain, subdomain, or page URL (required).',
				),
				'mode'                  => array(
					'type'        => 'string',
					'enum'        => array( 'as_is', 'one_per_domain', 'one_per_anchor' ),
					'description' => 'Aggregation mode: as_is, one_per_domain, or one_per_anchor.',
				),
				'limit'                 => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 1000,
					'default'     => 100,
					'description' => 'Maximum number of results (1-1000, default 100).',
				),
				'offset'                => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Pagination offset.',
				),
				'order_by'              => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Ordering for results.',
				),
				'filters'               => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Filter conditions.',
				),
				'backlinks_filters'     => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Backlink-specific filter conditions.',
				),
				'backlinks_status_type' => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'live', 'lost' ),
					'description' => 'Backlink status filter: all, live, or lost.',
				),
				'include_subdomains'    => array(
					'type'        => 'boolean',
					'description' => 'Include subdomains in results.',
				),
				'internal_list_limit'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 1000,
					'description' => 'Limit for internal lists (1-1000).',
				),
			),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['target'] ) || empty( $arguments['target'] ) ) {
			throw new \RuntimeException(
				'target is required.'
			);
		}

		
		if ( isset( $arguments['mode'] ) ) {
			$mode = (string) $arguments['mode'];
			if ( ! in_array( $mode, array( 'as_is', 'one_per_domain', 'one_per_anchor' ), true ) ) {
				throw new \RuntimeException(
					'mode must be one of: as_is, one_per_domain, one_per_anchor.'
				);
			}
		}

		
		if ( isset( $arguments['limit'] ) ) {
			$limit = (int) $arguments['limit'];
			if ( $limit < 1 || $limit > 1000 ) {
				throw new \RuntimeException(
					'limit must be between 1 and 1000.'
				);
			}
		}

		
		if ( isset( $arguments['backlinks_status_type'] ) ) {
			$backlinks_status_type = (string) $arguments['backlinks_status_type'];
			if ( ! in_array( $backlinks_status_type, array( 'all', 'live', 'lost' ), true ) ) {
				throw new \RuntimeException(
					'backlinks_status_type must be one of: all, live, lost.'
				);
			}
		}

		
		$task = array( 'target' => (string) $arguments['target'] );

		
		if ( isset( $arguments['mode'] ) ) {
			$task['mode'] = (string) $arguments['mode'];
		}
		if ( isset( $arguments['limit'] ) ) {
			$task['limit'] = (int) $arguments['limit'];
		}
		if ( isset( $arguments['offset'] ) ) {
			$task['offset'] = (int) $arguments['offset'];
		}
		if ( isset( $arguments['order_by'] ) ) {
			$task['order_by'] = (array) $arguments['order_by'];
		}
		if ( isset( $arguments['filters'] ) ) {
			$task['filters'] = (array) $arguments['filters'];
		}
		if ( isset( $arguments['backlinks_filters'] ) ) {
			$task['backlinks_filters'] = (array) $arguments['backlinks_filters'];
		}
		if ( isset( $arguments['backlinks_status_type'] ) ) {
			$task['backlinks_status_type'] = (string) $arguments['backlinks_status_type'];
		}
		if ( isset( $arguments['include_subdomains'] ) ) {
			$task['include_subdomains'] = (bool) $arguments['include_subdomains'];
		}
		if ( isset( $arguments['internal_list_limit'] ) ) {
			$task['internal_list_limit'] = (int) $arguments['internal_list_limit'];
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/backlinks/referring_domains/live',
			$task
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] ?? 0 )
		);

	}
}
