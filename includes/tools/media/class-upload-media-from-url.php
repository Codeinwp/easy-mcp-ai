<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\Tools\Base_Tool;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Upload_Media_From_Url extends Base_Tool {

    const HTTP_TIMEOUT_SECONDS = 30;

    public function get_name() {
        return 'wp_upload_media_from_url';
    }

    public function get_description() {
        return 'Downloads a file from a URL and imports it into the WordPress media library. Supply exactly one source: `url` (a public HTTPS URL — HTTPS only by default, local/private IPs blocked via DNS-resolved SSRF check; site owners can allow http via the easy_mcp_ai_allow_http_media_url filter) or `file` (a client file handoff — a generated or user-attached file the client references as an object with download_url and file_id, which the client fills in). Local paths and bare file IDs cannot be fetched. Optional: `filename` (override; for a file handoff the resolved name must carry an allowed extension such as image.png), `title`, `alt_text`, `caption`, `description` (the attachment\'s Description field, stored as post_content), `slug` (the attachment\'s URL slug; WordPress adds a suffix when it is taken), `post_id` (attach to a parent post), `featured_image` (true, with `post_id`: also make the imported image that post\'s featured image; needs edit rights on the post, a post type with featured images, and an image file — otherwise nothing is imported). Size limit: site `wp_max_upload_size()`. Returns { id, source_url, mime_type, title, alt_text, caption, file_size }, the text fields as stored, plus `slug` (as stored) when `slug` was sent and `featured_image` (true when it was set) when that was sent; use id as featured_media or source_url in post content. Avoids the ~33% base64 overhead of wp_upload_media.';
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
            'openWorldHint'   => true,
        );
    }

    public function get_input_schema() {
        return array(
            'type'       => 'object',
            'properties' => array(
                'url'      => array(
                    'type'        => 'string',
                    'description' => 'Public HTTPS URL to the file (http allowed only if the site enables the easy_mcp_ai_allow_http_media_url filter). Private/internal IPs are rejected. Supply either url or file, never both.',
                ),
                'file'     => array(
                    'type'        => 'object',
                    'description' => 'Client file handoff (a generated or user-attached file the client references). The client fills this in; download_url is a temporary HTTPS URL the site fetches. Supply either file or url, never both.',
                    'properties'  => array(
                        'download_url' => array( 'type' => 'string' ),
                        'file_id'      => array( 'type' => 'string' ),
                        'mime_type'    => array( 'type' => 'string' ),
                        'file_name'    => array( 'type' => 'string' ),
                    ),
                    'required'    => array( 'download_url', 'file_id' ),
                ),
                'filename' => array(
                    'type'        => 'string',
                    'description' => 'Optional filename override. Resolution order: this value, then file.file_name, then the URL basename. For a file handoff the resolved name must carry an allowed extension (signed download URLs often have none), unless file.mime_type names an image type the site allows. An image whose name has an unknown extension (.jfif) is stored under the type its bytes hold.',
                ),
                'title'    => array(
                    'type'        => 'string',
                    'description' => 'Optional attachment title.',
                ),
                'alt_text' => array(
                    'type'        => 'string',
                    'description' => 'Optional alt text for accessibility.',
                ),
                'caption'  => array(
                    'type'        => 'string',
                    'description' => 'Optional caption (stored as post_excerpt).',
                ),
                'post_id'        => array(
                    'type'        => 'integer',
                    'description' => 'Optional parent post ID to attach the media to.',
                ),
                'description'    => array(
                    'type'        => 'string',
                    'description' => 'Optional attachment description (the Description field, stored as post_content). WordPress filters HTML as it does for this user in the media editor.',
                ),
                'slug'           => array(
                    'type'        => 'string',
                    'description' => 'Optional attachment slug (post_name). WordPress sanitizes it and adds a suffix such as -2 when it is taken; the stored slug is returned.',
                ),
                'featured_image' => array(
                    'type'        => 'boolean',
                    'description' => 'With post_id: also set the imported image as that post\'s featured image. Default false. Refused, before anything is fetched, without post_id, for a post you cannot edit or whose type has no featured images; refused before import when the file is not an image.',
                ),
            ),
        );
    }

    public function get_definition() {
        $definition          = parent::get_definition();
        $definition['_meta'] = array( 'openai/fileParams' => array( 'file' ) );
        return $definition;
    }

    public function get_redacted_arguments() {
        return array( 'url' );
    }

    public function execute( array $arguments ) {
        $source = $this->resolve_source( $arguments );

        self::refuse_non_text_description( $arguments );
        $slug     = $this->parse_slug( $arguments );
        $featured = $this->featured_image_target( $arguments );

        $url      = $this->validate_remote_url( $source['url'] );
        $filename = $this->resolve_filename( $arguments, $source['filename_hint'], $source['handoff'], $url, $source['mime_hint'] );

        $max_bytes = wp_max_upload_size();
        $this->refuse_declared_oversize( $url, $max_bytes );

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp_file = wp_tempnam( $filename );
        if ( ! $tmp_file ) {
            throw new \RuntimeException( 'Could not create temporary file.' );
        }

        $body_len = $this->download_to( $url, $tmp_file, $max_bytes );
        $checked  = self::checked_file( $tmp_file, $filename, $featured );

        $file_array = array(
            'name'     => $checked['name'],
            'tmp_name' => $tmp_file,
            'type'     => $checked['type'],
            'size'     => $body_len,
        );

        $parent_post_id = self::attachable_parent( $arguments, $tmp_file );

        $attachment_id = media_handle_sideload( $file_array, $parent_post_id );
        if ( \is_wp_error( $attachment_id ) ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( $attachment_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        self::apply_attachment_fields( $attachment_id, $arguments, $slug );
        if ( $featured > 0 ) {
            self::set_featured_image( $featured, (int) $attachment_id );
        }

        $attachment = get_post( $attachment_id );

        $result = array(
            'id'         => (int) $attachment_id,
            'source_url' => wp_get_attachment_url( $attachment_id ),
            'mime_type'  => $attachment ? (string) $attachment->post_mime_type : (string) $checked['type'],
            'title'      => $attachment ? (string) $attachment->post_title : '',
            'alt_text'   => (string) get_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', true ),
            'caption'    => $attachment ? (string) $attachment->post_excerpt : '',
            'file_size'  => $body_len,
        );
        if ( null !== $slug ) {
            $result['slug'] = $attachment ? (string) $attachment->post_name : '';
        }
        if ( array_key_exists( 'featured_image', $arguments ) ) {
            $result['featured_image'] = $featured > 0;
        }
        return $result;
    }

    private function resolve_source( array $arguments ): array {
        $has_url  = array_key_exists( 'url', $arguments );
        $has_file = array_key_exists( 'file', $arguments );
        if ( $has_url === $has_file ) {
            throw new \InvalidArgumentException( 'Supply exactly one source: url, or file (a client file handoff carrying download_url and file_id).' );
        }

        if ( $has_file ) {
            $file = $this->validate_file_reference( $arguments['file'] );
            return array(
                'url'           => $file['download_url'],
                'filename_hint' => isset( $file['file_name'] ) ? (string) $file['file_name'] : '',
                'mime_hint'     => isset( $file['mime_type'] ) ? (string) $file['mime_type'] : '',
                'handoff'       => true,
            );
        }
        $this->validate_required( $arguments, array( 'url' ) );
        return array(
            'url'           => (string) $arguments['url'],
            'filename_hint' => '',
            'mime_hint'     => '',
            'handoff'       => false,
        );
    }

    private static function refuse_non_text_description( array $arguments ): void {
        if ( array_key_exists( 'description', $arguments ) && null !== $arguments['description'] && ! is_scalar( $arguments['description'] ) ) {
            throw new \InvalidArgumentException( 'description must be text. Nothing was imported.' );
        }
    }

    private function resolve_filename( array $arguments, string $filename_hint, bool $handoff, string $url, string $mime_hint = '' ): string {
        $filename = '';
        if ( ! empty( $arguments['filename'] ) ) {
            $filename = sanitize_file_name( (string) $arguments['filename'] );
        }
        if ( '' === $filename && '' !== $filename_hint ) {
            $filename = sanitize_file_name( $filename_hint );
        }
        if ( '' === $filename ) {
            $path     = (string) wp_parse_url( $url, PHP_URL_PATH );
            $filename = sanitize_file_name( basename( $path ) );
        }
        if ( $handoff ) {
            $type = wp_check_filetype( $filename );
            if ( empty( $type['type'] ) ) {
                $hinted   = self::image_filename_for_type( $filename, $mime_hint );
                $filename = null !== $hinted ? $hinted : $filename;
                $type     = wp_check_filetype( $filename );
            }
            if ( '' === $filename || empty( $type['type'] ) ) {
                throw new \InvalidArgumentException( 'Supply filename with an allowed file extension (for example image.png): the file handoff carries no usable filename.' );
            }
        }
        if ( '' === $filename ) {
            $filename = 'remote-' . wp_generate_password( 8, false ) . '.bin';
        }
        return $filename;
    }

    private function refuse_declared_oversize( string $url, int $max_bytes ): void {
        $head = wp_safe_remote_head(
            $url,
            array(
                'timeout'     => self::HTTP_TIMEOUT_SECONDS,
                'redirection' => 5,
            )
        );
        if ( is_wp_error( $head ) ) {
            return;
        }
        $code = (int) wp_remote_retrieve_response_code( $head );
        if ( $code < 200 || $code >= 400 ) {
            return;
        }
        $declared_len = (int) wp_remote_retrieve_header( $head, 'content-length' );
        if ( $declared_len > 0 && $declared_len > $max_bytes ) {
            throw new \InvalidArgumentException(
                sprintf( 'Remote file too large (%s). Maximum upload size is %s.', size_format( $declared_len ), size_format( $max_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    private function download_to( string $url, string $tmp_file, int $max_bytes ): int {
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'             => self::HTTP_TIMEOUT_SECONDS,
                'redirection'         => 5,
                'limit_response_size' => $max_bytes + 1,
                'stream'              => true,
                'filename'            => $tmp_file,
            )
        );
        if ( is_wp_error( $response ) ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException(
                sprintf( 'Could not fetch remote URL (%s). Request a fresh URL or file handoff and retry.', sanitize_key( (string) $response->get_error_code() ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( $status < 200 || $status >= 300 ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException(
                sprintf( 'Remote URL returned HTTP %d.', $status ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.filesize_filesize
        $body_len = (int) @filesize( $tmp_file );
        if ( $body_len <= 0 ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( 'Remote URL returned an empty body.' );
        }
        if ( $body_len > $max_bytes ) {
            \wp_delete_file( $tmp_file );
            throw new \InvalidArgumentException(
                sprintf( 'Remote file too large at %s. Maximum upload size is %s.', size_format( $body_len ), size_format( $max_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $body_len;
    }

    private static function checked_file( string $tmp_file, string $filename, int $featured ): array {
        $known = wp_check_filetype( $filename );
        if ( empty( $known['type'] ) ) {
            $renamed  = self::image_filename_from_bytes( $filename, $tmp_file );
            $filename = null !== $renamed ? $renamed : $filename;
        }
        $check = wp_check_filetype_and_ext( $tmp_file, $filename );
        if ( empty( $check['type'] ) ) {
            \wp_delete_file( $tmp_file );
            throw new \InvalidArgumentException( 'File type is not allowed for upload.' );
        }
        if ( ! empty( $check['proper_filename'] ) ) {
            $filename = $check['proper_filename'];
        }
        $not_image = self::featured_image_type_refusal( $featured, (string) $check['type'] );
        if ( null !== $not_image ) {
            \wp_delete_file( $tmp_file );
            throw new \InvalidArgumentException( $not_image ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return array(
            'type' => $check['type'],
            'name' => $filename,
        );
    }

    private static function attachable_parent( array $arguments, string $tmp_file ): int {
        $parent_post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
        if ( $parent_post_id < 0 ) {
            $parent_post_id = 0;
        }
        if ( $parent_post_id > 0 && ! current_user_can( 'edit_post', $parent_post_id ) ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException(
                sprintf( 'You do not have permission to attach media to post %d.', $parent_post_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        return $parent_post_id;
    }

    private static function apply_attachment_fields( int $attachment_id, array $arguments, ?string $slug ): void {
        $post_update = array( 'ID' => $attachment_id );
        if ( array_key_exists( 'title', $arguments ) ) {
            $post_update['post_title'] = sanitize_text_field( (string) $arguments['title'] );
        }
        if ( array_key_exists( 'caption', $arguments ) ) {
            $post_update['post_excerpt'] = sanitize_text_field( (string) $arguments['caption'] );
        }
        $post_update = self::add_post_fields( $post_update, $arguments, $slug );
        if ( count( $post_update ) > 1 ) {
            wp_update_post( $post_update );
        }
        if ( array_key_exists( 'alt_text', $arguments ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $arguments['alt_text'] ) );
        }
    }

    private function parse_slug( array $arguments ): ?string {
        if ( ! isset( $arguments['slug'] ) ) {
            return null;
        }
        $slug = $arguments['slug'];
        if ( ! is_string( $slug ) || '' === trim( $slug ) || '' === sanitize_title( $slug ) ) {
            throw new \InvalidArgumentException( 'slug must contain letters or numbers that can form a URL slug; omit it to keep the slug WordPress derives from the title. Nothing was imported.' );
        }
        return trim( $slug );
    }

    private function featured_image_target( array $arguments ): int {
        if ( ! isset( $arguments['featured_image'] ) || ! rest_sanitize_boolean( $arguments['featured_image'] ) ) {
            return 0;
        }
        $post_id = isset( $arguments['post_id'] ) ? (int) $arguments['post_id'] : 0;
        if ( $post_id <= 0 ) {
            throw new \InvalidArgumentException( 'featured_image needs post_id: the featured image is set on that post. Nothing was imported.' );
        }
        $post = get_post( $post_id );
        if ( ! $post ) {
            throw new \InvalidArgumentException( sprintf( 'Post %d not found, so its featured image cannot be set. Nothing was imported.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $type = isset( $post->post_type ) ? (string) $post->post_type : 'post';
        if ( ! post_type_supports( $type, 'thumbnail' ) ) {
            throw new \InvalidArgumentException( sprintf( 'Post %1$d is a "%2$s", a post type without featured images. Nothing was imported.', $post_id, $type ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            throw new \RuntimeException( sprintf( 'You do not have permission to edit post %d, so its featured image cannot be set. Nothing was imported.', $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $post_id;
    }

    private static function featured_image_type_refusal( int $featured, string $type ): ?string {
        if ( $featured <= 0 || 0 === strpos( $type, 'image/' ) ) {
            return null;
        }
        return sprintf( 'featured_image needs an image, and this file is %s. Nothing was imported.', '' !== $type ? $type : 'of an unknown type' );
    }

    private static function add_post_fields( array $post_update, array $arguments, ?string $slug ): array {
        if ( array_key_exists( 'description', $arguments ) ) {
            $post_update['post_content'] = wp_slash( (string) $arguments['description'] );
        }
        if ( null !== $slug ) {
            $post_update['post_name'] = wp_slash( $slug );
        }
        return $post_update;
    }

    private static function set_featured_image( int $post_id, int $attachment_id ): void {
        set_post_thumbnail( $post_id, $attachment_id );
        if ( (int) get_post_meta( $post_id, '_thumbnail_id', true ) !== (int) $attachment_id ) {
            throw new \RuntimeException(
                sprintf( 'Attachment %1$d was imported, but WordPress did not set it as the featured image of post %2$d. Set it with wp_update_post (featured_media), or remove the attachment with wp_delete_media.', $attachment_id, $post_id ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
    }

    private function validate_file_reference( $file ) {
        if ( ! is_array( $file ) ) {
            throw new \InvalidArgumentException( 'file must be a client file object carrying download_url and file_id; local paths and bare file IDs cannot be fetched.' );
        }
        foreach ( array( 'download_url', 'file_id' ) as $key ) {
            if ( ! isset( $file[ $key ] ) || ! is_string( $file[ $key ] ) || '' === trim( $file[ $key ] ) ) {
                throw new \InvalidArgumentException( 'file.download_url and file.file_id must be non-empty strings supplied by the client.' );
            }
        }
        foreach ( array( 'file_name', 'mime_type' ) as $key ) {
            if ( array_key_exists( $key, $file ) && ! is_string( $file[ $key ] ) ) {
                throw new \InvalidArgumentException( 'file.file_name and file.mime_type must be strings when supplied.' );
            }
        }
        $file['download_url'] = trim( $file['download_url'] );
        return $file;
    }

    private function validate_remote_url( $url ) {
        $url    = trim( $url );
        $parsed = wp_parse_url( $url );

        if ( ! $parsed || empty( $parsed['host'] ) ) {
            throw new \InvalidArgumentException( 'Invalid URL.' );
        }
        $scheme = strtolower( isset( $parsed['scheme'] ) ? $parsed['scheme'] : '' );
        $allow_http      = (bool) apply_filters( 'easy_mcp_ai_allow_http_media_url', false );
        $allowed_schemes = $allow_http ? array( 'http', 'https' ) : array( 'https' );
        if ( ! in_array( $scheme, $allowed_schemes, true ) ) {
            throw new \InvalidArgumentException( $allow_http ? 'URL must use http or https.' : 'URL must use HTTPS.' );
        }
        if ( ! empty( $parsed['user'] ) || ! empty( $parsed['pass'] ) ) {
            throw new \InvalidArgumentException( 'URLs containing userinfo are not allowed.' );
        }

        $host = $parsed['host'];
        if ( '' === $host ) {
            throw new \InvalidArgumentException( 'URL host is empty.' );
        }
        if ( '[' === $host[0] ) {
            $host = trim( $host, '[]' );
        }

        if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
            $this->reject_if_unsafe_ip( $host );
            return esc_url_raw( $url );
        }

        $resolved = $this->resolve_host( $host );
        if ( empty( $resolved ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Could not resolve host: %s', $host ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }
        foreach ( $resolved as $ip ) {
            $this->reject_if_unsafe_ip( $ip );
        }

        return esc_url_raw( $url );
    }

    private function resolve_host( $host ) {
        if ( ! function_exists( 'dns_get_record' ) ) {
            throw new \RuntimeException(
                'Cannot validate remote URL: dns_get_record() is unavailable on this PHP install. Re-enable it (it ships with PHP since 5.3) before using wp_upload_media_from_url.'
            );
        }

        $ips = array();

        $records = @dns_get_record( $host, DNS_A | DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if ( ! is_array( $records ) ) {
            return $ips;
        }
        foreach ( $records as $rec ) {
            $type = isset( $rec['type'] ) ? $rec['type'] : '';
            if ( 'A' === $type && ! empty( $rec['ip'] ) ) {
                $ips[] = (string) $rec['ip'];
            } elseif ( 'AAAA' === $type && ! empty( $rec['ipv6'] ) ) {
                $ips[] = (string) $rec['ipv6'];
            }
        }
        return $ips;
    }

    private function reject_if_unsafe_ip( $ip ) {
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            throw new \InvalidArgumentException(
                sprintf( 'Invalid IP address: %s', $ip ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            );
        }

        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) && 0 === stripos( $ip, '::ffff:' ) ) {
            $maybe_v4 = substr( $ip, 7 );
            if ( filter_var( $maybe_v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                $ip = $maybe_v4;
            }
        }

        $is_public = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ( false === $is_public ) {
            throw new \InvalidArgumentException(
                'URL resolves to a private / reserved IP range and cannot be fetched.'
            );
        }
    }
}
