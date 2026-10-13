<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduled_Posts_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://posts/scheduled';
    }

    public function get_name() {
        return 'Scheduled Posts';
    }

    public function get_description() {
        return 'All posts scheduled for future publication, ordered by publish date ascending.';
    }

    public function read() {
        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'future',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'ASC',
            
            
            
            
            
            'ignore_sticky_posts' => true,
        );

        
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            $args['author'] = get_current_user_id();
        }

        $query = new \WP_Query( $args );

        $posts = array();
        foreach ( $query->posts as $post ) {
            $posts[] = array(
                'id'             => (int) $post->ID,
                'title'          => get_the_title( $post ),
                'scheduled_date' => $post->post_date_gmt,
                'author'         => get_the_author_meta( 'display_name', $post->post_author ),
            );
        }

        \wp_reset_postdata();

        usort( $posts, function ( $a, $b ) {
            return strcmp( $a['scheduled_date'], $b['scheduled_date'] );
        } );

        return array( 'scheduled' => $posts, 'total' => (int) $query->found_posts );
    }
}
