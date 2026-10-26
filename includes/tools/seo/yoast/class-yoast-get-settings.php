<?php
namespace Easy_MCP_AI\Tools\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Easy_MCP_AI\Tools\Base_Tool;






class Yoast_Get_Settings extends Base_Tool {

	public function get_name() {
		return 'wp_yoast_get_settings';
	}

	public function get_description() {
		return 'Gets Yoast SEO\'s site-wide title and social settings: the title separator (with every separator Yoast offers), the SEO title, meta description, social title, social description and social image templates for each content type, and the default social image used when a page has none. Content types are named post_type:<name>, archive:<post type> (a custom post type\'s archive), taxonomy:<name>, home (used only while the homepage shows the latest posts; a static homepage uses its page\'s own SEO), author_archive, date_archive, search and 404; a field Yoast does not have for a type is left out. Templates keep Yoast\'s variables such as %%title%% and %%sep%%. Returns { separator: { key, character, options }, templates: [ { content_type, title, description, social_title, social_description, social_image: { id, url } } ], default_social_image: { id, url }, locked } — locked lists settings a network administrator locked for this site. Change them with wp_yoast_update_settings. Requires Yoast\'s wpseo_manage_options capability (administrators and SEO managers).';
	}

	public function get_category() {
		return 'yoast-seo';
	}

	public function get_required_capability() {
		
		return 'wpseo_manage_options';
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
		list( $titles, $social ) = Yoast_Site_Settings::instances();
		return Yoast_Site_Settings::read( $titles, $social );
	}
}
