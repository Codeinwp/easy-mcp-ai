<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Upload_Media extends Base_Tool {

    public function get_name() {
        return 'wp_upload_media';
    }

    public function get_description() {
        return 'Uploads a file to the WordPress media library from base64-encoded content. Required: `filename` (e.g. "photo.jpg" — extension determines MIME type), `content_base64` (base64-encoded file bytes, or a data:<mime>;base64,... URI). For a file the client holds as a URL, or hands off as a file reference, use wp_upload_media_from_url instead. Optional: `title`, `alt_text`, `caption`. Accepted types: image/* (jpeg/png/gif/webp), video/* (mp4/mov/avi), audio/* (mp3/wav/ogg), application/pdf, and others allowed by the site\'s upload settings. SVG is NOT accepted on a default WordPress install (no default MIME entry) unless a plugin enables it. File size limit: WordPress server `upload_max_filesize`. Returns { id, title, source_url, mime_type }. Empty-string handling differs by field: an empty `title` is ignored (the attachment keeps its auto-generated title from the filename), while an empty `caption` or `alt_text` is applied as-is, clearing that field.';
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
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'filename'       => array(
                    'type'        => 'string',
                    'description' => 'The filename for the uploaded file (e.g. photo.jpg).',
                ),
                'content_base64' => array(
                    'type'        => 'string',
                    'description' => 'The base64-encoded file content.',
                ),
                'title'          => array(
                    'type'        => 'string',
                    'description' => 'The title for the media item.',
                ),
                'alt_text'       => array(
                    'type'        => 'string',
                    'description' => 'The alt text for the media item.',
                ),
                'caption'        => array(
                    'type'        => 'string',
                    'description' => 'The caption for the media item.',
                ),
            ),
            'required'   => array( 'filename', 'content_base64' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'filename', 'content_base64' ) );

        $filename = sanitize_file_name( $arguments['filename'] );

        
        
        $max_upload_bytes = wp_max_upload_size();
        if ( ! is_string( $arguments['filename'] ) || ! is_string( $arguments['content_base64'] ) ) {
            throw new \InvalidArgumentException( 'filename and content_base64 must be strings.' );
        }
        
        
        
        
        $encoded = $arguments['content_base64'];
        $offset  = 0;
        if ( 0 === strncasecmp( $encoded, 'data:', 5 ) ) {
            $comma = strpos( $encoded, ',' );
            if ( false === $comma || $comma > 128 || ! preg_match( '#^data:[a-z0-9.+-]+/[a-z0-9.+-]+;base64$#i', substr( $encoded, 0, $comma ) ) ) {
                throw new \InvalidArgumentException( 'Use a base64 data URI: data:<mime>;base64,<content>.' );
            }
            $offset = $comma + 1;
        }
        $encoded_length = strlen( $encoded ) - $offset;
        if ( $encoded_length > (int) ceil( $max_upload_bytes * 4 / 3 ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'File too large. Maximum upload size is %s.', \size_format( $max_upload_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $decoded = base64_decode( $offset ? substr( $encoded, $offset ) : $encoded, true );

        if ( false === $decoded ) {
            throw new \InvalidArgumentException( 'Invalid base64-encoded content.' );
        }

        if ( strlen( $decoded ) > $max_upload_bytes ) {
            throw new \InvalidArgumentException(
                sprintf( 'File too large. Maximum upload size is %s.', \size_format( $max_upload_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        $filetype = wp_check_filetype( $filename );

        if ( empty( $filetype['type'] ) ) {
            throw new \InvalidArgumentException( 'File type is not allowed.' );
        }

        
        if ( function_exists( 'finfo_buffer' ) ) {
            $finfo         = finfo_open( FILEINFO_MIME_TYPE );
            $sniffed_mime  = finfo_buffer( $finfo, $decoded );
            finfo_close( $finfo );

            
            
            
            $declared = $filetype['type'];
            if ( $sniffed_mime !== $declared ) {
                
                $safe_mismatches = array(
                    'image/svg+xml' => array( 'text/xml', 'image/svg+xml' ),
                    'text/csv'      => array( 'text/plain' ),
                );
                $allowed_sniffs  = isset( $safe_mismatches[ $declared ] ) ? $safe_mismatches[ $declared ] : array();
                if ( ! in_array( $sniffed_mime, $allowed_sniffs, true ) ) {
                    throw new \InvalidArgumentException(
                        sprintf( 'File content does not match the declared file type (%s).', $declared ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    );
                }
            }
        }

        
        
        
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $tmp_file = wp_tempnam( $filename );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents( $tmp_file, $decoded );

        if ( false === $written ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( 'Failed to write temporary file.' );
        }

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
            'type'     => $filetype['type'],
            'size'     => strlen( $decoded ),
        );

        
        
        
        
        
        $attachment_id = media_handle_sideload( $file_array, 0 );

        if ( \is_wp_error( $attachment_id ) ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( $attachment_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        $meta_update_failed = false;
        $post_update        = array( 'ID' => $attachment_id );
        if ( ! empty( $arguments['title'] ) ) {
            $post_update['post_title'] = sanitize_text_field( $arguments['title'] );
        }
        if ( isset( $arguments['caption'] ) ) {
            $post_update['post_excerpt'] = sanitize_text_field( $arguments['caption'] );
        }
        if ( count( $post_update ) > 1 ) {
            $update_result = wp_update_post( $post_update );
            if ( 0 === $update_result || \is_wp_error( $update_result ) ) {
                $meta_update_failed = true;
            }
        }

        if ( isset( $arguments['alt_text'] ) ) {
            \update_post_meta( $attachment_id, '_wp_attachment_image_alt', \sanitize_text_field( $arguments['alt_text'] ) );
        }

        $attachment = get_post( $attachment_id );

        $result = array(
            'id'         => $attachment_id,
            'title'      => $attachment->post_title,
            'source_url' => wp_get_attachment_url( $attachment_id ),
            'mime_type'  => $attachment->post_mime_type,
        );

        if ( $meta_update_failed ) {
            $result['warning'] = 'Attachment uploaded successfully but metadata (title/caption) could not be updated.';
        }

        return $result;
    }
}
