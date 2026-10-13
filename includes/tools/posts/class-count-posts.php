<?php
namespace Easy_MCP_AI\Tools\Posts;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Count_Posts extends Base_Tool {

    public function get_name() {
        return 'wp_count_posts';
    }

    public function get_description() {
        return 'Returns post counts grouped by status (publish, draft, pending, future, private, trash, auto-draft, inherit) for a given post type. Optional: `post_type` (default \'post\'). Returns { post_type (the `post_type` echoed back), counts } where `counts` is keyed by status with INTEGER values (WP returns numeric strings; this tool casts them). Useful for dashboard-style summaries — `wp_list_posts` only returns one filtered total per call.';
    }

    public function get_category() {
        return 'posts';
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
            'properties' => array(
                'post_type' => array(
                    'type'        => 'string',
                    'description' => 'Post type slug. Defaults to "post".',
                ),
            ),
        );
    }

    public function execute( array $arguments ) {
        $post_type = isset( $arguments['post_type'] ) && '' !== $arguments['post_type']
            ? sanitize_key( (string) $arguments['post_type'] )
            : 'post';
        if ( ! post_type_exists( $post_type ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown post type: %s', $post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $counts = wp_count_posts( $post_type, 'readable' );
        return array(
            'post_type' => $post_type,
            'counts'    => $this->normalize_counts( $counts ),
        );
    }
}
