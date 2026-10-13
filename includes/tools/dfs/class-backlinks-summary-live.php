<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_Summary_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_backlinks_summary_live';
	}

	public function get_description() {
		return 'Returns aggregate backlink stats for a domain, subdomain, or page — total backlinks, referring domains, rank, broken links, and more. (meter: varies by plan; Backlinks is the most expensive API family)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Backlinks summary for target',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'target'                    => array(
					'type'        => 'string',
					'description' => 'The target domain, subdomain, or page URL (required).',
				),
				'internal_list_limit'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 1000,
					'default'     => 10,
					'description' => 'Limit for internal lists (1-1000, default 10).',
				),
				'backlinks_status_type'     => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'live', 'lost' ),
					'default'     => 'live',
					'description' => 'Backlink status filter: all, live, or lost (default live).',
				),
				'include_subdomains'        => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Include subdomains in results (default true).',
				),
				'include_indirect_links'    => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Include indirect backlinks (default true).',
				),
				'exclude_internal_backlinks' => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Exclude internal backlinks (default true).',
				),
				'backlinks_filters'         => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Array of backlink filters.',
				),
				'rank_scale'                => array(
					'type'        => 'string',
					'enum'        => array( 'one_hundred', 'one_thousand' ),
					'description' => 'Rank scale: one_hundred or one_thousand.',
				),
			),
			'required'   => array( 'target' ),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['target'] ) || empty( $arguments['target'] ) ) {
			throw new \RuntimeException(
				'target is required and must not be empty.'
			);
		}

		
		if ( isset( $arguments['backlinks_status_type'] ) ) {
			$backlinks_status_type = (string) $arguments['backlinks_status_type'];
			if ( ! in_array( $backlinks_status_type, array( 'all', 'live', 'lost' ), true ) ) {
				throw new \RuntimeException(
					'backlinks_status_type must be one of: all, live, lost.'
				);
			}
		}

		
		if ( isset( $arguments['internal_list_limit'] ) ) {
			$internal_list_limit = (int) $arguments['internal_list_limit'];
			if ( $internal_list_limit < 1 || $internal_list_limit > 1000 ) {
				throw new \RuntimeException(
					'internal_list_limit must be between 1 and 1000.'
				);
			}
		}

		
		if ( isset( $arguments['rank_scale'] ) ) {
			$rank_scale = (string) $arguments['rank_scale'];
			if ( ! in_array( $rank_scale, array( 'one_hundred', 'one_thousand' ), true ) ) {
				throw new \RuntimeException(
					'rank_scale must be one of: one_hundred, one_thousand.'
				);
			}
		}

		
		$task = array( 'target' => (string) $arguments['target'] );

		
		if ( isset( $arguments['internal_list_limit'] ) ) {
			$task['internal_list_limit'] = (int) $arguments['internal_list_limit'];
		}
		if ( isset( $arguments['backlinks_status_type'] ) ) {
			$task['backlinks_status_type'] = (string) $arguments['backlinks_status_type'];
		}
		if ( isset( $arguments['include_subdomains'] ) ) {
			$task['include_subdomains'] = (bool) $arguments['include_subdomains'];
		}
		if ( isset( $arguments['include_indirect_links'] ) ) {
			$task['include_indirect_links'] = (bool) $arguments['include_indirect_links'];
		}
		if ( isset( $arguments['exclude_internal_backlinks'] ) ) {
			$task['exclude_internal_backlinks'] = (bool) $arguments['exclude_internal_backlinks'];
		}
		if ( isset( $arguments['backlinks_filters'] ) ) {
			$task['backlinks_filters'] = (array) $arguments['backlinks_filters'];
		}
		if ( isset( $arguments['rank_scale'] ) ) {
			$task['rank_scale'] = (string) $arguments['rank_scale'];
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/backlinks/summary/live',
			$task
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] ?? 0 )
		);

	}
}
