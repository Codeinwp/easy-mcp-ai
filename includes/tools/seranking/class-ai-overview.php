<?php
namespace Easy_MCP_AI\Tools\SeRanking;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\SeRanking\SeRanking_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Validators;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ai_Overview extends Base_Tool {

	public function get_name() { return 'wp_seranking_ai_overview'; }
	public function get_description() {
		return 'SE Ranking AI Search overview (time series) — a target\'s brand & link presence, average position, and AI opportunity traffic in an AI engine over time. engine is one of ai-overview, chatgpt, perplexity, gemini, ai-mode. target is a domain or URL; source is an ISO alpha-2 country code (us, uk, de…). Returns { summary, time_series } as-is. WARNING: VERY EXPENSIVE — call sparingly. (meter: 800 credits/request)';
	}
	public function get_category() { return 'seranking'; }
	public function get_required_capability() { return 'manage_options'; }
	public function get_annotations() {
		return array(
			'title'           => 'SE Ranking AI overview',
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => true,
		);
	}
	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'required'   => array( 'engine', 'target', 'source' ),
			'properties' => array(
				'engine' => array( 'type' => 'string', 'enum' => array( 'ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode' ), 'description' => 'AI engine to report on.' ),
				'target' => array( 'type' => 'string', 'description' => 'Domain or URL to analyse.' ),
				'source' => array( 'type' => 'string', 'description' => 'ISO alpha-2 country code (e.g. us, uk, de).' ),
				'scope'  => array( 'type' => 'string', 'enum' => array( 'base_domain', 'domain', 'url' ), 'description' => 'How target is interpreted; default base_domain.' ),
				'brand'  => array( 'type' => 'string', 'description' => 'Optional brand name filter.' ),
			),
		);
	}
	public function execute( array $arguments ) {
		try {
			$this->validate_required( $arguments, array( 'engine', 'target', 'source' ) );
			$engine = trim( (string) ( $arguments['engine'] ?? '' ) );
			SeRanking_Validators::validate_enum( $engine, array( 'ai-overview', 'chatgpt', 'perplexity', 'gemini', 'ai-mode' ) );
			$target = trim( (string) ( $arguments['target'] ?? '' ) );
			$source = trim( (string) ( $arguments['source'] ?? '' ) );
			SeRanking_Validators::validate_target( $target );
			SeRanking_Validators::validate_source( $source );

			$query = array(
				'engine' => $engine,
				'target' => $target,
				'source' => $source,
			);
			if ( isset( $arguments['scope'] ) && '' !== $arguments['scope'] ) {
				$scope = trim( (string) $arguments['scope'] );
				SeRanking_Validators::validate_scope( $scope );
				$query['scope'] = $scope;
			}
			if ( isset( $arguments['brand'] ) && '' !== $arguments['brand'] ) {
				$query['brand'] = trim( (string) $arguments['brand'] );
			}

			return ( new SeRanking_Client() )->request( 'GET', '/v1/ai-search/overview/by-engine/time-series', $query );
		} catch ( \Exception $e ) {
			return array( 'error' => $e->getMessage() );
		}
	}
}
