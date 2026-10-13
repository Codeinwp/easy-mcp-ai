<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keyword_Overview extends Base_Tool {

	public function get_name() { return 'wp_semrush_keyword_overview'; }
	public function get_description() {
		return 'Semrush single-keyword overview — monthly search volume, CPC, competition density (0–1), and results count. phrase must be 1–80 characters and ≤10 whitespace-separated tokens. database defaults to us; use a Semrush region code (us, uk, de, fr, it, es, br, au, ca, in, etc.) for that country\'s SERP. Returns a single row. (meter: 10 units flat)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush keyword overview',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'phrase' ),
			'properties' => array(
				'phrase'   => array( 'description' => 'The keyword phrase to look up.', 'type' => 'string' ),
				'database' => array( 'type' => 'string', 'default' => 'us' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'phrase' ) );
			$phrase   = (string) ( $arguments['phrase'] ?? '' );
			$database = trim( (string) ( $arguments['database'] ?? 'us' ) );
			Semrush_Validators::validate_phrase( $phrase );
			Semrush_Validators::validate_database( $database );
			$out = ( new Semrush_Client() )->report( 'phrase_this', array(
				'phrase'   => $phrase,
				'database' => $database,
			) );
			$first = $out['items'][0] ?? array();
			return array_merge( $first, array(
				'_units_cost'     => $out['_units_cost'],
				'_cost_estimated' => true,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
