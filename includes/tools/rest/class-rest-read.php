<?php
namespace Easy_MCP_AI\Tools\Rest;

use Easy_MCP_AI\Tools\Rest_Route_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}




class Rest_Read extends Rest_Route_Tool {

    public function get_name() {
        return 'wp_rest_read';
    }

    public function get_description() {
        return 'Sends a GET request to any WordPress REST route a plugin or WordPress core registers (e.g. Contact Form 7, Redirection, WooCommerce settings, Site Kit), in-process, as this key\'s user: the route\'s own permission check decides. Find routes and their arguments with wp_rest_routes. Required: `route` (path with URL parameters filled in, e.g. "/redirection/v1/redirect"). Optional: `query` (object of query parameters), `fields` (comma-separated top-level fields). Returns { status, route, namespace, data, pagination? (total, total_pages, page, next_page), dedicated_tool? }. When `dedicated_tool` is present, prefer that tool for this kind of request. A route that answers an error is reported as an error with its status, code and message. Responses over 100 KB are refused: narrow them with per_page or fields.';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => self::shared_properties(),
            'required'   => array( 'route' ),
        );
    }

    protected function method_for( array $arguments ): string {
        return 'GET';
    }

    public function execute( array $arguments ) {
        return $this->call_route( $arguments );
    }
}
