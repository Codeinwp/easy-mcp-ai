<?php






namespace Easy_MCP_AI\Cache;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}






















final class Page_Cache_Opt_Out {

    
    const REST_NAMESPACE = '/easy-mcp-ai/v1';

    
    const LITESPEED_HEADER = 'X-LiteSpeed-Cache-Control';

    













    public static function on_rest_pre_serve_request( $served, $result = null, $request = null, $server = null ) {
        if ( $request instanceof \WP_REST_Request && self::is_own_route( (string) $request->get_route() ) ) {
            self::mark_uncacheable();
            if ( is_object( $server ) && method_exists( $server, 'send_header' ) ) {
                $server->send_header( self::LITESPEED_HEADER, 'no-cache' );
            }
        }
        return $served;
    }

    








    public static function apply_to_direct_response(): void {
        self::mark_uncacheable();
        if ( ! headers_sent() ) {
            header( self::LITESPEED_HEADER . ': no-cache' );
        }
    }

    





    public static function is_own_route( string $route ): bool {
        $route = '/' . ltrim( $route, '/' );
        return self::REST_NAMESPACE === $route || 0 === strpos( $route, self::REST_NAMESPACE . '/' );
    }

    





    private static function mark_uncacheable(): void {
        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }
        \do_action( 'litespeed_control_set_nocache', 'easy-mcp-ai: MCP and OAuth responses are never stored' );
    }
}
