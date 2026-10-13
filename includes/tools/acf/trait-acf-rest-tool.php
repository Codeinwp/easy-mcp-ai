<?php






namespace Easy_MCP_AI\Tools\ACF;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}








trait Acf_Rest_Tool {

    


    protected function require_acf() {
        if ( ! class_exists( 'ACF' ) ) {
            throw new \RuntimeException( 'Advanced Custom Fields (ACF) is not active on this site. Note: Secure Custom Fields (SCF) uses the same ACF class name so this check covers both.' );
        }
    }

    







    protected function acf_read( $type, $id, $route ) {
        $call = Acf_Rest_Call::run(
            function () use ( $route ) {
                return $this->rest_request( 'GET', $route );
            },
            $type,
            $id,
            $route,
            false
        );
        return array( 'acf_fields' => $call['data']['acf'] ?? array() )
            + Acf_Rest_Call::read_report( $call['data'], $call['opened'], $type );
    }

    








    protected function acf_write( $type, $id, $route, array $fields ) {
        $call = Acf_Rest_Call::run(
            function () use ( $route, $fields ) {
                return $this->rest_request( 'POST', $route, array( 'acf' => $fields ) );
            },
            $type,
            $id,
            $route,
            true
        );
        return array( 'acf_fields' => $call['data']['acf'] ?? array() )
            + Acf_Rest_Call::write_report( $fields, $call['saved'], $call['data'], $call['opened'], $type );
    }
}
