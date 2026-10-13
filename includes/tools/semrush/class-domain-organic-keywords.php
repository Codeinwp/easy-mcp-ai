<?php
namespace Easy_MCP_AI\Tools\Semrush;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\Semrush\Semrush_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Domain_Organic_Keywords extends Base_Tool {

	public function get_name() { return 'wp_semrush_domain_organic_keywords'; }
	public function get_description() {
		return 'Semrush list of organic keywords a domain ranks for in Google. Each row includes the keyword (Ph), current position (Po), previous position (Pp), position change (Pd), monthly search volume (Nq), CPC (Cp), ranking URL (Ur), traffic share % (Tr), traffic-cost share % (Tc), competition 0–1 (Co), results count (Nr), and last-updated date (Td). Domain must be a bare domain without protocol or www prefix. database defaults to us. Use display_filter to constrain (e.g. +|Nq|Gt|100 for volume > 100), display_sort to order (tr_desc default), display_offset to paginate. display_limit here can go up to 100,000 rows (as can phrase_questions and related_keywords; most other Semrush tools cap at 10,000). (meter: 10 units × rows returned)';
	}
	public function get_category() { return 'semrush'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'Semrush domain organic keywords',
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
				'domain'         => array( 'description' => 'The domain to analyse, without protocol (e.g. example.com).', 'type' => 'string' ),
				'database'       => array( 'type' => 'string', 'default' => 'us' ),
				'display_limit'  => array( 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 100000, 'description' => 'Max rows to return; default 100, max 100000 — as with phrase_questions and related_keywords. Most other Semrush tools cap at 10000.' ),
				'display_offset' => array( 'type' => 'integer', 'minimum' => 0 ),
				'display_sort'   => array( 'type' => 'string', 'enum' => array( 'tr_desc', 'tr_asc', 'po_asc', 'po_desc', 'nq_desc', 'cp_desc' ) ),
				'display_filter' => array( 'type' => 'string' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'domain' ) );
			$domain   = trim( (string) ( $arguments['domain'] ?? '' ) );
			$database = trim( (string) ( $arguments['database'] ?? 'us' ) );
			Semrush_Validators::validate_bare_domain( $domain );
			Semrush_Validators::validate_database( $database );

			$limit = isset( $arguments['display_limit'] ) ? (int) $arguments['display_limit'] : 100;
			if ( $limit < 1 || $limit > 100000 ) {
				throw new \InvalidArgumentException( 'display_limit must be between 1 and 100000.' );
			}

			$params = array(
				'domain'         => $domain,
				'database'       => $database,
				'display_limit'  => $limit,
				'export_columns' => 'Ph,Po,Pp,Pd,Nq,Cp,Ur,Tr,Tc,Co,Nr,Td',
			);
			if ( isset( $arguments['display_offset'] ) ) {
				$params['display_offset'] = (int) $arguments['display_offset'];
			}
			if ( ! empty( $arguments['display_sort'] ) ) {
				$params['display_sort'] = (string) $arguments['display_sort'];
			}
			if ( ! empty( $arguments['display_filter'] ) ) {
				Semrush_Validators::validate_display_filter( (string) $arguments['display_filter'] );
				$params['display_filter'] = (string) $arguments['display_filter'];
			}

			return ( new Semrush_Client() )->report( 'domain_organic', $params );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
