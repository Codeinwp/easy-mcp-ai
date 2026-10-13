<?php





















namespace Easy_MCP_AI\Meta\Providers;

use Easy_MCP_AI\Meta\Meta_Field_Provider;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Yoast_Meta_Provider extends Meta_Field_Provider {

    public function get_group_slug() {
        return 'yoast-seo';
    }

    public function get_label() {
        return 'Yoast SEO';
    }

    public function get_key_prefixes() {
        return array( '_yoast_wpseo_' );
    }

    public function is_active() {
        return class_exists( 'WPSEO_Options' );
    }

    public function get_dedicated_tool() {
        return 'wp_yoast_update_post_seo';
    }

    





    public static function can_edit_advanced( $user_id ) {
        if ( user_can( (int) $user_id, 'wpseo_manage_options' ) || user_can( (int) $user_id, 'wpseo_edit_advanced_metadata' ) ) {
            return true;
        }
        
        return class_exists( 'WPSEO_Options' ) && false === \WPSEO_Options::get( 'disableadvanced_meta' );
    }

    public function get_fields() {
        $advanced = array( __CLASS__, 'can_edit_advanced' );
        return array(
            '_yoast_wpseo_title'                 => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast SEO title.' ),
            '_yoast_wpseo_metadesc'              => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast meta description.' ),
            '_yoast_wpseo_focuskw'               => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast focus keyphrase.' ),
            '_yoast_wpseo_opengraph-title'       => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast Open Graph title.' ),
            '_yoast_wpseo_opengraph-description' => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast Open Graph description.' ),
            '_yoast_wpseo_opengraph-image'       => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'Yoast Open Graph image URL.' ),
            '_yoast_wpseo_twitter-title'         => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast Twitter card title.' ),
            '_yoast_wpseo_twitter-description'   => array( 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast Twitter card description.' ),
            '_yoast_wpseo_twitter-image'         => array( 'type' => 'string', 'sanitize' => 'url', 'description' => 'Yoast Twitter card image URL.' ),
            '_yoast_wpseo_is_cornerstone'        => array( 'type' => 'string', 'sanitize' => 'flag:1', 'enum' => array( '1', '' ), 'enum_is_hint' => true, 'description' => 'Yoast cornerstone flag: "1" to set, "" to clear.' ),
            '_yoast_wpseo_meta-robots-noindex'   => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'enum', 'enum' => array( '0', '1', '2' ), 'description' => 'Yoast robots noindex, tri-state: "0" default, "1" noindex, "2" index.' ),
            '_yoast_wpseo_meta-robots-nofollow'  => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'enum', 'enum' => array( '0', '1' ), 'description' => 'Yoast robots nofollow: "0" default, "1" nofollow.' ),
            '_yoast_wpseo_meta-robots-adv'       => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'csv', 'enum' => array( 'noimageindex', 'noarchive', 'nosnippet' ), 'enum_is_hint' => true, 'description' => 'Yoast advanced robots, comma-separated: noimageindex, noarchive, nosnippet.' ),
            '_yoast_wpseo_canonical'             => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'url', 'description' => 'Yoast canonical URL.' ),
            '_yoast_wpseo_bctitle'               => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast breadcrumb title.' ),
            '_yoast_wpseo_schema_page_type'      => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast schema page type, e.g. WebPage, AboutPage.' ),
            '_yoast_wpseo_schema_article_type'   => array( 'auth' => $advanced, 'type' => 'string', 'sanitize' => 'text', 'description' => 'Yoast schema article type, e.g. Article, NewsArticle.' ),
            '_yoast_wpseo_primary_category'      => array( 'type' => 'string', 'sanitize' => 'int_string', 'description' => 'Yoast primary category: the term id as a string, "" to clear.' ),
        );
    }
}
