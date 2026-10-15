<?php
namespace Easy_MCP_AI\Tools\Media;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}



















trait Local_Image {

    








    protected function require_local_image( int $media_id, string $doing, bool $current = false ): array {
        $post = \get_post( $media_id );
        if ( ! $post || 'attachment' !== $post->post_type ) {
            throw new \RuntimeException( sprintf( 'No media item with ID %d exists.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! \current_user_can( 'edit_post', $media_id ) ) {
            throw new \RuntimeException( sprintf( 'Sorry, you are not allowed to edit media %d.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $this->load_image_functions();

        $mime = (string) $post->post_mime_type;
        if ( ! $this->is_image( $media_id ) ) {
            throw new \RuntimeException( sprintf( 'Media %1$d is not an image (%2$s), so it cannot be %3$s.', $media_id, '' !== $mime ? $mime : 'unknown type', $doing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $original = $this->original_image_path( $media_id );
        $path     = $current ? $this->current_image_path( $media_id, $original ) : $original;
        if ( ! is_string( $path ) || '' === $path || ! $this->file_exists( $path ) ) {
            throw new \RuntimeException(
                sprintf( 'The original file of media %1$d is not on this server, so it cannot be %2$s here. A plugin may keep this site\'s media on a CDN or cloud storage; the image has to be restored to the media library first.', $media_id, $doing ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        if ( ! $this->editor_supports( $mime ) ) {
            throw new \RuntimeException( sprintf( 'The image editor on this server (GD or Imagick) cannot process %1$s images, so media %2$d cannot be %3$s.', $mime, $media_id, $doing ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $meta = $this->attachment_metadata( $media_id );

        return array(
            'path'     => $path,
            'original' => is_string( $original ) && '' !== $original ? $original : $path,
            'mime'     => $mime,
            'meta'     => is_array( $meta ) ? $meta : array(),
        );
    }

    











    protected function current_image_path( int $media_id, $original ) {
        $attached = $this->attached_file( $media_id );
        if ( ! is_string( $attached ) || '' === $attached ) {
            return $original;
        }
        if ( ! is_string( $original ) || '' === $original ) {
            return $attached;
        }
        
        
        
        $stem    = pathinfo( $original, PATHINFO_FILENAME );
        $derived = array( $stem, $stem . '-scaled', $stem . '-rotated' );
        return dirname( $attached ) === dirname( $original ) && in_array( pathinfo( $attached, PATHINFO_FILENAME ), $derived, true ) ? $original : $attached;
    }

    









    protected function redact_paths( string $message ): string {
        $basedir  = rtrim( $this->uploads_basedir(), '/' );
        $prefixes = array_filter( array( '' !== $basedir ? $basedir . '/' : '', ABSPATH ) );
        $message  = str_replace( $prefixes, '', $message );
        
        
        
        
        
        
        $patterns = array(
            '~([\'"`])(?:[A-Za-z]:[\\\\/]|/)[^\'"`]*[\\\\/]([^\\\\/\'"`]*)\\1~' => '$1$2$1',
            '~(?<![\\w.:/\\\\])[A-Za-z]:[\\\\/](?:[^\\\\/\\s\'"`]+[\\\\/])+~' => '',
            '~(?<![\\w.:/])/(?:[^/\\s\'"`]+/)+~'                             => '',
        );
        return (string) preg_replace( array_keys( $patterns ), array_values( $patterns ), $message );
    }

    






    protected function folder_label( string $dir ): string {
        $basedir = rtrim( $this->uploads_basedir(), '/' );
        if ( '' !== $basedir && 0 === strpos( rtrim( $dir, '/' ) . '/', $basedir . '/' ) ) {
            $relative = ltrim( substr( rtrim( $dir, '/' ), strlen( $basedir ) ), '/' );
            return '' !== $relative ? 'the folder ' . $relative . ' of the uploads folder' : 'the uploads folder';
        }
        return 'the image\'s folder';
    }

    


    protected function uploads_basedir(): string {
        $uploads = \wp_get_upload_dir();
        return isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
    }

    










    protected function resize_dimensions( int $orig_w, int $orig_h, int $dest_w, int $dest_h, bool $crop ): bool {
        return false !== \image_resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop );
    }

    



    protected function image_size( string $path ) {
        return \wp_getimagesize( $path );
    }

    



    protected function stored_orientation( string $path ): int {
        $read = \wp_read_image_metadata( $path );
        return is_array( $read ) && isset( $read['orientation'] ) ? (int) $read['orientation'] : 0;
    }

    



    protected function attached_file( int $media_id ) {
        return \get_attached_file( $media_id );
    }

    



    protected function is_writable_path( string $path ): bool {
        return (bool) \wp_is_writable( $path );
    }

    





    protected function load_image_functions(): void {
        if ( ! function_exists( 'wp_update_image_subsizes' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    



    protected function is_image( int $media_id ): bool {
        return (bool) \wp_attachment_is_image( $media_id );
    }

    



    protected function original_image_path( int $media_id ) {
        return \wp_get_original_image_path( $media_id );
    }

    



    protected function file_exists( string $path ): bool {
        return file_exists( $path );
    }

    



    protected function editor_supports( string $mime ): bool {
        return (bool) \wp_image_editor_supports( array( 'mime_type' => $mime ) );
    }

    



    protected function attachment_metadata( int $media_id ) {
        return \wp_get_attachment_metadata( $media_id );
    }

    




    protected function update_attachment_metadata( int $media_id, array $meta ): void {
        \wp_update_attachment_metadata( $media_id, $meta );
    }
}
