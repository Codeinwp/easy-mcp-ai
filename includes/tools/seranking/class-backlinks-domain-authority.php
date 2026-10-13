<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_Domain_Authority extends Base_Tool {

	public function get_name() { return 'wp_seranking_backlinks_domain_authority'; }
	public function get_description() {
		return 'SE Ranking domain authority (Domain InLink Rank) for a target — the 0–100 backlink-strength score per page. target is a domain, subdomain, or URL. Returns { pages: [{ url, domain_inlink_rank }] } as-is. (meter: 5 credits/target)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking backlinks domain authority',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target' ),
			'properties' => array(
				'target' => array( 'type' => 'string', 'description' => 'Domain, subdomain, or URL to score.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'target' ) );
			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );
			return ( new SeRanking_Client() )->request( 'GET', '/v1/backlinks/authority/domain', array( 'target' => $target ) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
