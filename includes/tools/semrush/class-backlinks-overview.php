<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_Overview extends Base_Tool {

	public function get_name() { return 'wp_semrush_backlinks_overview'; }
	public function get_description() {
		return 'Semrush aggregate backlinks summary for a target — Authority Score (ascore), total backlinks, distinct referring domains and IPs, follow vs nofollow split, sponsored/UGC counts, plus link-type counts (text, image, form, frame). target_type must be one of: root_domain (entire root domain like example.com), domain (a specific SUBDOMAIN like blog.example.com — yes, "domain" in Semrush\'s API means subdomain), or url (a specific page URL with http:// or https://). Backlinks reports do not take a database parameter; data is global. Requires a Semrush plan that includes the Backlinks API; otherwise returns ERROR 133 :: DB ACCESS DENIED. (meter: 40 units flat — cheap one-shot health summary)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush backlinks overview',
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
				'target'      => array( 'type' => 'string', 'description' => 'Domain, subdomain, or URL.' ),
				'target_type' => array( 'description' => 'How to read target: root_domain, domain, or url.', 'type' => 'string', 'enum' => array( 'root_domain', 'domain', 'url' ) ),
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
			$out = ( new Semrush_Client() )->report( 'backlinks_overview', array(
				'target'      => $target,
				'target_type' => $target_type,
			) );
			$first = $out['items'][0] ?? array();
			return array_merge( $first, array(
				'_units_cost'     => $out['_units_cost'],
				'_cost_estimated' => true,
			) );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
