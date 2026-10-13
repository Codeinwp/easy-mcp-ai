<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keywords_Overview extends Base_Tool {

	public function get_name() { return 'wp_seranking_keywords_overview'; }
	public function get_description() {
		return 'SE Ranking bulk keyword metrics — search volume, CPC, competition, difficulty, search intents, and history trend for a batch of keywords (1–5000) in one regional database. source is an ISO alpha-2 country code (us, uk, de…). Provide keywords as an array; optionally sort (default cpc), sort_order (default desc), and a history_from/history_to range. Returns { items: [...] }. (meter: ~10 credits/record)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking keywords overview',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'source', 'keywords' ),
			'properties' => array(
				'source'       => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'keywords'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1, 'maxItems' => 5000, 'description' => 'Keywords to look up (1–5000).' ),
				'sort'         => array( 'type' => 'string', 'description' => 'Sort field; default cpc.' ),
				'sort_order'   => array( 'type' => 'string', 'description' => 'Sort direction; default desc.' ),
				'history_from' => array( 'type' => 'string', 'description' => 'Start of history range (YYYY-MM).' ),
				'history_to'   => array( 'type' => 'string', 'description' => 'End of history range (YYYY-MM).' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'source', 'keywords' ) );
			$source   = trim( (string) ( $arguments['source'] ?? '' ) );
			$keywords = $arguments['keywords'] ?? array();
			if ( ! is_array( $keywords ) ) {
				throw new \InvalidArgumentException( 'keywords must be an array of strings.' );
			}
			SeRanking_Validators::validate_source( $source );
			SeRanking_Validators::validate_keywords( $keywords );

			$query = array( 'source' => $source );
			$body  = array( 'keywords' => array_values( $keywords ) );
			foreach ( array( 'sort', 'sort_order', 'history_from', 'history_to' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$body[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}

			$json = ( new SeRanking_Client() )->request( 'POST', '/v1/keywords/export', $query, $body );
			return array( 'items' => $json );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
