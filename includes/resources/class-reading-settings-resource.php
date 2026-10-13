<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Reading_Settings_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/reading-settings';
    }

    public function get_name() {
        return 'Reading Settings';
    }

    public function get_description() {
        return 'Homepage display type, front page and posts page IDs, and posts per page.';
    }

    public function read() {
        return array(
            'show_on_front'  => get_option( 'show_on_front', 'posts' ),
            'page_on_front'  => (int) get_option( 'page_on_front', 0 ),
            'page_for_posts' => (int) get_option( 'page_for_posts', 0 ),
            'posts_per_page' => (int) get_option( 'posts_per_page', 10 ),
            'posts_per_rss'  => (int) get_option( 'posts_per_rss', 10 ),
        );
    }
}
