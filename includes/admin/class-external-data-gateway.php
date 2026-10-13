<?php








namespace Easy_MCP_AI\Admin;

use Easy_MCP_AI\Ahrefs\Ahrefs_Client;
use Easy_MCP_AI\DFS\DataforSEO_Client;
use Easy_MCP_AI\GA\GA_Client;
use Easy_MCP_AI\GSC\GSC_Client;
use Easy_MCP_AI\Semrush\Semrush_Client;
use Easy_MCP_AI\SeRanking\SeRanking_Client;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class External_Data_Gateway {

    
    public function gsc_sites() {
        return GSC_Client::get( 'https://www.googleapis.com/webmasters/v3/sites', true );
    }

    
    public function ga_account_summaries() {
        return GA_Client::get( 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200', true );
    }

    
    public function dfs_balance() {
        return ( new DataforSEO_Client() )->get_balance( true );
    }

    
    public function semrush_balance() {
        return ( new Semrush_Client() )->get_balance();
    }

    
    public function seranking_balance() {
        return ( new SeRanking_Client() )->get_balance();
    }

    
    public function ahrefs_verify() {
        ( new Ahrefs_Client() )->verify_key();
    }
}
