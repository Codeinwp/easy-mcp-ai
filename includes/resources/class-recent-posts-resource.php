<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recent_Posts_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://posts/recent';
    }

    public function get_name() {
        return 'Recent Posts';
    }

    public function get_description() {
        return 'The 10 most recently published posts with title, date, excerpt, and link.';
    }

    public function read() {
        $query = new \WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'      => $post->ID,
                'title'   => get_the_title( $post ),
                'date'    => get_the_date( 'c', $post ),
                'excerpt' => wp_strip_all_tags( get_the_excerpt( $post ) ),
                'link'    => get_permalink( $post ),
                'author'  => get_the_author_meta( 'display_name', $post->post_author ),
            );
        }

        \wp_reset_postdata();

        return array( 'posts' => array_slice( $posts, 0, 10 ) );
    }
}
