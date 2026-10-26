<?php
namespace Easy_MCP_AI\Resources;

use Easy_MCP_AI\Tools\Posts\Count_Posts;

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
        return 'Counts of posts, pages, comments, users, categories, and tags. Unpublished post and page counts appear only for a user who can edit that type.';
    }

    public function read() {
        $comment_counts = wp_count_comments();

        return array(
            'posts'      => self::type_counts( 'post', array( 'draft', 'pending' ), array( 'trash' ) ),
            'pages'      => self::type_counts( 'page', array( 'draft' ), array() ),
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

    










    private static function type_counts( $post_type, array $in_total, array $outside ) {
        $counts   = wp_count_posts( $post_type );
        $type     = get_post_type_object( $post_type );
        $edit_cap = is_object( $type ) && isset( $type->cap->edit_posts ) ? (string) $type->cap->edit_posts : '';
        $out      = array( 'publish' => (int) $counts->publish );

        if ( '' === $edit_cap || ! current_user_can( $edit_cap ) ) {
            $out['total'] = $out['publish'];
            $out['note']  = Count_Posts::published_only_note( $post_type, $edit_cap );
            return $out;
        }

        $total = $out['publish'];
        foreach ( $in_total as $status ) {
            $out[ $status ] = (int) $counts->$status;
            $total         += $out[ $status ];
        }
        foreach ( $outside as $status ) {
            $out[ $status ] = (int) $counts->$status;
        }
        $out['total'] = $total;
        return $out;
    }

    private static function count_terms( $taxonomy ) {
        $result = wp_count_terms( array( 'taxonomy' => $taxonomy ) );
        return is_wp_error( $result ) ? 0 : (int) $result;
    }
}
