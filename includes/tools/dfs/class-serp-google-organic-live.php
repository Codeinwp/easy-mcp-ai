<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Serp_Google_Organic_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_serp_google_organic_live';
	}

	public function get_description() {
		return 'Fetches live Google organic SERP results for a keyword and location. Returns ranked items including organic results, featured snippets, AI overviews, and knowledge panels. (meter: ~$0.002 per call; 5× for queries using Google operators like site:, inurl:)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Live Google organic SERP scrape',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'keyword'                   => array(
					'type'        => 'string',
					'maxLength'   => 700,
					'description' => 'The search keyword (required, max 700 chars).',
				),
				'location_code'             => array(
					'type'        => 'integer',
					'description' => 'Numeric location code (e.g. 2840 for US). Exactly one of location_code or location_name is required.',
				),
				'location_name'             => array(
					'type'        => 'string',
					'description' => 'Location name (e.g. "London,England,United Kingdom"). Exactly one of location_code or location_name is required.',
				),
				'language_code'             => array(
					'type'        => 'string',
					'description' => 'ISO 639-1 language code, e.g. "en". Optional if you supply language_name instead — send one, not both.',
				),
				'language_name'             => array(
					'type'        => 'string',
					'description' => 'Full language name as DataForSEO spells it, e.g. "English". NOT a country or location name. Optional if you supply language_code instead — send one, not both. DataForSEO rejects an unrecognised value with "Invalid Field: language_name".',
				),
				'depth'                     => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'maximum'     => 200,
					'default'     => 10,
					'description' => 'Number of results to return (1-200, default 10).',
				),
				'device'                    => array(
					'type'        => 'string',
					'enum'        => array( 'desktop', 'mobile' ),
					'description' => 'Device type (desktop or mobile).',
				),
				'target'                    => array(
					'type'        => 'string',
					'description' => 'Domain or URL filter.',
				),
				'search_param'              => array(
					'type'        => 'string',
					'description' => 'Additional search parameters.',
				),
				'calculate_rectangles'      => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Whether to calculate rectangles.',
				),
				'load_async_ai_overview'    => array(
					'type'        => 'boolean',
					'default'     => false,
					'description' => 'Whether to load async AI overview.',
				),
				'os'                        => array(
					'type'        => 'string',
					'enum'        => array( 'windows', 'macos', 'android', 'ios' ),
					'description' => 'Operating system (windows, macos, android, ios).',
				),
				'tag'                       => array(
					'type'        => 'string',
					'maxLength'   => 255,
					'description' => 'Tag for tracking (max 255 chars).',
				),
			),
			'required'   => array( 'keyword' ),
			
			
			
			'oneOf'      => array(
				array( 'required' => array( 'location_code' ) ),
				array( 'required' => array( 'location_name' ) ),
			),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['keyword'] ) || empty( $arguments['keyword'] ) ) {
			throw new \RuntimeException(
				'keyword is required and must not exceed 700 characters.'
			);
		}
		$keyword = (string) $arguments['keyword'];
		if ( strlen( $keyword ) > 700 ) {
			throw new \RuntimeException(
				'keyword is required and must not exceed 700 characters.'
			);
		}

		
		$has_location_code = isset( $arguments['location_code'] );
		$has_location_name = isset( $arguments['location_name'] );
		if ( ( $has_location_code && $has_location_name ) || ( ! $has_location_code && ! $has_location_name ) ) {
			throw new \RuntimeException(
				'Exactly one of location_code or location_name is required.'
			);
		}

		
		if ( isset( $arguments['depth'] ) ) {
			$depth = (int) $arguments['depth'];
			if ( $depth < 1 || $depth > 200 ) {
				throw new \RuntimeException(
					'depth must be between 1 and 200.'
				);
			}
		}

		
		if ( isset( $arguments['device'] ) ) {
			$device = (string) $arguments['device'];
			if ( ! in_array( $device, array( 'desktop', 'mobile' ), true ) ) {
				throw new \RuntimeException(
					'device must be desktop or mobile.'
				);
			}
		}

		
		if ( isset( $arguments['os'] ) ) {
			$os = (string) $arguments['os'];
			if ( ! in_array( $os, array( 'windows', 'macos', 'android', 'ios' ), true ) ) {
				throw new \RuntimeException(
					'os must be one of: windows, macos, android, ios.'
				);
			}
		}

		
		if ( isset( $arguments['tag'] ) ) {
			$tag = (string) $arguments['tag'];
			if ( strlen( $tag ) > 255 ) {
				throw new \RuntimeException(
					'tag must not exceed 255 characters.'
				);
			}
		}

		
		$task = array( 'keyword' => $keyword );

		
		if ( $has_location_code ) {
			$task['location_code'] = (int) $arguments['location_code'];
		} else {
			$task['location_name'] = (string) $arguments['location_name'];
		}

		
		if ( isset( $arguments['language_code'] ) ) {
			$task['language_code'] = (string) $arguments['language_code'];
		}
		if ( isset( $arguments['language_name'] ) ) {
			$task['language_name'] = (string) $arguments['language_name'];
		}
		if ( isset( $arguments['depth'] ) ) {
			$task['depth'] = (int) $arguments['depth'];
		}
		if ( isset( $arguments['device'] ) ) {
			$task['device'] = (string) $arguments['device'];
		}
		if ( isset( $arguments['target'] ) ) {
			$task['target'] = (string) $arguments['target'];
		}
		if ( isset( $arguments['search_param'] ) ) {
			$task['search_param'] = (string) $arguments['search_param'];
		}
		if ( isset( $arguments['calculate_rectangles'] ) ) {
			$task['calculate_rectangles'] = (bool) $arguments['calculate_rectangles'];
		}
		if ( isset( $arguments['load_async_ai_overview'] ) ) {
			$task['load_async_ai_overview'] = (bool) $arguments['load_async_ai_overview'];
		}
		if ( isset( $arguments['os'] ) ) {
			$task['os'] = (string) $arguments['os'];
		}
		if ( isset( $arguments['tag'] ) ) {
			$task['tag'] = (string) $arguments['tag'];
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/serp/google/organic/live/advanced',
			$task
		);

		
		return array_merge(
			$result['tasks'][0]['result'][0] ?? array(),
			array( '_cost_usd' => $result['cost'] ?? 0 )
		);

	}
}
