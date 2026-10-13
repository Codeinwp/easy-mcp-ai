<?php






namespace Easy_MCP_AI\Tools;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Rest_Routes\Dedicated_Tools;
use Easy_MCP_AI\Rest_Routes\Namespace_State;
use Easy_MCP_AI\Rest_Routes\Route_Gate;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/class-base-tool.php';
require_once __DIR__ . '/../mcp/class-detailed-tool-error.php';
require_once __DIR__ . '/../rest-routes/class-namespace-state.php';
require_once __DIR__ . '/../rest-routes/class-route-gate.php';
require_once __DIR__ . '/../rest-routes/class-dedicated-tools.php';








abstract class Rest_Route_Tool extends Base_Tool {

    
    const MAX_RESPONSE_BYTES = 100000;

    
    const MAX_RESPONSE_FILTER = 'easy_mcp_ai_rest_max_response_bytes';

    
    const NAME_PREFIX = 'wp_rest_';

    public function get_category() {
        return 'rest';
    }

    



    public function get_required_capability() {
        return 'read';
    }

    






    public function approval_route( array $arguments ): ?string {
        try {
            return Route_Gate::normalize_path( $arguments['route'] ?? null );
        } catch ( \InvalidArgumentException $e ) {
            return null;
        }
    }

    





    public function describe( array $arguments ) {
        $path = $this->approval_route( $arguments );
        if ( null === $path ) {
            return parent::describe( $arguments );
        }
        try {
            $method = $this->method_for( $arguments );
        } catch ( \InvalidArgumentException $e ) {
            return parent::describe( $arguments );
        }
        $text = $method . ' ' . $path;
        foreach ( array( 'query', 'body' ) as $part ) {
            if ( ! empty( $arguments[ $part ] ) && is_array( $arguments[ $part ] ) ) {
                $json  = \wp_json_encode( $arguments[ $part ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
                $text .= ' ' . $part . ' ' . ( is_string( $json ) ? $json : '…' );
            }
        }
        return strlen( $text ) > 600 ? substr( $text, 0, 597 ) . '…' : $text;
    }

    






    abstract protected function method_for( array $arguments ): string;

    
    protected function sends_body(): bool {
        return false;
    }

    
    protected function reports_changes(): bool {
        return false;
    }

    






    protected function around_dispatch( callable $dispatch, array &$notes ): \WP_REST_Response {
        return $dispatch();
    }

    






    protected function call_route( array $arguments ): array {
        list( $method, $path, $query, $body, $resolved ) = $this->checked_call( $arguments );
        $server = \rest_get_server();

        $request = new \WP_REST_Request( $method, $path );
        if ( ! empty( $query ) ) {
            $request->set_query_params( $query );
        }
        if ( ! empty( $body ) ) {
            $request->set_header( 'content-type', 'application/json' );
            $request->set_body( \wp_json_encode( $body ) );
        }
        $fields = self::fields_argument( $arguments );
        if ( null !== $fields ) {
            $request->set_param( '_fields', $fields );
        }

        $notes    = array();
        $before   = self::changes_so_far();
        $response = $this->around_dispatch(
            function () use ( $request, $resolved ) {
                return self::dispatch( $request, $resolved );
            },
            $notes
        );
        if ( null !== $fields && function_exists( 'rest_filter_response_fields' ) ) {
            $response = \rest_filter_response_fields( $response, $server, $request );
        }
        if ( $response->is_error() ) {
            throw self::error_for( $method, $path, $response );
        }
        return $this->result( $method, $path, $resolved, $query, $response, $before, $notes );
    }

    









    public function precheck( array $arguments ): void {
        $this->checked_call( $arguments );
    }

    









    private function checked_call( array $arguments ): array {
        $method   = $this->method_for( $arguments );
        $path     = Route_Gate::normalize_path( $arguments['route'] ?? null );
        $query    = self::object_argument( $arguments, 'query' );
        $body     = $this->sends_body() ? self::object_argument( $arguments, 'body' ) : array();
        $resolved = Route_Gate::resolve( \rest_get_server(), $method, $path );
        $refusal  = Route_Gate::refusal( $method, $resolved, $query + $body );
        if ( null === $refusal ) {
            $refusal = Route_Gate::parameter_conflict( $resolved['url_params'], $query ) ?? Route_Gate::parameter_conflict( $resolved['url_params'], $body );
        }
        if ( null === $refusal ) {
            $refusal = $this->namespace_refusal( $resolved['namespace'] );
        }
        if ( null !== $refusal ) {
            throw new \RuntimeException( $refusal ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array( $method, $path, $query, $body, $resolved );
    }

    






    protected function namespace_refusal( string $namespace ): ?string {
        if ( ! Namespace_State::is_enabled( $namespace ) ) {
            return 'constant' === Namespace_State::off_by( $namespace )
                ? sprintf( 'The %s namespace is switched off on this site by EASY_MCP_AI_REST_NAMESPACES.', $namespace )
                : sprintf( 'The %s namespace is switched off on this site. An administrator can switch it on under Tools.', $namespace );
        }
        return null;
    }

    


















    private static function dispatch( \WP_REST_Request $request, array $resolved ): \WP_REST_Response {
        $server  = \rest_get_server();
        $confirm = static function ( $result, $dispatched, $route ) use ( $request, $resolved, $server ) {
            if ( null !== $result || ! $dispatched instanceof \WP_REST_Request ) {
                return $result;
            }
            $namespace = Route_Gate::namespace_of( $server, (string) $route );
            if ( $dispatched === $request ) {
                if ( $route === $resolved['route'] && Namespace_State::normalize( $namespace ) === Namespace_State::normalize( $resolved['namespace'] ) ) {
                    return null;
                }
                return new \WP_Error( 'easy_mcp_ai_route_changed', 'WordPress matched a different route than the one this call was checked against, so it was not run.', array( 'status' => 409 ) );
            }
            $refusal = Route_Gate::refusal( $dispatched->get_method(), array( 'route' => (string) $route, 'namespace' => $namespace ), self::request_params( $dispatched ) );
            return null === $refusal ? null : new \WP_Error( 'easy_mcp_ai_nested_route_refused', 'The route called another route that these tools never reach. ' . $refusal, array( 'status' => 403 ) );
        };
        \add_filter( 'rest_dispatch_request', $confirm, PHP_INT_MIN, 3 );
        try {
            return \rest_do_request( $request );
        } finally {
            \remove_filter( 'rest_dispatch_request', $confirm, PHP_INT_MIN );
        }
    }

    





    private static function request_params( \WP_REST_Request $request ): array {
        $json = $request->get_json_params();
        return array_merge( (array) $request->get_query_params(), (array) $request->get_body_params(), is_array( $json ) ? $json : array() );
    }

    











    private function result( string $method, string $path, array $resolved, array $query, \WP_REST_Response $response, ?int $before, array $notes ): array {
        $data   = $response->get_data();
        $result = array(
            'status'    => (int) $response->get_status(),
            'route'     => $path,
            'namespace' => $resolved['namespace'],
        );
        $headers    = array_change_key_case( (array) $response->get_headers(), CASE_LOWER );
        $pagination = self::pagination( $headers, $query );
        if ( null !== $pagination ) {
            $result['pagination'] = $pagination;
        }
        if ( 201 === $result['status'] && isset( $headers['location'] ) && is_string( $headers['location'] ) ) {
            $result['location'] = $headers['location'];
        }
        if ( $this->reports_changes() && null !== $before ) {
            $result['changes_recorded'] = self::changes_so_far() - $before;
            if ( 0 === $result['changes_recorded'] ) {
                $result['changes_note'] = 'The route answered success, but change history recorded no change. Writes to a plugin\'s own tables are only recorded while database capture is on.';
            }
        }
        $result  = array_merge( $result, $notes );
        $hint    = Dedicated_Tools::for_call( $method, $resolved['route'] );
        if ( null !== $hint ) {
            $result['dedicated_tool'] = $hint;
        }
        $encoded = \wp_json_encode( $data );
        $bytes   = is_string( $encoded ) ? strlen( $encoded ) : 0;
        $limit   = (int) \apply_filters( self::MAX_RESPONSE_FILTER, self::MAX_RESPONSE_BYTES );
        if ( $limit > 0 && $bytes > $limit ) {
            if ( ! $this->reports_changes() ) {
                throw new Detailed_Tool_Error(
                    sprintf( 'The response is %d KB, over the %d KB limit, so it was not returned.', (int) ceil( $bytes / 1024 ), (int) floor( $limit / 1024 ) ),
                    'Ask for less: a smaller per_page or page in `query`, or only the fields you need in `fields` (e.g. "id,title").'
                );
            }
            $result['data_omitted'] = array(
                'bytes'  => $bytes,
                'reason' => 'The write completed; its response is over the size limit, so it is not returned. Read the result back with wp_rest_read and `fields`.',
            );
            return $result;
        }
        $result['data'] = $data;
        return $result;
    }

    








    private static function error_for( string $method, string $path, \WP_REST_Response $response ): \Exception {
        $status  = (int) $response->get_status();
        $data    = $response->get_data();
        $code    = is_array( $data ) && isset( $data['code'] ) && is_string( $data['code'] ) ? $data['code'] : '';
        $message = self::rest_error_message_or_empty( $response );
        if ( '' === $message ) {
            return new \RuntimeException( self::rest_status_message( $method, $path, $response ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $short = sprintf( '%s %s answered HTTP %d%s.', $method, $path, $status, '' === $code ? '' : ' (' . $code . ')' );
        $full  = $short . ' ' . $message;
        if ( strlen( $full ) <= Detailed_Tool_Error::MAX_MESSAGE ) {
            return new \RuntimeException( $full ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return new Detailed_Tool_Error( $short, $message );
    }

    






    private static function pagination( array $headers, array $query ): ?array {
        if ( ! isset( $headers['x-wp-total'] ) && ! isset( $headers['x-wp-totalpages'] ) ) {
            return null;
        }
        $total = isset( $headers['x-wp-total'] ) ? (int) $headers['x-wp-total'] : 0;
        $pages = isset( $headers['x-wp-totalpages'] ) ? (int) $headers['x-wp-totalpages'] : 0;
        $page  = isset( $query['page'] ) && is_numeric( $query['page'] ) ? max( 1, (int) $query['page'] ) : 1;
        return array(
            'total'       => $total,
            'total_pages' => $pages,
            'page'        => $page,
            'next_page'   => $page < $pages ? $page + 1 : null,
        );
    }

    





    private static function changes_so_far(): ?int {
        if ( ! class_exists( '\\Easy_MCP_AI\\History\\Change_Context' ) || ! \Easy_MCP_AI\History\Change_Context::is_active() ) {
            return null;
        }
        if ( class_exists( '\\Easy_MCP_AI\\Config' ) && ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_change_log_enabled', true ) ) {
            return null;
        }
        return \Easy_MCP_AI\History\Change_Context::rows_written();
    }

    







    private static function object_argument( array $arguments, string $name ): array {
        if ( ! isset( $arguments[ $name ] ) || array() === $arguments[ $name ] ) {
            return array();
        }
        if ( ! is_array( $arguments[ $name ] ) || array_values( $arguments[ $name ] ) === $arguments[ $name ] ) {
            throw new \InvalidArgumentException( sprintf( '%s must be an object of parameter names and values.', $name ) );
        }
        return $arguments[ $name ];
    }

    





    private static function fields_argument( array $arguments ): ?string {
        $fields = $arguments['fields'] ?? null;
        if ( is_array( $fields ) ) {
            $fields = implode( ',', array_filter( $fields, 'is_string' ) );
        }
        if ( ! is_string( $fields ) || '' === trim( $fields ) ) {
            return null;
        }
        return trim( $fields );
    }

    




    protected static function shared_properties(): array {
        return array(
            'route'  => array(
                'type'        => 'string',
                'description' => 'REST path with its URL parameters filled in, e.g. "/redirection/v1/redirect/12" or "/wc/v3/shipping/zones". No query string; no /wp-json prefix.',
            ),
            'query'  => array(
                'type'        => 'object',
                'description' => 'Query parameters, e.g. {"per_page": 20, "page": 2}. Any keys the route accepts.',
            ),
            'fields' => array(
                'type'        => 'string',
                'description' => 'Comma-separated top-level fields to return (WordPress _fields), e.g. "id,title,status". Keeps large responses small.',
            ),
        );
    }
}
