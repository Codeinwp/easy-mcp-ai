<?php











namespace Easy_MCP_AI\Meta\Providers;

use Easy_MCP_AI\Meta\Meta_Field_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Tsf_Meta_Provider extends Meta_Field_Provider {

    public function get_group_slug() {
        return 'the-seo-framework';
    }

    public function get_label() {
        return 'The SEO Framework';
    }

    public function get_key_prefixes() {
        return array( '_genesis_' );
    }

    public function is_active() {
        return function_exists( 'tsf' );
    }

    public function get_dedicated_tool() {
        return 'wp_tsf_update_post_seo';
    }

    public function get_fields() {
        return array(
            '_genesis_title'          => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework SEO title.' ),
            '_genesis_description'    => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework meta description.' ),
            '_genesis_canonical_uri'  => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'The SEO Framework canonical URL.' ),
            '_genesis_noindex'        => array( 'type' => 'integer', 'sanitize' => 'qubit', 'description' => 'The SEO Framework noindex qubit: -1 force off, 0 default, 1 force on.' ),
            '_genesis_nofollow'       => array( 'type' => 'integer', 'sanitize' => 'qubit', 'description' => 'The SEO Framework nofollow qubit: -1 force off, 0 default, 1 force on.' ),
            '_genesis_noarchive'      => array( 'type' => 'integer', 'sanitize' => 'qubit', 'description' => 'The SEO Framework noarchive qubit: -1 force off, 0 default, 1 force on.' ),
            '_open_graph_title'       => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework Open Graph title.' ),
            '_open_graph_description' => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework Open Graph description.' ),
            '_twitter_title'          => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework Twitter title.' ),
            '_twitter_description'    => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'The SEO Framework Twitter description.' ),
            '_social_image_url'       => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'The SEO Framework social image URL.' ),
            '_social_image_id'        => array( 'type' => 'integer', 'sanitize' => 'int', 'description' => 'The SEO Framework social image attachment id.' ),
        );
    }
}
