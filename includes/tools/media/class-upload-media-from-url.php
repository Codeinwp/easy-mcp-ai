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
        return 'Downloads a file from a URL and imports it into the WordPress media library. Supply exactly one source: `url` (a public HTTPS URL — HTTPS only by default, local/private IPs blocked via DNS-resolved SSRF check; site owners can allow http via the easy_mcp_ai_allow_http_media_url filter) or `file` (a client file handoff — a generated or user-attached file the client references as an object with download_url and file_id, which the client fills in). Local paths and bare file IDs cannot be fetched. Optional: `filename` (override; for a file handoff the resolved name must carry an allowed extension such as image.png), `title`, `alt_text`, `caption`, `post_id` (attach to a parent post). Size limit: site `wp_max_upload_size()`. Returns { id, source_url, mime_type, title, file_size }; use id as featured_media or source_url in post content. Avoids the ~33% base64 overhead of wp_upload_media.';
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
                    'description' => 'Optional filename override. Resolution order: this value, then file.file_name, then the URL basename. For a file handoff the resolved name must carry an allowed extension (signed download URLs often have none).',
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
                'post_id'  => array(
                    'type'        => 'integer',
                    'description' => 'Optional parent post ID to attach the media to.',
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
        
        
        
        $has_url  = array_key_exists( 'url', $arguments );
        $has_file = array_key_exists( 'file', $arguments );
        if ( $has_url === $has_file ) {
            throw new \InvalidArgumentException( 'Supply exactly one source: url, or file (a client file handoff carrying download_url and file_id).' );
        }

        $filename_hint = '';
        if ( $has_file ) {
            $file          = $this->validate_file_reference( $arguments['file'] );
            $source_url    = $file['download_url'];
            $filename_hint = isset( $file['file_name'] ) ? (string) $file['file_name'] : '';
        } else {
            $this->validate_required( $arguments, array( 'url' ) );
            $source_url = (string) $arguments['url'];
        }

        $url = $this->validate_remote_url( $source_url );

        
        
        
        
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
        if ( $has_file ) {
            
            
            
            
            $type = wp_check_filetype( $filename );
            if ( '' === $filename || empty( $type['type'] ) ) {
                throw new \InvalidArgumentException( 'Supply filename with an allowed file extension (for example image.png): the file handoff carries no usable filename.' );
            }
        }
        if ( '' === $filename ) {
            $filename = 'remote-' . wp_generate_password( 8, false ) . '.bin';
        }

        $max_bytes = wp_max_upload_size();

        
        
        
        
        
        
        
        $head_validated_size = false;

        
        
        $head = wp_safe_remote_head(
            $url,
            array(
                'timeout'     => self::HTTP_TIMEOUT_SECONDS,
                'redirection' => 5,
            )
        );
        if ( ! is_wp_error( $head ) ) {
            $code = (int) wp_remote_retrieve_response_code( $head );
            if ( $code >= 200 && $code < 400 ) {
                $declared_len = (int) wp_remote_retrieve_header( $head, 'content-length' );
                if ( $declared_len > 0 ) {
                    if ( $declared_len > $max_bytes ) {
                        throw new \InvalidArgumentException(
                            sprintf( 'Remote file too large (%s). Maximum upload size is %s.', size_format( $declared_len ), size_format( $max_bytes ) ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        );
                    }
                    
                    
                    $head_validated_size = true;
                }
            }
        }

        
        
        
        
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $tmp_file = wp_tempnam( $filename );
        if ( ! $tmp_file ) {
            throw new \RuntimeException( 'Could not create temporary file.' );
        }

        
        
        
        
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
        unset( $head_validated_size ); 

        $check = wp_check_filetype_and_ext( $tmp_file, $filename );
        if ( empty( $check['type'] ) ) {
            \wp_delete_file( $tmp_file );
            throw new \InvalidArgumentException( 'File type is not allowed for upload.' );
        }
        
        
        if ( ! empty( $check['proper_filename'] ) ) {
            $filename = $check['proper_filename'];
        }

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp_file,
            'type'     => $check['type'],
            'size'     => $body_len,
        );

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

        
        
        
        
        $attachment_id = media_handle_sideload( $file_array, $parent_post_id );
        if ( \is_wp_error( $attachment_id ) ) {
            \wp_delete_file( $tmp_file );
            throw new \RuntimeException( $attachment_id->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }

        
        
        
        
        $post_update = array( 'ID' => $attachment_id );
        if ( array_key_exists( 'title', $arguments ) ) {
            $post_update['post_title'] = sanitize_text_field( (string) $arguments['title'] );
        }
        if ( array_key_exists( 'caption', $arguments ) ) {
            $post_update['post_excerpt'] = sanitize_text_field( (string) $arguments['caption'] );
        }
        if ( count( $post_update ) > 1 ) {
            wp_update_post( $post_update );
        }
        if ( array_key_exists( 'alt_text', $arguments ) ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $arguments['alt_text'] ) );
        }

        $attachment = get_post( $attachment_id );

        return array(
            'id'         => (int) $attachment_id,
            'source_url' => wp_get_attachment_url( $attachment_id ),
            'mime_type'  => $attachment ? (string) $attachment->post_mime_type : (string) $check['type'],
            'title'      => $attachment ? (string) $attachment->post_title : '',
            'file_size'  => $body_len,
        );
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
