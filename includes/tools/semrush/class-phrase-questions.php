<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phrase_Questions extends Base_Tool {

	public function get_name() { return 'wp_semrush_phrase_questions'; }
	public function get_description() {
		return 'Semrush question-style keyword variations containing a seed phrase — keywords beginning with how, what, why, where, when, who, which, can, etc. Useful for FAQ content, People Also Ask targeting, and intent research. Each row includes the question keyword, search volume, CPC, and competition. phrase must be 1–80 characters and ≤10 tokens. database defaults to us. display_limit max 100,000 (default 100). (meter: 40 units × rows returned)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush phrase questions',
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
				'phrase'        => array( 'description' => 'The keyword phrase to look up.', 'type' => 'string' ),
				'database'      => array( 'type' => 'string', 'default' => 'us' ),
				'display_limit' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 100000 ),
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
			$limit = isset( $arguments['display_limit'] ) ? (int) $arguments['display_limit'] : 100;
			if ( $limit < 1 || $limit > 100000 ) {
				throw new \InvalidArgumentException( 'display_limit must be between 1 and 100000.' );
			}
			return ( new Semrush_Client() )->report( 'phrase_questions', array(
				'phrase'        => $phrase,
				'database'      => $database,
				'display_limit' => $limit,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
