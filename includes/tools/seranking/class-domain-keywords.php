<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Keywords extends Base_Tool {

	public function get_name() { return 'wp_seranking_domain_keywords'; }
	public function get_description() {
		return 'SE Ranking keywords a domain (or specific URL) ranks for in one regional database — keyword, position, traffic share, search volume, CPC, and more. source is an ISO alpha-2 country code (us, uk, de…). Provide either domain (bare, e.g. example.com) or url (full URL). type defaults to organic. limit is 1–1000 (default 100). Returns { items: [...] }. (meter: 100 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking domain keywords',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'source' ),
			'properties' => array(
				'source'          => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'domain'          => array( 'type' => 'string', 'description' => 'Bare domain (example.com). Provide this OR url.' ),
				'url'             => array( 'type' => 'string', 'description' => 'Full URL. Provide this OR domain.' ),
				'type'            => array( 'type' => 'string', 'description' => 'Keyword type; default organic.' ),
				'with_subdomains' => array( 'type' => 'boolean', 'description' => 'Include subdomains. Default true.' ),
				'page'            => array( 'type' => 'integer', 'description' => 'Result page; default 1.' ),
				'limit'           => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100, 'description' => 'Rows to return (1–1000); default 100.' ),
				'order_field'     => array( 'type' => 'string', 'description' => 'Sort field; default traffic.' ),
				'order_type'      => array( 'type' => 'string', 'description' => 'Sort direction; default desc.' ),
				'cols'            => array( 'type' => 'string', 'description' => 'Comma-separated columns to return.' ),
				'pos_change'      => array( 'type' => 'string', 'description' => 'Filter by position change.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'source' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			SeRanking_Validators::validate_source( $source );

			$domain = trim( (string) ( $arguments['domain'] ?? '' ) );
			$url    = trim( (string) ( $arguments['url'] ?? '' ) );
			if ( '' === $domain && '' === $url ) {
				throw new \InvalidArgumentException( 'Provide either domain or url.' );
			}

			$query = array( 'source' => $source );
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
			foreach ( array( 'type', 'order_field', 'order_type', 'cols', 'pos_change' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}
			if ( isset( $arguments['page'] ) && '' !== $arguments['page'] ) {
				$query['page'] = (int) $arguments['page'];
			}
			if ( isset( $arguments['with_subdomains'] ) ) {
				
				
				$query['with_subdomains'] = rest_sanitize_boolean( $arguments['with_subdomains'] ) ? 1 : 0;
			}

			$json = ( new SeRanking_Client() )->request( 'GET', '/v1/domain/keywords', $query );
			return array( 'items' => $json );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
