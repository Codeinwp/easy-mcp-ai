<?php
namespace Easy_MCP_AI\Tools\GSC;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GSC\GSC_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Sitemaps extends Base_Tool {

    public function get_name() { return 'wp_gsc_list_sitemaps'; }

    public function get_description() {
        return 'Lists all submitted sitemaps for a Google Search Console property, including submission dates, download dates, error and warning counts, and URL counts by content type. Requires a Google service-account credential configured under Easy MCP AI → Tools → External data.';
    }

    public function get_category() { return 'gsc'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'List sitemaps',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'site_url'      => array( 'type' => 'string', 'description' => 'Property URL. Uses configured default if omitted.' ),
                'sitemap_index' => array( 'type' => 'string', 'description' => 'Optional. Filter to sitemaps within this sitemap index URL.' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $site_url = ! empty( $arguments['site_url'] )
            ? GSC_Client::validate_site_url( (string) $arguments['site_url'] )
            : GSC_Client::default_site_url();

        $encoded_site = rawurlencode( $site_url );
        $url          = "https://www.googleapis.com/webmasters/v3/sites/{$encoded_site}/sitemaps";
        $index        = ! empty( $arguments['sitemap_index'] ) ? trim( (string) $arguments['sitemap_index'] ) : '';
        if ( '' !== $index ) {
            if ( ! preg_match( '#^https?://\S+$#i', $index ) ) {
                throw new \RuntimeException( 'sitemap_index must be an absolute http(s) URL, e.g. https://example.com/sitemap-index.xml.' );
            }
            $url .= '?sitemapIndex=' . rawurlencode( $index );
        }

        $data = GSC_Client::get( $url );

        $result = array(
            'sitemaps' => $data['sitemap'] ?? array(),
            'count'    => count( $data['sitemap'] ?? array() ),
        );
        return $result;
    }
}
