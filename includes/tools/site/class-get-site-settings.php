<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Site_Settings extends Base_Tool {

    public function get_name() {
        return 'wp_get_site_settings';
    }

    public function get_description() {
        return 'Retrieves the current WordPress site settings. Returns { title, description, url, email (admin email), timezone, date_format, time_format, start_of_week, language, use_smilies, default_category, default_post_format, posts_per_page, show_on_front, page_on_front, page_for_posts, default_ping_status, default_comment_status, site_logo, site_icon }. Requires `manage_options` capability (administrators only).';
    }

    public function get_category() {
        return 'site';
    }

    public function get_required_capability() {
        return 'manage_options';
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
        $data = $this->rest_request( 'GET', '/wp/v2/settings' );

        return array(
            'title'                  => $data['title'] ?? null,
            'description'            => $data['description'] ?? null,
            'url'                    => $data['url'] ?? null,
            'email'                  => $data['email'] ?? null,
            'timezone'               => $data['timezone'] ?? null,
            'date_format'            => $data['date_format'] ?? null,
            'time_format'            => $data['time_format'] ?? null,
            'start_of_week'          => $data['start_of_week'] ?? null,
            'language'               => $data['language'] ?? null,
            'use_smilies'            => $data['use_smilies'] ?? null,
            'default_category'       => $data['default_category'] ?? null,
            'default_post_format'    => $data['default_post_format'] ?? null,
            'posts_per_page'         => $data['posts_per_page'] ?? null,
            'show_on_front'          => $data['show_on_front'] ?? null,
            'page_on_front'          => $data['page_on_front'] ?? null,
            'page_for_posts'         => $data['page_for_posts'] ?? null,
            'default_ping_status'    => $data['default_ping_status'] ?? null,
            'default_comment_status' => $data['default_comment_status'] ?? null,
            'site_logo'              => $data['site_logo'] ?? null,
            'site_icon'              => $data['site_icon'] ?? null,
        );
    }
}
