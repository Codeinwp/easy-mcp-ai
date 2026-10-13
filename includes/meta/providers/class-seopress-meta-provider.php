<?php









namespace Easy_MCP_AI\Meta\Providers;

use Easy_MCP_AI\Meta\Meta_Field_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Seopress_Meta_Provider extends Meta_Field_Provider {

    public function get_group_slug() {
        return 'seopress';
    }

    public function get_label() {
        return 'SEOPress';
    }

    public function get_key_prefixes() {
        return array( '_seopress_' );
    }

    public function is_active() {
        return function_exists( 'seopress_get_service' );
    }

    public function get_dedicated_tool() {
        return 'wp_seopress_update_post_title_desc';
    }

    public function get_fields() {
        return array(
            '_seopress_titles_title'          => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress meta title.' ),
            '_seopress_titles_desc'           => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress meta description.' ),
            '_seopress_analysis_target_kw'    => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress target keywords, comma-separated.' ),
            '_seopress_robots_canonical'      => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'SEOPress canonical URL.' ),
            '_seopress_robots_index'          => array( 'type' => 'string', 'sanitize' => 'flag:yes', 'enum' => array( 'yes', '' ), 'enum_is_hint' => true, 'description' => 'SEOPress noindex: "yes" to set, "" to clear.' ),
            '_seopress_robots_follow'         => array( 'type' => 'string', 'sanitize' => 'flag:yes', 'enum' => array( 'yes', '' ), 'enum_is_hint' => true, 'description' => 'SEOPress nofollow: "yes" to set, "" to clear.' ),
            '_seopress_robots_imageindex'     => array( 'type' => 'string', 'sanitize' => 'flag:yes', 'enum' => array( 'yes', '' ), 'enum_is_hint' => true, 'description' => 'SEOPress noimageindex: "yes" to set, "" to clear.' ),
            '_seopress_robots_snippet'        => array( 'type' => 'string', 'sanitize' => 'flag:yes', 'enum' => array( 'yes', '' ), 'enum_is_hint' => true, 'description' => 'SEOPress nosnippet: "yes" to set, "" to clear.' ),
            '_seopress_robots_breadcrumbs'    => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress breadcrumbs title.' ),
            '_seopress_robots_primary_cat'    => array( 'type' => 'string', 'sanitize' => 'int_string', 'description' => 'SEOPress primary category: the term id as a string, "" to clear.' ),
            '_seopress_social_fb_title'       => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress Facebook title.' ),
            '_seopress_social_fb_desc'        => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress Facebook description.' ),
            '_seopress_social_fb_img'         => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'SEOPress Facebook image URL.' ),
            '_seopress_social_twitter_title'  => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress Twitter title.' ),
            '_seopress_social_twitter_desc'   => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'SEOPress Twitter description.' ),
            '_seopress_social_twitter_img'    => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'SEOPress Twitter image URL.' ),
        );
    }
}
