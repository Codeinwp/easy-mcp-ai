<?php
namespace Easy_MCP_AI\Tools\Rest;

use Easy_MCP_AI\Tools\Rest_Route_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Delete extends Rest_Route_Tool {

    public function get_name() {
        return 'wp_rest_delete';
    }

    public function get_description() {
        return 'Sends a DELETE request to any WordPress REST route a plugin or WordPress core registers, in-process, as this key\'s user: the route\'s own permission check decides. Find routes with wp_rest_routes. Required: `route` (e.g. "/wc/v3/shipping/zones/3"). Optional: `query` (object, e.g. {"force": true} — without it, core content routes move items to the trash), `body` (JSON object), `fields`. Returns { status, route, namespace, data, changes_recorded?, dedicated_tool? }; `changes_recorded` 0 with a note means change history saw no change.';
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

    public function is_disabled_by_default() {
        return true;
    }

    public function get_input_schema() {
        $properties         = self::shared_properties();
        $properties['body'] = array(
            'type'        => 'object',
            'description' => 'JSON body, for the few routes that read DELETE parameters from it.',
        );
        return array(
            'type'       => 'object',
            'properties' => $properties,
            'required'   => array( 'route' ),
        );
    }

    protected function method_for( array $arguments ): string {
        return 'DELETE';
    }

    protected function sends_body(): bool {
        return true;
    }

    protected function reports_changes(): bool {
        return true;
    }

    public function execute( array $arguments ) {
        return $this->call_route( $arguments );
    }
}
