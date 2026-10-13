<?php
namespace Easy_MCP_AI\Tools\Site;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Post_Statuses extends Base_Tool {

    public function get_name() {
        return 'wp_get_post_statuses';
    }

    public function get_description() {
        return 'Lists all registered WordPress post statuses (built-in and plugin-added). Returns { statuses: [{ slug, name, public, queryable }] }. Built-in statuses: publish, future, draft, pending, private, trash. Plugin statuses (e.g. WooCommerce order statuses like wc-processing) are included if registered. Use this to discover valid values before filtering by status.';
    }

    public function get_category() {
        return 'site';
    }

    public function get_required_capability() {
        return 'edit_posts';
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
            'properties' => new \stdClass(),
        );
    }

    public function execute( array $arguments ) {
        $data = $this->rest_request( 'GET', '/wp/v2/statuses', array( 'context' => 'edit' ) );

        $statuses = array();
        foreach ( $data as $slug => $status ) {
            $statuses[] = array(
                'slug'      => $slug,
                'name'      => $status['name'],
                'public'    => isset( $status['public'] ) ? $status['public'] : false,
                'queryable' => isset( $status['queryable'] ) ? $status['queryable'] : false,
            );
        }

        return array(
            'statuses' => $statuses,
        );
    }
}
