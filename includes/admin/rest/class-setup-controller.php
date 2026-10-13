<?php












namespace Easy_MCP_AI\Admin\Rest;

use Easy_MCP_AI\Admin\Setup_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Setup_Controller extends Admin_Rest_Controller {

    public function register_routes() {
        $this->register_route( '/setup/complete', \WP_REST_Server::CREATABLE, array( $this, 'complete' ) );
        $this->register_route( '/setup/skip', \WP_REST_Server::CREATABLE, array( $this, 'skip' ) );
    }

    





    public function complete( $request ) {
        return $this->finish( false );
    }

    





    public function skip( $request ) {
        return $this->finish( true );
    }

    private function finish( $skipped ) {
        Setup_State::mark_complete();
        return $this->ok( array(
            
            'setupComplete' => Setup_State::is_complete(),
            'skipped'       => (bool) $skipped,
        ) );
    }
}
