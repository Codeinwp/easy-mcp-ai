<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Overview extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_overview'; }
	public function get_description() {
		return 'SE Ranking regional domain overview — organic & paid (adv) keyword counts, estimated traffic, traffic cost, and position distribution for a domain in one regional database. domain is a bare domain (example.com, no protocol or www). source is an ISO alpha-2 country code (us, uk, de…; the United Kingdom uses "uk"). Returns { organic: {...}, adv: {...} }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain overview',
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
				'source'          => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'domain'          => array( 'type' => 'string', 'description' => 'Bare domain (example.com).' ),
				'with_subdomains' => array( 'type' => 'boolean', 'default' => true, 'description' => 'Include subdomains. Default true.' ),
				'url'             => array( 'type' => 'string', 'description' => 'Optional full URL; overrides domain when set.' ),
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
			$query = array(
				'source'          => $source,
				'domain'          => $domain,
				
				
				
				'with_subdomains' => rest_sanitize_boolean( $arguments['with_subdomains'] ?? true ) ? 1 : 0,
			);
			if ( isset( $arguments['url'] ) && '' !== $arguments['url'] ) {
				$query['url'] = trim( (string) $arguments['url'] );
			}
			return ( new SeRanking_Client() )->request( 'GET', '/v1/domain/overview/db', $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
