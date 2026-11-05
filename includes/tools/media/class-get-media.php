<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\MCP\File_Result;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Get_Media extends Base_Tool {

    const MAX_FILE_BYTES = 5 * 1024 * 1024;

    private static $request_bytes = 0;

    public function get_name() {
        return 'wp_get_media';
    }

    public function get_description() {
        return 'Gets a single WordPress media attachment by ID. Returns { id, title, alt_text, caption, description, mime_type, source_url (original file URL), media_type (image/video/audio/file), date, post (ID of the post it\'s attached to, 0 if unattached), terms: { "<taxonomy slug>": [term IDs] } (membership in every attachment taxonomy exposed in the REST API, e.g. media folders such as `mlo-category`; empty when none is registered — resolve the IDs with `wp_list_terms`) }. For image attachments the response also includes top-level `width`, `height`, and `file_size` (in bytes) when those details are available. With `include_file: true` the reply also carries the file itself (the one at `source_url`), base64-encoded in an embedded resource after the JSON, and a `file` field { uri, mime_type, bytes } describing it; a file over the size limit (5 MB unless the site changed it), not on this server or outside the uploads folder is refused. Use it only when you cannot download `source_url` yourself.';
    }

    public function get_category() {
        return 'media';
    }

    public function get_required_capability() {
        return 'edit_posts';
    }

    public function get_annotations() {
        return array(
            'title'           => $this->get_title(),
            'readOnlyHint'    => true,
            'destructiveHint' => false,
            'openWorldHint'   => false,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'media_id'     => array(
                    'type'        => 'integer',
                    'description' => 'The ID of the media item to retrieve.',
                ),
                'include_file' => array(
                    'type'        => 'boolean',
                    'description' => 'Also return the file itself, base64-encoded in an embedded resource. Only when you cannot download source_url, e.g. without network access to this site. Default false.',
                ),
            ),
            'required'   => array( 'media_id' ),
        );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'media_id' ) );

        $media_id = $this->parse_required_id( $arguments['media_id'], 'media_id' );
        $data     = $this->rest_request( 'GET', '/wp/v2/media/' . $media_id, array( 'context' => 'edit' ) );

        $result = array(
            'id'          => $data['id'],
            'title'       => $data['title']['raw'],
            'caption'     => $data['caption']['raw'],
            'description' => $data['description']['raw'],
            'alt_text'    => $data['alt_text'] ?? '',
            'mime_type'   => $data['mime_type'],
            'source_url'  => $data['source_url'],
            'date'        => $data['date'],
            'media_type'  => $data['media_type'],
            'post'        => isset( $data['post'] ) ? (int) $data['post'] : 0,
            'terms'       => $this->item_taxonomy_terms( $data, $this->rest_taxonomies_for( 'attachment' ) ),
        );

        if ( ! empty( $data['media_details']['width'] ) ) {
            $result['width'] = $data['media_details']['width'];
        }

        if ( ! empty( $data['media_details']['height'] ) ) {
            $result['height'] = $data['media_details']['height'];
        }

        if ( ! empty( $data['media_details']['filesize'] ) ) {
            $result['file_size'] = $data['media_details']['filesize'];
        }

        if ( ! empty( $arguments['include_file'] ) && \rest_sanitize_boolean( $arguments['include_file'] ) ) {
            return $this->with_file( $media_id, $result );
        }

        return $result;
    }

    private function with_file( int $media_id, array $result ): File_Result {
        $path = $this->local_file( $media_id );
        $size = (int) filesize( $path );
        $max  = self::max_file_bytes();
        if ( $size > $max ) {
            throw new \RuntimeException( sprintf( 'The file of media %1$d is %2$s, over the %3$s limit of include_file. Download source_url instead.', $media_id, \size_format( $size, 1 ), \size_format( $max ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( self::$request_bytes + $size > $max ) {
            throw new \RuntimeException( sprintf( 'include_file already returned %1$s in this request, and one request carries at most %2$s. Ask for media %3$d in a separate request.', \size_format( self::$request_bytes, 1 ), \size_format( $max ), $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        \wp_raise_memory_limit( 'image' );
        $bytes = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file under the uploads folder.
        if ( false === $bytes ) {
            throw new \RuntimeException( sprintf( 'The file of media %d could not be read on this server. Download source_url instead.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $type = \wp_check_filetype( basename( $path ) )['type'];
        $mime = is_string( $type ) && '' !== $type ? $type : (string) $result['mime_type'];
        $mime = '' !== $mime ? $mime : 'application/octet-stream';

        self::$request_bytes += strlen( $bytes );
        $result['file']       = array(
            'uri'       => $result['source_url'],
            'mime_type' => $mime,
            'bytes'     => strlen( $bytes ),
        );
        return new File_Result( $result, (string) $result['source_url'], $mime, $bytes );
    }

    private function local_file( int $media_id ): string {
        $attached = \get_attached_file( $media_id );
        $real     = is_string( $attached ) && '' !== $attached ? realpath( $attached ) : false;
        if ( false === $real || ! is_file( $real ) ) {
            throw new \RuntimeException( sprintf( 'The file of media %d is not on this server; a plugin may keep media in cloud storage. Download source_url instead.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        $uploads = \wp_get_upload_dir();
        $base    = isset( $uploads['basedir'] ) ? realpath( (string) $uploads['basedir'] ) : false;
        if ( false === $base || 0 !== strpos( $real, rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
            throw new \RuntimeException( sprintf( 'The file of media %d is outside the uploads folder, so it is not returned. Download source_url instead.', $media_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $real;
    }

    public static function reset_request_state(): void {
        self::$request_bytes = 0;
    }

    private static function max_file_bytes(): int {
        $max = \apply_filters( 'easy_mcp_ai_media_file_max_bytes', self::MAX_FILE_BYTES );
        return is_int( $max ) && $max > 0 ? $max : self::MAX_FILE_BYTES;
    }
}
