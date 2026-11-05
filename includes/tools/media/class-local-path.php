<?php
namespace Easy_MCP_AI\Tools\Media;

use Easy_MCP_AI\MCP\Detailed_Tool_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Local_Path {

    const MAX_LENGTH = 1024;

    const WAYS_OUT = 'Use a public HTTPS url with wp_upload_media_from_url, or send the file with wp_upload_media: content_base64 for a small file, or filename, size and sha256 for a one-time upload link you send the file to with curl.';

    public static function refusal( string $message ): Detailed_Tool_Error {
        return new Detailed_Tool_Error( $message, self::WAYS_OUT );
    }

    public static function looks_like( $value ): bool {
        if ( ! is_string( $value ) ) {
            return false;
        }
        $value = trim( $value );
        if ( '' === $value || strlen( $value ) > self::MAX_LENGTH || false !== strpbrk( $value, "\r\n" ) ) {
            return false;
        }
        return (bool) preg_match( '#^(?:file:|sandbox:|~[\\\\/]|\.{1,2}[\\\\/]|[A-Za-z]:[\\\\/]|\\\\\\\\|/(?!/))#i', $value );
    }
}
