<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;

class Rankmath_Get_Settings extends Base_Tool {

	use Rank_Math_Setup;

	public function get_name() {
		return 'wp_rm_get_settings';
	}

	public function get_description() {
		return 'Gets Rank Math SEO\'s site-wide settings that wp_rm_update_settings changes, as Rank Math reads them: the noindex switches for paginated pages, search results and password-protected pages, the 404 title, the social profiles (Facebook page, Twitter username, additional profile URLs), the Image SEO alt and title formats, the custom robots.txt text, and the Local SEO tab (Person or Company, names, logo, URL, email, phone, address, business type, opening hours, phone numbers, price range, additional organization info, whether a Google Maps API key is set, geo coordinates, About and Contact pages). Returns { settings, about, not_applied, robots_txt }: settings holds each field\'s value under the name wp_rm_update_settings takes; about gives each one\'s name on Rank Math\'s screen and what it does (noindex_paginated_pages does not reach the pages of a post split with <!--nextpage-->, whatever its label says); not_applied lists fields that have no effect on this site or that Rank Math\'s own screen would not offer, with the reason (Image SEO or Local SEO module off; a field for a Person while the site is an Organization, or the reverse; robots.txt not served); the Maps API key is never returned, only "set, ending in" its last 4 characters; robots_txt is { served_from, editable, reason }, where served_from is physical_file (a robots.txt file in the site root is served instead), rank_math (the custom text) or wordpress (WordPress\'s default, with Rank Math\'s Sitemap line). Fields of a Rank Math settings group the user lacks the capability for are left out. Requires Rank Math\'s rank_math_titles capability (administrators by default); the Image SEO and robots.txt fields also rank_math_general.';
	}

	public function get_category() {
		return 'rank-math';
	}

	public function get_required_capability() {
		return function_exists( 'rank_math' ) ? 'rank_math_titles' : 'manage_options';
	}

	public function get_annotations() {
		return array(
			'title'           => $this->get_title(),
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'openWorldHint'   => false,
		);
	}

	public function get_input_schema() {
		return array(
			'type'       => 'object',
			'properties' => new \stdClass(),
		);
	}

	public function execute( array $arguments ) {
		$this->require_rank_math_ready( 'read its settings' );
		$settings    = Rankmath_Site_Settings::values();
		$entity      = (string) Rankmath_Site_Settings::value( 'knowledgegraph_type', Rankmath_Site_Settings::stored( 'titles' ) );
		$not_applied = array();
		foreach ( array_keys( $settings ) as $field ) {
			$reason = Rankmath_Site_Settings::why_not_applied( $field, $entity );
			if ( '' !== $reason ) {
				$not_applied[] = array(
					'field'  => $field,
					'reason' => $reason,
				);
			}
		}
		$out = array(
			'settings'    => $settings,
			'about'       => array_combine( array_keys( $settings ), array_map( array( Rankmath_Site_Settings::class, 'about' ), array_keys( $settings ) ) ),
			'not_applied' => $not_applied,
		);
		if ( array_key_exists( 'robots_txt', $settings ) ) {
			$reason            = Rankmath_Site_Settings::robots_lock_reason();
			$out['robots_txt'] = array(
				'served_from' => Rankmath_Site_Settings::robots_served_from( Rankmath_Site_Settings::stored( 'general' ) ),
				'editable'    => '' === $reason,
				'reason'      => $reason,
			);
		}
		return $out;
	}
}
