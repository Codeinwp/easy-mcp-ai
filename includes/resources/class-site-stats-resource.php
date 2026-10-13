<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Site_Stats_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://site/stats';
    }

    public function get_name() {
        return 'Site Statistics';
    }

    public function get_description() {
        return 'Counts of posts, pages, comments, users, categories, and tags.';
    }

    public function read() {
        $post_counts    = wp_count_posts( 'post' );
        $page_counts    = wp_count_posts( 'page' );
        $comment_counts = wp_count_comments();

        return array(
            'posts'      => array(
                'publish' => (int) $post_counts->publish,
                'draft'   => (int) $post_counts->draft,
                'pending' => (int) $post_counts->pending,
                'trash'   => (int) $post_counts->trash,
                'total'   => (int) $post_counts->publish + (int) $post_counts->draft + (int) $post_counts->pending,
            ),
            'pages'      => array(
                'publish' => (int) $page_counts->publish,
                'draft'   => (int) $page_counts->draft,
                'total'   => (int) $page_counts->publish + (int) $page_counts->draft,
            ),
            'comments'   => array(
                'approved' => (int) $comment_counts->approved,
                'pending'  => (int) $comment_counts->moderated,
                'spam'     => (int) $comment_counts->spam,
                'total'    => (int) $comment_counts->total_comments,
            ),
            'users'      => (int) count_users()['total_users'],
            'categories' => self::count_terms( 'category' ),
            'tags'       => self::count_terms( 'post_tag' ),
        );
    }

    private static function count_terms( $taxonomy ) {
        $result = wp_count_terms( array( 'taxonomy' => $taxonomy ) );
        return is_wp_error( $result ) ? 0 : (int) $result;
    }
}
