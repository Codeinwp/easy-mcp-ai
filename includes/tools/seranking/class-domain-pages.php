<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Pages extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_pages'; }
	public function get_description() {
		return 'SE Ranking top organic pages or subdomains of a target — per-page URL, title, ranking-keyword count, estimated traffic share, traffic cost, and search intents. mode selects pages (mode=pages → /domain/pages) or subdomains (mode=subdomains → /domain/subdomains). source is an ISO alpha-2 country code (us, uk, de…). scope is base_domain, domain, or url — for mode=subdomains use scope=base_domain (the SE Ranking API may return a 500 for other scopes on that branch). type defaults to organic; limit defaults to 1000. Returns { items: [...] }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain pages',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'mode', 'target', 'scope', 'source' ),
			'properties' => array(
				'mode'        => array( 'type' => 'string', 'enum' => array( 'pages', 'subdomains' ), 'description' => 'pages = top URLs; subdomains = subdomain breakdown.' ),
				'target'      => array( 'type' => 'string', 'description' => 'Domain or URL to analyse, matching scope.' ),
				'scope'       => array( 'type' => 'string', 'enum' => array( 'base_domain', 'domain', 'url' ), 'description' => 'How target is interpreted.' ),
				'source'      => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'type'        => array( 'type' => 'string', 'description' => 'Keyword type; default organic.' ),
				'order_field' => array( 'type' => 'string', 'description' => 'Sort field; default keywords_count.' ),
				'order_type'  => array( 'type' => 'string', 'description' => 'Sort direction; default desc.' ),
				'offset'      => array( 'type' => 'integer', 'description' => 'Row offset.' ),
				'limit'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 1000, 'description' => 'Rows to return (1–10000); default 1000.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'mode', 'target', 'scope', 'source' ) );
			$mode = trim( (string) ( $arguments['mode'] ?? '' ) );
			SeRanking_Validators::validate_enum( $mode, array( 'pages', 'subdomains' ) );
			$path = ( 'subdomains' === $mode ) ? '/v1/domain/subdomains' : '/v1/domain/pages';

			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			$scope  = trim( (string) ( $arguments['scope'] ?? '' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );
			SeRanking_Validators::validate_scope( $scope );
			SeRanking_Validators::validate_source( $source );
			
			if ( 'subdomains' === $mode && 'url' === $scope ) {
				throw new \InvalidArgumentException( 'scope "url" is not supported when mode=subdomains; use base_domain or domain.' );
			}

			$query = array(
				'target' => $target,
				'scope'  => $scope,
				'source' => $source,
			);
			foreach ( array( 'type', 'order_field', 'order_type' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}
			if ( isset( $arguments['offset'] ) && '' !== $arguments['offset'] ) {
				$query['offset'] = (int) $arguments['offset'];
			}
			if ( isset( $arguments['limit'] ) && '' !== $arguments['limit'] ) {
				$limit = (int) $arguments['limit'];
				SeRanking_Validators::validate_limit( $limit, 10000 );
				$query['limit'] = $limit;
			}

			$json = ( new SeRanking_Client() )->request( 'GET', $path, $query );
			return array( 'items' => $json );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
