<?php
namespace Easy_MCP_AI\Tools\DFS;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\DFS\DataforSEO_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keywords_Search_Volume_Live extends Base_Tool {

	public function get_name() {
		return 'wp_dfs_keywords_search_volume_live';
	}

	public function get_description() {
		return 'Returns Google Ads search volume, CPC, and competition data for up to 1,000 keywords in one call. Rate limit: 12 requests/minute per account (much stricter than the 2,000/min global cap). (meter: flat per request regardless of keyword count)';
	}

	public function get_category() {
		return 'dfs';
	}

	public function get_required_capability() {
		return 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => 'Google Ads keyword search volume',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'keywords'                 => array(
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => 1000,
					'items'       => array(
						'type'      => 'string',
						'maxLength' => 80,
					),
					'description' => '1–1000 keywords; each keyword is at most 80 characters and 10 words.',
				),
				'location_code'            => array(
					'type'        => 'integer',
					'description' => 'Location code (e.g. 2840 for United States)',
				),
				'location_name'            => array(
					'type'        => 'string',
					'description' => 'Location name (e.g. United States)',
				),
				'language_code'            => array(
					'type'        => 'string',
					'description' => 'ISO 639-1 language code, e.g. "en". Optional if you supply language_name instead — send one, not both.',
				),
				'language_name'            => array(
					'type'        => 'string',
					'description' => 'Full language name as DataForSEO spells it, e.g. "English". NOT a country or location name. Optional if you supply language_code instead — send one, not both.',
				),
				'search_partners'          => array(
					'type'        => 'boolean',
					'description' => 'Include Google search partners',
				),
				'date_from'                => array(
					'type'        => 'string',
					'description' => 'Date from in YYYY-MM-DD format',
				),
				'date_to'                  => array(
					'type'        => 'string',
					'description' => 'Date to in YYYY-MM-DD format',
				),
				'include_adult_keywords'   => array(
					'type'        => 'boolean',
					'description' => 'Include adult keywords',
				),
				'sort_by'                  => array(
					'type'        => 'string',
					'enum'        => array( 'relevance', 'search_volume', 'competition_index', 'low_top_of_page_bid', 'high_top_of_page_bid' ),
					'description' => 'Sort results by this field',
				),
			),
			'required'   => array( 'keywords' ),
		);
	}

	public function execute( array $arguments ) {
		
		if ( ! isset( $arguments['keywords'] ) || ! is_array( $arguments['keywords'] ) ) {
			throw new \RuntimeException( 'keywords must be a non-empty array.' );
		}

		$keywords = $arguments['keywords'];

		if ( empty( $keywords ) ) {
			throw new \RuntimeException( 'keywords must be a non-empty array.' );
		}

		if ( count( $keywords ) > 1000 ) {
			throw new \RuntimeException( 'keywords array must not exceed 1000 entries.' );
		}

		
		foreach ( $keywords as $keyword ) {
			if ( ! is_string( $keyword ) ) {
				throw new \RuntimeException( 'Each keyword must be a string of at most 80 characters.' );
			}

			if ( strlen( $keyword ) > 80 ) {
				throw new \RuntimeException( 'Each keyword must be a string of at most 80 characters.' );
			}

			
			$token_count = count( array_filter( explode( ' ', trim( $keyword ) ) ) );
			if ( $token_count > 10 ) {
				throw new \RuntimeException( 'Each keyword must contain at most 10 words.' );
			}
		}

		
		if ( isset( $arguments['date_from'] ) && ! empty( $arguments['date_from'] ) ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['date_from'] ) ) {
				throw new \RuntimeException( 'date_from and date_to must be in YYYY-MM-DD format.' );
			}
		}

		if ( isset( $arguments['date_to'] ) && ! empty( $arguments['date_to'] ) ) {
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $arguments['date_to'] ) ) {
				throw new \RuntimeException( 'date_from and date_to must be in YYYY-MM-DD format.' );
			}
		}

		
		if ( isset( $arguments['sort_by'] ) && ! empty( $arguments['sort_by'] ) ) {
			$valid_sort_values = array( 'relevance', 'search_volume', 'competition_index', 'low_top_of_page_bid', 'high_top_of_page_bid' );
			if ( ! in_array( $arguments['sort_by'], $valid_sort_values, true ) ) {
				throw new \RuntimeException(
					'sort_by must be one of: ' . implode( ', ', $valid_sort_values ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				);
			}
		}

		
		$body = array( 'keywords' => $keywords );

		
		$optional_params = array(
			'location_code',
			'location_name',
			'language_code',
			'language_name',
			'search_partners',
			'date_from',
			'date_to',
			'include_adult_keywords',
			'sort_by',
		);

		foreach ( $optional_params as $param ) {
			if ( isset( $arguments[ $param ] ) && '' !== $arguments[ $param ] && null !== $arguments[ $param ] ) {
				$body[ $param ] = $arguments[ $param ];
			}
		}

		
		$client = new DataforSEO_Client();
		$result = $client->post(
			DataforSEO_Client::BASE_URL . '/v3/keywords_data/google_ads/search_volume/live',
			$body
		);

		return array(
			'results'   => $result['tasks'][0]['result'] ?? array(),
			'_cost_usd' => $result['cost'],
		);
	}
}
