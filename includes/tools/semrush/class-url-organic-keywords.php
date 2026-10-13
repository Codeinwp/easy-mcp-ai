<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Url_Organic_Keywords extends Base_Tool {

	public function get_name() { return 'wp_semrush_url_organic_keywords'; }
	public function get_description() {
		return 'Semrush list of organic keywords a specific page (URL) ranks for in Google — page-level rather than domain-level. Each row includes the keyword, current position, search volume, CPC, traffic share, and competition. url must be a full URL with https:// prefix (no bare domain). database defaults to us. display_limit max 10,000 (default 100). Use this for page-level SEO analysis; use wp_semrush_domain_organic_keywords for whole-domain coverage. (meter: 10 units × rows returned)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush URL organic keywords',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'url' ),
			'properties' => array(
				'url'           => array( 'type' => 'string', 'description' => 'Full URL with https:// prefix.' ),
				'database'      => array( 'type' => 'string', 'default' => 'us' ),
				'display_limit' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 10000 ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$url      = trim( (string) ( $arguments['url'] ?? '' ) );
			$database = trim( (string) ( $arguments['database'] ?? 'us' ) );
			if ( '' === $url || ! \wp_http_validate_url( $url ) || 0 !== stripos( $url, 'https://' ) ) {
				throw new \InvalidArgumentException( 'url must be a valid full URL with https:// prefix.' );
			}
			Semrush_Validators::validate_database( $database );
			$limit = isset( $arguments['display_limit'] ) ? (int) $arguments['display_limit'] : 100;
			if ( $limit < 1 || $limit > 10000 ) {
				throw new \InvalidArgumentException( 'display_limit must be between 1 and 10000.' );
			}
			return ( new Semrush_Client() )->report( 'url_organic', array(
				'url'           => $url,
				'database'      => $database,
				'display_limit' => $limit,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
