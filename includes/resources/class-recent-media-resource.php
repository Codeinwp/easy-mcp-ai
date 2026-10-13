<?php
namespace Easy_MCP_AI\Resources;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Recent_Media_Resource extends Base_Resource {

    public function get_uri() {
        return 'wp://media/recent';
    }

    public function get_name() {
        return 'Recent Media';
    }

    public function get_description() {
        return 'The 20 most recently uploaded media items with URLs, MIME types, and alt text.';
    }

    public function read() {
        $query = new \WP_Query( array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        $items = array();
        foreach ( $query->posts as $post ) {
            $items[] = array(
                'id'        => (int) $post->ID,
                'title'     => get_the_title( $post ),
                'url'       => wp_get_attachment_url( $post->ID ),
                'mime_type' => $post->post_mime_type,
                'alt_text'  => get_post_meta( $post->ID, '_wp_attachment_image_alt', true ),
                'date'      => $post->post_date_gmt,
            );
        }

        \wp_reset_postdata();

        return array( 'media' => $items );
    }
}
