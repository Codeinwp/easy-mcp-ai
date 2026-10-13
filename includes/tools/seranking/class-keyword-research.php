<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Keyword_Research extends Base_Tool {

	public function get_name() { return 'wp_seranking_keyword_research'; }
	public function get_description() {
		return 'SE Ranking keyword research — expand a seed keyword into similar, related, question, or long-tail variations. mode selects the report: similar | related | questions | longtail (→ /keywords/{mode}). source is an ISO alpha-2 country code (us, uk, de…). limit defaults to 50 (cap 1000). The longtail mode ignores sort/sort_order. Returns { total, keywords: [...] } as-is. (meter: 10 credits/record; longtail 1 credit/record)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking keyword research',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'mode', 'source', 'keyword' ),
			'properties' => array(
				'mode'       => array( 'type' => 'string', 'enum' => array( 'similar', 'related', 'questions', 'longtail' ), 'description' => 'Which keyword report to run.' ),
				'source'     => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'keyword'    => array( 'type' => 'string', 'description' => 'Seed keyword to expand.' ),
				'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 50, 'description' => 'Rows to return (1–1000); default 50.' ),
				'offset'     => array( 'type' => 'integer', 'description' => 'Row offset; default 0.' ),
				'sort'       => array( 'type' => 'string', 'description' => 'Sort field (ignored for longtail).' ),
				'sort_order' => array( 'type' => 'string', 'description' => 'Sort direction; default desc (ignored for longtail).' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'mode', 'source', 'keyword' ) );
			$mode = trim( (string) ( $arguments['mode'] ?? '' ) );
			SeRanking_Validators::validate_enum( $mode, array( 'similar', 'related', 'questions', 'longtail' ) );
			$path = '/v1/keywords/' . $mode;

			$source  = trim( (string) ( $arguments['source'] ?? '' ) );
			$keyword = trim( (string) ( $arguments['keyword'] ?? '' ) );
			SeRanking_Validators::validate_source( $source );
			SeRanking_Validators::validate_keyword( $keyword );

			$query = array( 'source' => $source, 'keyword' => $keyword );
			if ( isset( $arguments['limit'] ) && '' !== $arguments['limit'] ) {
				$limit = (int) $arguments['limit'];
				SeRanking_Validators::validate_limit( $limit, 1000 );
				$query['limit'] = $limit;
			}
			if ( isset( $arguments['offset'] ) && '' !== $arguments['offset'] ) {
				$query['offset'] = (int) $arguments['offset'];
			}
			if ( 'longtail' !== $mode ) {
				foreach ( array( 'sort', 'sort_order' ) as $opt ) {
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
