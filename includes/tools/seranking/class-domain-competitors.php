<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Competitors extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_competitors'; }
	public function get_description() {
		return 'SE Ranking organic competitors for a domain in one regional database — competing domains with shared keyword counts, relevance, total keywords, estimated traffic, and traffic cost (max 500 rows). source is an ISO alpha-2 country code (us, uk, de…). domain is a bare domain (example.com). type defaults to organic. Returns { items: [...] }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain competitors',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'source', 'domain' ),
			'properties' => array(
				'source' => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'domain' => array( 'type' => 'string', 'description' => 'Bare domain (example.com).' ),
				'type'   => array( 'type' => 'string', 'description' => 'Keyword type; default organic.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'source', 'domain' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			$domain = trim( (string) ( $arguments['domain'] ?? '' ) );
			SeRanking_Validators::validate_source( $source );
			SeRanking_Validators::validate_bare_domain( $domain );
			$query = array( 'source' => $source, 'domain' => $domain );
			if ( isset( $arguments['type'] ) && '' !== $arguments['type'] ) {
				$query['type'] = trim( (string) $arguments['type'] );
			}
			$json = ( new SeRanking_Client() )->request( 'GET', '/v1/domain/competitors', $query );
			return array( 'items' => $json );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
