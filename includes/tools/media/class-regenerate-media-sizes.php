<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/trait-local-image.php';



















class Regenerate_Media_Sizes extends Base_Tool {

    use Local_Image;

    public function get_name() {
        return 'wp_regenerate_media_sizes';
    }

    public function get_description() {
        return 'Regenerates the image sizes WordPress makes for one image attachment (thumbnail, medium, large and every size the theme or plugins register), from the image as it is now (the original upload, or the edited file after an edit saved in wp-admin). Required: `media_id`. Optional: `mode` — "missing" (default) creates only the sizes the image lacks, e.g. after a theme added a size; "all" rebuilds every size, e.g. after a size\'s dimensions changed in Settings > Media. Optional `sizes`: names of registered sizes to limit the call to. Old files are kept on disk, so posts that link an old size keep working; the full-size file is never rebuilt. Returns { id, mode, changed, created, regenerated, failed, sizes: { name: { width, height, file } } }, plus `failed_reason` when a size could not be written (e.g. a file the web server cannot overwrite); `changed: false` with a `reason` when there was nothing to do. An image with no metadata at all (an upload that did not finish processing) is rebuilt whole, full-size copy included, and answers `metadata_rebuilt: true`; such a call cannot be limited with `sizes`. One attachment per call. Refused when the original file is not on this server (media kept on a CDN or cloud storage).';
    }

    public function get_category() {
        return 'media';
    }

    public function get_required_capability() {
        return 'upload_files';
    }

    


    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => false,
            'destructiveHint' => false, 
            'idempotentHint'  => true,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'media_id' => array(
                    'type'        => 'integer',
                    'description' => 'ID of the image attachment.',
                ),
                'mode'     => array(
                    'type'        => 'string',
                    'enum'        => array( 'missing', 'all' ),
                    'default'     => 'missing',
                    'description' => '"missing" creates only absent sizes; "all" rebuilds every size from the original.',
                ),
                'sizes'    => array(
                    'type'        => 'array',
                    'items'       => array( 'type' => 'string' ),
                    'description' => 'Registered size names to limit the call to, e.g. ["thumbnail", "medium"]. Default: every registered size.',
                ),
            ),
            'required'   => array( 'media_id' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id    = isset( $arguments['media_id'] ) ? (int) $arguments['media_id'] : 0;
        $media = $id > 0 ? $this->peek( '/wp/v2/media/' . $id, 'id,title,source_url' ) : null;
        if ( ! $media ) {
            return parent::describe( $arguments );
        }
        $all   = isset( $arguments['mode'] ) && 'all' === $arguments['mode'];
        $named = isset( $arguments['sizes'] ) && is_array( $arguments['sizes'] ) && array() !== $arguments['sizes'];
        $title = self::rest_title( $media );
        $file  = isset( $media['source_url'] ) ? basename( (string) $media['source_url'] ) : '';
        $file  = '' !== $file ? $file : __( 'unknown file', 'easy-mcp-ai' );

        
        
        if ( ! $named ) {
            return sprintf(
                $all
                    /* translators: 1: media title, 2: file name, 3: media id */
                    ? __( "Rebuild every image size of media '%1\$s' (%2\$s, ID %3\$d).", 'easy-mcp-ai' )
                    /* translators: 1: media title, 2: file name, 3: media id */
                    : __( "Create the missing image sizes of media '%1\$s' (%2\$s, ID %3\$d).", 'easy-mcp-ai' ),
                $title,
                $file,
                $id
            );
        }
        return sprintf(
            $all
                /* translators: 1: comma-separated image size names, 2: media title, 3: file name, 4: media id */
                ? __( "Rebuild the image sizes %1\$s of media '%2\$s' (%3\$s, ID %4\$d).", 'easy-mcp-ai' )
                /* translators: 1: comma-separated image size names, 2: media title, 3: file name, 4: media id */
                : __( "Create the missing image sizes among %1\$s of media '%2\$s' (%3\$s, ID %4\$d).", 'easy-mcp-ai' ),
            implode( ', ', array_map( 'strval', $arguments['sizes'] ) ),
            $title,
            $file,
            $id
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id' ) );

        $media_id = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $mode     = isset( $arguments['mode'] ) && '' !== $arguments['mode'] ? $arguments['mode'] : 'missing';
        $this->refuse_unless_one_of( 'mode', $mode, array( 'missing', 'all' ), $mode, '"missing" or "all"' );

        $source = $this->require_local_image( $media_id, 'regenerated', true );
        $sizes  = $this->parse_sizes( $arguments['sizes'] ?? null );
        $named  = ! empty( $arguments['sizes'] );

        return $this->reading_current_image(
            $media_id,
            $source,
            function () use ( $media_id, $mode, $sizes, $named, $source ) {
                return empty( $source['meta']['width'] )
                    ? $this->rebuild_metadata( $media_id, $mode, $named, $source )
                    : $this->regenerate( $media_id, $mode, $sizes, $source );
            }
        );
    }

    











    private function reading_current_image( int $media_id, array $source, callable $run ): array {
        if ( $source['path'] === $source['original'] ) {
            return $run();
        }
        $current = static function ( $path, $attachment_id ) use ( $media_id, $source ) {
            return (int) $attachment_id === $media_id ? $source['path'] : $path;
        };
        \add_filter( 'wp_get_original_image_path', $current, PHP_INT_MAX, 2 );
        try {
            return $run();
        } finally {
            \remove_filter( 'wp_get_original_image_path', $current, PHP_INT_MAX );
        }
    }

    









    private function regenerate( int $media_id, string $mode, array $sizes, array $source ): array {
        $before = isset( $source['meta']['sizes'] ) && is_array( $source['meta']['sizes'] ) ? $source['meta']['sizes'] : array();

        
        
        
        $rebuildable = 'all' === $mode ? array_keys( array_intersect_key( $before, array_flip( $sizes ) ) ) : array();
        $wanted      = array_intersect_key( $this->missing_subsizes( $media_id, $source, $rebuildable ), array_flip( $sizes ) );
        $targets     = array_keys( $wanted );
        if ( array() === $targets ) {
            return array(
                'id'          => $media_id,
                'mode'        => $mode,
                'changed'     => false,
                'reason'      => 'all' === $mode ? 'None of the requested sizes fits this image: WordPress makes no size larger than the original.' : 'Nothing to create: every requested size that fits this image already exists, and WordPress makes no size larger than the original.',
                'created'     => array(),
                'regenerated' => array(),
                'failed'      => array(),
                'sizes'       => self::size_summary( $before ),
            );
        }

        
        
        
        $forgotten = array_intersect_key( $before, $wanted );
        if ( array() !== $forgotten ) {
            $meta          = $source['meta'];
            $meta['sizes'] = array_diff_key( $before, $forgotten );
            $this->update_attachment_metadata( $media_id, $meta );
        }

        
        
        $result = null;
        $thrown = null;
        try {
            $result = $this->update_subsizes( $media_id, $wanted );
        } catch ( \Throwable $e ) {
            $thrown = $e;
        }
        $made   = array_values( array_intersect( $targets, array_keys( $this->current_sizes( $media_id ) ) ) );
        $failed = array_values( array_diff( $targets, $made ) );
        $this->restore_forgotten( $media_id, array_diff_key( $forgotten, array_flip( $made ) ) );

        if ( null !== $thrown ) {
            throw new \RuntimeException( sprintf( 'The sizes of media %1$d were not regenerated: %2$s', $media_id, $this->redact_paths( $thrown->getMessage() ) ), 0, $thrown ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( \is_wp_error( $result ) ) {
            throw new \RuntimeException( sprintf( 'The sizes of media %1$d were not regenerated: %2$s', $media_id, $this->redact_paths( $result->get_error_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( array() === $made ) {
            throw new \RuntimeException( sprintf( 'The image editor produced none of the sizes of media %1$d: %2$s.%3$s', $media_id, implode( ', ', $failed ), $this->failure_cause( $source['path'], $failed, $forgotten ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $reply = array(
            'id'          => $media_id,
            'mode'        => $mode,
            'changed'     => true,
            'created'     => array_values( array_diff( $made, array_keys( $before ) ) ),
            'regenerated' => array_values( array_intersect( $made, array_keys( $before ) ) ),
            'failed'      => $failed,
            'sizes'       => self::size_summary( $this->current_sizes( $media_id ) ),
        );
        if ( array() !== $failed ) {
            $reply['failed_reason'] = trim( $this->failure_cause( $source['path'], $failed, $forgotten ) );
        }
        return $reply;
    }

    
















    private function rebuild_metadata( int $media_id, string $mode, bool $named, array $source ): array {
        if ( $named ) {
            throw new \InvalidArgumentException( sprintf( 'Media %d has no image metadata (its upload did not finish processing), so WordPress rebuilds all of it, every size and the full-size copy included. Call again without `sizes`.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        try {
            $result = $this->update_subsizes( $media_id, $this->registered_subsizes() );
        } catch ( \Throwable $e ) {
            throw new \RuntimeException( sprintf( 'The metadata of media %1$d could not be rebuilt: %2$s', $media_id, $this->redact_paths( $e->getMessage() ) ), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $meta = $this->attachment_metadata( $media_id );
        if ( \is_wp_error( $result ) || ! is_array( $meta ) || empty( $meta['width'] ) ) {
            $message = \is_wp_error( $result ) ? $this->redact_paths( $result->get_error_message() ) : 'WordPress generated no metadata';
            throw new \RuntimeException( sprintf( 'The metadata of media %1$d could not be rebuilt: %2$s', $media_id, $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $made   = isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();
        $failed = array_keys( $this->missing_subsizes( $media_id, $source ) );
        if ( array() === $made && array() !== $failed ) {
            throw new \RuntimeException( sprintf( 'The metadata of media %1$d was rebuilt, but the image editor produced none of its sizes: %2$s.%3$s', $media_id, implode( ', ', $failed ), $this->failure_cause( $source['path'], $failed, array() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $reply = array(
            'id'               => $media_id,
            'mode'             => $mode,
            'changed'          => true,
            'metadata_rebuilt' => true,
            'file'             => basename( (string) ( $meta['file'] ?? '' ) ),
            'created'          => array_keys( $made ),
            'regenerated'      => array(),
            'failed'           => $failed,
            'sizes'            => self::size_summary( $made ),
        );
        if ( array() !== $failed ) {
            $reply['failed_reason'] = trim( $this->failure_cause( $source['path'], $failed, array() ) );
        }
        return $reply;
    }

    










    private function failure_cause( string $original, array $failed, array $forgotten ): string {
        $dir = dirname( $original );
        if ( ! $this->is_writable_path( $dir ) ) {
            return sprintf( ' %s is not writable by the web server, so WordPress cannot write new size files there.', ucfirst( $this->folder_label( $dir ) ) );
        }
        $locked = array();
        foreach ( $failed as $name ) {
            $file = isset( $forgotten[ $name ]['file'] ) ? $dir . '/' . $forgotten[ $name ]['file'] : '';
            if ( '' !== $file && $this->file_exists( $file ) && ! $this->is_writable_path( $file ) ) {
                $locked[] = basename( $file );
            }
        }
        if ( array() !== $locked ) {
            return sprintf( ' These existing size files are not writable by the web server, so WordPress could not replace them: %s. Their owner or permissions have to be fixed on the server.', implode( ', ', $locked ) );
        }
        return ' WordPress\'s image editor does not report why: core discards its error.';
    }

    







    protected function parse_sizes( $value ): array {
        $registered = array_keys( $this->registered_subsizes() );
        if ( null === $value || '' === $value || array() === $value ) {
            return $registered;
        }
        if ( is_string( $value ) ) {
            $value = array_map( 'trim', explode( ',', $value ) );
        }
        if ( ! is_array( $value ) ) {
            throw new \InvalidArgumentException( 'Invalid sizes: give a list of image size names, e.g. ["thumbnail", "medium"].' );
        }
        $names   = array_values( array_unique( array_map( 'strval', $value ) ) );
        $unknown = array_diff( $names, $registered );
        if ( array() !== $unknown ) {
            throw new \InvalidArgumentException( sprintf( 'Unknown image size: %1$s. Registered sizes: %2$s.', implode( ', ', $unknown ), implode( ', ', $registered ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $names;
    }

    







    private function restore_forgotten( int $media_id, array $forgotten ): void {
        if ( array() === $forgotten ) {
            return;
        }
        $meta = $this->attachment_metadata( $media_id );
        $meta = is_array( $meta ) ? $meta : array();
        $meta['sizes'] = array_merge( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array(), $forgotten );
        $this->update_attachment_metadata( $media_id, $meta );
    }

    



    private function current_sizes( int $media_id ): array {
        $meta = $this->attachment_metadata( $media_id );
        return is_array( $meta ) && isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array();
    }

    






    private static function size_summary( array $sizes ): \stdClass {
        $summary = array();
        foreach ( $sizes as $name => $entry ) {
            $summary[ (string) $name ] = array(
                'width'  => (int) ( $entry['width'] ?? 0 ),
                'height' => (int) ( $entry['height'] ?? 0 ),
                'file'   => (string) ( $entry['file'] ?? '' ),
            );
        }
        return (object) $summary;
    }

    


    protected function registered_subsizes(): array {
        $this->load_image_functions();
        return \wp_get_registered_image_subsizes();
    }

    















    protected function missing_subsizes( int $media_id, array $source, array $without = array() ): array {
        $upright = $this->upright_size_of_rotated( $source['path'] );
        if ( null === $upright ) {
            return $this->core_missing_without( $media_id, $without );
        }
        $fits = array();
        foreach ( $this->registered_subsizes() as $name => $size ) {
            if ( $this->resize_dimensions( $upright[0], $upright[1], (int) $size['width'], (int) $size['height'], ! empty( $size['crop'] ) ) ) {
                $fits[ $name ] = $size;
            }
        }
        $meta = $this->attachment_metadata( $media_id );
        $meta = is_array( $meta ) ? $meta : array();
        $have = array_diff_key( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array(), array_flip( $without ) );
        if ( isset( $meta['sizes'] ) ) {
            $meta['sizes'] = $have;
        }
        
        return (array) \apply_filters( 'wp_get_missing_image_subsizes', array_diff_key( $fits, $have ), $meta, $media_id );
    }

    








    private function core_missing_without( int $media_id, array $without ): array {
        if ( array() === $without ) {
            return $this->core_missing_subsizes( $media_id );
        }
        $hide = static function ( $meta, $attachment_id ) use ( $media_id, $without ) {
            if ( (int) $attachment_id === $media_id && is_array( $meta ) && isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
                $meta['sizes'] = array_diff_key( $meta['sizes'], array_flip( $without ) );
            }
            return $meta;
        };
        \add_filter( 'wp_get_attachment_metadata', $hide, PHP_INT_MAX, 2 );
        try {
            return $this->core_missing_subsizes( $media_id );
        } finally {
            \remove_filter( 'wp_get_attachment_metadata', $hide, PHP_INT_MAX );
        }
    }

    









    private function upright_size_of_rotated( string $path ): ?array {
        
        $stored_as = $this->stored_orientation( $path );
        
        $orientation = (int) \apply_filters( 'wp_image_maybe_exif_rotate', 0 === $stored_as ? null : $stored_as, $path );
        if ( $orientation < 5 || $orientation > 8 ) {
            return null;
        }
        $stored = $this->image_size( $path );
        return is_array( $stored ) ? array( (int) $stored[1], (int) $stored[0] ) : null;
    }

    



    protected function core_missing_subsizes( int $media_id ): array {
        return (array) \wp_get_missing_image_subsizes( $media_id );
    }

    









    protected function update_subsizes( int $media_id, array $wanted ) {
        $only = static function () use ( $wanted ) {
            return $wanted;
        };
        \add_filter( 'wp_get_missing_image_subsizes', $only, PHP_INT_MAX );
        try {
            return $this->core_update_subsizes( $media_id );
        } finally {
            \remove_filter( 'wp_get_missing_image_subsizes', $only, PHP_INT_MAX );
        }
    }

    



    protected function core_update_subsizes( int $media_id ) {
        return \wp_update_image_subsizes( $media_id );
    }
}
