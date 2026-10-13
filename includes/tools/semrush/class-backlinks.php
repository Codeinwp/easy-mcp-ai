<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks extends Base_Tool {

	public function get_name() { return 'wp_semrush_backlinks'; }
	public function get_description() {
		return 'Semrush list of individual backlinks pointing to a target. Each row includes source page URL and title, anchor text, source-page Authority Score, first-seen and last-seen dates, follow/nofollow/sponsored/ugc flags, and the target URL. target_type: root_domain (root domain) | domain (subdomain only — Semrush uses "domain" to mean subdomain) | url (specific page; must be a full http:// or https:// URL). display_limit max 10,000 (default 100). display_sort: page_ascore_desc | last_seen_desc | first_seen_desc. Requires Semrush Backlinks API tier; otherwise returns ERROR 133 :: DB ACCESS DENIED. (meter: 40 units × rows returned)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush backlinks list',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target', 'target_type' ),
			'properties' => array(
				'target'        => array( 'description' => 'The domain or URL to analyse; target_type says how to read it.', 'type' => 'string' ),
				'target_type'   => array( 'description' => 'How to read target: root_domain, domain, or url.', 'type' => 'string', 'enum' => array( 'root_domain', 'domain', 'url' ) ),
				'display_limit' => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 10000 ),
				'display_sort'  => array( 'type' => 'string', 'enum' => array( 'page_ascore_desc', 'last_seen_desc', 'first_seen_desc' ) ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'target', 'target_type' ) );
			$target      = trim( (string) $arguments['target'] );
			$target_type = trim( (string) $arguments['target_type'] );
			Semrush_Validators::validate_target_type( $target_type );
			if ( 'url' === $target_type && ! \wp_http_validate_url( $target ) ) {
				throw new \InvalidArgumentException( 'target must be a valid URL when target_type=url.' );
			}
			$limit = isset( $arguments['display_limit'] ) ? (int) $arguments['display_limit'] : 100;
			if ( $limit < 1 || $limit > 10000 ) {
				throw new \InvalidArgumentException( 'display_limit must be between 1 and 10000.' );
			}
			$params = array(
				'target'        => $target,
				'target_type'   => $target_type,
				'display_limit' => $limit,
			);
			if ( ! empty( $arguments['display_sort'] ) ) {
				$params['display_sort'] = (string) $arguments['display_sort'];
			}
			return ( new Semrush_Client() )->report( 'backlinks', $params );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
