<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Overview extends Base_Tool {

	public function get_name() { return 'wp_semrush_domain_overview'; }
	public function get_description() {
		return 'Semrush single-database overview for a domain — Semrush rank, total organic keywords ranking, organic traffic estimate, organic traffic cost, plus paid (Adwords) keyword count, traffic, and cost. Domain must be a bare domain without protocol or www prefix (e.g. example.com, sub.example.com, example.co.uk). database defaults to us. Returns one row of fields Dn,Rk,Or,Ot,Oc,Ad,At,Ac. (meter: 10 units flat)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush domain overview',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'domain' ),
			'properties' => array(
				'domain'   => array( 'type' => 'string', 'description' => 'Bare domain (e.g. example.com).' ),
				'database' => array( 'type' => 'string', 'description' => 'Region code; default us.', 'default' => 'us' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'domain' ) );
			$domain   = trim( (string) ( $arguments['domain'] ?? '' ) );
			$database = trim( (string) ( $arguments['database'] ?? 'us' ) );
			Semrush_Validators::validate_bare_domain( $domain );
			Semrush_Validators::validate_database( $database );
			$client = new Semrush_Client();
			$out    = $client->report( 'domain_rank', array(
				'domain'         => $domain,
				'database'       => $database,
				'export_columns' => 'Dn,Rk,Or,Ot,Oc,Ad,At,Ac',
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
