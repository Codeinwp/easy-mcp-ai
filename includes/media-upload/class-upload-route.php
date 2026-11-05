<?php
namespace Easy_MCP_AI\Media_Upload;

use Easy_MCP_AI\Auth\Token_Auth;
use Easy_MCP_AI\Auth\Token_Manager;
use Easy_MCP_AI\Config;
use Easy_MCP_AI\MCP\Error_Codes;
use Easy_MCP_AI\MCP\Server;
use Easy_MCP_AI\Tools\Media\Upload_Media;
use Easy_MCP_AI\Tools\Tool_State;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Upload_Route {

    const NAMESPACE_V1 = 'easy-mcp-ai/v1';
    const PATH         = 'media-upload';

    const NOT_FOUND = 'This upload link is unknown, already used or expired. Call wp_upload_media with filename, size and sha256 for a new one.';

    private $sessions;

    public function __construct( ?Upload_Sessions $sessions = null ) {
        $this->sessions = null !== $sessions ? $sessions : new Upload_Sessions();
    }

    public static function register(): void {
        \register_rest_route(
            self::NAMESPACE_V1,
            '/' . self::PATH . '/(?P<id>[a-f0-9]{32})',
            array(
                'methods'             => array( 'PUT', 'POST' ),
                'callback'            => array( __CLASS__, 'handle' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function url( string $id, string $key ): string {
        return \add_query_arg( array( 'key' => $key ), \rest_url( self::NAMESPACE_V1 . '/' . self::PATH . '/' . $id ) );
    }

    public static function handle( $request ) {
        $url   = $request->get_url_params();
        $query = $request->get_query_params();
        list( $status, $data ) = ( new self() )->receive(
            isset( $url['id'] ) && is_string( $url['id'] ) ? $url['id'] : '',
            isset( $query['key'] ) ? $query['key'] : null,
            (string) $request->get_body(),
            (string) $request->get_header( 'content-type' )
        );
        $response = new \WP_REST_Response( $data, $status );
        $response->header( 'Cache-Control', 'no-store' );
        return $response;
    }

    public function receive( string $id, $key, string $body, string $content_type ): array {
        $closed = self::closed();
        if ( null !== $closed ) {
            return $closed;
        }
        $session = $this->sessions->find( $id );
        if ( null === $session || ! Upload_Sessions::key_matches( $session, $key ) ) {
            return self::refusal( 404, 'not_found', self::NOT_FOUND );
        }
        if ( $this->sessions->is_expired( $session ) ) {
            $this->sessions->discard( $session );
            return self::refusal( 404, 'not_found', self::NOT_FOUND );
        }
        if ( Upload_Sessions::STATUS_RECEIVING === $session['status'] ) {
            return self::refusal( 409, 'receiving', 'Another upload to this link is being stored. Wait for it to answer. If it failed, ask wp_upload_media for a new link. Nothing was stored.' );
        }
        if ( Upload_Sessions::STATUS_PENDING !== $session['status'] ) {
            return self::refusal( 409, 'already_received', 'This link already received its file. Call wp_upload_media with filename and upload_id to add it to the media library.' );
        }
        if ( 0 === stripos( trim( $content_type ), 'multipart/' ) ) {
            return self::refusal( 415, 'form_upload', 'Send the file itself as the request body: curl -T <file> \'<upload_url>\'. A form upload (curl -F) is not accepted. Nothing was stored.' );
        }
        $refusal = self::mismatch( $session, $body );
        if ( null !== $refusal ) {
            return $refusal;
        }
        return $this->store( $session, $body );
    }

    private static function closed(): ?array {
        if ( Server::is_paused() ) {
            return self::refusal( 503, 'paused', Error_Codes::PAUSED_MESSAGE );
        }
        if ( ! ( new Token_Auth( new Token_Manager() ) )->check_ip_allowed() ) {
            return self::refusal( 403, 'ip_forbidden', 'Requests from your IP address are not permitted by this site\'s IP allow-list.' );
        }
        $tool = new Upload_Media();
        $on   = Config::tool_policy_valid()
            && Tool_State::is_enabled( $tool )
            && Server::matches_tool_patterns( $tool->get_name(), (array) Config::get( 'allowed_tool_patterns' ) );
        if ( ! $on ) {
            return self::refusal( 403, 'tool_disabled', 'wp_upload_media is switched off on this site, and its upload links with it.' );
        }
        return null;
    }

    private static function mismatch( array $session, string $body ): ?array {
        $length = strlen( $body );
        if ( $length !== (int) $session['size'] ) {
            return self::refusal(
                $length > (int) $session['size'] ? 413 : 400,
                'size_mismatch',
                sprintf( 'The body is %d bytes; this link takes exactly %d. Send the file itself, unencoded. Nothing was stored.', $length, (int) $session['size'] )
            );
        }
        if ( ! hash_equals( (string) $session['sha256'], hash( 'sha256', $body ) ) ) {
            return self::refusal( 422, 'sha256_mismatch', 'The body\'s SHA-256 is not the one this link was made for: the file changed or was damaged on the way. Nothing was stored.' );
        }
        return null;
    }

    private function store( array $session, string $body ): array {
        $not_stored = self::refusal( 500, 'not_stored', 'The site could not store the file: its uploads folder is not writable or full. Nothing was stored.' );
        $part       = Upload_Sessions::new_part( (string) $session['id'] );
        $path       = '' !== $this->sessions->folder() ? $this->sessions->part_path( $part ) : '';
        if ( '' === $path ) {
            return $not_stored;
        }
        $receiving           = $session;
        $receiving['status'] = Upload_Sessions::STATUS_RECEIVING;
        $receiving['part']   = $part;
        $receiving           = $this->sessions->swap( $session, $receiving );
        if ( null === $receiving ) {
            return self::refusal( 409, 'already_received', 'Another upload to this link started first, or the link expired. Nothing was stored.' );
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a file in the folder Upload_Sessions owns.
        if ( strlen( $body ) !== file_put_contents( $path, $body ) ) {
            $this->sessions->delete_part( $part );
            $this->sessions->replace( $receiving, $session );
            return $not_stored;
        }
        $received            = $receiving;
        $received['status']  = Upload_Sessions::STATUS_RECEIVED;
        $received['expires'] = $this->sessions->time() + Upload_Sessions::TTL;
        if ( ! $this->sessions->replace( $receiving, $received ) ) {
            $this->sessions->delete_part( $part );
            return self::refusal( 404, 'not_found', self::NOT_FOUND );
        }
        return array(
            200,
            array(
                'received'   => true,
                'upload_id'  => (string) $session['id'],
                'size'       => (int) $session['size'],
                'sha256'     => (string) $session['sha256'],
                'expires_at' => gmdate( 'Y-m-d\TH:i:s\Z', (int) $received['expires'] ),
                'next'       => 'Call wp_upload_media with filename and this upload_id before expires_at to add the file to the media library.',
            ),
        );
    }

    private static function refusal( int $status, string $error, string $message ): array {
        return array( $status, array( 'error' => $error, 'message' => $message ) );
    }
}
