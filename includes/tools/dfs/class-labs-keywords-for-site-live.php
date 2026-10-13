<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Labs_Keywords_For_Site_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_labs_keywords_for_site_live';
	}

	public function get_description() {
		return 'Discovers keywords a domain ranks for or is likely to rank for using DataforSEO Labs. Required: `target` (bare domain, no https:// or www) and exactly one of `location_code` (integer, e.g. 2840 for US) or `location_name` (string, e.g. "United States") — both missing or both present throws an error. Optional: `language_code` (e.g. en), `include_clickstream_data` (doubles cost). (meter: ~$0.0006 per call; 2× with include_clickstream_data)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Labs keywords for site',
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
					'description' => 'Bare domain e.g. example.com, NOT https:// or www.',
				),
				'location_code'             => array(
					'type'        => 'integer',
					'description' => 'Location code (e.g. 2840 for United States).',
				),
				'location_name'             => array(
					'type'        => 'string',
					'description' => 'Location name (e.g. United States).',
				),
				'language_code'             => array(
					'type'        => 'string',
					'description' => 'ISO 639-1 language code, e.g. "en". Optional if you supply language_name instead — send one, not both.',
				),
				'language_name'             => array(
					'type'        => 'string',
					'description' => 'Full language name as DataForSEO spells it, e.g. "English". NOT a country or location name. Optional if you supply language_code instead — send one, not both.',
				),
				'include_subdomains'        => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => 'Include keywords from subdomains.',
				),
				'include_serp_info'         => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Include SERP info for each keyword.',
				),
				'include_clickstream_data'  => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Include clickstream data (increases cost 2x).',
				),
				'filters'                   => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'DataForSEO tuple-style filter array, not objects — e.g. ["keyword_info.search_volume", ">", 100], optionally joined by "and"/"or" string elements between conditions. See DataForSEO Labs API filter docs.',
				),
				'order_by'                  => array(
					'type'        => 'array',
					'items'       => (object) array(),
					'description' => 'Array of "field,direction" strings (e.g. "keyword_info.search_volume,desc"), not objects.',
				),
				'limit'                     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 1000,
					'default'     => 100,
					'description' => 'Maximum results to return (1-1000, default 100).',
				),
				'offset'                    => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => 'Result offset for pagination.',
				),
			),
			'required'   => array( 'target' ),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['target'] ) || '' === $arguments['target'] ) {
			throw new \RuntimeException( 'target is required.' );
		}

		$target = (string) $arguments['target'];

		
		if ( preg_match( '/^https?:\/\//', $target ) ) {
			throw new \RuntimeException( 'target must be a bare domain (e.g. example.com), without protocol or www prefix.' );
		}

		
		if ( strpos( $target, 'www.' ) === 0 ) {
			throw new \RuntimeException( 'target must be a bare domain (e.g. example.com), without protocol or www prefix.' );
		}

		
		$has_location_code = isset( $arguments['location_code'] ) && '' !== $arguments['location_code'] && null !== $arguments['location_code'];
		$has_location_name = isset( $arguments['location_name'] ) && '' !== $arguments['location_name'] && null !== $arguments['location_name'];

		if ( ! $has_location_code && ! $has_location_name ) {
			throw new \RuntimeException( 'Exactly one of location_code or location_name is required.' );
		}

		if ( $has_location_code && $has_location_name ) {
			throw new \RuntimeException( 'Exactly one of location_code or location_name is required.' );
		}

		
		$body = array( 'target' => $target );

		
		if ( $has_location_code ) {
			$body['location_code'] = (int) $arguments['location_code'];
		} else {
			$body['location_name'] = (string) $arguments['location_name'];
		}

		
		$optional_params = array(
			'language_code',
			'language_name',
			'include_subdomains',
			'include_serp_info',
			'include_clickstream_data',
			'filters',
			'order_by',
			'limit',
			'offset',
		);

		foreach ( $optional_params as $param ) {
			if ( isset( $arguments[ $param ] ) && '' !== $arguments[ $param ] && null !== $arguments[ $param ] ) {
				$body[ $param ] = $arguments[ $param ];
			}
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/dataforseo_labs/google/keywords_for_site/live',
			$body
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] )
		);
	}
}
