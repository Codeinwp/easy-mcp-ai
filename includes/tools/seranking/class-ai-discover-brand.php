<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Discover_Brand extends Base_Tool {

	public function get_name() { return 'wp_seranking_ai_discover_brand'; }
	public function get_description() {
		return 'SE Ranking AI Search brand discovery — the brand names associated with a target in AI-engine answers, used to seed AI brand-presence tracking. target is a domain or URL; source is an ISO alpha-2 country code (us, uk, de…); scope controls how target is interpreted (base_domain, domain, url). Returns { brands: [...] } as-is. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking AI discover brand',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target', 'source', 'scope' ),
			'properties' => array(
				'target' => array( 'type' => 'string', 'description' => 'Domain or URL to analyse.' ),
				'source' => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'scope'  => array( 'type' => 'string', 'enum' => array( 'base_domain', 'domain', 'url' ), 'description' => 'How target is interpreted.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'target', 'source', 'scope' ) );
			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			$scope  = trim( (string) ( $arguments['scope'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );
			SeRanking_Validators::validate_source( $source );
			SeRanking_Validators::validate_scope( $scope );

			return ( new SeRanking_Client() )->request( 'GET', '/v1/ai-search/discover-brand', array(
				'target' => $target,
				'source' => $source,
				'scope'  => $scope,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
