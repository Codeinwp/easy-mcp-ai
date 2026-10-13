<?php
namespace Easy_MCP_AI\Tools\GSC;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GSC\GSC_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Sites extends Base_Tool {

    public function get_name() { return 'wp_gsc_list_sites'; }

    public function get_description() {
        return 'Lists all Google Search Console properties accessible to the configured service account, including each property\'s permission level.';
    }

    public function get_category() { return 'gsc'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'List Search Console sites',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $data = GSC_Client::get( 'https://www.googleapis.com/webmasters/v3/sites' );

        $sites = array();
        foreach ( $data['siteEntry'] ?? array() as $entry ) {
            $sites[] = array(
                'siteUrl'         => $entry['siteUrl'] ?? '',
                'permissionLevel' => $entry['permissionLevel'] ?? '',
            );
        }
        return array( 'sites' => $sites, 'count' => count( $sites ) );
    }
}
