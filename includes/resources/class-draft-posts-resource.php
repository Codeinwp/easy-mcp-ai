<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Draft_Posts_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://posts/drafts';
    }

    public function get_name() {
        return 'Draft Posts';
    }

    public function get_description() {
        return 'Up to 50 most recently modified draft posts with title, date, and author.';
    }

    public function read() {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'draft',
            'posts_per_page' => 50,
            'orderby'        => 'modified',
            'order'          => 'DESC',
            
            
            
            
            
            
            
            
            'ignore_sticky_posts' => true,
        );

        
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $query = new \WP_Query( $args );

        $posts = array();
        foreach ( $query->posts as $post ) {
            
            
            
            
            
            
            
            
            
            $modified_gmt = $post->post_modified_gmt;
            if ( empty( $modified_gmt ) || '0000-00-00 00:00:00' === $modified_gmt ) {
                $modified_gmt = \get_gmt_from_date( $post->post_modified );
            }

            $posts[] = array(
                'id'       => (int) $post->ID,
                'title'    => get_the_title( $post ),
                'modified' => $modified_gmt,
                'author'   => get_the_author_meta( 'display_name', $post->post_author ),
            );
        }

        \wp_reset_postdata();

        return array( 'drafts' => $posts, 'total' => (int) $query->found_posts );
    }
}
