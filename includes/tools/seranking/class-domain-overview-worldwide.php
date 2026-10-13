<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Overview_Worldwide extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_overview_worldwide'; }
	public function get_description() {
		return 'SE Ranking worldwide domain overview — aggregated organic & paid (adv) metrics for a domain across all regional databases, optionally broken down by zone. domain is a bare domain (example.com, no protocol or www). Returns { organic: [...], adv: [...] }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain overview worldwide',
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
				'domain'          => array( 'type' => 'string', 'description' => 'Bare domain (example.com).' ),
				'currency'        => array( 'type' => 'string', 'description' => 'Currency code for cost values; default USD.' ),
				'fields'          => array( 'type' => 'string', 'description' => 'Comma-separated list of fields to return.' ),
				'show_zones_list' => array( 'type' => 'boolean', 'description' => 'Include per-zone breakdown rather than aggregate only. Default false.' ),
				'with_subdomains' => array( 'type' => 'boolean', 'description' => 'Include subdomains. Default true.' ),
				'url'             => array( 'type' => 'string', 'description' => 'Optional full URL; overrides domain when set.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'domain' ) );
			$domain = trim( (string) ( $arguments['domain'] ?? '' ) );
			SeRanking_Validators::validate_bare_domain( $domain );
			$query = array( 'domain' => $domain );
			foreach ( array( 'currency', 'fields', 'url' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}
			
			
			
			foreach ( array( 'show_zones_list', 'with_subdomains' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = rest_sanitize_boolean( $arguments[ $opt ] ) ? 1 : 0;
				}
			}
			return ( new SeRanking_Client() )->request( 'GET', '/v1/domain/overview/worldwide', $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
