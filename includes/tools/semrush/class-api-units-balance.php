<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api_Units_Balance extends Base_Tool {

	public function get_name() { return 'wp_semrush_api_units_balance'; }
	public function get_description() {
		return 'Returns the configured Semrush account\'s remaining API units balance as an integer. Free to call (does not deduct units). Always returns a live reading — use before/after a sequence of paid Semrush calls to measure actual consumption. Response: { balance: int, fetched_at: ISO8601 timestamp }. No input parameters. (meter: 0 — free)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush API units balance',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => (object) array(),
		);
	}
	public function execute( array $arguments ) {
		try {
			return ( new Semrush_Client() )->get_balance();
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
