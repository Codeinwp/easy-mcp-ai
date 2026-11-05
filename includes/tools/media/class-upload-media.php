<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;
use Easy_MCP_AI\Media_Upload\Upload_Route;
use Easy_MCP_AI\Media_Upload\Upload_Sessions;
use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Upload_Media extends Base_Tool {

    const HOW_TO_SEND = 'A small file: content_base64 with its bytes in base64. Any file you can reach from a shell: filename, size (bytes) and sha256 (hex) with no content; the reply holds an upload_url and a curl command that sends the file, after which you call again with filename and upload_id. A file at a public HTTPS URL: wp_upload_media_from_url.';

    const NO_SUCH_UPLOAD = 'No open upload has this upload_id for this connection. Links last 15 minutes. Ask for a new one with filename, size and sha256.';

    private $sessions;

    private $identity;

    public function __construct( ?Upload_Sessions $sessions = null ) {
        $this->sessions = $sessions;
    }

    public function set_request_identity( array $identity ): void {
        $this->identity = $identity;
    }

    public function get_name() {
        return 'wp_upload_media';
    }

    public function get_description() {
        return 'Uploads a file you hold to the WordPress media library, in one of two ways. Small file: `filename` plus `content_base64` (the bytes in base64, or a data:<mime>;base64,... URI); add `size` and `sha256` and the site refuses mistyped or cut-off content instead of storing it. Larger file, or base64 too long to type, when you can run a shell: call with `filename`, `size` (bytes) and `sha256` (hex) and no content. The reply holds a one-time `upload_url` and a ready `command` (curl -T) that sends the raw file; run it within 15 minutes, then call again with `filename` and `upload_id` (plus any title, alt_text, caption) to create the attachment. The link takes only that exact file, from this connection. This site cannot read a local path (/mnt/data/..., C:\\..., file://). For a file at a public URL, or one your client hands off as a file reference, use wp_upload_media_from_url. Optional: `title`, `alt_text`, `caption`. Accepted types: image/* (jpeg/png/gif/webp), video/* (mp4/mov/avi), audio/* (mp3/wav/ogg), application/pdf, and others allowed by the site\'s upload settings. SVG is NOT accepted on a default WordPress install (no default MIME entry) unless a plugin enables it. File size limit: WordPress server `upload_max_filesize`. Returns { id, title, source_url, mime_type }; the link step returns { uploaded: false, upload_id, upload_url, command, expires_at }. Empty-string handling differs by field: an empty `title` is ignored (the attachment keeps its auto-generated title from the filename), while an empty `caption` or `alt_text` is applied as-is, clearing that field.';
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
                    'description' => 'The filename for the uploaded file (e.g. photo.jpg); its extension sets the type. Needed in every call.',
                ),
                'content_base64' => array(
                    'type'        => 'string',
                    'description' => 'The base64-encoded file content, or a data:<mime>;base64,... URI. For a small file; for a larger one, ask for an upload link with size and sha256 instead.',
                ),
                'size'           => array(
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'description' => 'The file\'s length in bytes. With sha256 and no content_base64: asks for a one-time upload link. With content_base64: checked against the decoded bytes.',
                ),
                'sha256'         => array(
                    'type'        => 'string',
                    'description' => 'The file\'s SHA-256 digest, 64 hexadecimal characters (sha256sum or shasum -a 256 prints it). With size and no content_base64: asks for a one-time upload link. With content_base64: checked against the decoded bytes.',
                ),
                'upload_id'      => array(
                    'type'        => 'string',
                    'description' => 'The upload_id from the link step, after the file was sent to its upload_url. Adds that file to the media library.',
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
                    'description' => 'The caption for the media item. HTML is kept as far as WordPress allows it for your account.',
                ),
            ),
            'required'   => array( 'filename' ),
        );
    }

    public function is_read_only_call( array $arguments ) {
        return ! self::given( $arguments, 'content_base64' ) && ! self::given( $arguments, 'upload_id' );
    }

    public function execute( array $arguments ) {
        $this->validate_required( $arguments, array( 'filename' ) );
        if ( ! is_string( $arguments['filename'] ) ) {
            throw new \InvalidArgumentException( 'filename must be a string.' );
        }
        if ( isset( $arguments['caption'] ) ) {
            $arguments['caption'] = self::markup_arg( $arguments, 'caption' );
        }
        if ( self::given( $arguments, 'upload_id' ) ) {
            if ( self::given( $arguments, 'content_base64' ) ) {
                throw new \InvalidArgumentException( 'Send content_base64 or upload_id, not both.' );
            }
            return $this->finish_upload( $arguments );
        }
        if ( self::given( $arguments, 'content_base64' ) ) {
            return $this->upload_base64( $arguments );
        }
        if ( self::given( $arguments, 'size' ) || self::given( $arguments, 'sha256' ) ) {
            $this->validate_required( $arguments, array( 'size', 'sha256' ) );
            return $this->open_link( $arguments );
        }
        throw new Detailed_Tool_Error( 'Missing the file: send content_base64, or size and sha256 to get a one-time upload link.', self::HOW_TO_SEND );
    }

    private function upload_base64( array $arguments ): array {
        if ( ! is_string( $arguments['content_base64'] ) ) {
            throw new \InvalidArgumentException( 'filename and content_base64 must be strings.' );
        }
        $max_upload_bytes = wp_max_upload_size();
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
            throw self::too_large( $max_upload_bytes );
        }

        $decoded = base64_decode( $offset ? substr( $encoded, $offset ) : $encoded, true );

        if ( false === $decoded ) {
            throw self::with_path_hint( 'Invalid base64-encoded content.', $encoded );
        }

        if ( strlen( $decoded ) > $max_upload_bytes ) {
            throw self::too_large( $max_upload_bytes );
        }

        if ( self::given( $arguments, 'size' ) && strlen( $decoded ) !== self::parse_size( $arguments['size'] ) ) {
            throw new \InvalidArgumentException( sprintf( 'content_base64 decodes to %d bytes, not the %d you declared: it was mistyped or cut off. Nothing was uploaded.', strlen( $decoded ), self::parse_size( $arguments['size'] ) ) );
        }
        if ( self::given( $arguments, 'sha256' ) && ! hash_equals( self::parse_sha256( $arguments['sha256'] ), hash( 'sha256', $decoded ) ) ) {
            throw new \InvalidArgumentException( 'content_base64 decodes to bytes whose SHA-256 is not the one you declared: it was mistyped or cut off. Nothing was uploaded.' );
        }

        try {
            list( $filename, $type ) = self::checked_type( sanitize_file_name( $arguments['filename'] ), $decoded );
        } catch ( \InvalidArgumentException $e ) {
            throw self::with_path_hint( $e->getMessage(), $encoded );
        }

        return $this->sideload( $filename, $type, $decoded, $arguments );
    }

    private function open_link( array $arguments ): array {
        $owner = $this->owner( 'no upload link was made' );
        $size  = self::parse_size( $arguments['size'] );
        $hash  = self::parse_sha256( $arguments['sha256'] );
        $max   = wp_max_upload_size();
        if ( $size > $max ) {
            throw self::too_large( $max );
        }
        $sessions = $this->sessions();
        $sessions->sweep();
        if ( $sessions->open_count( $owner ) >= Upload_Sessions::MAX_OPEN ) {
            throw self::too_many_links();
        }
        if ( '' === $sessions->folder() ) {
            throw new \RuntimeException( 'The site cannot take uploads right now: its uploads folder is not writable.' );
        }
        $filename = sanitize_file_name( $arguments['filename'] );
        $id       = Upload_Sessions::new_id();
        $key      = Upload_Sessions::new_key();
        $expires  = $sessions->time() + Upload_Sessions::TTL;
        $created  = $sessions->create(
            array(
                'id'       => $id,
                'key_hash' => hash( 'sha256', $key ),
                'owner'    => $owner,
                'filename' => $filename,
                'size'     => $size,
                'sha256'   => $hash,
                'status'   => Upload_Sessions::STATUS_PENDING,
                'part'     => '',
                'created'  => $sessions->time(),
                'expires'  => $expires,
            )
        );
        if ( ! $created ) {
            throw new \RuntimeException( 'The upload link could not be stored. Try again.' );
        }
        if ( $sessions->open_count( $owner ) > Upload_Sessions::MAX_OPEN ) {
            $mine = $sessions->find( $id );
            if ( null !== $mine ) {
                $sessions->remove( $mine );
            }
            throw self::too_many_links();
        }
        $url = Upload_Route::url( $id, $key );
        return array(
            'uploaded'   => false,
            'upload_id'  => $id,
            'upload_url' => $url,
            'method'     => 'PUT',
            'size'       => $size,
            'sha256'     => $hash,
            'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', $expires ),
            'command'    => 'curl -sS -T ' . self::shell_quote( '' !== $filename ? $filename : 'file' ) . ' ' . self::shell_quote( $url ),
            'next'       => 'Run command where the file is, with the file\'s path in place of its name, before expires_at. It answers "received": true. Then call wp_upload_media with filename and this upload_id to add the file to the media library.',
        );
    }

    private static function too_many_links(): \InvalidArgumentException {
        return new \InvalidArgumentException( sprintf( 'This connection already has %d open upload links. Finish them, or wait until they expire (15 minutes), then ask again.', Upload_Sessions::MAX_OPEN ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
    }

    private function finish_upload( array $arguments ): array {
        $owner    = $this->owner( 'the upload was not finished' );
        $sessions = $this->sessions();
        $id       = is_string( $arguments['upload_id'] ) ? strtolower( trim( $arguments['upload_id'] ) ) : '';
        $session  = $sessions->find( $id );
        if ( null === $session || $owner !== $session['owner'] ) {
            throw new \InvalidArgumentException( self::NO_SUCH_UPLOAD );
        }
        if ( $sessions->is_expired( $session ) ) {
            $sessions->discard( $session );
            throw new \InvalidArgumentException( self::NO_SUCH_UPLOAD );
        }
        if ( Upload_Sessions::STATUS_RECEIVED !== $session['status'] ) {
            throw new \InvalidArgumentException( 'No file has arrived for this upload_id yet. Run the command from the link step (it answers "received": true), then call again.' );
        }
        if ( ( self::given( $arguments, 'size' ) && self::parse_size( $arguments['size'] ) !== (int) $session['size'] )
            || ( self::given( $arguments, 'sha256' ) && self::parse_sha256( $arguments['sha256'] ) !== $session['sha256'] ) ) {
            throw new \InvalidArgumentException( 'This upload_id was made for another file: its size or sha256 differ from the ones you sent.' );
        }
        $bytes = $this->stored_bytes( $session );
        $max   = wp_max_upload_size();
        if ( strlen( $bytes ) > $max ) {
            throw self::too_large( $max );
        }
        list( $filename, $type ) = self::checked_type( sanitize_file_name( $arguments['filename'] ), $bytes );
        if ( ! $sessions->remove( $session ) ) {
            throw new \InvalidArgumentException( 'This upload was already finished by another call.' );
        }
        try {
            return $this->sideload( $filename, $type, $bytes, $arguments );
        } finally {
            $sessions->delete_part( (string) $session['part'] );
        }
    }

    private static function given( array $arguments, string $key ): bool {
        return isset( $arguments[ $key ] ) && '' !== $arguments[ $key ];
    }

    private function owner( string $not_done ): string {
        if ( ! is_array( $this->identity ) ) {
            throw new \RuntimeException( 'No request identity, so ' . $not_done . '.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return Upload_Sessions::credential_key( $this->identity );
    }

    private function sessions(): Upload_Sessions {
        if ( null === $this->sessions ) {
            $this->sessions = new Upload_Sessions();
        }
        return $this->sessions;
    }

    private function stored_bytes( array $session ): string {
        $sessions = $this->sessions();
        $path     = $sessions->part_path( (string) $session['part'] );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file in the folder Upload_Sessions owns.
        $bytes = '' !== $path && is_readable( $path ) ? file_get_contents( $path ) : false;
        if ( false === $bytes || ! hash_equals( (string) $session['sha256'], hash( 'sha256', $bytes ) ) ) {
            $sessions->discard( $session );
            throw new \RuntimeException( 'The received file is gone or changed. Ask for a new upload link and send the file again.' );
        }
        return $bytes;
    }

    private static function parse_size( $value ): int {
        if ( is_string( $value ) && ctype_digit( $value ) ) {
            $value = (int) $value;
        } elseif ( is_float( $value ) && floor( $value ) === $value && $value <= PHP_INT_MAX ) {
            $value = (int) $value;
        }
        if ( ! is_int( $value ) || $value < 1 ) {
            throw new \InvalidArgumentException( 'size must be the file\'s length in bytes: a whole number above 0.' );
        }
        return $value;
    }

    private static function parse_sha256( $value ): string {
        if ( ! is_string( $value ) || ! preg_match( '/^[a-f0-9]{64}\z/i', trim( $value ) ) ) {
            throw new \InvalidArgumentException( 'sha256 must be the file\'s SHA-256 digest: 64 hexadecimal characters, as sha256sum or shasum -a 256 prints it.' );
        }
        return strtolower( trim( $value ) );
    }

    private static function too_large( int $max_upload_bytes ): \InvalidArgumentException {
        return new \InvalidArgumentException(
            sprintf( 'File too large. Maximum upload size is %s.', \size_format( $max_upload_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        );
    }

    private static function with_path_hint( string $message, string $content ): \InvalidArgumentException {
        if ( ! Local_Path::looks_like( $content ) ) {
            return new \InvalidArgumentException( $message );
        }
        return Local_Path::refusal( $message . ' If content_base64 holds a file path: this site cannot read files on your side.' );
    }

    private static function shell_quote( string $value ): string {
        return "'" . str_replace( "'", "'\\''", $value ) . "'";
    }

    private static function checked_type( string $filename, string $bytes ): array {
        $filetype = wp_check_filetype( $filename );

        if ( empty( $filetype['type'] ) ) {
            $filename = self::renamed_image( $filename, $bytes );
            $filetype = wp_check_filetype( $filename );
        }
        if ( empty( $filetype['type'] ) ) {
            throw new \InvalidArgumentException( 'File type is not allowed.' );
        }

        if ( function_exists( 'finfo_buffer' ) ) {
            $finfo         = finfo_open( FILEINFO_MIME_TYPE );
            $sniffed_mime  = finfo_buffer( $finfo, $bytes );

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

        return array( $filename, $filetype['type'] );
    }

    private static function renamed_image( string $filename, string $bytes ): string {
        $probe = tempnam( function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir(), 'emai' );
        if ( false === $probe ) {
            return $filename;
        }
        $renamed = null;
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        if ( false !== file_put_contents( $probe, $bytes ) ) {
            $renamed = self::image_filename_from_bytes( $filename, $probe );
        }
        \wp_delete_file( $probe );
        return null !== $renamed ? $renamed : $filename;
    }

    protected function sideload( string $filename, string $type, string $bytes, array $arguments ): array {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $tmp_file = wp_tempnam( $filename );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $written = file_put_contents( $tmp_file, $bytes );

        if ( false === $written ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( 'Failed to write temporary file.' );
        }

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
            'type'     => $type,
            'size'     => strlen( $bytes ),
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
            $post_update['post_excerpt'] = wp_slash( (string) $arguments['caption'] );
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
