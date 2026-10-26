<?php
namespace Easy_MCP_AI\Tools\Appearance;


if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
































final class Customizer_Changeset {

    const POST_TYPE = 'customize_changeset';

    




    public static function is_available(): bool {
        return post_type_exists( self::POST_TYPE );
    }

    







    public static function create( string $setting_id, $value ): int {
        return self::write( self::entry( $setting_id, $value ), 0, 'auto-draft' );
    }

    







    public static function stage( string $setting_id, $value ): string {
        $draft = self::shared_draft();
        $data  = null === $draft ? array() : self::data_of( $draft );
        $id    = self::write( array_merge( $data, self::entry( $setting_id, $value ) ), null === $draft ? 0 : (int) $draft->ID, 'draft' );
        $post  = get_post( $id );
        return null === $post ? '' : (string) $post->post_name;
    }

    













    public static function discard( int $id ): void {
        try {
            wp_delete_post( $id, true );
        } catch ( \Throwable $e ) {
            unset( $e );
        }
    }

    








    public static function preview_url( string $uuid ): string {
        return admin_url( 'customize.php?changeset_uuid=' . $uuid );
    }

    








    public static function share_url( string $uuid ): string {
        return add_query_arg( array( 'customize_changeset_uuid' => $uuid ), home_url( '/' ) );
    }

    






    private static function entry( string $setting_id, $value ): array {
        return array(
            get_stylesheet() . '::' . $setting_id => array(
                'value'             => $value,
                'type'              => 'theme_mod',
                'user_id'           => get_current_user_id(),
                'date_modified_gmt' => current_time( 'mysql', true ),
            ),
        );
    }

    




    private static function shared_draft(): ?object {
        $posts = get_posts(
            array(
                'post_type'        => self::POST_TYPE,
                'post_status'      => 'draft',
                'posts_per_page'   => 1,
                'orderby'          => 'date',
                'order'            => 'DESC',
                'suppress_filters' => true,
            )
        );
        if ( empty( $posts ) || ! is_object( $posts[0] ) ) {
            return null;
        }
        $prefix = get_stylesheet() . '::';
        foreach ( array_keys( self::data_of( $posts[0] ) ) as $key ) {
            if ( false !== strpos( (string) $key, '::' ) && 0 !== strpos( (string) $key, $prefix ) ) {
                return null;
            }
        }
        return $posts[0];
    }

    





    private static function data_of( object $post ): array {
        $data = json_decode( (string) ( $post->post_content ?? '' ), true );
        return is_array( $data ) ? $data : array();
    }

    













    private static function write( array $data, int $id, string $status ): int {
        $json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
        if ( ! is_string( $json ) || ! is_array( json_decode( $json, true ) ) ) {
            
            
            
            throw new \RuntimeException( 'The Customizer changeset was not written: its data cannot be stored as JSON. Nothing was changed.' );
        }
        $post = array( 'post_content' => $json );
        $keep = static function ( $prepared, $postarr, $unsanitized ) {
            if ( is_array( $prepared ) && isset( $prepared['post_type'], $unsanitized['post_content'] ) && self::POST_TYPE === $prepared['post_type'] ) {
                $prepared['post_content'] = $unsanitized['post_content'];
            }
            return $prepared;
        };
        add_filter( 'wp_insert_post_data', $keep, 5, 3 );
        try {
            $result = 0 === $id
                ? wp_insert_post(
                    wp_slash(
                        $post + array(
                            'post_type'   => self::POST_TYPE,
                            'post_status' => $status,
                            'post_name'   => wp_generate_uuid4(),
                            'post_author' => get_current_user_id(),
                        )
                    ),
                    true
                )
                : wp_update_post( wp_slash( $post + array( 'ID' => $id ) ), true );
        } finally {
            remove_filter( 'wp_insert_post_data', $keep, 5 );
        }
        if ( is_wp_error( $result ) || ! $result ) {
            throw new \RuntimeException( 'WordPress could not save the Customizer changeset' . ( is_wp_error( $result ) ? ': ' . $result->get_error_message() : '.' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return (int) $result;
    }
}
