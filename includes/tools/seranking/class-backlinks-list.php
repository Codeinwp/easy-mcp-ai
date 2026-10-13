<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Backlinks_List extends Base_Tool {

	public function get_name() { return 'wp_seranking_backlinks_list'; }
	public function get_description() {
		return 'SE Ranking detailed backlink data for a target. report selects the dataset: backlinks (individual links → /backlinks/all), anchors (anchor-text breakdown → /backlinks/anchors), or refdomains (referring domains → /backlinks/refdomains). target is a domain, subdomain, or URL; mode controls aggregation (host default, domain, url). limit defaults to 100 (cap 10000). The backlinks report also supports per_domain, inlink/domain-inlink rank ranges, URL/anchor/nofollow filters. Returns the report object as-is. (meter: 1 credit/record)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking backlinks list',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'report', 'target' ),
			'properties' => array(
				'report'                 => array( 'type' => 'string', 'enum' => array( 'backlinks', 'anchors', 'refdomains' ), 'description' => 'Which backlink dataset to return.' ),
				'target'                 => array( 'type' => 'string', 'description' => 'Domain, subdomain, or URL to analyse.' ),
				'mode'                   => array( 'type' => 'string', 'enum' => array( 'host', 'domain', 'url' ), 'description' => 'Aggregation level: host (default), domain, or url.' ),
				'limit'                  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10000, 'default' => 100, 'description' => 'Rows to return (1–10000); default 100.' ),
				'order_by'               => array( 'type' => 'string', 'description' => 'Sort field.' ),
				'per_domain'             => array( 'type' => 'integer', 'description' => '(backlinks only) Max links per referring domain (1–100).' ),
				'inlink_rank_from'       => array( 'type' => 'integer', 'description' => '(backlinks only) Min page InLink Rank.' ),
				'inlink_rank_to'         => array( 'type' => 'integer', 'description' => '(backlinks only) Max page InLink Rank.' ),
				'domain_inlink_rank_from' => array( 'type' => 'integer', 'description' => '(backlinks only) Min domain InLink Rank.' ),
				'domain_inlink_rank_to'  => array( 'type' => 'integer', 'description' => '(backlinks only) Max domain InLink Rank.' ),
				'url_from_filter'        => array( 'type' => 'string', 'description' => '(backlinks only) Source-URL filter.' ),
				'url_from_filter_mode'   => array( 'type' => 'string', 'description' => '(backlinks only) Match mode for url_from_filter.' ),
				'url_to_filter'          => array( 'type' => 'string', 'description' => '(backlinks only) Target-URL filter.' ),
				'anchor_filter'          => array( 'type' => 'string', 'description' => '(backlinks only) Anchor-text filter.' ),
				'nofollow_filter'        => array( 'type' => 'string', 'description' => '(backlinks only) Dofollow/nofollow filter.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'report', 'target' ) );
			$report = trim( (string) ( $arguments['report'] ?? '' ) );
			SeRanking_Validators::validate_enum( $report, array( 'backlinks', 'anchors', 'refdomains' ) );
			$paths = array(
				'backlinks'  => '/v1/backlinks/all',
				'anchors'    => '/v1/backlinks/anchors',
				'refdomains' => '/v1/backlinks/refdomains',
			);
			$path = $paths[ $report ];

			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );

			$query = array( 'target' => $target );
			if ( isset( $arguments['mode'] ) && '' !== $arguments['mode'] ) {
				$mode = trim( (string) $arguments['mode'] );
				SeRanking_Validators::validate_mode( $mode );
				$query['mode'] = $mode;
			}
			if ( isset( $arguments['limit'] ) && '' !== $arguments['limit'] ) {
				$limit = (int) $arguments['limit'];
				SeRanking_Validators::validate_limit( $limit, 10000 );
				$query['limit'] = $limit;
			}
			if ( isset( $arguments['order_by'] ) && '' !== $arguments['order_by'] ) {
				$query['order_by'] = trim( (string) $arguments['order_by'] );
			}

			if ( 'backlinks' === $report ) {
				foreach ( array( 'per_domain', 'inlink_rank_from', 'inlink_rank_to', 'domain_inlink_rank_from', 'domain_inlink_rank_to' ) as $opt ) {
					if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
						$query[ $opt ] = (int) $arguments[ $opt ];
					}
				}
				foreach ( array( 'url_from_filter', 'url_from_filter_mode', 'url_to_filter', 'anchor_filter', 'nofollow_filter' ) as $opt ) {
					if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
						$query[ $opt ] = trim( (string) $arguments[ $opt ] );
					}
				}
			}

			return ( new SeRanking_Client() )->request( 'GET', $path, $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
