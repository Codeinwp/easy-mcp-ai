<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Prompts extends Base_Tool {

	public function get_name() { return 'wp_seranking_ai_prompts'; }
	public function get_description() {
		return 'SE Ranking AI Search prompts — the AI-engine prompts where a target or brand appears, with prompt text, volume, type, and the AI answer (text + links). by selects the lookup: target (→ /ai-search/prompts-by-target, requires engine, target, source, scope) or brand (→ /ai-search/prompts-by-brand, requires engine, brand, source). engine is one of ai-overview, chatgpt, perplexity, gemini, ai-mode; source is an ISO alpha-2 country code. limit defaults to 100 (max 1000). Returns { total, date, prompts } as-is. WARNING: VERY EXPENSIVE — call sparingly. (meter: 200 credits/prompt)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking AI prompts',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'by', 'engine', 'source' ),
			'properties' => array(
				'by'         => array( 'type' => 'string', 'enum' => array( 'target', 'brand' ), 'description' => 'Look up prompts by target or by brand.' ),
				'engine'     => array( 'type' => 'string', 'enum' => array( 'ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode' ), 'description' => 'AI engine to report on.' ),
				'source'     => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'target'     => array( 'type' => 'string', 'description' => 'Domain or URL (required when by=target).' ),
				'scope'      => array( 'type' => 'string', 'enum' => array( 'base_domain', 'domain', 'url' ), 'description' => 'How target is interpreted (required when by=target).' ),
				'brand'      => array( 'type' => 'string', 'description' => 'Brand name (required when by=brand).' ),
				'sort'       => array( 'type' => 'string', 'description' => 'Sort field; default volume.' ),
				'sort_order' => array( 'type' => 'string', 'description' => 'Sort direction; default desc.' ),
				'limit'      => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100, 'description' => 'Rows to return (1–1000); default 100.' ),
				'offset'     => array( 'type' => 'integer', 'description' => 'Row offset; default 0.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'by', 'engine', 'source' ) );
			$by = trim( (string) ( $arguments['by'] ?? '' ) );
			SeRanking_Validators::validate_enum( $by, array( 'target', 'brand' ) );
			$path = ( 'brand' === $by ) ? '/v1/ai-search/prompts-by-brand' : '/v1/ai-search/prompts-by-target';

			$engine = trim( (string) ( $arguments['engine'] ?? '' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			SeRanking_Validators::validate_enum( $engine, array( 'ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode' ) );
			SeRanking_Validators::validate_source( $source );

			$query = array( 'engine' => $engine, 'source' => $source );

			if ( 'target' === $by ) {
				$this->validate_required( $arguments, array( 'target', 'scope' ) );
				$target = trim( (string) ( $arguments['target'] ?? '' ) );
				$scope  = trim( (string) ( $arguments['scope'] ?? '' ) );
				SeRanking_Validators::validate_target( $target );
				SeRanking_Validators::validate_scope( $scope );
				$query['target'] = $target;
				$query['scope']  = $scope;
			} else {
				$this->validate_required( $arguments, array( 'brand' ) );
				$query['brand'] = trim( (string) $arguments['brand'] );
			}

			if ( isset( $arguments['limit'] ) && '' !== $arguments['limit'] ) {
				$limit = (int) $arguments['limit'];
				SeRanking_Validators::validate_limit( $limit, 1000 );
				$query['limit'] = $limit;
			}
			if ( isset( $arguments['offset'] ) && '' !== $arguments['offset'] ) {
				$query['offset'] = (int) $arguments['offset'];
			}
			foreach ( array( 'sort', 'sort_order' ) as $opt ) {
				if ( isset( $arguments[ $opt ] ) && '' !== $arguments[ $opt ] ) {
					$query[ $opt ] = trim( (string) $arguments[ $opt ] );
				}
			}

			return ( new SeRanking_Client() )->request( 'GET', $path, $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
