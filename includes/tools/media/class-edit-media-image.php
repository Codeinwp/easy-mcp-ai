<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}













class Edit_Media_Image extends Base_Tool {

    use Local_Image;

    
    const MAX_OPERATIONS = 10;

    
    const ANGLES = array( 90, 180, 270 );

    public function get_name() {
        return 'wp_edit_media_image';
    }

    public function get_description() {
        return 'Edits a library image (crop, rotate, flip, resize) and saves the result as a NEW media attachment named `<original>-edited`; the source attachment and every post using it are left unchanged. Required: `media_id`, `operations` (1-10, applied in order). Each operation: `{type: "crop", x, y, width, height}` in pixels; `{type: "rotate", angle}` (90, 180 or 270, clockwise); `{type: "flip", horizontal, vertical}`; `{type: "resize", width, height, crop}` (one or both sides; `crop: true` fills exactly width x height, cutting from the centre, and both must be within the current image size; WordPress never enlarges). The image is edited as it is now (after an edit saved in wp-admin, the edited version). The first operation works in the `width` x `height` that `wp_get_media` reports; each later one works on the result of the one before. An old photo stored sideways with a camera-orientation tag is edited upright, so its width and height are swapped; a refusal names the size. An operation that would change nothing is refused. Optional `title`, `alt_text`, `caption` for the copy (default: the source\'s). Returns { id, parent_id, title, alt_text, mime_type, source_url, width, height, file, operations }. To use the copy, set it as a featured image or insert it into content; to undo, delete it with `wp_delete_media`. Refused when the original file is not on this server (media kept on a CDN or cloud storage).';
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
            'idempotentHint'  => false, 
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'media_id'   => array(
                    'type'        => 'integer',
                    'description' => 'ID of the image attachment to edit. It is not changed; the result is a new attachment.',
                ),
                'operations' => array(
                    'type'        => 'array',
                    'minItems'    => 1,
                    'maxItems'    => self::MAX_OPERATIONS,
                    'description' => 'Edits applied in order. The first works in the width x height wp_get_media reports; each later one on the previous result.',
                    'items'       => array(
                        'type'       => 'object',
                        'properties' => array(
                            'type'       => array(
                                'type'        => 'string',
                                'enum'        => array( 'crop', 'rotate', 'flip', 'resize' ),
                                'description' => 'The edit.',
                            ),
                            'x'          => array(
                                'type'        => 'integer',
                                'minimum'     => 0,
                                'description' => 'crop: left edge of the area to keep, in pixels from the left.',
                            ),
                            'y'          => array(
                                'type'        => 'integer',
                                'minimum'     => 0,
                                'description' => 'crop: top edge of the area to keep, in pixels from the top.',
                            ),
                            'width'      => array(
                                'type'        => 'integer',
                                'minimum'     => 1,
                                'description' => 'crop: width of the area to keep. resize: the new width (or maximum width when crop is false).',
                            ),
                            'height'     => array(
                                'type'        => 'integer',
                                'minimum'     => 1,
                                'description' => 'crop: height of the area to keep. resize: the new height (or maximum height when crop is false).',
                            ),
                            'angle'      => array(
                                'type'        => 'integer',
                                'description' => 'rotate: 90, 180 or 270 degrees clockwise.',
                            ),
                            'horizontal' => array(
                                'type'        => 'boolean',
                                'description' => 'flip: mirror left to right.',
                            ),
                            'vertical'   => array(
                                'type'        => 'boolean',
                                'description' => 'flip: mirror top to bottom.',
                            ),
                            'crop'       => array(
                                'type'        => 'boolean',
                                'description' => 'resize: true fills exactly width x height (both required, neither larger than the image), cutting the excess from the centre; false (default) fits the image inside them, keeping its proportions.',
                            ),
                        ),
                        'required'   => array( 'type' ),
                    ),
                ),
                'title'      => array(
                    'type'        => 'string',
                    'description' => 'Title of the new attachment. Default: the source\'s title.',
                ),
                'alt_text'   => array(
                    'type'        => 'string',
                    'description' => 'Alt text of the new attachment. Default: the source\'s alt text.',
                ),
                'caption'    => array(
                    'type'        => 'string',
                    'description' => 'Caption of the new attachment. Default: the source\'s caption.',
                ),
            ),
            'required'   => array( 'media_id', 'operations' ),
        );
    }

    
    public function describe( array $arguments ) {
        $id    = isset( $arguments['media_id'] ) ? (int) $arguments['media_id'] : 0;
        $media = $id > 0 ? $this->peek( '/wp/v2/media/' . $id, 'id,title,source_url' ) : null;
        try {
            $operations = $this->parse_operations( $arguments['operations'] ?? null );
        } catch ( \InvalidArgumentException $e ) {
            $operations = array();
        }
        if ( ! $media || empty( $operations ) ) {
            return parent::describe( $arguments );
        }
        $file = isset( $media['source_url'] ) ? basename( (string) $media['source_url'] ) : '';
        return sprintf(
            /* translators: 1: media title, 2: file name, 3: media id, 4: list of edits */
            __( "Create an edited copy of media '%1\$s' (%2\$s, ID %3\$d): %4\$s. The original is not changed.", 'easy-mcp-ai' ),
            self::rest_title( $media ),
            '' !== $file ? $file : __( 'unknown file', 'easy-mcp-ai' ),
            $id,
            implode( '; ', array_map( array( $this, 'summarize_operation' ), $operations ) )
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id', 'operations' ) );

        $media_id   = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $operations = $this->parse_operations( $arguments['operations'] );
        $source     = $this->require_local_image( $media_id, 'edited', true );

        $editor  = $this->open_in_frame( $media_id, $source );
        $applied = array();
        foreach ( $operations as $index => $operation ) {
            $applied[] = $this->apply_operation( $editor, $index, $operation );
        }

        $saved  = $this->save_copy( $editor, $source['original'] );
        $new_id = $this->insert_copy( $media_id, $saved, $arguments );
        $alt    = $this->text_argument( $arguments, 'alt_text', (string) \get_post_meta( $media_id, '_wp_attachment_image_alt', true ) );
        $meta   = $this->finish_copy( $new_id, $media_id, $saved, $source, $alt );

        
        
        $post = \get_post( $new_id );
        return array(
            'id'         => $new_id,
            'parent_id'  => $media_id,
            'title'      => $post ? (string) $post->post_title : '',
            'alt_text'   => (string) \get_post_meta( $new_id, '_wp_attachment_image_alt', true ),
            'mime_type'  => (string) $saved['mime-type'],
            'source_url' => (string) $this->attachment_url( $new_id ),
            'width'      => (int) $meta['width'],
            'height'     => (int) $meta['height'],
            'file'       => basename( (string) $meta['file'] ),
            'operations' => $applied,
        );
    }

    








    protected function parse_operations( $value ): array {
        if ( is_string( $value ) ) {
            $value = $this->parse_json_param( $value, 'operations' );
        }
        if ( ! is_array( $value ) || array() === $value || array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
            throw new \InvalidArgumentException( 'Invalid operations: a list of 1 to ' . self::MAX_OPERATIONS . ' edits is required, e.g. [{"type": "resize", "width": 800}].' );
        }
        if ( count( $value ) > self::MAX_OPERATIONS ) {
            throw new \InvalidArgumentException( sprintf( 'Too many operations: %d given, at most %d per call.', count( $value ), self::MAX_OPERATIONS ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $parsed = array();
        foreach ( $value as $index => $operation ) {
            $parsed[] = $this->parse_operation( $index, $operation );
        }
        return $parsed;
    }

    





    private function parse_operation( int $index, $operation ): array {
        $at   = sprintf( 'operations[%d]', $index );
        $type = is_array( $operation ) && isset( $operation['type'] ) && is_string( $operation['type'] ) ? $operation['type'] : '';
        if ( ! in_array( $type, array( 'crop', 'rotate', 'flip', 'resize' ), true ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %s: "type" must be crop, rotate, flip or resize.', $at ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $at .= ' (' . $type . ')';

        switch ( $type ) {
            case 'crop':
                return array(
                    'type'   => 'crop',
                    'x'      => $this->operation_int( $operation, 'x', $at, 0, true ),
                    'y'      => $this->operation_int( $operation, 'y', $at, 0, true ),
                    'width'  => $this->operation_int( $operation, 'width', $at, 1, true ),
                    'height' => $this->operation_int( $operation, 'height', $at, 1, true ),
                );

            case 'rotate':
                $angle = $this->operation_int( $operation, 'angle', $at, 0, true );
                if ( ! in_array( $angle, self::ANGLES, true ) ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: angle must be 90, 180 or 270 (clockwise), got %2$d.', $at, $angle ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                return array(
                    'type'  => 'rotate',
                    'angle' => $angle,
                );

            case 'flip':
                $horizontal = $this->operation_bool( $operation, 'horizontal', $at );
                $vertical   = $this->operation_bool( $operation, 'vertical', $at );
                if ( ! $horizontal && ! $vertical ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: set horizontal, vertical or both to true; a flip with neither changes nothing.', $at ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                return array(
                    'type'       => 'flip',
                    'horizontal' => $horizontal,
                    'vertical'   => $vertical,
                );

            default:
                $width  = $this->operation_int( $operation, 'width', $at, 1, false );
                $height = $this->operation_int( $operation, 'height', $at, 1, false );
                $crop   = $this->operation_bool( $operation, 'crop', $at );
                if ( null === $width && null === $height ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: give width, height or both.', $at ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( $crop && ( null === $width || null === $height ) ) {
                    throw new \InvalidArgumentException( sprintf( 'Invalid %s: crop: true needs both width and height.', $at ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $resize = array(
                    'type' => 'resize',
                    'crop' => $crop,
                );
                if ( null !== $width ) {
                    $resize['width'] = $width;
                }
                if ( null !== $height ) {
                    $resize['height'] = $height;
                }
                return $resize;
        }
    }

    










    private function operation_int( array $operation, string $key, string $at, int $min, bool $required ): ?int {
        if ( ! array_key_exists( $key, $operation ) || null === $operation[ $key ] ) {
            if ( $required ) {
                throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s is required.', $at, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            return null;
        }
        $value = $operation[ $key ];
        if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
            $value = (int) $value;
        }
        if ( ! is_int( $value ) || $value < $min ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s must be an integer of at least %3$d.', $at, $key, $min ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $value;
    }

    








    private function operation_bool( array $operation, string $key, string $at ): bool {
        if ( ! array_key_exists( $key, $operation ) || null === $operation[ $key ] ) {
            return false;
        }
        if ( ! is_bool( $operation[ $key ] ) ) {
            throw new \InvalidArgumentException( sprintf( 'Invalid %1$s: %2$s must be true or false.', $at, $key ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $operation[ $key ];
    }

    



















    protected function open_in_frame( int $media_id, array $source ) {
        $editor = $this->image_editor( $source['path'] );
        if ( \is_wp_error( $editor ) ) {
            throw new \RuntimeException( sprintf( 'The original file of media %1$d could not be opened: %2$s', $media_id, $this->redact_paths( $editor->get_error_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $rotated = $editor->maybe_exif_rotate();
        if ( \is_wp_error( $rotated ) ) {
            throw new \RuntimeException( sprintf( 'The original file of media %1$d could not be turned upright from its camera orientation: %2$s', $media_id, $this->redact_paths( $rotated->get_error_message() ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $frame_w = isset( $source['meta']['width'] ) ? (int) $source['meta']['width'] : 0;
        $frame_h = isset( $source['meta']['height'] ) ? (int) $source['meta']['height'] : 0;
        $size    = $editor->get_size();
        
        
        
        
        
        
        
        $sideways = ( $size['width'] > $size['height'] && $frame_w < $frame_h ) || ( $size['width'] < $size['height'] && $frame_w > $frame_h );
        if ( $frame_w > 0 && $frame_h > 0 && ! $sideways && ( $size['width'] > $frame_w || $size['height'] > $frame_h ) ) {
            $scaled = $editor->resize( $frame_w, $frame_h, true );
            if ( true !== $scaled ) {
                $message = \is_wp_error( $scaled ) ? $this->redact_paths( $scaled->get_error_message() ) : 'the image editor reported no result';
                throw new \RuntimeException( sprintf( 'The original file of media %1$d could not be brought to its reported size %2$dx%3$d: %4$s', $media_id, $frame_w, $frame_h, $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
        }
        return $editor;
    }

    









    protected function apply_operation( $editor, int $index, array $operation ): array {
        $at   = sprintf( 'operations[%d] (%s)', $index, $operation['type'] );
        $size = $editor->get_size();
        $w    = (int) $size['width'];
        $h    = (int) $size['height'];

        switch ( $operation['type'] ) {
            case 'crop':
                if ( $operation['x'] + $operation['width'] > $w || $operation['y'] + $operation['height'] > $h ) {
                    throw new \RuntimeException( sprintf( '%1$s does not fit the image: the area %2$dx%3$d at %4$d,%5$d extends past its %6$dx%7$d.', $at, $operation['width'], $operation['height'], $operation['x'], $operation['y'], $w, $h ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( 0 === $operation['x'] && 0 === $operation['y'] && $w === $operation['width'] && $h === $operation['height'] ) {
                    throw new \RuntimeException( sprintf( '%1$s changes nothing: the area is the whole %2$dx%3$d image.', $at, $w, $h ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $result = $editor->crop( $operation['x'], $operation['y'], $operation['width'], $operation['height'] );
                break;

            case 'rotate':
                
                $result = $editor->rotate( 0 - $operation['angle'] );
                break;

            case 'flip':
                
                
                
                $result = $editor->flip( $operation['vertical'], $operation['horizontal'] );
                break;

            default:
                $width  = $operation['width'] ?? null;
                $height = $operation['height'] ?? null;
                
                
                
                if ( $operation['crop'] && ( $width > $w || $height > $h ) ) {
                    throw new \RuntimeException( sprintf( '%1$s cannot fill %2$dx%3$d from the %4$dx%5$d image: WordPress never enlarges an image. Ask for at most %4$dx%5$d.', $at, $width, $height, $w, $h ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                if ( ! $this->resize_dimensions( $w, $h, (int) $width, (int) $height, $operation['crop'] ) ) {
                    throw new \RuntimeException( sprintf( '%1$s changes nothing: the image is already %2$dx%3$d, and WordPress never enlarges an image.', $at, $w, $h ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                $result = $editor->resize( $width, $height, $operation['crop'] );
                break;
        }

        if ( true !== $result ) {
            $message = \is_wp_error( $result ) ? $this->redact_paths( $result->get_error_message() ) : 'the image editor reported no result';
            throw new \RuntimeException( sprintf( '%1$s failed: %2$s', $at, $message ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $after = $editor->get_size();
        return $operation + array(
            'width_after'  => (int) $after['width'],
            'height_after' => (int) $after['height'],
        );
    }

    








    protected function save_copy( $editor, string $original_path ): array {
        $uploads = $this->upload_dir();
        if ( ! empty( $uploads['error'] ) ) {
            throw new \RuntimeException( 'The uploads folder is not available: ' . $this->redact_paths( (string) $uploads['error'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $ext  = pathinfo( $original_path, PATHINFO_EXTENSION );
        $name = basename( $original_path, '.' . $ext );
        $name = preg_match( '/-edited(-\d+)?$/', $name ) ? preg_replace( '/-edited(-\d+)?$/', '-edited', $name ) : $name . '-edited';
        $file = $this->unique_filename( $uploads['path'], $name . '.' . $ext );

        
        
        
        $saved = $editor->save( rtrim( $uploads['path'], '/' ) . '/' . $file );
        if ( ! is_array( $saved ) || empty( $saved['path'] ) || ! $this->file_exists( (string) $saved['path'] ) ) {
            $message = \is_wp_error( $saved ) ? ': ' . $this->redact_paths( $saved->get_error_message() ) : '.';
            throw new \RuntimeException( 'The image editor did not save the edited image' . $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $saved['url'] = rtrim( $uploads['url'], '/' ) . '/' . basename( (string) $saved['path'] );
        return $saved;
    }

    









    protected function insert_copy( int $media_id, array $saved, array $arguments ): int {
        $source = \get_post( $media_id );
        $post   = array(
            'post_mime_type' => (string) $saved['mime-type'],
            'guid'           => (string) $saved['url'],
            'post_title'     => $this->text_argument( $arguments, 'title', (string) $source->post_title ),
            'post_excerpt'   => $this->text_argument( $arguments, 'caption', (string) $source->post_excerpt ),
            'post_content'   => (string) $source->post_content,
            'post_status'    => 'inherit',
        );

        $new_id = $this->insert_attachment( $post, (string) $saved['path'] );
        if ( \is_wp_error( $new_id ) || ! $new_id ) {
            $this->delete_file( (string) $saved['path'] );
            $message = \is_wp_error( $new_id ) ? $this->redact_paths( $new_id->get_error_message() ) : 'WordPress returned no ID';
            throw new \RuntimeException( 'The edited image was not added to the media library: ' . $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return (int) $new_id;
    }

    












    protected function finish_copy( int $new_id, int $media_id, array $saved, array $source, string $alt ): array {
        try {
            if ( '' !== $alt ) {
                \update_post_meta( $new_id, '_wp_attachment_image_alt', \wp_slash( $alt ) );
            }

            $meta = $this->generate_metadata( $new_id, (string) $saved['path'] );
            if ( empty( $meta ) || empty( $meta['width'] ) || empty( $meta['height'] ) || empty( $meta['file'] ) ) {
                throw new \RuntimeException( 'WordPress generated no metadata for the edited image' );
            }

            
            
            
            if ( isset( $source['meta']['image_meta'], $meta['image_meta'] ) && is_array( $meta['image_meta'] ) ) {
                foreach ( (array) $source['meta']['image_meta'] as $key => $value ) {
                    if ( empty( $meta['image_meta'][ $key ] ) && ! empty( $value ) ) {
                        $meta['image_meta'][ $key ] = $value;
                    }
                }
            }
            if ( ! empty( $meta['image_meta']['orientation'] ) ) {
                $meta['image_meta']['orientation'] = 1;
            }
            $meta['parent_image'] = array(
                'attachment_id' => $media_id,
                'file'          => \_wp_relative_upload_path( $source['original'] ),
            );

            
            $meta = \apply_filters( 'wp_edited_image_metadata', $meta, $new_id, $media_id );
            $this->update_attachment_metadata( $new_id, $meta );
            return $meta;
        } catch ( \Throwable $e ) {
            $this->delete_attachment( $new_id );
            
            
            
            
            if ( $this->file_exists( (string) $saved['path'] ) ) {
                $this->delete_file( (string) $saved['path'] );
            }
            throw new \RuntimeException( sprintf( '%s; the new attachment %d was removed again.', rtrim( $this->redact_paths( $e->getMessage() ), '.' ), $new_id ), 0, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
    }

    









    private function text_argument( array $arguments, string $key, string $fallback ): string {
        if ( isset( $arguments[ $key ] ) && is_scalar( $arguments[ $key ] ) && '' !== (string) $arguments[ $key ] ) {
            return \sanitize_text_field( (string) $arguments[ $key ] );
        }
        return $fallback;
    }

    





    private function summarize_operation( array $operation ): string {
        switch ( $operation['type'] ) {
            case 'crop':
                return sprintf( 'crop %dx%d at %d,%d', $operation['width'], $operation['height'], $operation['x'], $operation['y'] );
            case 'rotate':
                return sprintf( 'rotate %d° clockwise', $operation['angle'] );
            case 'flip':
                return 'flip ' . implode( ' and ', array_keys( array_filter( array( 'horizontally' => $operation['horizontal'], 'vertically' => $operation['vertical'] ) ) ) );
            default:
                $box = sprintf( '%sx%s', $operation['width'] ?? 'auto', $operation['height'] ?? 'auto' );
                return $operation['crop'] ? 'resize and crop to ' . $box : 'resize to fit ' . $box;
        }
    }

    



    protected function image_editor( string $path ) {
        return \wp_get_image_editor( $path );
    }

    


    protected function upload_dir(): array {
        return \wp_upload_dir();
    }

    




    protected function unique_filename( string $dir, string $filename ): string {
        return \wp_unique_filename( $dir, $filename );
    }

    




    protected function insert_attachment( array $post, string $path ) {
        return \wp_insert_attachment( \wp_slash( $post ), $path, 0, true );
    }

    




    protected function generate_metadata( int $attachment_id, string $path ): array {
        return (array) \wp_generate_attachment_metadata( $attachment_id, $path );
    }

    



    protected function delete_attachment( int $attachment_id ): void {
        \wp_delete_attachment( $attachment_id, true );
    }

    



    protected function delete_file( string $path ): void {
        \wp_delete_file( $path );
    }

    



    protected function attachment_url( int $attachment_id ) {
        return \wp_get_attachment_url( $attachment_id );
    }
}
