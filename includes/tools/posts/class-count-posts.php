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
        return 'Returns post counts grouped by status (publish, draft, pending, future, private, trash, auto-draft, inherit) for a given post type. Optional: `post_type` (default \'post\'). Returns { post_type (the `post_type` echoed back), counts } where `counts` is keyed by status with INTEGER values (WP returns numeric strings; this tool casts them). Useful for dashboard-style summaries — `wp_list_posts` only returns one filtered total per call. Every status is counted only for a user who can edit that post type (its own capability, e.g. edit_pages for pages); anyone else gets only the published count of a public type, with a `note` saying so, and is refused a type that is not public.';
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
        $type = get_post_type_object( $post_type );
        if ( ! is_object( $type ) ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown post type: %s', $post_type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $edit_cap = isset( $type->cap->edit_posts ) ? (string) $type->cap->edit_posts : '';
        $can_edit = '' !== $edit_cap && current_user_can( $edit_cap );
        if ( ! $can_edit && ! is_post_type_viewable( $type ) ) {
            $why = '' === $edit_cap
                ? 'it declares no edit capability'
                : sprintf( "counting it needs that type's %s capability", $edit_cap );
            throw new \RuntimeException( sprintf( "You are not allowed to count posts of type '%1\$s': it is not public, and %2\$s.", $post_type, $why ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $counts = $this->normalize_counts( wp_count_posts( $post_type, 'readable' ) );
        if ( $can_edit ) {
            return array(
                'post_type' => $post_type,
                'counts'    => $counts,
            );
        }
        return array(
            'post_type' => $post_type,
            'counts'    => self::public_counts( $counts ),
            'note'      => self::published_only_note( $post_type, $edit_cap ),
        );
    }

    







    public static function published_only_note( string $post_type, string $edit_cap ): string {
        if ( '' === $edit_cap ) {
            return sprintf( "Only published posts are counted: '%s' declares no edit capability, so its other statuses are counted for no one.", $post_type );
        }
        return sprintf( "Only published posts are counted: the other statuses of '%1\$s' are counted only for users with that type's %2\$s capability.", $post_type, $edit_cap );
    }

    






    private static function public_counts( array $counts ): array {
        $public = array();
        foreach ( $counts as $status => $count ) {
            $object = get_post_status_object( (string) $status );
            if ( is_object( $object ) && ! empty( $object->public ) ) {
                $public[ $status ] = $count;
            }
        }
        return $public;
    }
}
