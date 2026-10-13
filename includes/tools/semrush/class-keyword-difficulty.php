<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keyword_Difficulty extends Base_Tool {

	public function get_name() { return 'wp_semrush_keyword_difficulty'; }
	public function get_description() {
		return 'Semrush keyword difficulty score (0–100) for a single phrase — estimates how hard it is to rank in the top 20 for that keyword. Higher = harder. phrase must be 1–80 characters and ≤10 tokens; database defaults to us. Single-phrase only in this MVP (bulk semicolon-joined input is not yet supported). The most expensive Semrush tool per call — prefer wp_semrush_keyword_overview for cheap volume/CPC checks first. (meter: 50 units × rows returned — typically 50 for a single phrase)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush keyword difficulty',
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
			return ( new Semrush_Client() )->report( 'phrase_kdi', array(
				'phrase'   => $phrase,
				'database' => $database,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
