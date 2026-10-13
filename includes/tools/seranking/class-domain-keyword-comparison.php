<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Keyword_Comparison extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_keyword_comparison'; }
	public function get_description() {
		return 'SE Ranking keyword comparison between a domain (or URL) and a competitor — shared keywords (diff=false, the default) or the keyword gap (diff=true) in one regional database. source is an ISO alpha-2 country code (us, uk, de…). Provide either domain (bare) or url, plus compare (the competitor bare domain). When diff=true, position/url/price/traffic are null. limit is 1–1000 (default 100). Returns { items: [...] }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain keyword comparison',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'source', 'compare' ),
			'properties' => array(
				'source'      => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'domain'      => array( 'type' => 'string', 'description' => 'Bare domain (example.com). Provide this OR url.' ),
				'url'         => array( 'type' => 'string', 'description' => 'Full URL. Provide this OR domain.' ),
				'compare'     => array( 'type' => 'string', 'description' => 'Competitor bare domain to compare against.' ),
				'type'        => array( 'type' => 'string', 'description' => 'Keyword type; default organic.' ),
				'page'        => array( 'type' => 'integer', 'description' => 'Result page; default 1.' ),
				'limit'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100, 'description' => 'Rows to return (1–1000); default 100.' ),
				'diff'        => array( 'type' => 'boolean', 'default' => false, 'description' => 'false = shared keywords (the default), true = the keyword gap.' ),
				'order_field' => array( 'type' => 'string', 'description' => 'Sort field; default keyword.' ),
				'order_type'  => array( 'type' => 'string', 'description' => 'Sort direction; default asc.' ),
				'cols'        => array( 'type' => 'string', 'description' => 'Comma-separated columns to return.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'source', 'compare' ) );
			$source  = trim( (string) ( $arguments['source'] ?? '' ) );
			$compare = trim( (string) ( $arguments['compare'] ?? '' ) );
			SeRanking_Validators::validate_source( $source );
			SeRanking_Validators::validate_bare_domain( $compare );

			$domain = trim( (string) ( $arguments['domain'] ?? '' ) );
			$url    = trim( (string) ( $arguments['url'] ?? '' ) );
			if ( '' === $domain && '' === $url ) {
				throw new \InvalidArgumentException( 'Provide either domain or url.' );
			}

			$query = array( 'source' => $source, 'compare' => $compare );
			if ( '' !== $domain ) {
				SeRanking_Validators::validate_bare_domain( $domain );
				$query['domain'] = $domain;
			} else {
				$query['url'] = $url;
			}

			if ( isset( $arguments['limit'] ) && '' !== $arguments['limit'] ) {
				$limit = (int) $arguments['limit'];
				SeRanking_Validators::validate_limit( $limit, 1000 );
				$query['limit'] = $limit;
			}
			if ( isset( $arguments['diff'] ) && '' !== $arguments['diff'] ) {
				
				
				
				$query['diff'] = rest_sanitize_boolean( $arguments['diff'] ) ? 1 : 0;
			}
			if ( isset( $arguments['page'] ) && '' !== $arguments['page'] ) {
				$query['page'] = (int) $arguments['page'];
			}
			foreach ( array( 'type', 'order_field', 'order_type', 'cols' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}

			$json = ( new SeRanking_Client() )->request( 'GET', '/v1/domain/keywords/comparison', $query );
			return array( 'items' => $json );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
