<?php
namespace Easy_MCP_AI\Tools\Rest;

use Easy_MCP_AI\Tools\Rest_Route_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/../class-rest-route-tool.php';






class Rest_Write extends Rest_Route_Tool {

    
    const METHODS = array( 'POST', 'PUT', 'PATCH' );

    
    const PUBLISHING_STATUSES = array( 'publish', 'future' );

    public function get_name() {
        return 'wp_rest_write';
    }

    public function get_description() {
        return 'Sends a POST, PUT or PATCH request to any WordPress REST route a plugin or WordPress core registers (e.g. create a Redirection redirect, save a WooCommerce shipping zone), in-process, as this key\'s user: the route\'s own permission check decides. Find routes and their arguments with wp_rest_routes. Required: `route`. Optional: `method` (POST default, PUT, PATCH), `body` (JSON object), `query` (object), `fields`. Returns { status, route, namespace, data, changes_recorded?, force_draft?, dedicated_tool? }; `changes_recorded` 0 with a note means change history saw no change. A POST can delete or uninstall on some routes, so treat every call as a real change. While Force draft is on, a new post this call would publish is saved as a draft. The site address (url) and admin email (email) settings cannot be changed here.';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'idempotentHint'  => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        $properties           = self::shared_properties();
        $properties['method'] = array(
            'type'        => 'string',
            'enum'        => self::METHODS,
            'description' => 'HTTP method. POST (default) creates or runs an action; PUT and PATCH update.',
        );
        $properties['body']   = array(
            'type'        => 'object',
            'description' => 'JSON body: the parameters the route accepts, e.g. {"url": "/old", "action_data": {"url": "/new"}}.',
        );
        return array(
            'type'       => 'object',
            'properties' => $properties,
            'required'   => array( 'route' ),
        );
    }

    protected function method_for( array $arguments ): string {
        $method = isset( $arguments['method'] ) && is_string( $arguments['method'] ) && '' !== $arguments['method'] ? strtoupper( $arguments['method'] ) : 'POST';
        if ( ! in_array( $method, self::METHODS, true ) ) {
            throw new \InvalidArgumentException( 'method must be POST, PUT or PATCH. Use wp_rest_read for GET and wp_rest_delete for DELETE.' );
        }
        return $method;
    }

    protected function sends_body(): bool {
        return true;
    }

    protected function reports_changes(): bool {
        return true;
    }

    






    protected function around_dispatch( callable $dispatch, array &$notes ): \WP_REST_Response {
        if ( ! \Easy_MCP_AI\Config::get( 'easy_mcp_ai_force_draft_on_create', false ) ) {
            return $dispatch();
        }
        $drafted = 0;
        $filter  = static function ( $data, $postarr ) use ( &$drafted ) {
            if ( ! is_array( $data ) || ! empty( $postarr['ID'] ) ) {
                return $data;
            }
            $status = isset( $data['post_status'] ) ? (string) $data['post_status'] : '';
            $type   = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
            if ( in_array( $status, self::PUBLISHING_STATUSES, true ) && \is_post_type_viewable( $type ) ) {
                $data['post_status'] = 'draft';
                ++$drafted;
            }
            return $data;
        };
        \add_filter( 'wp_insert_post_data', $filter, PHP_INT_MAX, 2 );
        try {
            return $dispatch();
        } finally {
            \remove_filter( 'wp_insert_post_data', $filter, PHP_INT_MAX );
            if ( $drafted > 0 ) {
                $notes['force_draft'] = sprintf( 'Force draft is on: %d new post(s) this call would have published were saved as drafts.', $drafted );
            }
        }
    }

    public function execute( array $arguments ) {
        return $this->call_route( $arguments );
    }
}
