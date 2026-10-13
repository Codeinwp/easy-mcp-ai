<?php



















namespace Easy_MCP_AI\Meta\Providers;

use Easy_MCP_AI\Meta\Meta_Field_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rank_Math_Meta_Provider extends Meta_Field_Provider {

    public function get_group_slug() {
        return 'rank-math';
    }

    public function get_label() {
        return 'Rank Math SEO';
    }

    public function get_key_prefixes() {
        return array( 'rank_math_' );
    }

    public function is_active() {
        return function_exists( 'rank_math' );
    }

    public function get_dedicated_tool() {
        return 'wp_rm_update_post_seo';
    }

    public function get_fields() {
        return array(
            'rank_math_title'                => array( 'auth' => 'rank_math_onpage_general', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math SEO title.' ),
            'rank_math_description'          => array( 'auth' => 'rank_math_onpage_general', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math meta description.' ),
            'rank_math_focus_keyword'        => array( 'auth' => 'rank_math_onpage_general', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math focus keyword(s), comma-separated.' ),
            'rank_math_canonical_url'        => array( 'auth' => 'rank_math_onpage_advanced', 'type' => 'string', 'sanitize' => 'url', 'description' => 'Rank Math canonical URL.' ),
            'rank_math_facebook_title'       => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math Facebook / Open Graph title.' ),
            'rank_math_facebook_description' => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math Facebook / Open Graph description.' ),
            'rank_math_facebook_image'       => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'url', 'description' => 'Rank Math Facebook / Open Graph image URL.' ),
            'rank_math_twitter_title'        => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math Twitter card title.' ),
            'rank_math_twitter_description'  => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math Twitter card description.' ),
            'rank_math_twitter_image'        => array( 'auth' => 'rank_math_onpage_social', 'type' => 'string', 'sanitize' => 'url', 'description' => 'Rank Math Twitter card image URL.' ),
            'rank_math_robots'               => array( 'auth' => 'rank_math_onpage_advanced',
                'type'        => 'array',
                'sanitize'    => 'string_list',
                'enum'        => array( 'index', 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' ),
                'items'       => array( 'type' => 'string', 'enum' => array( 'index', 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet' ) ),
                'description' => 'Rank Math robots directives as an array of tokens: index, noindex, nofollow, noarchive, noimageindex, nosnippet.',
            ),
            'rank_math_advanced_robots'      => array( 'auth' => 'rank_math_onpage_advanced',
                'type'        => 'object',
                'sanitize'    => 'object_keys',
                'properties'  => array(
                    'max-snippet'       => array( 'type' => array( 'integer', 'boolean' ) ),
                    'max-video-preview' => array( 'type' => array( 'integer', 'boolean' ) ),
                    'max-image-preview' => array( 'type' => array( 'string', 'boolean' ) ),
                ),
                'description' => 'Rank Math advanced robots: max-snippet, max-video-preview (integers) and max-image-preview (large, standard, none); false disables a directive, as Rank Math stores it.',
            ),
            'rank_math_breadcrumb_title'     => array( 'auth' => 'rank_math_onpage_advanced', 'type' => 'string', 'sanitize' => 'text', 'description' => 'Rank Math breadcrumb title.' ),
            'rank_math_pillar_content'       => array( 'auth' => 'rank_math_onpage_general', 'type' => 'string', 'sanitize' => 'flag:on', 'enum' => array( 'on', '' ), 'enum_is_hint' => true, 'description' => 'Rank Math pillar content flag: "on" to set, "" to clear.' ),
            'rank_math_primary_category'     => array( 'auth' => 'rank_math_onpage_general', 'type' => 'string', 'sanitize' => 'int_string', 'description' => 'Rank Math primary category: the term id as a string, "" to clear.' ),
        );
    }
}
