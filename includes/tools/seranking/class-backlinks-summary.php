<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_Summary extends Base_Tool {

	public function get_name() { return 'wp_seranking_backlinks_summary'; }
	public function get_description() {
		return 'SE Ranking backlink profile summary for a target — total backlinks, referring domains, domain/page authority (InLink Rank), dofollow/nofollow split, and new/lost trends. target is a domain, subdomain, or URL. mode controls aggregation: host (default, subdomain only), domain (whole domain), or url (exact page). Returns { summary: [...] } as-is. (meter: 100 credits/target)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking backlinks summary',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'target' ),
			'properties' => array(
				'target' => array( 'type' => 'string', 'description' => 'Domain, subdomain, or URL to analyse.' ),
				'mode'   => array( 'type' => 'string', 'enum' => array( 'host', 'domain', 'url' ), 'description' => 'Aggregation level: host (default), domain, or url.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'target' ) );
			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );
			$query = array( 'target' => $target );
			if ( isset( $arguments['mode'] ) && '' !== $arguments['mode'] ) {
				$mode = trim( (string) $arguments['mode'] );
				SeRanking_Validators::validate_mode( $mode );
				$query['mode'] = $mode;
			}
			return ( new SeRanking_Client() )->request( 'GET', '/v1/backlinks/summary', $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
