<?php
namespace Easy_MCP_AI\Tools\GA;

use Easy_MCP_AI\Tools\Base_Tool;
use Easy_MCP_AI\GA\GA_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class List_Account_Summaries extends Base_Tool {

    public function get_name() { return 'wp_ga_list_account_summaries'; }

    public function get_description() {
        return 'Lists all Google Analytics 4 accounts and their properties accessible to the configured service account. Returns the full account→property tree in one call — the recommended entry point for discovering a valid property_id.';
    }

    public function get_category() { return 'ga'; }

    public function get_required_capability() { return 'manage_options'; }

    public function get_annotations() {
        return array(
            'title'           => 'List Analytics account summaries',
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'page_size'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 200, 'description' => 'Maximum number of account summaries to return (1-200).' ),
                'page_token' => array( 'type' => 'string', 'description' => 'Pagination token returned in a previous response.' ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $size  = max( 1, min( 200, (int) ( $arguments['page_size'] ?? 200 ) ) );
        $token = isset( $arguments['page_token'] ) ? (string) $arguments['page_token'] : '';

        $url = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=' . $size;
        if ( '' !== $token ) {
            $url .= '&pageToken=' . rawurlencode( $token );
        }
        $data = GA_Client::get( $url );

        $result = array(
            'accountSummaries' => $data['accountSummaries'] ?? array(),
            'nextPageToken'    => $data['nextPageToken'] ?? null,
            'count'            => count( $data['accountSummaries'] ?? array() ),
        );
        return $result;
    }
}
