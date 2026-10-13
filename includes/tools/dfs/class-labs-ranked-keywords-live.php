<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labs_Ranked_Keywords_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_labs_ranked_keywords_live';
	}

	public function get_description() {
		return 'Returns keywords a domain or specific URL currently ranks for in Google, using DataforSEO Labs. Accepts bare domain (domain-level) or full URL with https:// or www. (page-level). Location is optional — omit for cross-location results. (meter: ~$0.0006 per call; 2× with include_clickstream_data)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Labs ranked keywords for domain/URL',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'target'                     => array(
					'type'        => 'string',
					'description' => 'Target domain (e.g. "example.com") or full URL with https:// or www. (e.g. "https://example.com/page" or "www.example.com/page"). Required.',
				),
				'location_code'              => array(
					'type'        => 'integer',
					'description' => 'Numeric location code (e.g. 2840 for US). Optional.',
				),
				'location_name'              => array(
					'type'        => 'string',
					'description' => 'Location name (e.g. "London,England,United Kingdom"). Optional.',
				),
				'language_code'              => array(
					'type'        => 'string',
					'description' => 'ISO 639-1 language code, e.g. "en". Optional if you supply language_name instead — send one, not both.',
				),
				'language_name'              => array(
					'type'        => 'string',
					'description' => 'Full language name as DataForSEO spells it, e.g. "English". NOT a country or location name. Optional if you supply language_code instead — send one, not both. DataForSEO rejects an unrecognised value with "Invalid Field: language_name".',
				),
				'ignore_synonyms'            => array(
					'type'        => 'boolean',
					'description' => 'Whether to ignore synonym keywords.',
				),
				'include_clickstream_data'   => array(
					'type'        => 'boolean',
					'description' => 'Whether to include clickstream data (doubles cost).',
				),
				'item_types'                 => array(
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => array( 'organic', 'paid', 'featured_snippet', 'local_pack', 'ai_overview_reference' ),
					),
					'description' => 'Subset of item types to include (organic, paid, featured_snippet, local_pack, ai_overview_reference).',
				),
				'load_rank_absolute'         => array(
					'type'        => 'boolean',
					'description' => 'Whether to load absolute rank values.',
				),
				'historical_serp_mode'       => array(
					'type'        => 'string',
					'enum'        => array( 'live', 'lost', 'all' ),
					'description' => 'SERP mode: live (current rankings), lost (previously ranked), or all.',
				),
				'filters'                    => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Filter conditions.',
				),
				'order_by'                   => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Ordering for results.',
				),
				'limit'                      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 1000,
					'default'     => 100,
					'description' => 'Maximum number of results (1-1000, default 100).',
				),
				'offset'                     => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Pagination offset.',
				),
			),
			'required'   => array( 'target' ),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['target'] ) || empty( $arguments['target'] ) ) {
			throw new \RuntimeException(
				'target is required.'
			);
		}
		$target = (string) $arguments['target'];

		
		if ( isset( $arguments['historical_serp_mode'] ) ) {
			$mode = (string) $arguments['historical_serp_mode'];
			if ( ! in_array( $mode, array( 'live', 'lost', 'all' ), true ) ) {
				throw new \RuntimeException(
					'historical_serp_mode must be one of: live, lost, all.'
				);
			}
		}

		
		if ( isset( $arguments['item_types'] ) ) {
			if ( ! is_array( $arguments['item_types'] ) ) {
				throw new \RuntimeException(
					'item_types must be an array.'
				);
			}
			$allowed_types = array( 'organic', 'paid', 'featured_snippet', 'local_pack', 'ai_overview_reference' );
			foreach ( $arguments['item_types'] as $type ) {
				if ( ! in_array( $type, $allowed_types, true ) ) {
					throw new \RuntimeException(
						'item_types contains invalid value: ' . $type . '. Must be one of: organic, paid, featured_snippet, local_pack, ai_overview_reference.' // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					);
				}
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

		
		$task = array( 'target' => $target );

		
		if ( isset( $arguments['location_code'] ) ) {
			$task['location_code'] = (int) $arguments['location_code'];
		}
		if ( isset( $arguments['location_name'] ) ) {
			$task['location_name'] = (string) $arguments['location_name'];
		}

		
		if ( isset( $arguments['language_code'] ) ) {
			$task['language_code'] = (string) $arguments['language_code'];
		}
		if ( isset( $arguments['language_name'] ) ) {
			$task['language_name'] = (string) $arguments['language_name'];
		}
		if ( isset( $arguments['ignore_synonyms'] ) ) {
			$task['ignore_synonyms'] = (bool) $arguments['ignore_synonyms'];
		}
		if ( isset( $arguments['include_clickstream_data'] ) ) {
			$task['include_clickstream_data'] = (bool) $arguments['include_clickstream_data'];
		}
		if ( isset( $arguments['item_types'] ) ) {
			$task['item_types'] = $arguments['item_types'];
		}
		if ( isset( $arguments['load_rank_absolute'] ) ) {
			$task['load_rank_absolute'] = (bool) $arguments['load_rank_absolute'];
		}
		if ( isset( $arguments['historical_serp_mode'] ) ) {
			$task['historical_serp_mode'] = (string) $arguments['historical_serp_mode'];
		}
		if ( isset( $arguments['filters'] ) ) {
			$task['filters'] = $arguments['filters'];
		}
		if ( isset( $arguments['order_by'] ) ) {
			$task['order_by'] = $arguments['order_by'];
		}
		if ( isset( $arguments['limit'] ) ) {
			$task['limit'] = (int) $arguments['limit'];
		}
		if ( isset( $arguments['offset'] ) ) {
			$task['offset'] = (int) $arguments['offset'];
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/dataforseo_labs/google/ranked_keywords/live',
			$task
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] ?? 0 )
		);

	}
}
