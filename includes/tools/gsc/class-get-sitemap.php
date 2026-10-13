<?php
namespace Easy_MCP_AI\Tools\GSC;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GSC\GSC_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Sitemap extends Base_Tool {

    public function get_name() { return 'wp_gsc_get_sitemap'; }

    public function get_description() {
        return 'Gets full details for a specific submitted sitemap including submitted vs indexed URL counts per content type (web, image, video), errors, and warnings. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'gsc'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'Get sitemap',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'site_url'  => array( 'type' => 'string', 'description' => 'The Search Console property URL. Required — does not fall back to the configured default.' ),
                'feed_path' => array( 'type' => 'string', 'description' => 'The sitemap URL, e.g. https://example.com/sitemap.xml' ),
            ),
            'required' => array( 'site_url', 'feed_path' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'site_url', 'feed_path' ) );
        $feed_path = trim( (string) $arguments['feed_path'] );
        if ( ! preg_match( '#^https?://\S+$#i', $feed_path ) ) {
            throw new \InvalidArgumentException( 'feed_path must be an absolute http(s) URL to the sitemap.' );
        }
        $encoded_site = rawurlencode( GSC_Client::validate_site_url( (string) $arguments['site_url'] ) );
        $encoded_feed = rawurlencode( $feed_path );
        $url          = "https://www.googleapis.com/webmasters/v3/sites/{$encoded_site}/sitemaps/{$encoded_feed}";
        $data = GSC_Client::get( $url );

        return array(
            'path'            => $data['path'] ?? '',
            'lastSubmitted'   => $data['lastSubmitted'] ?? null,
            'lastDownloaded'  => $data['lastDownloaded'] ?? null,
            'isPending'       => $data['isPending'] ?? false,
            'isSitemapsIndex' => $data['isSitemapsIndex'] ?? false,
            'type'            => $data['type'] ?? '',
            'errors'          => (int) ( $data['errors'] ?? 0 ),
            'warnings'        => (int) ( $data['warnings'] ?? 0 ),
            'contents'        => $data['contents'] ?? array(),
        );
    }
}
