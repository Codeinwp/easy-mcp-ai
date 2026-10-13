<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Competitors_Organic extends Base_Tool {

	public function get_name() { return 'wp_semrush_competitors_organic'; }
	public function get_description() {
		return 'Semrush list of organic competitors for a domain — other domains ranking on the same keywords. Each row includes the competitor domain, competition relevance score, common keyword count, the competitor\'s total organic keywords, organic traffic estimate, and organic cost. Domain must be a bare domain without protocol or www prefix. database defaults to us. display_limit max 10,000 (default 50). (meter: 40 units × rows returned)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush organic competitors',
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
				'domain'        => array( 'description' => 'The domain to analyse, without protocol (e.g. example.com).', 'type' => 'string' ),
				'database'      => array( 'type' => 'string', 'default' => 'us' ),
				'display_limit' => array( 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 10000 ),
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
			$limit = isset( $arguments['display_limit'] ) ? (int) $arguments['display_limit'] : 50;
			if ( $limit < 1 || $limit > 10000 ) {
				throw new \InvalidArgumentException( 'display_limit must be between 1 and 10000.' );
			}
			return ( new Semrush_Client() )->report( 'domain_organic_organic', array(
				'domain'        => $domain,
				'database'      => $database,
				'display_limit' => $limit,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
