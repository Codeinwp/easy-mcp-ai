<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Account_Balance extends Base_Tool {

	public function get_name() { return 'wp_seranking_account_balance'; }
	public function get_description() {
		return 'Returns the configured SE Ranking account\'s subscription status and remaining Data API credit balance. Free to call (does not deduct credits). Always returns a live reading — use before/after a sequence of paid SE Ranking calls to measure actual consumption. Response: { status, expiration_date, units_limit, units_left, fetched_at }. No input parameters. (meter: 0 credits — free)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking account balance',
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
			return ( new SeRanking_Client() )->get_balance();
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
