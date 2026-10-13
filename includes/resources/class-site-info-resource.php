<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Info_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/info';
    }

    public function get_name() {
        return 'Site Information';
    }

    public function get_description() {
        return 'WordPress site name, URL, description, version, timezone, and language.';
    }

    public function read() {
        global $wp_version;

        return array(
            'name'         => get_bloginfo( 'name' ),
            'description'  => get_bloginfo( 'description' ),
            'url'          => home_url(),
            'admin_url'    => admin_url(),
            'wp_version'   => $wp_version,
            'php_version'  => phpversion(),
            'timezone'     => wp_timezone_string(),
            'language'     => get_locale(),
            'charset'      => get_bloginfo( 'charset' ),
            'is_multisite' => is_multisite(),
        );
    }
}
